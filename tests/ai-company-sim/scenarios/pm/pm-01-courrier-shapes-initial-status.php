<?php

// Product Manager — scénario 1 : enregistrer les 3 "formes" réelles de
// courrier (normal, sinistre, confidentiel) et vérifier que le statut/
// service initial correspond à la règle documentée, pas à une intuition.
//
// Usage : php tests/ai-company-sim/scenarios/pm/pm-01-courrier-shapes-initial-status.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\RegistrationForm;
use App\Models\Courrier;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : pm-01-courrier-shapes-initial-status.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'product_manager';

Scenario::executer($memoire, $role, 'pm-01-courrier-shapes-initial-status', function () use ($memoire, $seed, $role) {
    $agent = TaggedFactory::utilisateur($memoire, $seed, 'agent-pm01', 'Agent');
    $dga = TaggedFactory::utilisateur($memoire, $seed, 'dga-pm01', 'DGA');
    $agent->destinatairesTransfert()->attach($dga->id);
    Livewire::actingAs($agent);

    $resultats = [];

    // Forme 1 : normal — attendu en_attente_de_transfert (sans service, en
    // attente que l'agent clique "Transférer").
    $objetNormal = TaggedFactory::tag($seed, 'Facture fournisseur à régler');
    Livewire::test(RegistrationForm::class)
        ->set('form.sens', 'entrant')->set('form.date_mouvement', now()->format('Y-m-d'))
        ->set('form.objet', $objetNormal)->set('form.type_document', 'Lettre')
        ->set('form.mode_reception', 'email')->set('form.priorite', 'normale')->set('form.confidentialite', 1)
        ->call('enregistrer');
    $cNormal = Courrier::where('objet', $objetNormal)->latest('id')->first();
    $resultats['normal'] = ['attendu' => 'en_attente_de_transfert', 'obtenu' => $cNormal?->statut, 'ok' => $cNormal?->statut === 'en_attente_de_transfert'];
    if ($cNormal) {
        $memoire->enregistrerEntite('Courrier', $cNormal->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $cNormal->numero_reference, 'created_by_role' => $role]);
    }

    // Forme 2 : confidentiel — mode dédié de RegistrationForm (voir
    // DECISIONS.md "fusion explicitement demandée"), jamais ouvert/scanné
    // (objet toujours fixe "Correspondance confidentielle (non ouverte)",
    // seul le nom sur l'enveloppe est saisi).
    $nomEnveloppe = TaggedFactory::tag($seed, 'Pli confidentiel RH — Me Dupont');
    Livewire::test(RegistrationForm::class)
        ->call('basculerModeConfidentiel', true)
        ->set('form.destinataire', $nomEnveloppe)
        ->set('destinataireChoix', "user-{$dga->id}")
        ->set('form.confidentialite', 3)
        ->call('enregistrer');
    $cConfidentiel = Courrier::where('destinataire', $nomEnveloppe)->where('confidentiel_direct', true)->latest('id')->first();
    $resultats['confidentiel'] = ['attendu_confidentialite' => 3, 'obtenu' => $cConfidentiel?->confidentialite, 'ok' => $cConfidentiel?->confidentialite === 3];
    if ($cConfidentiel) {
        $memoire->enregistrerEntite('Courrier', $cConfidentiel->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $cConfidentiel->numero_reference, 'created_by_role' => $role]);
    }

    $toutOk = $resultats['normal']['ok'] && $resultats['confidentiel']['ok'];

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'courrier_shapes_check', 'result' => $toutOk ? 'success' : 'failure',
        'details' => $resultats,
    ]);

    foreach ($resultats as $forme => $r) {
        if (! $r['ok']) {
            $memoire->signalerBug([
                'title' => "Le statut/attribut initial du courrier '{$forme}' ne correspond pas à la règle documentée",
                'severity' => 'P2',
                'priority' => 'P2',
                'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Product Manager"],
                'user_role' => 'Agent',
                'preconditions' => "Enregistrement d'un courrier de forme '{$forme}'.",
                'steps_to_reproduce' => ["Voir pm-01-courrier-shapes-initial-status.php, forme '{$forme}'"],
                'expected_result' => json_encode($r),
                'actual_result' => 'Voir champ obtenu ci-dessus : '.json_encode($r),
                'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (courrier_shapes_check, scenario pm-01)'],
                'affected_module_url' => 'App\\Livewire\\Backend\\RegistrationForm'.($forme === 'confidentiel' ? 'Confidentiel' : ''),
                'related_data' => $r,
                'frequency' => 'à confirmer par QA',
                'business_impact' => "Un courrier de forme '{$forme}' pourrait suivre un circuit incorrect dès l'enregistrement.",
                'security_impact' => $forme === 'confidentiel' ? 'Niveau de confidentialité potentiellement incorrect.' : 'none',
                'regression_status' => 'new',
                'recommended_fix' => 'Revoir la logique de statut initial dans RegistrationForm::enregistrer().',
            ]);
        }
    }

    echo 'Résultats : '.json_encode($resultats)."\n";

    return $resultats;
});
