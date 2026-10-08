<?php

// "AI Employee Company" testing system — boots the REAL application against
// the REAL dev database (mysql/gec from the real .env), deliberately NOT via
// PHPUnit (phpunit.xml forces sqlite/:memory: for the `testing` environment,
// which is the opposite of what this system needs). Livewire::test() has
// been verified to work fine from a plain booted app — it implements its own
// test harness (RequestBroker/InitialRender/SubsequentRender) using only the
// container, nothing PHPUnit-specific. See the plan at
// C:\Users\NSIANV-EDS-ANK\.claude\plans\lovely-splashing-yeti.md for the
// full rationale.
//
// Every script in scenarios/ requires this file first, then uses
// AiCompanySim\CompanyMemory / TaggedFactory / Seed to act and record state.

define('AICO_ROOT', __DIR__);

require AICO_ROOT.'/../../vendor/autoload.php';

$app = require AICO_ROOT.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Safety check repeated from every real-DB script this session has used —
// this system must NEVER accidentally run against something unexpected.
$dbName = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
if ($dbName !== 'gec') {
    fwrite(STDERR, "ABANDON : base inattendue '{$dbName}', 'gec' attendue.\n");
    exit(1);
}

require AICO_ROOT.'/lib/Seed.php';
require AICO_ROOT.'/lib/CompanyMemory.php';
require AICO_ROOT.'/lib/TaggedFactory.php';
require AICO_ROOT.'/lib/Scenario.php';

return $app;
