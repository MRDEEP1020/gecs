<?php

// UX Researcher : scénario 2 — les erreurs de validation des champs
// obligatoires doivent afficher un message FRANÇAIS lisible, jamais une
// clé de validation brute (ex. "validation.min", ":attribute").
//
// Usage : php tests/ai-company-sim/scenarios/ux/ux-02-messages-erreur-lisibles.php <run_id>

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
    fwrite(STDERR, "Usage : ux-02-messages-erreur-lisibles.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'ux_researcher';

Scenario::executer($memoire, $role, 'ux-02-messages-erreur-lisibles', function () use ($memoire, $seed, $role) {
    $responsable = TaggedFactory::utilisateur($memoire, $seed, 'responsable-ux02', 'Responsable de service');
    $service = Service::create(['nom' => TaggedFactory::tag($seed, 'Service UX02'), 'code' => TaggedFactory::codeCourt($seed, 'ux-02-service'), 'actif' => true, 'responsable_id' => $responsable->id]);
    $memoire->enregistrerEntite('Service', $service->id, ['tag' => "AICO-{$seed}"]);

    $courrier = Courrier::create([
        'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999), 'sens' => 'entrant', 'date_mouvement' => now(),
        'objet' => TaggedFactory::tag($seed, 'Courrier pour test de message d\'erreur'),
        'type_document' => 'Lettre', 'mode_reception' => 'email', 'service_id' => $service->id, 'statut' => 'en_traitement',
    ]);
    $memoire->enregistrerEntite('Courrier', $courrier->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier->numero_reference, 'created_by_role' => $role]);
    CourrierHistorique::create(['courrier_id' => $courrier->id, 'auteur_id' => $responsable->id, 'action' => 'creation']);

    Livewire::actingAs($responsable);

    // motifRenvoi vide → doit échouer avec un message lisible, pas "required".
    $test = Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])->call('renvoyerPourCorrection');
    $message = $test->errors()->first('motifRenvoi');

    $brut = $message === null
        || str_contains($message, 'validation.')
        || preg_match('/^The .+ field/i', $message)
        || str_contains($message, ':attribute');

    $lisible = ! $brut && filled($message);

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'validation_message_check', 'result' => $lisible ? 'success' : 'failure',
        'message' => $message,
    ]);

    if (! $lisible) {
        $bugId = $memoire->signalerBug([
            'title' => 'Le message d\'erreur de validation pour un champ obligatoire n\'est pas un texte français lisible',
            'severity' => 'P3',
            'priority' => 'P3',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} UX Researcher"],
            'user_role' => 'Responsable de service',
            'preconditions' => "Courrier id={$courrier->id} en_traitement, motifRenvoi vide.",
            'steps_to_reproduce' => ["ShowCourrier::class, ['courrierId' => {$courrier->id}]->call('renvoyerPourCorrection') sans motifRenvoi"],
            'expected_result' => 'Un message français lisible (ex. "Le motif du renvoi est requis.").',
            'actual_result' => 'message = '.json_encode($message),
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (validation_message_check, scenario ux-02)'],
            'affected_module_url' => 'App\\Livewire\\Backend\\ShowCourrier::renvoyerPourCorrection',
            'related_data' => ['courrier_id' => $courrier->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Un utilisateur non technique ne comprendrait pas pourquoi son action a échoué.',
            'security_impact' => 'none',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier la traduction du message de validation pour motifRenvoi (lang/fr.json).',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — message non lisible : ".json_encode($message)."\n";
    } else {
        echo "OK — message d'erreur lisible : \"{$message}\"\n";
    }

    return $courrier;
});
