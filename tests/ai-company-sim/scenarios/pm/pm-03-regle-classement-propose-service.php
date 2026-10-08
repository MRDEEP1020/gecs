<?php

// Product Manager — scénario 3 : une règle de classement basée sur
// l'EXPÉDITEUR (pas l'objet, pour varier de op-02) doit vraiment proposer
// le service configuré (service_propose_id) sur un nouveau courrier
// correspondant — pas juste "la règle a été enregistrée". L'indexation
// réelle tourne via IndexCourrierJob (normalement mis en queue), appelé ici
// directement pour ne pas dépendre d'un worker actif.
//
// Usage : php tests/ai-company-sim/scenarios/pm/pm-03-regle-classement-propose-service.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Jobs\IndexCourrierJob;
use App\Livewire\Backend\RegistrationForm;
use App\Models\Courrier;
use App\Models\RegleClassement;
use App\Models\Service;
use App\Services\ClassificationService;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : pm-03-regle-classement-propose-service.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'product_manager';

Scenario::executer($memoire, $role, 'pm-03-regle-classement-propose-service', function () use ($memoire, $seed, $role) {
    $agent = TaggedFactory::utilisateur($memoire, $seed, 'agent-pm03', 'Agent');

    $serviceCible = Service::create([
        'nom' => TaggedFactory::tag($seed, 'Direction Commerciale Test'),
        'code' => TaggedFactory::codeCourt($seed, 'pm-03-service'),
        'actif' => true,
    ]);
    $memoire->enregistrerEntite('Service', $serviceCible->id, ['tag' => "AICO-{$seed}"]);

    $organisationCliente = TaggedFactory::tag($seed, 'EASYTECH GROUP SA');
    $regle = RegleClassement::create([
        'nom' => TaggedFactory::tag($seed, 'Routage client EASYTECH'),
        'mots_cles' => [$organisationCliente],
        'champs' => ['expediteur'],
        'service_propose_id' => $serviceCible->id,
        'priorite' => 1,
        'actif' => true,
    ]);
    $memoire->enregistrerEntite('RegleClassement', $regle->id, ['tag' => "AICO-{$seed}"]);

    Livewire::actingAs($agent);
    $objet = TaggedFactory::tag($seed, 'Demande de devis — renouvellement de contrat');

    Livewire::test(RegistrationForm::class)
        ->set('form.sens', 'entrant')->set('form.date_mouvement', now()->format('Y-m-d'))
        ->set('form.expediteur_organisation', $organisationCliente)
        ->set('form.objet', $objet)->set('form.type_document', 'Lettre')
        ->set('form.mode_reception', 'email')->set('form.priorite', 'normale')->set('form.confidentialite', 1)
        ->call('enregistrer');

    $courrier = Courrier::where('objet', $objet)->latest('id')->first();

    if (! $courrier) {
        $memoire->enregistrerEvenement(['actor_role' => $role, 'action' => 'livewire_call', 'result' => 'failure', 'details' => 'Courrier non créé — précondition non remplie.']);

        return null;
    }

    $memoire->enregistrerEntite('Courrier', $courrier->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier->numero_reference, 'created_by_role' => $role]);

    (new IndexCourrierJob($courrier))->handle(app(ClassificationService::class));
    $courrier->refresh();

    $correct = (int) $courrier->service_propose_id === $serviceCible->id;

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'job_run', 'component' => IndexCourrierJob::class,
        'target_entity' => ['type' => 'Courrier', 'id' => $courrier->id],
        'result' => $correct ? 'success' : 'failure',
        'service_propose_id' => $courrier->service_propose_id,
        'service_attendu' => $serviceCible->id,
    ]);

    if (! $correct) {
        $bugId = $memoire->signalerBug([
            'title' => 'Une règle de classement basée sur l\'expéditeur ne propose pas le service configuré',
            'severity' => 'P2',
            'priority' => 'P2',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Product Manager"],
            'user_role' => 'Agent',
            'preconditions' => "Règle id={$regle->id} (champ 'expediteur', mot-clé '{$organisationCliente}') → service id={$serviceCible->id}. Courrier id={$courrier->id} avec expediteur_organisation='{$organisationCliente}'.",
            'steps_to_reproduce' => ["(new IndexCourrierJob(\$courrier))->handle(app(ClassificationService::class))"],
            'expected_result' => "service_propose_id = {$serviceCible->id}.",
            'actual_result' => 'service_propose_id = '.($courrier->service_propose_id ?? 'null'),
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (job_run, scenario pm-03)'],
            'affected_module_url' => 'App\\Services\\ClassificationService / App\\Jobs\\IndexCourrierJob',
            'related_data' => ['courrier_id' => $courrier->id, 'regle_id' => $regle->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Les règles de classement basées sur l\'expéditeur (pas seulement l\'objet) ne fonctionneraient pas, réduisant l\'utilité du Module 3.',
            'security_impact' => 'none',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier ClassificationService::classer() pour le champ "expediteur".',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — règle sur expéditeur non appliquée.\n";
    } else {
        echo "OK — service_propose_id correctement résolu via la règle expéditeur.\n";
    }

    return $courrier;
});
