<?php

// Administrateur — scénario 1 : construire un Site → Département réel
// ponté à un vrai Service via OrganisationIndex, puis vérifier que la
// cascade Site/Département d'une DGA (ShowCourrier::validerService())
// résout bien le VRAI service_id — le pont "Organisation v2" que les
// commentaires du code qualifient eux-mêmes de subtil.
//
// Usage : php tests/ai-company-sim/scenarios/admin/admin-01-org-bridge-dga-cascade.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\ShowCourrier;
use App\Models\Courrier;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : admin-01-org-bridge-dga-cascade.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'administrator';

Scenario::executer($memoire, $role, 'admin-01-org-bridge-dga-cascade', function () use ($memoire, $seed, $role) {
    $dga = TaggedFactory::utilisateur($memoire, $seed, 'dga-admin01', 'DGA');

    $departement = TaggedFactory::departementPonte($memoire, $seed, 'Direction Technique Test Admin');
    $serviceCible = $departement->service;

    $courrier = Courrier::create([
        'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999),
        'sens' => 'entrant',
        'date_mouvement' => now(),
        'objet' => TaggedFactory::tag($seed, 'Demande de renouvellement de parc informatique'),
        'type_document' => 'Lettre',
        'mode_reception' => 'email',
        'statut' => 'en_cours_de_transfert',
    ]);
    $memoire->enregistrerEntite('Courrier', $courrier->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier->numero_reference, 'created_by_role' => $role]);
    \App\Models\CourrierHistorique::create(['courrier_id' => $courrier->id, 'auteur_id' => $dga->id, 'action' => 'creation']);

    Livewire::actingAs($dga);

    $test = Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
        ->set('siteSelectionneId', $departement->parent_id)
        ->set('departementSelectionneId', $departement->id)
        ->call('validerService');

    $courrier->refresh();
    $correct = $test->errors()->isEmpty() && $courrier->service_id === $serviceCible->id && $courrier->statut === 'enregistre';

    $memoire->enregistrerEvenement([
        'actor_role' => $role,
        'action' => 'livewire_call',
        'component' => ShowCourrier::class,
        'method' => 'validerService',
        'target_entity' => ['type' => 'Courrier', 'id' => $courrier->id],
        'result' => $correct ? 'success' : 'failure',
        'details' => "Cascade Site={$departement->parent_id}/Département={$departement->id} doit résoudre service_id={$serviceCible->id}",
        'actual_service_id' => $courrier->service_id,
    ]);

    if (! $correct) {
        $bugId = $memoire->signalerBug([
            'title' => 'La cascade Site/Département de la DGA ne résout pas le vrai service ponté',
            'severity' => 'P1',
            'priority' => 'P1',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Administrateur"],
            'user_role' => 'DGA',
            'preconditions' => "Département id={$departement->id} ponté au service id={$serviceCible->id} sous Site id={$departement->parent_id}.",
            'steps_to_reproduce' => ["ShowCourrier::class->set('siteSelectionneId', {$departement->parent_id})->set('departementSelectionneId', {$departement->id})->call('validerService')"],
            'expected_result' => "service_id = {$serviceCible->id}, statut = enregistre.",
            'actual_result' => 'service_id = '.($courrier->service_id ?? 'null').', statut = '.$courrier->statut.', erreurs = '.json_encode($test->errors()->all()),
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (validerService, scenario admin-01)'],
            'affected_module_url' => 'App\\Livewire\\Backend\\ShowCourrier',
            'related_data' => ['courrier_id' => $courrier->id, 'departement_id' => $departement->id, 'service_id' => $serviceCible->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Un courrier validé par la DGA pourrait atterrir sur le mauvais service, ou rester bloqué.',
            'security_impact' => 'none',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier OrganizationUnit::service_id et la cascade dans ShowCourrier::validerService().',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — cascade Site/Département incorrecte.\n";
    } else {
        echo "OK — cascade résolue correctement, service_id={$courrier->service_id}.\n";
    }

    return $courrier;
});
