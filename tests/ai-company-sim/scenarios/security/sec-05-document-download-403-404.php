<?php

// Security Engineer — scénario 5 : téléchargement de document — 403 pour un
// courrier hors périmètre, 404 propre (jamais 500) pour un fichier manquant
// sur un courrier accessible.
//
// Usage : php tests/ai-company-sim/scenarios/security/sec-05-document-download-403-404.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Http\Controllers\CourrierDocumentDownloadController;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\Service;
use Illuminate\Support\Facades\Auth;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : sec-05-document-download-403-404.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'security_engineer';

Scenario::executer($memoire, $role, 'sec-05-document-download-403-404', function () use ($memoire, $seed, $role) {
    $attaquant = TaggedFactory::utilisateur($memoire, $seed, 'attaquant-sec05', 'Agent');
    $serviceVictime = Service::create(['nom' => TaggedFactory::tag($seed, 'Service Victime Sec05'), 'code' => TaggedFactory::codeCourt($seed, 'sec-05-v'), 'actif' => true]);
    $memoire->enregistrerEntite('Service', $serviceVictime->id, ['tag' => "AICO-{$seed}"]);
    $tiers = TaggedFactory::utilisateur($memoire, $seed, 'tiers-sec05', 'Agent');

    // Cas 1 : hors périmètre → 403.
    $courrierHorsPerimetre = Courrier::create([
        'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999), 'sens' => 'entrant', 'date_mouvement' => now(),
        'objet' => TaggedFactory::tag($seed, 'Document hors périmètre'),
        'type_document' => 'Lettre', 'mode_reception' => 'email', 'service_id' => $serviceVictime->id, 'statut' => 'enregistre',
        'fichier_path' => 'courriers/inexistant-aico-'.$seed.'.pdf',
    ]);
    $memoire->enregistrerEntite('Courrier', $courrierHorsPerimetre->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrierHorsPerimetre->numero_reference, 'created_by_role' => $role]);
    CourrierHistorique::create(['courrier_id' => $courrierHorsPerimetre->id, 'auteur_id' => $tiers->id, 'action' => 'creation']);

    // Cas 2 : dans le périmètre de l'attaquant, mais fichier absent du stockage → 404.
    $courrierFichierManquant = Courrier::create([
        'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999), 'sens' => 'entrant', 'date_mouvement' => now(),
        'objet' => TaggedFactory::tag($seed, 'Document avec fichier manquant sur le stockage'),
        'type_document' => 'Lettre', 'mode_reception' => 'email', 'statut' => 'enregistre',
        'fichier_path' => 'courriers/inexistant-aico-'.$seed.'-2.pdf',
    ]);
    $memoire->enregistrerEntite('Courrier', $courrierFichierManquant->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrierFichierManquant->numero_reference, 'created_by_role' => $role]);
    CourrierHistorique::create(['courrier_id' => $courrierFichierManquant->id, 'auteur_id' => $attaquant->id, 'action' => 'creation']);

    Auth::login($attaquant);

    $statusHorsPerimetre = null;
    try {
        app(CourrierDocumentDownloadController::class)->__invoke($courrierHorsPerimetre);
        $statusHorsPerimetre = 200;
    } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
        $statusHorsPerimetre = 403;
    } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
        $statusHorsPerimetre = $e->getStatusCode();
    } catch (\Throwable $e) {
        $statusHorsPerimetre = 'exception: '.get_class($e);
    }

    $statusFichierManquant = null;
    try {
        app(CourrierDocumentDownloadController::class)->__invoke($courrierFichierManquant);
        $statusFichierManquant = 200;
    } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
        $statusFichierManquant = 403;
    } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
        $statusFichierManquant = $e->getStatusCode();
    } catch (\Throwable $e) {
        $statusFichierManquant = 'exception: '.get_class($e);
    }

    $correct = $statusHorsPerimetre === 403 && $statusFichierManquant === 404;

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'document_download_check', 'result' => $correct ? 'success' : 'failure',
        'status_hors_perimetre' => $statusHorsPerimetre, 'status_fichier_manquant' => $statusFichierManquant,
    ]);

    if (! $correct) {
        $bugId = $memoire->signalerBug([
            'title' => 'CourrierDocumentDownloadController renvoie un statut incorrect (hors 403/404 attendus)',
            'severity' => 'P1',
            'priority' => 'P1',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Security Engineer"],
            'user_role' => 'Agent',
            'preconditions' => "Cas 1 : courrier id={$courrierHorsPerimetre->id} hors périmètre. Cas 2 : courrier id={$courrierFichierManquant->id} dans le périmètre, fichier_path pointant vers un fichier absent.",
            'steps_to_reproduce' => ['CourrierDocumentDownloadController::__invoke() sur les deux cas'],
            'expected_result' => 'Cas 1 = 403, Cas 2 = 404 (jamais 500/200).',
            'actual_result' => "Cas 1 = {$statusHorsPerimetre}, Cas 2 = {$statusFichierManquant}",
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (document_download_check, scenario sec-05)'],
            'affected_module_url' => 'App\\Http\\Controllers\\CourrierDocumentDownloadController',
            'related_data' => ['courrier_hors_perimetre_id' => $courrierHorsPerimetre->id, 'courrier_fichier_manquant_id' => $courrierFichierManquant->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Un fichier manquant ferait planter la page (500) au lieu d\'un message propre, ou un accès non autorisé serait mal signalé.',
            'security_impact' => $statusHorsPerimetre === 200 ? 'CRITIQUE — téléchargement hors périmètre.' : 'Modéré.',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier Gate::authorize et la distinction NotFoundHttpException/FilesystemException dans le contrôleur.',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — statuts : hors_perimetre={$statusHorsPerimetre}, fichier_manquant={$statusFichierManquant}.\n";
    } else {
        echo "OK — 403 pour hors périmètre, 404 pour fichier manquant.\n";
    }

    return [$courrierHorsPerimetre, $courrierFichierManquant];
});
