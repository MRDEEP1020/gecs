<?php

// Security Engineer — scénario 3 : re-vérification de la classe de bug
// EditForm::courrier() déjà corrigée une fois dans ce projet (2026-09-24,
// voir memory "pentest_courrierlist_idor") — monter EditForm sur un
// courrier autorisé, puis ÉCHANGER courrierId via ->set() pour viser un
// courrier hors périmètre, et tenter d'enregistrer une modification.
//
// Usage : php tests/ai-company-sim/scenarios/security/sec-03-idor-editform-courrierid-swap.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\EditForm;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\Service;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : sec-03-idor-editform-courrierid-swap.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'security_engineer';

Scenario::executer($memoire, $role, 'sec-03-idor-editform-courrierid-swap', function () use ($memoire, $seed, $role) {
    $attaquant = TaggedFactory::utilisateur($memoire, $seed, 'attaquant-sec03', 'Agent');

    $serviceAttaquant = Service::create(['nom' => TaggedFactory::tag($seed, 'Service Attaquant Sec03'), 'code' => TaggedFactory::codeCourt($seed, 'sec-03-a'), 'actif' => true]);
    $memoire->enregistrerEntite('Service', $serviceAttaquant->id, ['tag' => "AICO-{$seed}"]);
    $serviceVictime = Service::create(['nom' => TaggedFactory::tag($seed, 'Service Victime Sec03'), 'code' => TaggedFactory::codeCourt($seed, 'sec-03-v'), 'actif' => true]);
    $memoire->enregistrerEntite('Service', $serviceVictime->id, ['tag' => "AICO-{$seed}"]);

    $courrierAutorise = Courrier::create([
        'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999), 'sens' => 'entrant', 'date_mouvement' => now(),
        'objet' => TaggedFactory::tag($seed, 'Courrier légitimement modifiable par l\'attaquant'),
        'type_document' => 'Lettre', 'mode_reception' => 'email', 'service_id' => $serviceAttaquant->id, 'statut' => 'enregistre',
    ]);
    $memoire->enregistrerEntite('Courrier', $courrierAutorise->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrierAutorise->numero_reference, 'created_by_role' => $role]);
    CourrierHistorique::create(['courrier_id' => $courrierAutorise->id, 'auteur_id' => $attaquant->id, 'action' => 'creation']);

    $courrierVictime = Courrier::create([
        'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999), 'sens' => 'entrant', 'date_mouvement' => now(),
        'objet' => TaggedFactory::tag($seed, 'Courrier de la victime — ne doit jamais être modifiable'),
        'type_document' => 'Lettre', 'mode_reception' => 'email', 'service_id' => $serviceVictime->id, 'statut' => 'enregistre',
    ]);
    $memoire->enregistrerEntite('Courrier', $courrierVictime->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrierVictime->numero_reference, 'created_by_role' => $role]);
    $tiers = TaggedFactory::utilisateur($memoire, $seed, 'tiers-sec03', 'Agent');
    CourrierHistorique::create(['courrier_id' => $courrierVictime->id, 'auteur_id' => $tiers->id, 'action' => 'creation']);
    $objetVictimeAvant = $courrierVictime->objet;

    Livewire::actingAs($attaquant);

    // Ne PAS se fier à assertForbidden()/une exception précise pour
    // juger du résultat : un authorize() qui échoue EN COURS de méthode
    // (pas au mount()) corrompt le snapshot retourné par le harnais de
    // test Livewire, et toute interaction suivante (même assertForbidden())
    // lève alors une InvalidArgumentException de bas niveau sans rapport
    // avec le vrai verdict (constat fait en déboguant ce scénario précis).
    // Le seul signal fiable est l'effet réel en base : l'objet de la
    // victime a-t-il VRAIMENT changé ?
    try {
        Livewire::test(EditForm::class, ['courrierId' => $courrierAutorise->id])
            ->set('courrierId', $courrierVictime->id)
            ->set('form.objet', TaggedFactory::tag($seed, 'OBJET MODIFIÉ PAR L\'ATTAQUANT'))
            ->call('enregistrerModification');
    } catch (\Throwable $e) {
        // Attendu si bloqué (authorize() lève en cours de méthode) — on
        // vérifie l'effet réel ci-dessous, pas cette exception elle-même.
    }

    $courrierVictime->refresh();
    $objetReellementModifie = $courrierVictime->objet !== $objetVictimeAvant;
    $modificationAcceptee = $objetReellementModifie;

    $correct = ! $objetReellementModifie;

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'idor_courrierid_swap', 'target_entity' => ['type' => 'Courrier', 'id' => $courrierVictime->id],
        'result' => $correct ? 'success' : 'failure',
        'modification_acceptee' => $modificationAcceptee, 'objet_reellement_modifie' => $objetReellementModifie,
    ]);

    if (! $correct) {
        $bugId = $memoire->signalerBug([
            'title' => 'RÉGRESSION : EditForm::courrierId échangé via ->set() permet de modifier un courrier hors périmètre',
            'severity' => 'P0',
            'priority' => 'P0',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Security Engineer"],
            'user_role' => 'Agent',
            'preconditions' => "EditForm monté légitimement sur id={$courrierAutorise->id}, puis courrierId basculé vers id={$courrierVictime->id} (hors périmètre).",
            'steps_to_reproduce' => ["EditForm::class, ['courrierId' => {$courrierAutorise->id}]->set('courrierId', {$courrierVictime->id})->call('enregistrerModification')"],
            'expected_result' => '403, objet de la victime inchangé.',
            'actual_result' => 'objet de la victime modifié='.($objetReellementModifie ? 'OUI (IDOR confirmé)' : 'non'),
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (idor_courrierid_swap, scenario sec-03)'],
            'affected_module_url' => 'App\\Livewire\\Backend\\EditForm',
            'related_data' => ['courrier_victime_id' => $courrierVictime->id],
            'frequency' => 'RÉGRESSION — ce exact bug avait déjà été corrigé le 2026-09-24 (voir memory pentest_courrierlist_idor)',
            'business_impact' => 'Un agent pourrait modifier n\'importe quel courrier en changeant juste un id côté client.',
            'security_impact' => 'CRITIQUE — IDOR en écriture, régression d\'un correctif déjà appliqué.',
            'regression_status' => 'regressed',
            'recommended_fix' => 'Revoir EditForm::enregistrerModification() — authorize() doit porter sur le courrierId ACTUEL, pas celui du mount().',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — RÉGRESSION IDOR confirmée sur EditForm.\n";
    } else {
        echo "OK — le correctif IDOR sur EditForm tient toujours (pas de régression).\n";
    }

    return $courrierVictime;
});
