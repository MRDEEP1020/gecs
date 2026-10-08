<?php

// CEO / Test Director — compile daily-report.md à partir de l'état réel du
// run (events.jsonl, manifest.json, bugs/*.json). Dernière phase, toujours
// exécutée en dernier.
//
// Usage : php tests/ai-company-sim/scenarios/ceo/ceo-02-generate-report.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : ceo-02-generate-report.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$runInfo = $memoire->lireRunInfo();
$evenements = $memoire->lireEvenements();
$bugs = $memoire->listerBugs();
$manifest = $memoire->lireManifest();

$bugsConfirmes = array_filter($bugs, fn ($b) => $b['status'] === 'confirmed');
$bugsRejetes = array_filter($bugs, fn ($b) => $b['status'] === 'rejected');
$bugsNonVerifies = array_filter($bugs, fn ($b) => in_array($b['status'], ['reported', 'unverified'], true));

$parSeverite = ['P0' => [], 'P1' => [], 'P2' => [], 'P3' => []];
foreach ($bugsConfirmes as $b) {
    $parSeverite[$b['severity']][] = $b;
}

$findingsSecurite = array_filter($bugsConfirmes, fn ($b) => ($b['security_impact'] ?? 'none') !== 'none');
$findingsUx = array_filter($bugs, fn ($b) => str_contains(strtolower($b['affected_module_url'] ?? ''), 'ux') || ($b['detected_by']['role'] ?? '') === 'ux_researcher');

// Seuls les 9 rôles réels du plan — les étiquettes de comptes de
// configuration (TaggedFactory::utilisateur() journalise sous le rôle_acio
// court, ex. "ops-agent") sont rattachées au rôle principal du scénario en
// cours via le dernier 'scenario_started' vu, pas affichées séparément.
$rolesConnus = ['operations_employee', 'administrator', 'external_correspondent', 'product_manager', 'finance_employee', 'ux_researcher', 'security_engineer', 'ceo_test_director', 'qa_engineer'];

$activiteParRole = [];
$dernierRolePrincipal = null;
foreach ($evenements as $e) {
    $role = $e['actor_role'] ?? 'inconnu';

    if (in_array($role, $rolesConnus, true)) {
        $dernierRolePrincipal = $role;
    } elseif ($e['action'] === 'user_created' && $dernierRolePrincipal !== null) {
        // Compte de configuration créé pendant un scénario en cours —
        // rattaché au rôle principal, pas affiché comme un "employé" séparé.
        $role = $dernierRolePrincipal;
    }

    $activiteParRole[$role] ??= ['total' => 0, 'succes' => 0, 'echecs' => 0, 'scenarios' => []];
    $activiteParRole[$role]['total']++;
    if (($e['result'] ?? null) === 'success') {
        $activiteParRole[$role]['succes']++;
    } elseif (($e['result'] ?? null) === 'failure') {
        $activiteParRole[$role]['echecs']++;
    }
    if (isset($e['scenario'])) {
        $activiteParRole[$role]['scenarios'][$e['scenario']] = true;
    }
}

$nP0 = count($parSeverite['P0']);
$nP1 = count($parSeverite['P1']);
$sante = $nP0 > 0 ? 'FAIL' : ($nP1 > 0 ? 'WARNING' : 'PASS');
$recommandation = $nP0 > 0 ? 'CRITICAL FAILURE' : ($nP1 > 0 ? 'READY WITH MINOR FIXES' : 'READY FOR PRODUCTION');

$md = "# Rapport de journée simulée — AI Employee Company (GEC)\n\n";
$md .= "**Run** : `{$runInfo['run_id']}` — **Graine** : `{$runInfo['seed']}`\n\n";
$md .= "## Résumé exécutif\n\n**Santé globale : {$sante}**\n\n";
$md .= 'Bugs confirmés : '.count($bugsConfirmes)." (P0: {$nP0}, P1: {$nP1}, P2: ".count($parSeverite['P2']).', P3: '.count($parSeverite['P3']).")\n";
$md .= 'Bugs rejetés (faux positifs, vérifiés) : '.count($bugsRejetes)."\n";
$md .= 'Bugs non vérifiés / en attente : '.count($bugsNonVerifies)."\n\n";

$md .= "## Limitations honnêtes de ce système (à lire avant toute conclusion)\n\n";
$md .= "- **Aucune automatisation de navigateur n'existe dans cet environnement** : aucun test visuel, CSS, responsive, ni erreur console JS n'a été (ni ne peut être) effectué. L'UX Researcher se limite à ce qui est observable en HTTP/données.\n";
$md .= "- **« Customer »** a été remappé en « External Correspondent » : GEC n'a aucune surface libre-service externe réelle — ce rôle représente la personne dont le courrier est traité par un agent, jamais un compte GEC.\n";
$md .= "- **« Finance »** a été remappé en intégrité numérique/séquentielle (numero_reference, confidentialité, décharges) : GEC n'a aucun module de facturation/paiement réel.\n\n";

$md .= "## Activité par employé\n\n";
foreach ($activiteParRole as $role => $stats) {
    $md .= "- **{$role}** : ".count($stats['scenarios'])." scénario(s), {$stats['total']} événement(s), {$stats['succes']} succès, {$stats['echecs']} échec(s).\n";
}

$md .= "\n## Bugs confirmés par sévérité\n\n";
foreach (['P0', 'P1', 'P2', 'P3'] as $sev) {
    $md .= "### {$sev} (".count($parSeverite[$sev]).")\n\n";
    if (empty($parSeverite[$sev])) {
        $md .= "Aucun.\n\n";

        continue;
    }
    foreach ($parSeverite[$sev] as $b) {
        $md .= "- **{$b['bug_id']}** — {$b['title']}\n";
        $md .= "  - Détecté par : {$b['detected_by']['role']} | Module : {$b['affected_module_url']}\n";
        $md .= "  - Impact métier : {$b['business_impact']}\n";
        $md .= "  - Correctif recommandé : {$b['recommended_fix']}\n";
    }
    $md .= "\n";
}

$md .= "## Findings sécurité (séparés)\n\n";
if (empty($findingsSecurite)) {
    $md .= "Aucun finding de sécurité confirmé ce run.\n\n";
} else {
    foreach ($findingsSecurite as $b) {
        $md .= "- **{$b['bug_id']}** — {$b['title']} : {$b['security_impact']}\n";
    }
    $md .= "\n";
}

$md .= "## Résultats de régression\n\n";
$md .= "Bugs confirmés de runs précédents re-testés ce run : voir la sortie de `qa-verify-bugs.php` (aucun historique de bug confirmé à ce jour — premier run complet du système).\n\n";

$md .= "## Bugs rejetés (faux positifs vérifiés, pour transparence)\n\n";
foreach ($bugsRejetes as $b) {
    $md .= "- **{$b['bug_id']}** — {$b['title']}\n";
    $md .= '  - Raison du rejet : '.($b['rejection_reason'] ?? 'non renseignée')."\n";
}
if (empty($bugsRejetes)) {
    $md .= "Aucun.\n";
}

$md .= "\n## Bugs non vérifiés / en attente\n\n";
foreach ($bugsNonVerifies as $b) {
    $md .= "- **{$b['bug_id']}** — {$b['title']} (statut : {$b['status']})\n";
}
if (empty($bugsNonVerifies)) {
    $md .= "Aucun.\n";
}

$md .= "\n## Impact métier\n\n";
if ($nP0 > 0 || $nP1 > 0) {
    $md .= "Des défauts P0/P1 confirmés affecteraient directement des workflows réels (voir détail par bug ci-dessus) — correction requise avant le pilote.\n\n";
} else {
    $md .= "Aucun défaut confirmé de sévérité P0/P1 — les workflows métier testés ce run se comportent conformément aux règles documentées (DECISIONS.md).\n\n";
}

$md .= "## Actions recommandées\n\n";
if (empty($bugsConfirmes)) {
    $md .= "1. Aucune action corrective requise suite à ce run.\n";
    $md .= "2. Étendre la couverture (voir §8 \"descopes\" du plan d'architecture) lors d'une prochaine itération : concurrence réelle contrôlée, historique de régression complet, Module 5/7 (SLA) plus en profondeur.\n\n";
} else {
    $i = 1;
    foreach (array_merge($parSeverite['P0'], $parSeverite['P1']) as $b) {
        $md .= ($i++).". Corriger {$b['bug_id']} ({$b['severity']}) — {$b['title']}\n";
    }
    $md .= "\n";
}

$md .= "## Recommandation de mise en production\n\n**{$recommandation}**\n";

file_put_contents($memoire->runDir.'/daily-report.md', $md);
echo "Rapport généré : {$memoire->runDir}/daily-report.md\n";
echo "Santé globale : {$sante} | Recommandation : {$recommandation}\n";
