<?php

// Security Engineer — scénario 2 : sondage HTTP réel (curl, vraie session
// cookie, pas d'appel direct au composant) — un utilisateur authentifié
// mais hors périmètre doit recevoir 403 sur /courriers/{id}, jamais 200/500.
//
// Usage : php tests/ai-company-sim/scenarios/security/sec-02-http-privilege-probe.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\Service;
use Illuminate\Support\Facades\Hash;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : sec-02-http-privilege-probe.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'security_engineer';

Scenario::executer($memoire, $role, 'sec-02-http-privilege-probe', function () use ($memoire, $seed, $role) {
    $motDePasse = 'AicoSec2026!'.$seed;
    $attaquant = TaggedFactory::utilisateur($memoire, $seed, 'attaquant-sec02', 'Collaborateur', ['password' => Hash::make($motDePasse)]);

    $serviceVictime = Service::create([
        'nom' => TaggedFactory::tag($seed, 'Service Victime Sec02'),
        'code' => TaggedFactory::codeCourt($seed, 'sec-02-service'),
        'actif' => true,
    ]);
    $memoire->enregistrerEntite('Service', $serviceVictime->id, ['tag' => "AICO-{$seed}"]);

    $courrierVictime = Courrier::create([
        'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999),
        'sens' => 'entrant', 'date_mouvement' => now(),
        'objet' => TaggedFactory::tag($seed, 'Dossier hors périmètre — sondage HTTP'),
        'type_document' => 'Lettre', 'mode_reception' => 'email',
        'service_id' => $serviceVictime->id, 'statut' => 'enregistre', 'confidentialite' => 1,
    ]);
    $memoire->enregistrerEntite('Courrier', $courrierVictime->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrierVictime->numero_reference, 'created_by_role' => $role]);
    // L'auteur de création doit être QUELQU'UN D'AUTRE que l'attaquant,
    // sinon le privilège "voir_propre" l'autoriserait légitimement.
    $tiers = TaggedFactory::utilisateur($memoire, $seed, 'tiers-sec02', 'Agent');
    CourrierHistorique::create(['courrier_id' => $courrierVictime->id, 'auteur_id' => $tiers->id, 'action' => 'creation']);

    $cookieJar = tempnam(sys_get_temp_dir(), 'aico_sec02_');

    $ch = curl_init('https://gecs.test/login');
    curl_setopt_array($ch, [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_COOKIEJAR => $cookieJar, CURLOPT_COOKIEFILE => $cookieJar, CURLOPT_RETURNTRANSFER => true]);
    $html = curl_exec($ch);
    curl_close($ch);
    preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m);
    $token = $m[1] ?? '';

    $ch = curl_init('https://gecs.test/login');
    curl_setopt_array($ch, [
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_COOKIEJAR => $cookieJar, CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query(['_token' => $token, 'email' => $attaquant->email, 'password' => $motDePasse]),
    ]);
    curl_exec($ch);
    curl_close($ch);

    $ch = curl_init("https://gecs.test/courriers/{$courrierVictime->id}");
    curl_setopt_array($ch, [
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_COOKIEJAR => $cookieJar, CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => true,
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    @unlink($cookieJar);

    $correct = $status === 403;

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'http_privilege_probe', 'target_entity' => ['type' => 'Courrier', 'id' => $courrierVictime->id],
        'result' => $correct ? 'success' : 'failure', 'http_status' => $status,
    ]);

    if (! $correct) {
        $bugId = $memoire->signalerBug([
            'title' => 'Un collaborateur authentifié hors périmètre reçoit un statut HTTP inattendu sur un courrier qui ne lui appartient pas',
            'severity' => $status === 200 ? 'P0' : 'P2',
            'priority' => $status === 200 ? 'P0' : 'P2',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Security Engineer"],
            'user_role' => 'Collaborateur',
            'preconditions' => "Attaquant id={$attaquant->id} sans lien avec le service {$serviceVictime->id}, session HTTP réelle.",
            'steps_to_reproduce' => ["curl (session connectée) https://gecs.test/courriers/{$courrierVictime->id}"],
            'expected_result' => '403.',
            'actual_result' => "status={$status}",
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (http_privilege_probe, scenario sec-02)'],
            'affected_module_url' => "/courriers/{$courrierVictime->id}",
            'related_data' => ['courrier_id' => $courrierVictime->id, 'status' => $status],
            'frequency' => 'à confirmer par QA',
            'business_impact' => $status === 200 ? 'Fuite de données courrier à n\'importe quel compte authentifié.' : 'Erreur serveur au lieu d\'un refus propre.',
            'security_impact' => $status === 200 ? 'CRITIQUE — accès non autorisé confirmé en conditions HTTP réelles.' : 'Modéré — mauvaise gestion d\'erreur, pas une fuite.',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier le middleware/Policy réel sur la route courriers.show.',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — status HTTP inattendu : {$status}.\n";
    } else {
        echo "OK — 403 confirmé en conditions HTTP réelles.\n";
    }

    return $courrierVictime;
});
