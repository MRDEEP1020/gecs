<?php

// Opérations — scénario 1 : un agent enregistre un courrier entrant normal
// (lettre, pas de scan/OCR dans ce scénario-ci — voir op-05 pour le flux
// scan-first) via le VRAI composant RegistrationForm, exactement le même
// patron que tests/Feature/Courriers/RegistrationFormTest.php
// (test_un_type_de_document_personnalise_peut_etre_enregistre), pointé sur
// la vraie base `gec`. C'est le scénario de vérification "go/no-go" du
// harnais lui-même (étape 4 du plan) avant de construire le reste.
//
// Usage : php tests/ai-company-sim/scenarios/operations/op-01-register-letter.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\Seed;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\RegistrationForm;
use App\Models\Courrier;
use App\Models\Service;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : op-01-register-letter.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'operations_employee';

$objet = Seed::pick('op-01-objet', $seed, [
    'Demande de renouvellement de licence logicielle pour le parc bureautique',
    'Renouvellement du contrat d\'assurance flotte automobile',
    'Facture fournisseur en attente de traitement comptable',
    'Demande de mise à jour des coordonnées bancaires du bénéficiaire',
]);
$memoire->enregistrerVariantChoisi('op-01-objet', $objet);

Scenario::executer($memoire, $role, 'op-01-register-letter', function () use ($memoire, $seed, $role, $objet) {
    $agent = TaggedFactory::utilisateur($memoire, $seed, 'ops-agent', 'Agent');

    $service = Service::where('nom', 'like', "%AICO-{$seed}%")->first();
    if (! $service) {
        $service = Service::create([
            'nom' => TaggedFactory::tag($seed, 'Service Test Opérations'),
            'code' => TaggedFactory::codeCourt($seed, 'op-01-service'),
            'actif' => true,
        ]);
        $memoire->enregistrerEntite('Service', $service->id, ['tag' => "AICO-{$seed}"]);
    }

    Livewire::actingAs($agent);

    $objetTague = TaggedFactory::tag($seed, $objet);

    $test = Livewire::test(RegistrationForm::class)
        ->set('form.sens', 'entrant')
        ->set('form.date_mouvement', now()->format('Y-m-d'))
        ->set('form.service_id', $service->id)
        ->set('form.objet', $objetTague)
        ->set('form.type_document', 'Lettre')
        ->set('form.mode_reception', 'email')
        ->set('form.priorite', 'normale')
        ->set('form.confidentialite', 1)
        ->call('enregistrer');

    $erreurs = $test->errors()->all();

    $courrier = Courrier::where('objet', $objetTague)->latest('id')->first();

    $memoire->enregistrerEvenement([
        'actor_role' => $role,
        'actor_label' => "AICO-{$seed} Agent Opérations",
        'action' => 'livewire_call',
        'component' => RegistrationForm::class,
        'method' => 'enregistrer',
        'target_entity' => ['type' => 'Courrier', 'id' => $courrier?->id],
        'result' => empty($erreurs) && $courrier ? 'success' : 'failure',
        'details' => "Enregistrement d'un courrier entrant normal — objet : {$objetTague}",
        'validation_errors' => $erreurs,
    ]);

    if ($courrier) {
        $memoire->enregistrerEntite('Courrier', $courrier->id, [
            'tag' => "AICO-{$seed}",
            'numero_reference' => $courrier->numero_reference,
            'created_by_role' => $role,
        ]);

        echo "OK — Courrier créé : id={$courrier->id} numero_reference={$courrier->numero_reference}\n";
    } else {
        echo "ÉCHEC — aucun courrier créé. Erreurs de validation : ".json_encode($erreurs)."\n";
    }

    return $courrier;
});
