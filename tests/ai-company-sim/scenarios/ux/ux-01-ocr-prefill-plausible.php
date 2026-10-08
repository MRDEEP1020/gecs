<?php

// UX Researcher (périmètre honnêtement limité — voir persona) : scénario 1
// — un scan OCR avec un texte structuré réaliste ("Objet : ...") doit
// produire un pré-remplissage non vide et plausible de form.objet, pas
// juste "le champ existe".
//
// Usage : php tests/ai-company-sim/scenarios/ux/ux-01-ocr-prefill-plausible.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\RegistrationForm;
use App\Models\CourrierBrouillon;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : ux-01-ocr-prefill-plausible.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'ux_researcher';

Scenario::executer($memoire, $role, 'ux-01-ocr-prefill-plausible', function () use ($memoire, $seed, $role) {
    $agent = TaggedFactory::utilisateur($memoire, $seed, 'agent-ux01', 'Agent');

    $objetAttendu = TaggedFactory::tag($seed, 'Réclamation sinistre auto véhicule immatriculé LT-1234-AB');

    $brouillon = CourrierBrouillon::create([
        'fichier_path' => 'brouillons/aico-'.$seed.'-ux01.pdf',
        'nom_original' => 'scan-aico-'.$seed.'.pdf',
        'type_mime' => 'application/pdf',
        'taille' => 1000,
        'cree_par_id' => $agent->id,
        'ocr_statut' => 'reussi',
        'texte_ocr' => "Objet : {$objetAttendu}\n",
        'ocr_confiance' => 90,
    ]);
    $memoire->enregistrerEntite('CourrierBrouillon', $brouillon->id, ['tag' => "AICO-{$seed}"]);

    Livewire::actingAs($agent);
    $test = Livewire::test(RegistrationForm::class, ['brouillonId' => $brouillon->id]);

    $objetPreRempli = $test->get('form.objet');
    $preRempliCorrectement = filled($objetPreRempli) && str_contains($objetPreRempli, $objetAttendu);

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'ocr_prefill_check', 'result' => $preRempliCorrectement ? 'success' : 'failure',
        'objet_pre_rempli' => $objetPreRempli,
    ]);

    if (! $preRempliCorrectement) {
        $bugId = $memoire->signalerBug([
            'title' => 'Le pré-remplissage OCR ne reconnaît pas le motif "Objet : ..." dans le texte scanné',
            'severity' => 'P2',
            'priority' => 'P2',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} UX Researcher"],
            'user_role' => 'Agent',
            'preconditions' => "Brouillon id={$brouillon->id} avec texte_ocr=\"Objet : {$objetAttendu}\".",
            'steps_to_reproduce' => ["Livewire::test(RegistrationForm::class, ['brouillonId' => {$brouillon->id}])->get('form.objet')"],
            'expected_result' => "form.objet contient \"{$objetAttendu}\".",
            'actual_result' => 'form.objet = '.json_encode($objetPreRempli),
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (ocr_prefill_check, scenario ux-01)'],
            'affected_module_url' => 'App\\Livewire\\Backend\\RegistrationForm::preremplirDepuisBrouillon',
            'related_data' => ['brouillon_id' => $brouillon->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'L\'agent devrait ressaisir l\'objet à la main pour chaque courrier — l\'un des principaux gains de temps du Module 1 ne fonctionnerait pas.',
            'security_impact' => 'none',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier le motif de reconnaissance "Objet :" dans RegistrationForm::preremplirDepuisBrouillon().',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — pré-remplissage OCR non plausible.\n";
    } else {
        echo "OK — pré-remplissage OCR plausible : \"{$objetPreRempli}\".\n";
    }

    return $brouillon;
});
