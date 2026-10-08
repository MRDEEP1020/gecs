<?php

// Security Engineer — scénario 4 : une délégation DGA DÉSACTIVÉE (DGA de
// retour) ne doit plus donner AUCUN accès — angle distinct d'admin-04
// (qui teste le mauvais DGA ; ici c'est le BON DGA mais une délégation
// expirée/désactivée).
//
// Usage : php tests/ai-company-sim/scenarios/security/sec-04-delegation-desactivee-abus.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\ShowCourrier;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\DelegationDga;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : sec-04-delegation-desactivee-abus.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'security_engineer';

Scenario::executer($memoire, $role, 'sec-04-delegation-desactivee-abus', function () use ($memoire, $seed, $role) {
    $admin = TaggedFactory::utilisateur($memoire, $seed, 'admin-sec04', 'Administrateur');
    $dga = TaggedFactory::utilisateur($memoire, $seed, 'dga-sec04', 'DGA');
    $rh = TaggedFactory::utilisateur($memoire, $seed, 'rh-sec04', 'Collaborateur');

    $delegation = DelegationDga::create([
        'delegant_id' => $dga->id, 'delegataire_id' => $rh->id,
        'debut_le' => now()->subDay(), 'actif' => false,
        'active_par_id' => $admin->id, 'desactive_par_id' => $admin->id, 'desactive_le' => now()->subHour(),
    ]);
    $memoire->enregistrerEntite('DelegationDga', $delegation->id, ['tag' => "AICO-{$seed}"]);

    $courrier = Courrier::create([
        'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999), 'sens' => 'entrant', 'date_mouvement' => now(),
        'objet' => TaggedFactory::tag($seed, 'Courrier adressé au DGA — délégation désactivée'),
        'type_document' => 'Lettre', 'mode_reception' => 'email', 'statut' => 'en_cours_de_transfert',
        'destinataire_transfert_id' => $dga->id,
    ]);
    $memoire->enregistrerEntite('Courrier', $courrier->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier->numero_reference, 'created_by_role' => $role]);
    CourrierHistorique::create(['courrier_id' => $courrier->id, 'auteur_id' => $admin->id, 'action' => 'creation']);

    Livewire::actingAs($rh);

    $accesRefuse = true;
    try {
        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])->assertForbidden();
    } catch (\Throwable $e) {
        $accesRefuse = false;
    }

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'delegation_desactivee_check', 'target_entity' => ['type' => 'Courrier', 'id' => $courrier->id],
        'result' => $accesRefuse ? 'success' : 'failure',
    ]);

    if (! $accesRefuse) {
        $bugId = $memoire->signalerBug([
            'title' => 'Une délégation DGA DÉSACTIVÉE donne encore accès au courrier du DGA délégant',
            'severity' => 'P0',
            'priority' => 'P0',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Security Engineer"],
            'user_role' => 'Collaborateur (ex-délégataire)',
            'preconditions' => "Délégation id={$delegation->id} (actif=false, désactivée il y a 1h). Courrier id={$courrier->id} adressé au DGA={$dga->id}.",
            'steps_to_reproduce' => ["Livewire::actingAs(rh)->test(ShowCourrier::class, ['courrierId' => {$courrier->id}])"],
            'expected_result' => '403 — une délégation désactivée ne couvre plus rien.',
            'actual_result' => 'Accès obtenu.',
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (delegation_desactivee_check, scenario sec-04)'],
            'affected_module_url' => 'App\\Models\\DelegationDga::scopeActives / App\\Policies\\CourrierPolicy',
            'related_data' => ['courrier_id' => $courrier->id, 'delegation_id' => $delegation->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Un ex-délégataire garderait un accès permanent aux courriers du DGA même après son retour.',
            'security_impact' => 'CRITIQUE — persistance d\'accès après révocation.',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier DelegationDga::scopeActives() est bien appliqué partout où delegationsDgaActivesIds() est utilisé.',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — accès persistant après désactivation.\n";
    } else {
        echo "OK — la délégation désactivée ne donne plus aucun accès.\n";
    }

    return $delegation;
});
