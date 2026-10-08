<?php

// Administrateur — scénario 2 : modifier uniquement le téléphone d'un
// utilisateur ne doit JAMAIS lui retirer son service_id — régression déjà
// documentée une fois dans ce projet (DECISIONS.md, UserList).
//
// Usage : php tests/ai-company-sim/scenarios/admin/admin-02-phone-edit-keeps-service.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\UserList;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : admin-02-phone-edit-keeps-service.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'administrator';

Scenario::executer($memoire, $role, 'admin-02-phone-edit-keeps-service', function () use ($memoire, $seed, $role) {
    $admin = TaggedFactory::utilisateur($memoire, $seed, 'admin-admin02', 'Administrateur');
    $collaborateur = TaggedFactory::utilisateur($memoire, $seed, 'collaborateur-admin02', 'Collaborateur');

    $service = Service::create([
        'nom' => TaggedFactory::tag($seed, 'Service Test Admin02'),
        'code' => TaggedFactory::codeCourt($seed, 'admin-02-service'),
        'actif' => true,
    ]);
    $memoire->enregistrerEntite('Service', $service->id, ['tag' => "AICO-{$seed}"]);
    $collaborateur->update(['service_id' => $service->id]);

    Livewire::actingAs($admin);

    $morceaux = explode(' ', $collaborateur->name, 2);
    $nouveauTelephone = '690'.random_int(100000, 999999);

    $test = Livewire::test(UserList::class)
        ->call('ouvrirEdition', $collaborateur->id)
        ->set('editionNom', $morceaux[0] ?? $collaborateur->name)
        ->set('editionPrenom', $morceaux[1] ?? 'X')
        ->set('editionEmail', $collaborateur->email)
        ->set('editionTelephone', $nouveauTelephone)
        ->call('enregistrerEdition');

    $collaborateur->refresh();
    $correct = $test->errors()->isEmpty() && $collaborateur->telephone === $nouveauTelephone && $collaborateur->service_id === $service->id;

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'livewire_call', 'component' => UserList::class, 'method' => 'enregistrerEdition',
        'target_entity' => ['type' => 'User', 'id' => $collaborateur->id],
        'result' => $correct ? 'success' : 'failure',
        'service_id_avant' => $service->id, 'service_id_apres' => $collaborateur->service_id,
    ]);

    if (! $correct) {
        $bugId = $memoire->signalerBug([
            'title' => 'Modifier le téléphone d\'un utilisateur lui retire son service_id',
            'severity' => 'P1',
            'priority' => 'P1',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Administrateur"],
            'user_role' => 'Administrateur',
            'preconditions' => "Utilisateur id={$collaborateur->id} avec service_id={$service->id}.",
            'steps_to_reproduce' => ["UserList::class->call('ouvrirEdition', {$collaborateur->id})->set('editionTelephone', '{$nouveauTelephone}')->call('enregistrerEdition')"],
            'expected_result' => "telephone mis à jour, service_id INCHANGÉ ({$service->id}).",
            'actual_result' => 'telephone='.$collaborateur->telephone.', service_id='.($collaborateur->service_id ?? 'null'),
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (enregistrerEdition, scenario admin-02)'],
            'affected_module_url' => 'App\\Livewire\\Backend\\UserList',
            'related_data' => ['user_id' => $collaborateur->id, 'service_id' => $service->id],
            'frequency' => 'à confirmer par QA — régression déjà corrigée une fois selon DECISIONS.md',
            'business_impact' => 'Un collaborateur perdrait silencieusement son rattachement de service en corrigeant juste son téléphone.',
            'security_impact' => 'none',
            'regression_status' => 'possible_regression',
            'recommended_fix' => 'Vérifier UserList::enregistrerEdition() — resoudreServiceDepuisCascade() / préservation du service_id existant.',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — service_id perdu après édition du téléphone.\n";
    } else {
        echo "OK — téléphone modifié, service_id préservé ({$collaborateur->service_id}).\n";
    }

    return $collaborateur;
});
