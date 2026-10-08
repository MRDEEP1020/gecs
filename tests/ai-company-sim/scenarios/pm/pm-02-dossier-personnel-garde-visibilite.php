<?php

// Product Manager — scénario 2 : classer un courrier dans un dossier
// PERSONNEL (du collaborateur) ne doit JAMAIS le cacher à son propre
// responsable de service — règle déjà actée (DECISIONS.md 2026-09-24,
// "Dossier de classement : les acteurs du circuit gardent l'accès").
//
// Usage : php tests/ai-company-sim/scenarios/pm/pm-02-dossier-personnel-garde-visibilite.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\DossierClassementList;
use App\Models\Affectation;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\DossierClassement;
use App\Models\Service;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : pm-02-dossier-personnel-garde-visibilite.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'product_manager';

Scenario::executer($memoire, $role, 'pm-02-dossier-personnel-garde-visibilite', function () use ($memoire, $seed, $role) {
    $responsable = TaggedFactory::utilisateur($memoire, $seed, 'responsable-pm02', 'Responsable de service');
    $collaborateur = TaggedFactory::utilisateur($memoire, $seed, 'collaborateur-pm02', 'Collaborateur');

    $service = Service::create([
        'nom' => TaggedFactory::tag($seed, 'Service Dossier Test'),
        'code' => TaggedFactory::codeCourt($seed, 'pm-02-service'),
        'actif' => true,
        'responsable_id' => $responsable->id,
    ]);
    $memoire->enregistrerEntite('Service', $service->id, ['tag' => "AICO-{$seed}"]);
    $collaborateur->update(['service_id' => $service->id]);

    $courrier = Courrier::create([
        'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999),
        'sens' => 'entrant',
        'date_mouvement' => now(),
        'objet' => TaggedFactory::tag($seed, 'Dossier traité classé par le collaborateur'),
        'type_document' => 'Lettre',
        'mode_reception' => 'email',
        'service_id' => $service->id,
        'statut' => 'traite',
    ]);
    $memoire->enregistrerEntite('Courrier', $courrier->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier->numero_reference, 'created_by_role' => $role]);
    CourrierHistorique::create(['courrier_id' => $courrier->id, 'auteur_id' => $collaborateur->id, 'action' => 'creation']);
    Affectation::create(['courrier_id' => $courrier->id, 'user_id' => $collaborateur->id, 'affecte_par_id' => $responsable->id]);

    Livewire::actingAs($collaborateur);

    $nomDossier = TaggedFactory::tag($seed, 'Mon dossier personnel');
    Livewire::test(DossierClassementList::class)
        ->call('ouvrirCreation', null)
        ->set('nomDossier', $nomDossier)
        ->call('creerDossier');

    $dossier = DossierClassement::where('nom', $nomDossier)->latest('id')->first();

    if (! $dossier) {
        $memoire->enregistrerEvenement(['actor_role' => $role, 'action' => 'livewire_call', 'result' => 'failure', 'details' => 'Dossier personnel non créé — précondition non remplie.']);

        return null;
    }

    $memoire->enregistrerEntite('DossierClassement', $dossier->id, ['tag' => "AICO-{$seed}"]);
    $courrier->update(['dossier_classement_id' => $dossier->id]);

    $responsableVoitEncore = $responsable->can('view', $courrier);

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'policy_check', 'target_entity' => ['type' => 'Courrier', 'id' => $courrier->id],
        'result' => $responsableVoitEncore ? 'success' : 'failure',
        'details' => 'Courrier classé dans le dossier PERSONNEL du collaborateur — le responsable de service doit le voir malgré tout.',
    ]);

    if (! $responsableVoitEncore) {
        $bugId = $memoire->signalerBug([
            'title' => 'Classer un courrier dans un dossier personnel le cache à son propre responsable de service',
            'severity' => 'P1',
            'priority' => 'P1',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Product Manager"],
            'user_role' => 'Responsable de service',
            'preconditions' => "Courrier id={$courrier->id} affecté au collaborateur id={$collaborateur->id}, classé dans son dossier personnel id={$dossier->id}.",
            'steps_to_reproduce' => ["\$responsable->can('view', \$courrier) après \$courrier->update(['dossier_classement_id' => {$dossier->id}])"],
            'expected_result' => 'Le responsable du service voit toujours le courrier (règle "les acteurs du circuit gardent l\'accès").',
            'actual_result' => 'Accès refusé au responsable.',
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (policy_check, scenario pm-02)'],
            'affected_module_url' => 'App\\Policies\\CourrierPolicy / App\\Models\\Courrier::impliqueUtilisateur',
            'related_data' => ['courrier_id' => $courrier->id, 'dossier_id' => $dossier->id, 'responsable_id' => $responsable->id],
            'frequency' => 'à confirmer par QA — régression possible d\'une règle déjà actée',
            'business_impact' => 'Un responsable perdrait la visibilité sur le travail de son équipe dès qu\'un collaborateur range un courrier dans son propre classement.',
            'security_impact' => 'none',
            'regression_status' => 'possible_regression',
            'recommended_fix' => 'Vérifier Courrier::impliqueUtilisateur() et CourrierPolicy::accesDossierSuffisant().',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — responsable perd l'accès.\n";
    } else {
        echo "OK — le responsable garde l'accès malgré le classement personnel.\n";
    }

    return $courrier;
});
