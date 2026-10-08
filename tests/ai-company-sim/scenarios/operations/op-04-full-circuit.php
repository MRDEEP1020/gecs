<?php

// Opérations — scénario 4 : circuit complet affecter → démarrer traitement
// → soumettre pour validation → renvoyer pour correction → re-soumission →
// valider, exactement le même enchaînement que Courrier A de
// SimulationParcoursReelTest, via les VRAIS composants ShowCourrier.
//
// Usage : php tests/ai-company-sim/scenarios/operations/op-04-full-circuit.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\ShowCourrier;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\Service;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : op-04-full-circuit.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'operations_employee';

Scenario::executer($memoire, $role, 'op-04-full-circuit', function () use ($memoire, $seed, $role) {
    $responsable = TaggedFactory::utilisateur($memoire, $seed, 'responsable-op04', 'Responsable de service');
    $collaborateur = TaggedFactory::utilisateur($memoire, $seed, 'collaborateur-op04', 'Collaborateur');

    $service = Service::create([
        'nom' => TaggedFactory::tag($seed, 'Service Circuit Complet'),
        'code' => TaggedFactory::codeCourt($seed, 'op-04-service'),
        'actif' => true,
        'responsable_id' => $responsable->id,
    ]);
    $memoire->enregistrerEntite('Service', $service->id, ['tag' => "AICO-{$seed}"]);

    $responsable->update(['service_id' => $service->id]);
    $collaborateur->update(['service_id' => $service->id]);

    $objet = TaggedFactory::tag($seed, 'Demande de renouvellement de licence logicielle — circuit complet');

    $courrier = Courrier::create([
        'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999),
        'sens' => 'entrant',
        'date_mouvement' => now(),
        'objet' => $objet,
        'type_document' => 'Lettre',
        'mode_reception' => 'email',
        'service_id' => $service->id,
        'statut' => 'enregistre',
    ]);
    $memoire->enregistrerEntite('Courrier', $courrier->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier->numero_reference, 'created_by_role' => $role]);
    CourrierHistorique::create(['courrier_id' => $courrier->id, 'auteur_id' => $responsable->id, 'action' => 'creation']);

    $etapes = [];

    Livewire::actingAs($responsable);
    $t1 = Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
        ->set('collaborateurSelectionne', $collaborateur->id)
        ->call('affecter');
    $etapes['affecter'] = $t1->errors()->isEmpty();

    Livewire::actingAs($collaborateur);
    $t2 = Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])->call('demarrerTraitement');
    $etapes['demarrerTraitement'] = $t2->errors()->isEmpty();

    $t3 = Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])->call('soumettrePourValidation');
    $etapes['soumettrePourValidation'] = $t3->errors()->isEmpty();

    Livewire::actingAs($responsable);
    $t4 = Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
        ->set('motifRenvoi', TaggedFactory::tag($seed, 'Pièce justificative manquante, merci de compléter le dossier'))
        ->call('renvoyerPourCorrection');
    $etapes['renvoyerPourCorrection'] = $t4->errors()->isEmpty();

    Livewire::actingAs($collaborateur);
    $t5 = Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])->call('soumettrePourValidation');
    $etapes['re-soumettrePourValidation'] = $t5->errors()->isEmpty();

    Livewire::actingAs($responsable);
    $t6 = Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])->call('valider');
    $etapes['valider'] = $t6->errors()->isEmpty();

    $courrier->refresh();
    $toutOk = ! in_array(false, $etapes, true) && $courrier->statut === 'traite';

    $memoire->enregistrerEvenement([
        'actor_role' => $role,
        'action' => 'livewire_call',
        'component' => ShowCourrier::class,
        'method' => 'circuit_complet',
        'target_entity' => ['type' => 'Courrier', 'id' => $courrier->id],
        'result' => $toutOk ? 'success' : 'failure',
        'details' => 'Circuit affecter→traiter→soumettre→renvoi→re-soumission→valider',
        'etapes' => $etapes,
        'statut_final' => $courrier->statut,
    ]);

    if (! $toutOk) {
        $etapeEnEchec = array_search(false, $etapes, true);
        $bugId = $memoire->signalerBug([
            'title' => "Le circuit complet du courrier échoue à l'étape '{$etapeEnEchec}'",
            'severity' => 'P1',
            'priority' => 'P1',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Opérations"],
            'user_role' => 'Responsable de service / Collaborateur',
            'preconditions' => "Courrier id={$courrier->id} enregistré, service id={$service->id} avec responsable et collaborateur rattachés.",
            'steps_to_reproduce' => ['affecter', 'demarrerTraitement', 'soumettrePourValidation', 'renvoyerPourCorrection', 'soumettrePourValidation', 'valider'],
            'expected_result' => 'Toutes les étapes réussissent, statut final = traite.',
            'actual_result' => 'Étapes : '.json_encode($etapes).'. Statut final : '.$courrier->statut,
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (livewire_call, scenario op-04)'],
            'affected_module_url' => 'App\\Livewire\\Backend\\ShowCourrier',
            'related_data' => ['courrier_id' => $courrier->id, 'service_id' => $service->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Le circuit de traitement standard d\'un courrier serait bloqué, empêchant sa clôture.',
            'security_impact' => 'none',
            'regression_status' => 'new',
            'recommended_fix' => "Investiguer WorkflowService::{$etapeEnEchec}() et les transitions de statut associées.",
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — échec à l'étape '{$etapeEnEchec}'.\n";
    } else {
        echo "OK — circuit complet réussi, statut final = {$courrier->statut}.\n";
    }

    return $courrier;
});
