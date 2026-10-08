<?php

// External Correspondent — scénario 4 : deux courriers NON LIÉS du même
// correspondant, espacés dans le temps, ne doivent jamais être fusionnés
// ou mal reliés — GEC n'a aucune notion de "profil client" qui pourrait
// implicitement réutiliser l'état du précédent courrier.
//
// Usage : php tests/ai-company-sim/scenarios/external_correspondent/ec-04-pas-de-fusion-implicite.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\DossierClassementList;
use App\Livewire\Backend\RegistrationForm;
use App\Models\Courrier;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : ec-04-pas-de-fusion-implicite.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'external_correspondent';

Scenario::executer($memoire, $role, 'ec-04-pas-de-fusion-implicite', function () use ($memoire, $seed, $role) {
    $agent = TaggedFactory::utilisateur($memoire, $seed, 'agent-ec04', 'Agent');
    // Dossiers de classement : privilège dossiers_classement.creer réservé à
    // Responsable de service/Collaborateur (pas Agent) — voir PrivilegeSeeder.
    $classeur = TaggedFactory::utilisateur($memoire, $seed, 'classeur-ec04', 'Collaborateur');
    $organisation = TaggedFactory::tag($seed, 'NSIA PARTENAIRE DISTRIBUTION SARL');

    Livewire::actingAs($classeur);

    $nomDossier = TaggedFactory::tag($seed, 'Dossier premier courrier');
    Livewire::test(DossierClassementList::class)->call('ouvrirCreation', null)->set('nomDossier', $nomDossier)->call('creerDossier');
    $dossier = \App\Models\DossierClassement::where('nom', $nomDossier)->latest('id')->first();
    if ($dossier) {
        $memoire->enregistrerEntite('DossierClassement', $dossier->id, ['tag' => "AICO-{$seed}"]);
    }

    Livewire::actingAs($agent);

    $objet1 = TaggedFactory::tag($seed, 'Premier courrier — demande initiale');
    Livewire::test(RegistrationForm::class)
        ->set('form.sens', 'entrant')->set('form.date_mouvement', now()->subDays(60)->format('Y-m-d'))
        ->set('form.expediteur_organisation', $organisation)->set('form.objet', $objet1)->set('form.type_document', 'Lettre')
        ->set('form.mode_reception', 'email')->set('form.priorite', 'normale')->set('form.confidentialite', 1)
        ->call('enregistrer');
    $courrier1 = Courrier::where('objet', $objet1)->latest('id')->first();

    if ($courrier1 && $dossier) {
        $courrier1->update(['dossier_classement_id' => $dossier->id]);
        $memoire->enregistrerEntite('Courrier', $courrier1->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier1->numero_reference, 'created_by_role' => $role]);
    }

    $objet2 = TaggedFactory::tag($seed, 'Second courrier — sujet totalement différent, sans rapport');
    Livewire::test(RegistrationForm::class)
        ->set('form.sens', 'entrant')->set('form.date_mouvement', now()->format('Y-m-d'))
        ->set('form.expediteur_organisation', $organisation)->set('form.objet', $objet2)->set('form.type_document', 'Lettre')
        ->set('form.mode_reception', 'email')->set('form.priorite', 'normale')->set('form.confidentialite', 1)
        ->call('enregistrer');
    $courrier2 = Courrier::where('objet', $objet2)->latest('id')->first();

    if ($courrier2) {
        $memoire->enregistrerEntite('Courrier', $courrier2->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier2->numero_reference, 'created_by_role' => $role]);
    }

    $pasDeFusion = $courrier1 && $courrier2
        && $courrier2->dossier_classement_id === null
        && $courrier2->service_propose_id === null
        && $courrier1->id !== $courrier2->id
        && $courrier1->numero_reference !== $courrier2->numero_reference;

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'no_merge_check', 'result' => $pasDeFusion ? 'success' : 'failure',
        'courrier1_dossier' => $courrier1?->dossier_classement_id, 'courrier2_dossier' => $courrier2?->dossier_classement_id,
    ]);

    if (! $pasDeFusion) {
        $bugId = $memoire->signalerBug([
            'title' => 'Deux courriers non liés du même correspondant partagent un état qui ne devrait pas être réutilisé',
            'severity' => 'P1',
            'priority' => 'P1',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Correspondant externe"],
            'user_role' => 'Agent',
            'preconditions' => "Deux courriers du même expéditeur_organisation='{$organisation}', 60 jours d'écart.",
            'steps_to_reproduce' => ['Enregistrer un 1er courrier, le classer dans un dossier', 'Enregistrer un 2e courrier non lié, même expéditeur'],
            'expected_result' => 'Le second courrier a dossier_classement_id=null et service_propose_id=null (aucun héritage implicite).',
            'actual_result' => 'courrier2.dossier_classement_id='.($courrier2?->dossier_classement_id ?? 'null').', service_propose_id='.($courrier2?->service_propose_id ?? 'null'),
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (no_merge_check, scenario ec-04)'],
            'affected_module_url' => 'App\\Livewire\\Backend\\RegistrationForm',
            'related_data' => ['courrier1_id' => $courrier1?->id, 'courrier2_id' => $courrier2?->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Deux dossiers sans rapport pourraient être mélangés, un classement/service erroné propagé par erreur.',
            'security_impact' => 'none',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier qu\'aucune logique ne réutilise l\'état d\'un courrier précédent par expéditeur.',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — fusion implicite détectée.\n";
    } else {
        echo "OK — aucune fusion implicite entre les deux courriers.\n";
    }

    return [$courrier1, $courrier2];
});
