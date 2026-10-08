<?php

// External Correspondent — scénario 3 : GEC n'a AUCUNE surface libre-
// service externe — on confirme qu'aucune route courriers.*/admin.* n'est
// accessible sans authentification (il n'y a pas de "connexion client" à
// contourner, puisqu'elle n'existe pas). Vraie requête HTTP (curl), sans
// cookie, contre le serveur de dev réel.
//
// Usage : php tests/ai-company-sim/scenarios/external_correspondent/ec-03-no-unauthenticated-access.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : ec-03-no-unauthenticated-access.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'external_correspondent';

Scenario::executer($memoire, $role, 'ec-03-no-unauthenticated-access', function () use ($memoire, $seed, $role) {
    $routes = [
        '/dashboard',
        '/courriers/rechercher',
        '/courriers/enregistres',
        '/courriers/1',
        '/admin/utilisateurs',
        '/admin/organisation',
    ];

    $resultats = [];

    foreach ($routes as $route) {
        $ch = curl_init("https://gecs.test{$route}");
        curl_setopt_array($ch, [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Correct = redirigé vers le login (302) ou refusé (401/403) —
        // JAMAIS 200 sans authentification.
        $resultats[$route] = ['status' => $status, 'ok' => in_array($status, [302, 401, 403], true)];
    }

    $toutOk = ! in_array(false, array_column($resultats, 'ok'), true);

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'http_unauthenticated_sweep', 'result' => $toutOk ? 'success' : 'failure',
        'details' => $resultats,
    ]);

    foreach ($resultats as $route => $r) {
        if (! $r['ok']) {
            $memoire->signalerBug([
                'title' => "La route '{$route}' est accessible sans authentification",
                'severity' => 'P0',
                'priority' => 'P0',
                'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Correspondant externe"],
                'user_role' => 'invité (non authentifié)',
                'preconditions' => 'Aucun cookie de session.',
                'steps_to_reproduce' => ["curl -k https://gecs.test{$route}"],
                'expected_result' => '302 (redirection login) ou 401/403.',
                'actual_result' => 'status='.$r['status'],
                'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (http_unauthenticated_sweep, scenario ec-03)'],
                'affected_module_url' => $route,
                'related_data' => $r,
                'frequency' => 'à confirmer par QA',
                'business_impact' => 'Fuite de données courrier/administration à n\'importe qui sur le réseau, sans compte.',
                'security_impact' => 'CRITIQUE — accès non authentifié à des données internes.',
                'regression_status' => 'new',
                'recommended_fix' => 'Vérifier le middleware auth sur cette route dans routes/web.php.',
            ]);
        }
    }

    echo 'Résultats : '.json_encode($resultats)."\n";

    return $resultats;
});
