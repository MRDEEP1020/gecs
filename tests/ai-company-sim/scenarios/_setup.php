<?php

// Phase 0 — initialise un nouveau run : génère la graine, crée le dossier
// runs/<timestamp>-seed-<seed>/ avec ses fichiers d'état vides. N'agit sur
// aucune donnée métier. Affiche le run_id sur stdout pour que l'orchestrateur
// (et les scripts de phase suivants) le récupèrent.
//
// Usage : php tests/ai-company-sim/scenarios/_setup.php [seed]

require __DIR__.'/../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Seed;

$seed = isset($argv[1]) ? (int) $argv[1] : Seed::generer();

$memoire = CompanyMemory::creerNouveauRun($seed);

echo "RUN_ID={$memoire->runDir}\n";
echo 'run_id_court='.basename($memoire->runDir)."\n";
echo "seed={$seed}\n";
