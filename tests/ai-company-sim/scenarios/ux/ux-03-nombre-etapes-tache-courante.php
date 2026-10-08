<?php

// UX Researcher : scénario 3 — compter le nombre réel d'appels Livewire
// nécessaires pour la tâche la plus courante ("l'agent enregistre une
// lettre scannée simple"). Donnée brute pour un humain, PAS un verdict
// pass/fail (voir persona : périmètre honnêtement limité, aucune mesure
// visuelle/ergonomique n'est possible ici).
//
// Usage : php tests/ai-company-sim/scenarios/ux/ux-03-nombre-etapes-tache-courante.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\RegistrationForm;
use App\Models\Courrier;
use App\Models\CourrierBrouillon;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : ux-03-nombre-etapes-tache-courante.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'ux_researcher';

Scenario::executer($memoire, $role, 'ux-03-nombre-etapes-tache-courante', function () use ($memoire, $seed, $role) {
    $agent = TaggedFactory::utilisateur($memoire, $seed, 'agent-ux03', 'Agent');

    $brouillon = CourrierBrouillon::create([
        'fichier_path' => 'brouillons/aico-'.$seed.'-ux03.pdf',
        'nom_original' => 'scan-aico-'.$seed.'-ux03.pdf',
        'type_mime' => 'application/pdf', 'taille' => 1000, 'cree_par_id' => $agent->id,
        'ocr_statut' => 'reussi',
        'texte_ocr' => 'Objet : '.TaggedFactory::tag($seed, 'Facture à régler')."\n",
        'ocr_confiance' => 90,
    ]);
    $memoire->enregistrerEntite('CourrierBrouillon', $brouillon->id, ['tag' => "AICO-{$seed}"]);

    Livewire::actingAs($agent);

    // Étape 1 : ouvrir le formulaire pré-rempli depuis le brouillon.
    $test = Livewire::test(RegistrationForm::class, ['brouillonId' => $brouillon->id]);
    $etapes = 1;

    // Étape 2 : seuls les champs NON pré-remplis restent à saisir à la main
    // (ici : type_document, mode_reception — objet et date déjà proposés).
    $test->set('form.type_document', 'Lettre')->set('form.mode_reception', 'email');
    $etapes++;

    // Étape 3 : valider.
    $test->call('enregistrer');
    $etapes++;

    // Recherche par objet taguée (pas la valeur relue de $test->get(), qui
    // peut différer légèrement de ce qui a réellement été persisté) — la
    // seule garantie robuste est le tag + l'auteur le plus récent.
    $courrier = Courrier::whereHas('historiques', fn ($q) => $q->where('auteur_id', $agent->id)->where('action', 'creation'))
        ->where('objet', 'like', "%AICO-{$seed}%")
        ->latest('id')->first();
    if ($courrier) {
        $memoire->enregistrerEntite('Courrier', $courrier->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier->numero_reference, 'created_by_role' => $role]);
    }

    // Pas de pass/fail — donnée brute consignée pour le rapport final, à
    // juger par un humain (voir persona).
    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'step_count_baseline', 'result' => 'success',
        'details' => "Enregistrement d'une lettre scannée simple (scan déjà fait, OCR réussi) : {$etapes} interactions Livewire (ouverture pré-remplie + 2 champs manuels + validation).",
        'nombre_etapes' => $etapes,
    ]);

    echo "Donnée UX (pas pass/fail) — tâche 'enregistrer une lettre scannée simple' : {$etapes} interactions Livewire.\n";

    return $etapes;
});
