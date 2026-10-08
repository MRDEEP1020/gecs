<?php

// CEO / Test Director : vérification de fumée (circuit DGA complet,
// différent du circuit "service déjà connu" d'op-04) + 2 vérifications
// croisées au hasard dans le manifest du run (une entité créée par un
// rôle, revérifiée par la CEO elle-même).
//
// Usage : php tests/ai-company-sim/scenarios/ceo/ceo-01-smoke-test-and-spotcheck.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\ShowCourrier;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : ceo-01-smoke-test-and-spotcheck.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'ceo_test_director';

Scenario::executer($memoire, $role, 'ceo-01-smoke-full-dga-circuit', function () use ($memoire, $seed, $role) {
    $dga = TaggedFactory::utilisateur($memoire, $seed, 'dga-ceo01', 'DGA');
    $departement = TaggedFactory::departementPonte($memoire, $seed, 'Direction Smoke Test CEO');

    $courrier = Courrier::create([
        'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999), 'sens' => 'entrant', 'date_mouvement' => now(),
        'objet' => TaggedFactory::tag($seed, 'Vérification de fumée — circuit DGA complet'),
        'type_document' => 'Lettre', 'mode_reception' => 'email', 'statut' => 'en_cours_de_transfert',
    ]);
    $memoire->enregistrerEntite('Courrier', $courrier->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier->numero_reference, 'created_by_role' => $role]);
    CourrierHistorique::create(['courrier_id' => $courrier->id, 'auteur_id' => $dga->id, 'action' => 'creation']);

    Livewire::actingAs($dga);
    $test = Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
        ->set('siteSelectionneId', $departement->parent_id)
        ->set('departementSelectionneId', $departement->id)
        ->call('validerService');

    $courrier->refresh();
    $ok = $test->errors()->isEmpty() && $courrier->statut === 'enregistre' && $courrier->service_id === $departement->service_id;

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'smoke_test', 'result' => $ok ? 'success' : 'failure',
        'details' => 'Circuit DGA complet (validation de service via cascade Site/Département).',
    ]);

    echo $ok ? "Vérification de fumée : OK.\n" : "Vérification de fumée : ÉCHEC (voir events.jsonl).\n";

    if (! $ok) {
        $memoire->signalerBug([
            'title' => 'Vérification de fumée CEO échouée — circuit DGA de base',
            'severity' => 'P0', 'priority' => 'P0',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} CEO"],
            'user_role' => 'DGA',
            'preconditions' => "Courrier id={$courrier->id} en_cours_de_transfert.",
            'steps_to_reproduce' => ['validerService via cascade Site/Département'],
            'expected_result' => 'statut=enregistre, service_id correct.',
            'actual_result' => 'statut='.$courrier->statut,
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (smoke_test, scenario ceo-01)'],
            'affected_module_url' => 'App\\Livewire\\Backend\\ShowCourrier',
            'related_data' => ['courrier_id' => $courrier->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Le circuit de base le plus fondamental de l\'application serait cassé.',
            'security_impact' => 'none', 'regression_status' => 'new',
            'recommended_fix' => 'Investigation immédiate requise — ce chemin est utilisé par la quasi-totalité des scénarios.',
        ]);
    }

    return $courrier;
});

// ===== Vérification croisée au hasard =====
$manifest = $memoire->lireManifest();
$courriersTraces = $manifest['Courrier'] ?? [];

if (count($courriersTraces) >= 2) {
    $echantillon = (array) array_rand(array_flip(array_column($courriersTraces, 'id')), min(2, count($courriersTraces)));

    foreach ($echantillon as $courrierId) {
        $courrier = Courrier::find($courrierId);
        $coherent = $courrier !== null;

        $memoire->enregistrerEvenement([
            'actor_role' => $role, 'action' => 'spotcheck_croise', 'target_entity' => ['type' => 'Courrier', 'id' => $courrierId],
            'result' => $coherent ? 'success' : 'failure',
            'details' => 'Relecture directe en base d\'une entité créée par un autre rôle ce run — cohérence manifest/état réel.',
        ]);

        echo $coherent
            ? "Vérification croisée : courrier #{$courrierId} cohérent avec le manifest.\n"
            : "Vérification croisée : courrier #{$courrierId} INTROUVABLE malgré le manifest !\n";
    }
}
