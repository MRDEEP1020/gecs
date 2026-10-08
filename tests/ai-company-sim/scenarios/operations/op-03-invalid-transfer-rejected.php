<?php

// Opérations — scénario 3 : un agent tente de transférer un courrier à un
// DGA qui n'est PAS dans sa liste autorisée de destinataires
// (destinatairesTransfert()) — doit être refusé par la validation, jamais
// silencieusement accepté (Règle n°6 CLAUDE.md : jamais confiance à un ID
// client sans vérification serveur).
//
// Usage : php tests/ai-company-sim/scenarios/operations/op-03-invalid-transfer-rejected.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\RegistrationForm;
use App\Livewire\Backend\ShowCourrier;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : op-03-invalid-transfer-rejected.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'operations_employee';

Scenario::executer($memoire, $role, 'op-03-invalid-transfer-rejected', function () use ($memoire, $seed, $role) {
    $agent = TaggedFactory::utilisateur($memoire, $seed, 'ops-agent', 'Agent');
    $dgaNonAutorise = TaggedFactory::utilisateur($memoire, $seed, 'dga-non-autorise', 'DGA');
    // Volontairement PAS attaché à destinatairesTransfert() de l'agent.

    Livewire::actingAs($agent);

    $objet = TaggedFactory::tag($seed, 'Courrier à transférer — test de rejet de destinataire non autorisé');

    $test = Livewire::test(RegistrationForm::class)
        ->set('form.sens', 'entrant')
        ->set('form.date_mouvement', now()->format('Y-m-d'))
        ->set('form.objet', $objet)
        ->set('form.type_document', 'Lettre')
        ->set('form.mode_reception', 'email')
        ->set('form.priorite', 'normale')
        ->set('form.confidentialite', 1)
        ->call('enregistrer');

    $courrier = Courrier::where('objet', $objet)->latest('id')->first();

    if (! $courrier) {
        $memoire->enregistrerEvenement([
            'actor_role' => $role, 'action' => 'livewire_call', 'result' => 'failure',
            'details' => 'Précondition non remplie — courrier non créé, scénario de rejet de transfert non testable.',
        ]);

        return null;
    }

    $memoire->enregistrerEntite('Courrier', $courrier->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier->numero_reference, 'created_by_role' => $role]);
    CourrierHistorique::create(['courrier_id' => $courrier->id, 'auteur_id' => $agent->id, 'action' => 'creation']);

    $testTransfert = Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
        ->set('destinataireTransfertChoisi', $dgaNonAutorise->id)
        ->call('transferer');

    $aUneErreur = $testTransfert->errors()->has('destinataireTransfertChoisi');
    $courrier->refresh();
    $transfertAEuLieu = $courrier->statut === 'en_cours_de_transfert' || $courrier->destinataire_transfert_id === $dgaNonAutorise->id;

    $correct = $aUneErreur && ! $transfertAEuLieu;

    $memoire->enregistrerEvenement([
        'actor_role' => $role,
        'action' => 'livewire_call',
        'component' => ShowCourrier::class,
        'method' => 'transferer',
        'target_entity' => ['type' => 'Courrier', 'id' => $courrier->id],
        'result' => $correct ? 'success' : 'failure',
        'details' => "Tentative de transfert vers un DGA (id={$dgaNonAutorise->id}) absent de destinatairesTransfert() de l'agent",
        'a_une_erreur_destinataire' => $aUneErreur,
        'transfert_a_eu_lieu' => $transfertAEuLieu,
    ]);

    if (! $correct) {
        $bugId = $memoire->signalerBug([
            'title' => 'Un courrier peut être transféré à un DGA non présent dans la liste autorisée de l\'agent',
            'severity' => 'P1',
            'priority' => 'P1',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Agent Opérations"],
            'user_role' => 'Agent',
            'preconditions' => "Agent id={$agent->id} sans {$dgaNonAutorise->id} dans destinatairesTransfert().",
            'steps_to_reproduce' => [
                "Livewire::test(ShowCourrier::class, ['courrierId' => {$courrier->id}])->set('destinataireTransfertChoisi', {$dgaNonAutorise->id})->call('transferer')",
            ],
            'expected_result' => "Erreur de validation sur destinataireTransfertChoisi, statut inchangé.",
            'actual_result' => 'erreur='.($aUneErreur ? 'oui' : 'non').', transfert_effectif='.($transfertAEuLieu ? 'oui' : 'non').', statut final='.$courrier->statut,
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (livewire_call, scenario op-03)'],
            'affected_module_url' => 'App\\Livewire\\Backend\\ShowCourrier',
            'related_data' => ['courrier_id' => $courrier->id, 'agent_id' => $agent->id, 'dga_non_autorise_id' => $dgaNonAutorise->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Un agent pourrait transférer un courrier à n\'importe quel DGA, contournant la liste de destinataires curatée par l\'administrateur.',
            'security_impact' => 'Contournement d\'un contrôle d\'autorisation côté serveur (Règle n°6 CLAUDE.md).',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier ShowCourrier::transferer() revalide bien contre Auth::user()->destinatairesTransfert() côté serveur.',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — transfert vers destinataire non autorisé n'a pas été bloqué.\n";
    } else {
        echo "OK — transfert vers un DGA non autorisé correctement refusé.\n";
    }

    return $courrier;
});
