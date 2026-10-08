<?php

// Opérations — scénario 5 : émission d'une décharge (emprunt de l'original
// physique d'un courrier archivé, Module 9) puis marquage "rendue". Module
// avec ~aucune couverture automatisée existante trouvée — zone plausible de
// bug de premier passage (voir mémoire : risque de cache obsolète déjà
// documenté sur dechargeActive).
//
// Usage : php tests/ai-company-sim/scenarios/operations/op-05-decharge-emprunt-retour.php <run_id>

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
    fwrite(STDERR, "Usage : op-05-decharge-emprunt-retour.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'operations_employee';

Scenario::executer($memoire, $role, 'op-05-decharge-emprunt-retour', function () use ($memoire, $seed, $role) {
    $admin = TaggedFactory::utilisateur($memoire, $seed, 'admin-op05', 'Administrateur');
    $emprunteur = TaggedFactory::utilisateur($memoire, $seed, 'emprunteur-op05', 'Collaborateur');

    $objet = TaggedFactory::tag($seed, 'Contrat archivé — dossier emprunté pour consultation physique');

    $courrier = Courrier::create([
        'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999),
        'sens' => 'entrant',
        'date_mouvement' => now()->subMonths(6),
        'objet' => $objet,
        'type_document' => 'Contrat',
        'mode_reception' => 'depot_physique',
        'statut' => 'archive',
    ]);
    $memoire->enregistrerEntite('Courrier', $courrier->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier->numero_reference, 'created_by_role' => $role]);
    CourrierHistorique::create(['courrier_id' => $courrier->id, 'auteur_id' => $admin->id, 'action' => 'creation']);

    Livewire::actingAs($admin);

    $motif = TaggedFactory::tag($seed, 'Consultation pour un contrôle interne');

    $testEmission = Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
        ->set('dechargeEmprunteurId', $emprunteur->id)
        ->set('dechargeMotif', $motif)
        ->call('emettreDecharge');

    $decharge = Decharge::where('courrier_id', $courrier->id)->latest('id')->first();
    $emissionOk = $testEmission->errors()->isEmpty() && $decharge && $decharge->rendu_le === null;

    if ($decharge) {
        $memoire->enregistrerEntite('Decharge', $decharge->id, ['tag' => "AICO-{$seed}", 'courrier_id' => $courrier->id]);
    }

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'livewire_call', 'component' => ShowCourrier::class, 'method' => 'emettreDecharge',
        'target_entity' => ['type' => 'Courrier', 'id' => $courrier->id],
        'result' => $emissionOk ? 'success' : 'failure',
        'decharge_id' => $decharge?->id,
    ]);

    if (! $emissionOk) {
        $bugId = $memoire->signalerBug([
            'title' => 'Émission d\'une décharge sur un courrier archivé échoue ou ne crée pas la ligne Decharge attendue',
            'severity' => 'P2',
            'priority' => 'P2',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Opérations"],
            'user_role' => 'Administrateur',
            'preconditions' => "Courrier id={$courrier->id} au statut 'archive'.",
            'steps_to_reproduce' => ["ShowCourrier::class, ['courrierId' => {$courrier->id}]->set('dechargeEmprunteurId', {$emprunteur->id})->call('emettreDecharge')"],
            'expected_result' => 'Une ligne Decharge est créée, rendu_le est null, numero_reference généré (format DECH-<année>-<id>).',
            'actual_result' => 'decharge='.($decharge ? "id {$decharge->id}" : 'aucune').', erreurs='.json_encode($testEmission->errors()->all()),
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (livewire_call, scenario op-05)'],
            'affected_module_url' => 'App\\Livewire\\Backend\\ShowCourrier',
            'related_data' => ['courrier_id' => $courrier->id, 'decharge_id' => $decharge?->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Impossible de tracer un emprunt d\'original physique — risque de conformité (Module 9).',
            'security_impact' => 'none',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier WorkflowService::emettreDecharge() et CourrierPolicy::emettreDecharge().',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — émission de décharge incorrecte.\n";

        return $courrier;
    }

    echo "OK — décharge émise : id={$decharge->id} numero_reference={$decharge->numero_reference}\n";

    // Le même courrier ré-emprunté AVANT retour doit être refusé (une seule
    // décharge active à la fois) — risque de cache obsolète déjà documenté
    // en mémoire sur $courrier->dechargeActive.
    $courrierFrais = Courrier::find($courrier->id);
    $testDoubleEmprunt = Livewire::test(ShowCourrier::class, ['courrierId' => $courrierFrais->id])
        ->set('dechargeEmprunteurId', $emprunteur->id)
        ->call('emettreDecharge');

    $secondeDechargeCreee = Decharge::where('courrier_id', $courrier->id)->count() > 1;

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'livewire_call', 'method' => 'emettreDecharge (double emprunt)',
        'target_entity' => ['type' => 'Courrier', 'id' => $courrier->id],
        'result' => $secondeDechargeCreee ? 'failure' : 'success',
    ]);

    if ($secondeDechargeCreee) {
        $bugId = $memoire->signalerBug([
            'title' => 'Un courrier déjà emprunté (décharge active) peut être ré-emprunté — deux décharges actives simultanées',
            'severity' => 'P1',
            'priority' => 'P1',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Opérations"],
            'user_role' => 'Administrateur',
            'preconditions' => "Courrier id={$courrier->id} a déjà une décharge active (non rendue).",
            'steps_to_reproduce' => ['Émettre une décharge', 'Sans la marquer rendue, émettre une seconde décharge sur le même courrier'],
            'expected_result' => 'La seconde émission est refusée (une seule décharge active à la fois).',
            'actual_result' => 'Une seconde ligne Decharge a été créée pour le même courrier.',
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (double emprunt, scenario op-05)'],
            'affected_module_url' => 'App\\Livewire\\Backend\\ShowCourrier / App\\Services\\WorkflowService::emettreDecharge',
            'related_data' => ['courrier_id' => $courrier->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Deux personnes pourraient croire détenir légitimement l\'original physique du même courrier.',
            'security_impact' => 'none',
            'regression_status' => 'new',
            'recommended_fix' => 'Revoir la vérification "déjà emprunté" dans WorkflowService::emettreDecharge() — mémoire du projet documente un risque de cache obsolète sur $courrier->dechargeActive.',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — double emprunt accepté.\n";
    } else {
        echo "OK — double emprunt correctement refusé.\n";
    }

    // Retour de la décharge.
    $testRetour = Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])->call('marquerDechargeRendue');
    $decharge->refresh();
    $retourOk = $decharge->rendu_le !== null;

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'livewire_call', 'method' => 'marquerDechargeRendue',
        'target_entity' => ['type' => 'Decharge', 'id' => $decharge->id],
        'result' => $retourOk ? 'success' : 'failure',
    ]);

    echo $retourOk ? "OK — décharge marquée rendue.\n" : "ÉCHEC — décharge non marquée rendue.\n";

    if (! $retourOk) {
        $memoire->signalerBug([
            'title' => 'marquerDechargeRendue() ne renseigne pas rendu_le',
            'severity' => 'P2',
            'priority' => 'P2',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Opérations"],
            'user_role' => 'Administrateur',
            'preconditions' => "Décharge active id={$decharge->id}.",
            'steps_to_reproduce' => ["ShowCourrier::class, ['courrierId' => {$courrier->id}]->call('marquerDechargeRendue')"],
            'expected_result' => 'rendu_le est renseigné à now().',
            'actual_result' => 'rendu_le reste null.',
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (marquerDechargeRendue, scenario op-05)'],
            'affected_module_url' => 'App\\Livewire\\Backend\\ShowCourrier',
            'related_data' => ['decharge_id' => $decharge->id, 'courrier_id' => $courrier->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Le dossier resterait indéfiniment marqué comme emprunté.',
            'security_impact' => 'none',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier WorkflowService::marquerDechargeRendue().',
        ]);
    }

    return $courrier;
});
