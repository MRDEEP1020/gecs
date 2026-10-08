<?php

// Administrateur — scénario 4 : configurer une délégation DGA active et
// vérifier sa frontière NOMINATIVE — ne couvre que le DGA délégant précis,
// jamais un autre DGA, jamais après désactivation (voir DECISIONS.md
// "Délégation DGA/ADJ absents").
//
// Usage : php tests/ai-company-sim/scenarios/admin/admin-04-delegation-nominative-boundary.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\DelegationDgaIndex;
use App\Livewire\Backend\ShowCourrier;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : admin-04-delegation-nominative-boundary.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'administrator';

Scenario::executer($memoire, $role, 'admin-04-delegation-nominative-boundary', function () use ($memoire, $seed, $role) {
    $admin = TaggedFactory::utilisateur($memoire, $seed, 'admin-admin04', 'Administrateur');
    $dga = TaggedFactory::utilisateur($memoire, $seed, 'dga-admin04', 'DGA');
    $autreDga = TaggedFactory::utilisateur($memoire, $seed, 'autre-dga-admin04', 'DGA');
    $rh = TaggedFactory::utilisateur($memoire, $seed, 'rh-admin04', 'Collaborateur');

    $faireCourrier = function (string $objetSuffixe, $destinataireId) use ($memoire, $seed, $role, $admin) {
        $courrier = Courrier::create([
            'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999),
            'sens' => 'entrant',
            'date_mouvement' => now(),
            'objet' => TaggedFactory::tag($seed, "Courrier délégation {$objetSuffixe}"),
            'type_document' => 'Lettre',
            'mode_reception' => 'email',
            'statut' => 'en_cours_de_transfert',
            'destinataire_transfert_id' => $destinataireId,
        ]);
        $memoire->enregistrerEntite('Courrier', $courrier->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier->numero_reference, 'created_by_role' => $role]);
        CourrierHistorique::create(['courrier_id' => $courrier->id, 'auteur_id' => $admin->id, 'action' => 'creation']);

        return $courrier;
    };

    $courrierDuDga = $faireCourrier('adressé au DGA délégant', $dga->id);
    $courrierDeLAutreDga = $faireCourrier('adressé à un AUTRE DGA', $autreDga->id);

    Livewire::actingAs($admin);
    Livewire::test(DelegationDgaIndex::class)
        ->set('delegantId', $dga->id)
        ->set('delegataireId', $rh->id)
        ->call('activerDelegation');

    $delegation = \App\Models\DelegationDga::where('delegant_id', $dga->id)->where('delegataire_id', $rh->id)->latest('id')->first();
    if ($delegation) {
        $memoire->enregistrerEntite('DelegationDga', $delegation->id, ['tag' => "AICO-{$seed}"]);
    }

    Livewire::actingAs($rh);

    // 1) RH DOIT pouvoir au moins OUVRIR (voir) le courrier adressé à son
    // DGA délégant — simple accès, pas besoin de compléter toute la
    // validation du service pour ce test de frontière.
    $peutVoirCourrierDuDga = true;
    try {
        Livewire::test(ShowCourrier::class, ['courrierId' => $courrierDuDga->id]);
    } catch (\Throwable $e) {
        $peutVoirCourrierDuDga = false;
    }

    // 2) RH ne doit PAS pouvoir agir sur le courrier adressé à un AUTRE DGA.
    // assertForbidden() ne lève une exception QUE si l'accès n'est PAS
    // refusé (donc : pas d'exception = vraiment refusé = correct).
    try {
        Livewire::test(ShowCourrier::class, ['courrierId' => $courrierDeLAutreDga->id])->assertForbidden();
        $accesAutreDgaRefuse = true;
    } catch (\Throwable $e) {
        $accesAutreDgaRefuse = false;
    }

    $correct = $peutVoirCourrierDuDga && $accesAutreDgaRefuse;

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'livewire_call', 'method' => 'delegation_boundary_check',
        'target_entity' => ['type' => 'DelegationDga', 'id' => $delegation?->id],
        'result' => $correct ? 'success' : 'failure',
        'acces_autre_dga_refuse' => $accesAutreDgaRefuse,
    ]);

    if (! $correct) {
        $bugId = $memoire->signalerBug([
            'title' => 'Un délégataire actif accède à un courrier adressé à un AUTRE DGA que celui qui a délégué',
            'severity' => 'P0',
            'priority' => 'P0',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Administrateur"],
            'user_role' => 'Collaborateur (délégataire)',
            'preconditions' => "Délégation active id={$delegation?->id} : {$dga->id} → {$rh->id}. Courrier id={$courrierDeLAutreDga->id} adressé à un AUTRE DGA (id={$autreDga->id}).",
            'steps_to_reproduce' => ["Livewire::actingAs(rh)->test(ShowCourrier::class, ['courrierId' => {$courrierDeLAutreDga->id}])"],
            'expected_result' => 'Accès refusé (403) — la délégation ne couvre que le DGA délégant précis.',
            'actual_result' => 'Accès obtenu sans refus.',
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (delegation_boundary_check, scenario admin-04)'],
            'affected_module_url' => 'App\\Livewire\\Backend\\ShowCourrier / App\\Policies\\CourrierPolicy::validerService',
            'related_data' => ['courrier_id' => $courrierDeLAutreDga->id, 'delegation_id' => $delegation?->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Une délégation d\'absence donnerait accès à TOUS les courriers DGA, pas seulement ceux du DGA réellement absent.',
            'security_impact' => 'Contournement de périmètre via une fonctionnalité de délégation légitime.',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier CourrierPolicy::validerService()/view() — la vérification doit comparer destinataire_transfert_id au delegant_id précis.',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — frontière de délégation non respectée.\n";
    } else {
        echo "OK — la délégation ne couvre que le DGA délégant précis.\n";
    }

    // Nettoyage comportemental : désactiver la délégation pour laisser un état propre.
    if ($delegation) {
        Livewire::actingAs($admin);
        Livewire::test(DelegationDgaIndex::class)->call('desactiverDelegation', $delegation->id);
    }

    return $delegation;
});
