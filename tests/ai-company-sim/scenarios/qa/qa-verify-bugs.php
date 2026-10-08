<?php

// QA Engineer — vérifie chaque bug signalé CE run avant qu'il ne compte
// comme confirmé (voir persona QA). Protocole mécanique appliqué ici :
// 1) le(s) id(s) cité(s) dans related_data existent-ils toujours réellement
//    en base (un bug qui cite une ligne déjà nettoyée est "unverified", pas
//    "confirmed" aveuglément) ; 2) tout bug encore "reported" après ce
//    passage doit être examiné manuellement avant d'être compté dans le
//    rapport final — jamais silencieusement promu "confirmed".
//
// Usage : php tests/ai-company-sim/scenarios/qa/qa-verify-bugs.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : qa-verify-bugs.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$bugs = $memoire->listerBugs();

echo 'Bugs à vérifier : '.count(array_filter($bugs, fn ($b) => $b['status'] === 'reported'))."\n";

foreach ($bugs as $bug) {
    if ($bug['status'] !== 'reported') {
        continue;
    }

    $idsCites = [];
    foreach ($bug['related_data'] ?? [] as $cle => $valeur) {
        if (is_int($valeur) && str_ends_with($cle, '_id')) {
            $idsCites[$cle] = $valeur;
        }
    }

    $tousPresents = true;
    foreach ($idsCites as $cle => $id) {
        $table = match (true) {
            str_contains($cle, 'courrier') => 'courriers',
            str_contains($cle, 'user') || str_contains($cle, 'agent') || str_contains($cle, 'dga') || str_contains($cle, 'responsable') || str_contains($cle, 'collaborateur') => 'users',
            str_contains($cle, 'delegation') => 'delegations_dga',
            str_contains($cle, 'decharge') => 'decharges',
            str_contains($cle, 'service') => 'services',
            str_contains($cle, 'regle') => 'regles_classement',
            default => null,
        };

        if ($table && ! \Illuminate\Support\Facades\DB::table($table)->where('id', $id)->exists()) {
            $tousPresents = false;
        }
    }

    if (! $tousPresents) {
        $memoire->mettreAJourBug($bug['bug_id'], [
            'status' => 'unverified',
            'unverified_reason' => 'Une ou plusieurs entités citées dans related_data n\'existent plus en base au moment de la vérification QA.',
            'verified_by' => 'QA Engineer (vérification automatisée, processus neuf)',
            'verified_at' => (new \DateTimeImmutable)->format(DATE_ATOM),
        ]);
        echo "{$bug['bug_id']} → unverified (entité citée disparue)\n";

        continue;
    }

    // Les ids existent toujours : QA DOIT rejouer manuellement
    // steps_to_reproduce avant de confirmer — ce script ne le fait pas
    // automatiquement pour des étapes en texte libre arbitraire (hors
    // scope mécanique de cette 1ère itération, voir le plan approuvé,
    // §8 descopes). Reste "reported" tant que ce n'est pas fait, JAMAIS
    // promu confirmed par défaut.
    echo "{$bug['bug_id']} → entités valides, en attente de rejouer manuellement steps_to_reproduce (reste 'reported').\n";
}

// Régression : bugs confirmés des runs précédents.
$runsAnterieurs = array_filter(glob(AICO_ROOT.'/runs/*'), fn ($d) => basename($d) !== basename($memoire->runDir));
$bugsConfirmesAnterieurs = [];

foreach ($runsAnterieurs as $dir) {
    foreach (glob($dir.'/bugs/BUG-*.json') as $fichier) {
        $b = json_decode(file_get_contents($fichier), true);
        if (($b['status'] ?? null) === 'confirmed') {
            $bugsConfirmesAnterieurs[] = $b;
        }
    }
}

echo "\nBugs CONFIRMÉS de runs précédents à re-tester pour régression : ".count($bugsConfirmesAnterieurs)."\n";
foreach ($bugsConfirmesAnterieurs as $b) {
    echo " - {$b['bug_id']} : {$b['title']}\n";
}

if (empty($bugsConfirmesAnterieurs)) {
    echo "(Aucun bug confirmé dans l'historique des runs précédents — rien à re-tester.)\n";
}
