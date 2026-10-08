<?php

// Opérations — scénario 2 : un sinistre confirmé par l'agent doit être routé
// directement au service proposé par une règle de classement, SANS passer
// par la validation DGA (voir RegistrationForm::enregistrer(),
// CourrierForm::estUnSinistre()). On crée notre propre règle taguée plutôt
// que de dépendre d'une règle réelle déjà en base, pour que ce scénario
// reste déterministe et indépendant de la configuration réelle.
//
// Usage : php tests/ai-company-sim/scenarios/operations/op-02-sinistre-direct-routing.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\RegistrationForm;
use App\Models\Courrier;
use App\Models\RegleClassement;
use App\Models\Service;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : op-02-sinistre-direct-routing.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'operations_employee';

Scenario::executer($memoire, $role, 'op-02-sinistre-direct-routing', function () use ($memoire, $seed, $role) {
    $agent = TaggedFactory::utilisateur($memoire, $seed, 'ops-agent', 'Agent');

    $serviceDsin = Service::create([
        'nom' => TaggedFactory::tag($seed, 'Direction des Sinistres Test'),
        'code' => TaggedFactory::codeCourt($seed, 'op-02-dsin'),
        'actif' => true,
    ]);
    $memoire->enregistrerEntite('Service', $serviceDsin->id, ['tag' => "AICO-{$seed}"]);

    $motCleUnique = "sinistre-aico{$seed}";
    $regle = RegleClassement::create([
        'nom' => TaggedFactory::tag($seed, 'Routage direct sinistre'),
        'mots_cles' => [$motCleUnique],
        'champs' => ['objet'],
        'service_propose_id' => $serviceDsin->id,
        'priorite' => 1,
        'actif' => true,
    ]);
    $memoire->enregistrerEntite('RegleClassement', $regle->id, ['tag' => "AICO-{$seed}"]);

    $objet = TaggedFactory::tag($seed, "Déclaration de {$motCleUnique} — véhicule accidenté le 02/10/2026, dégâts matériels importants");

    Livewire::actingAs($agent);

    $test = Livewire::test(RegistrationForm::class)
        ->set('form.sens', 'entrant')
        ->set('form.date_mouvement', now()->format('Y-m-d'))
        ->set('form.objet', $objet)
        ->set('form.type_document', 'Sinistre')
        ->set('form.sous_type_sinistre', 'materiel')
        ->set('form.mode_reception', 'email')
        ->set('form.priorite', 'urgente')
        ->set('form.confidentialite', 1)
        ->call('enregistrer');

    $erreurs = $test->errors()->all();
    $courrier = Courrier::where('objet', $objet)->latest('id')->first();

    $routageCorrect = $courrier && $courrier->service_id === $serviceDsin->id && $courrier->statut !== 'en_attente_de_transfert';

    $memoire->enregistrerEvenement([
        'actor_role' => $role,
        'action' => 'livewire_call',
        'component' => RegistrationForm::class,
        'method' => 'enregistrer',
        'target_entity' => ['type' => 'Courrier', 'id' => $courrier?->id],
        'result' => $routageCorrect ? 'success' : 'failure',
        'details' => "Sinistre matériel — attendu : routage direct vers service {$serviceDsin->id} sans passage DGA",
        'actual_service_id' => $courrier?->service_id,
        'actual_statut' => $courrier?->statut,
        'validation_errors' => $erreurs,
    ]);

    if ($courrier) {
        $memoire->enregistrerEntite('Courrier', $courrier->id, [
            'tag' => "AICO-{$seed}",
            'numero_reference' => $courrier->numero_reference,
            'created_by_role' => $role,
        ]);
    }

    if (! $routageCorrect) {
        $bugId = $memoire->signalerBug([
            'title' => 'Un sinistre confirmé avec une règle de classement active n\'est pas routé directement (reste en attente DGA)',
            'severity' => 'P1',
            'priority' => 'P1',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Agent Opérations"],
            'user_role' => 'Agent',
            'preconditions' => "Règle de classement active (id={$regle->id}) mappant le mot-clé '{$motCleUnique}' au service {$serviceDsin->id}, objet du courrier contenant ce mot-clé, type_document='Sinistre'.",
            'steps_to_reproduce' => [
                "Livewire::test(RegistrationForm::class)->set('form.type_document','Sinistre')->set('form.objet', '...{$motCleUnique}...')->call('enregistrer')",
            ],
            'expected_result' => "service_id = {$serviceDsin->id}, statut != en_attente_de_transfert (saute la validation DGA).",
            'actual_result' => 'service_id = '.($courrier?->service_id ?? 'null').', statut = '.($courrier?->statut ?? 'inconnu, courrier non créé'),
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (livewire_call, scenario op-02)'],
            'affected_module_url' => 'App\\Livewire\\Backend\\RegistrationForm',
            'related_data' => ['courrier_id' => $courrier?->id, 'regle_id' => $regle->id, 'service_id' => $serviceDsin->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Un sinistre resterait bloqué en attente de validation DGA au lieu d\'aller directement au bon service, ralentissant le traitement des déclarations de sinistre.',
            'security_impact' => 'none',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier ClassificationService::classer() et RegistrationForm::enregistrer() pour la branche estUnSinistre().',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — routage sinistre incorrect.\n";
    } else {
        echo "OK — sinistre routé directement : courrier id={$courrier->id}, service_id={$courrier->service_id}, statut={$courrier->statut}\n";
    }

    return $courrier;
});
