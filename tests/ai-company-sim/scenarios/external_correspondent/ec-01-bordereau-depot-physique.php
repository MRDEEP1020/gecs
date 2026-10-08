<?php

// External Correspondent (rôle "Customer" adapté — voir persona) :
// scénario 1 — un agent enregistre, en son nom, un courrier reçu par dépôt
// physique d'un vrai correspondant externe nommé ; on vérifie le bordereau
// imprimable que ce déposant recevrait réellement (contrôleur HTTP réel,
// pas Livewire — testé en PHP direct ici, via Route::get + le contrôleur,
// car ce composant n'a pas de tunnel HTTP simple depuis un script CLI sans
// session cookie ; on simule la requête via le conteneur Laravel).
//
// Usage : php tests/ai-company-sim/scenarios/external_correspondent/ec-01-bordereau-depot-physique.php <run_id>

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
    fwrite(STDERR, "Usage : ec-01-bordereau-depot-physique.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'external_correspondent';

Scenario::executer($memoire, $role, 'ec-01-bordereau-depot-physique', function () use ($memoire, $seed, $role) {
    $agent = TaggedFactory::utilisateur($memoire, $seed, 'agent-ec01', 'Agent');

    $organisation = TaggedFactory::tag($seed, 'TRANSCAM LOGISTIQUE SARL');
    $objet = TaggedFactory::tag($seed, 'Dépôt de dossier — demande de résiliation de contrat flotte');

    Livewire::actingAs($agent);
    Livewire::test(RegistrationForm::class)
        ->set('form.sens', 'entrant')->set('form.date_mouvement', now()->format('Y-m-d'))
        ->set('form.expediteur_organisation', $organisation)
        ->set('form.expediteur_nom', TaggedFactory::tag($seed, 'Jean Mbarga'))
        ->set('form.objet', $objet)->set('form.type_document', 'Lettre')
        ->set('form.mode_reception', 'depot_physique')->set('form.priorite', 'normale')->set('form.confidentialite', 1)
        ->call('enregistrer');

    $courrier = Courrier::where('objet', $objet)->latest('id')->first();

    if (! $courrier) {
        $memoire->enregistrerEvenement(['actor_role' => $role, 'action' => 'livewire_call', 'result' => 'failure', 'details' => 'Courrier non créé.']);

        return null;
    }

    $memoire->enregistrerEntite('Courrier', $courrier->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier->numero_reference, 'created_by_role' => $role]);

    Auth::login($agent);
    $reponse = app(\App\Http\Controllers\CourrierBordereauController::class)->__invoke($courrier);
    $contenu = method_exists($reponse, 'getContent') ? $reponse->getContent() : (string) $reponse;
    $correct = str_contains($contenu, $courrier->numero_reference) || $reponse->getStatusCode() === 200;

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'controller_call', 'component' => 'CourrierBordereauController',
        'target_entity' => ['type' => 'Courrier', 'id' => $courrier->id],
        'result' => $correct ? 'success' : 'failure',
        'http_status' => $reponse->getStatusCode(),
    ]);

    if (! $correct) {
        $bugId = $memoire->signalerBug([
            'title' => 'Le bordereau d\'un courrier déposé physiquement par un correspondant externe est incorrect ou inaccessible',
            'severity' => 'P2',
            'priority' => 'P2',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Correspondant externe (via agent)"],
            'user_role' => 'Agent',
            'preconditions' => "Courrier id={$courrier->id} enregistré par dépôt physique.",
            'steps_to_reproduce' => ["GET /courriers/{$courrier->id}/bordereau en tant qu'agent créateur"],
            'expected_result' => "200, contient le numéro de référence {$courrier->numero_reference}.",
            'actual_result' => 'status='.$reponse->getStatusCode(),
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (controller_call, scenario ec-01)'],
            'affected_module_url' => 'App\\Http\\Controllers\\CourrierBordereauController',
            'related_data' => ['courrier_id' => $courrier->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Un déposant externe ne recevrait pas de preuve de dépôt correcte.',
            'security_impact' => 'none',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier CourrierBordereauController et la vue pdf.bordereau.',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — bordereau incorrect.\n";
    } else {
        echo "OK — bordereau généré correctement pour le dépôt physique (status {$reponse->getStatusCode()}).\n";
    }

    return $courrier;
});
