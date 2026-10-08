<?php

// Finance (rôle adapté) : scénario 3 — audit de la numérotation des
// décharges (format DECH-<année>-<id paddé>) sur un petit lot : aucun
// doublon, format respecté.
//
// Usage : php tests/ai-company-sim/scenarios/finance/fin-03-decharge-numerotation-audit.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\ShowCourrier;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\Decharge;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : fin-03-decharge-numerotation-audit.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'finance_employee';

Scenario::executer($memoire, $role, 'fin-03-decharge-numerotation-audit', function () use ($memoire, $seed, $role) {
    $admin = TaggedFactory::utilisateur($memoire, $seed, 'admin-fin03', 'Administrateur');
    $emprunteur = TaggedFactory::utilisateur($memoire, $seed, 'emprunteur-fin03', 'Collaborateur');
    Livewire::actingAs($admin);

    $numeros = [];
    for ($i = 1; $i <= 3; $i++) {
        $courrier = Courrier::create([
            'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999),
            'sens' => 'entrant', 'date_mouvement' => now()->subMonths(3),
            'objet' => TaggedFactory::tag($seed, "Contrat archivé #{$i} — audit décharges"),
            'type_document' => 'Contrat', 'mode_reception' => 'depot_physique', 'statut' => 'archive',
        ]);
        $memoire->enregistrerEntite('Courrier', $courrier->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier->numero_reference, 'created_by_role' => $role]);
        CourrierHistorique::create(['courrier_id' => $courrier->id, 'auteur_id' => $admin->id, 'action' => 'creation']);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->set('dechargeEmprunteurId', $emprunteur->id)
            ->call('emettreDecharge');

        $decharge = Decharge::where('courrier_id', $courrier->id)->latest('id')->first();
        if ($decharge) {
            $memoire->enregistrerEntite('Decharge', $decharge->id, ['tag' => "AICO-{$seed}", 'courrier_id' => $courrier->id]);
            $numeros[] = $decharge->numero_reference;
        }
    }

    $formatValide = array_reduce($numeros, fn ($ok, $n) => $ok && (bool) preg_match('/^DECH-\d{4}-\d+$/', $n), true);
    $correct = count($numeros) === 3 && count($numeros) === count(array_unique($numeros)) && $formatValide;

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'decharge_numbering_audit', 'result' => $correct ? 'success' : 'failure',
        'numeros' => $numeros,
    ]);

    if (! $correct) {
        $bugId = $memoire->signalerBug([
            'title' => 'La numérotation des décharges présente un doublon ou un format incorrect',
            'severity' => 'P2',
            'priority' => 'P2',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Finance"],
            'user_role' => 'Administrateur',
            'preconditions' => '3 décharges émises successivement sur 3 courriers archivés distincts.',
            'steps_to_reproduce' => ['3x ShowCourrier::emettreDecharge() successifs'],
            'expected_result' => '3 numéros uniques, format DECH-<année>-<id>.',
            'actual_result' => json_encode($numeros),
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (decharge_numbering_audit, scenario fin-03)'],
            'affected_module_url' => 'App\\Models\\Decharge::booted()',
            'related_data' => ['numeros' => $numeros],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Des reçus de décharge dupliqués ou mal formés compromettraient la traçabilité des emprunts physiques (Module 9).',
            'security_impact' => 'none',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier Decharge::booted() (hook created) pour la génération du numéro.',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — numérotation décharge incorrecte.\n";
    } else {
        echo 'OK — 3 décharges, numéros uniques et bien formés : '.implode(', ', $numeros)."\n";
    }

    return $numeros;
});
