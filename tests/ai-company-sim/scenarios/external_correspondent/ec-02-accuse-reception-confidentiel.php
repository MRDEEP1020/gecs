<?php

// External Correspondent — scénario 2 : un dépôt confidentiel génère un
// accusé de réception — la seule "preuve" qu'un externe détient vraiment
// dans ce système (specifications-modules-GEC.md, Module 1).
//
// Usage : php tests/ai-company-sim/scenarios/external_correspondent/ec-02-accuse-reception-confidentiel.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\RegistrationForm;
use App\Models\Courrier;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : ec-02-accuse-reception-confidentiel.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'external_correspondent';

Scenario::executer($memoire, $role, 'ec-02-accuse-reception-confidentiel', function () use ($memoire, $seed, $role) {
    $agent = TaggedFactory::utilisateur($memoire, $seed, 'agent-ec02', 'Agent');
    $dga = TaggedFactory::utilisateur($memoire, $seed, 'dga-ec02', 'DGA');
    $agent->destinatairesTransfert()->attach($dga->id);

    $nomEnveloppe = TaggedFactory::tag($seed, 'Déposant externe — Maître Essomba');

    Livewire::actingAs($agent);
    Livewire::test(RegistrationForm::class)
        ->call('basculerModeConfidentiel', true)
        ->set('form.destinataire', $nomEnveloppe)
        ->set('destinataireChoix', "user-{$dga->id}")
        ->set('form.confidentialite', 2)
        ->call('enregistrer');

    $courrier = Courrier::where('destinataire', $nomEnveloppe)->where('confidentiel_direct', true)->latest('id')->first();

    if (! $courrier) {
        $memoire->enregistrerEvenement(['actor_role' => $role, 'action' => 'livewire_call', 'result' => 'failure', 'details' => 'Courrier confidentiel non créé.']);

        return null;
    }

    $memoire->enregistrerEntite('Courrier', $courrier->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier->numero_reference, 'created_by_role' => $role]);

    Auth::login($agent);
    $correct = false;
    $statusCode = null;
    try {
        $reponse = app(\App\Http\Controllers\CourrierAccuseReceptionController::class)->__invoke($courrier);
        $statusCode = $reponse->getStatusCode();
        $correct = $statusCode === 200;
    } catch (\Throwable $e) {
        $statusCode = 'exception: '.$e->getMessage();
    }

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'controller_call', 'component' => 'CourrierAccuseReceptionController',
        'target_entity' => ['type' => 'Courrier', 'id' => $courrier->id],
        'result' => $correct ? 'success' : 'failure',
        'http_status' => $statusCode,
    ]);

    if (! $correct) {
        $bugId = $memoire->signalerBug([
            'title' => 'L\'accusé de réception d\'un dépôt confidentiel n\'est pas accessible à l\'agent qui l\'a enregistré',
            'severity' => 'P1',
            'priority' => 'P1',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Correspondant externe (via agent)"],
            'user_role' => 'Agent',
            'preconditions' => "Courrier confidentiel id={$courrier->id} (confidentialite=2, confidentiel_direct=true).",
            'steps_to_reproduce' => ["GET /courriers/{$courrier->id}/accuse-reception en tant qu'agent créateur"],
            'expected_result' => '200.',
            'actual_result' => 'status='.$statusCode,
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (controller_call, scenario ec-02)'],
            'affected_module_url' => 'App\\Http\\Controllers\\CourrierAccuseReceptionController',
            'related_data' => ['courrier_id' => $courrier->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Un déposant confidentiel ne recevrait jamais sa seule preuve de dépôt — risque de conformité.',
            'security_impact' => 'none',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier CourrierPolicy::imprimerAccuseReception() et le mode confidentiel de RegistrationForm.',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — accusé de réception inaccessible.\n";
    } else {
        echo "OK — accusé de réception généré (status {$statusCode}).\n";
    }

    return $courrier;
});
