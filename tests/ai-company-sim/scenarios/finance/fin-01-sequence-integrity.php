<?php

// Finance (rôle adapté — voir persona) : scénario 1 — intégrité de la
// séquence numero_reference après plusieurs enregistrements réels via le
// VRAI formulaire (pas d'insertion directe) : aucun trou, aucun doublon.
//
// Usage : php tests/ai-company-sim/scenarios/finance/fin-01-sequence-integrity.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\RegistrationForm;
use App\Models\Courrier;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : fin-01-sequence-integrity.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'finance_employee';

Scenario::executer($memoire, $role, 'fin-01-sequence-integrity', function () use ($memoire, $seed, $role) {
    $agent = TaggedFactory::utilisateur($memoire, $seed, 'agent-fin01', 'Agent');
    Livewire::actingAs($agent);

    $numeros = [];
    for ($i = 1; $i <= 5; $i++) {
        $objet = TaggedFactory::tag($seed, "Courrier d'audit séquence #{$i}");
        Livewire::test(RegistrationForm::class)
            ->set('form.sens', 'entrant')->set('form.date_mouvement', now()->format('Y-m-d'))
            ->set('form.objet', $objet)->set('form.type_document', 'Lettre')
            ->set('form.mode_reception', 'email')->set('form.priorite', 'normale')->set('form.confidentialite', 1)
            ->call('enregistrer');
        $courrier = Courrier::where('objet', $objet)->latest('id')->first();
        if ($courrier) {
            $memoire->enregistrerEntite('Courrier', $courrier->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier->numero_reference, 'created_by_role' => $role]);
            $numeros[] = $courrier->numero_reference;
        }
    }

    // Pas d'hypothèse de stricte adjacence : d'AUTRES rôles peuvent
    // enregistrer des courriers en parallèle dans la même simulation
    // (compteur partagé, par design — voir Finance persona). Seule
    // l'unicité de NOS propres numéros est une vraie garantie attendue.
    $correct = count($numeros) === 5 && count($numeros) === count(array_unique($numeros));

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'sequence_audit', 'result' => $correct ? 'success' : 'failure',
        'numeros' => $numeros,
    ]);

    if (! $correct) {
        $bugId = $memoire->signalerBug([
            'title' => 'La séquence numero_reference présente un trou ou un doublon après 5 enregistrements successifs',
            'severity' => 'P1',
            'priority' => 'P1',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Finance"],
            'user_role' => 'Agent',
            'preconditions' => '5 enregistrements successifs via RegistrationForm::enregistrer().',
            'steps_to_reproduce' => ['5x RegistrationForm::enregistrer() successifs, comparer les numero_reference obtenus'],
            'expected_result' => '5 numéros uniques, séquence strictement croissante de 1.',
            'actual_result' => json_encode($numeros),
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (sequence_audit, scenario fin-01)'],
            'affected_module_url' => 'App\\Services\\NumeroReferenceGenerator',
            'related_data' => ['numeros' => $numeros],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Des numéros de référence dupliqués ou incohérents compromettraient la traçabilité officielle du courrier.',
            'security_impact' => 'none',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier NumeroSequence::lockForUpdate() dans NumeroReferenceGenerator.',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — séquence incorrecte.\n";
    } else {
        echo 'OK — 5 numéros uniques et séquentiels : '.implode(', ', $numeros)."\n";
    }

    return $numeros;
});
