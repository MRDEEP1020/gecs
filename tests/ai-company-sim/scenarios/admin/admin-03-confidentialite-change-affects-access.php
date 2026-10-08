<?php

// Administrateur — scénario 3 : élever le niveau de confidentialité d'un
// utilisateur doit RÉELLEMENT changer son accès (pas juste persister le
// champ) — testé en vérifiant l'accès avant/après sur un vrai courrier
// niveau 3.
//
// Usage : php tests/ai-company-sim/scenarios/admin/admin-03-confidentialite-change-affects-access.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\UserList;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\User;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : admin-03-confidentialite-change-affects-access.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'administrator';

Scenario::executer($memoire, $role, 'admin-03-confidentialite-change-affects-access', function () use ($memoire, $seed, $role) {
    $admin = TaggedFactory::utilisateur($memoire, $seed, 'admin-admin03', 'Administrateur');
    $collaborateur = TaggedFactory::utilisateur($memoire, $seed, 'collaborateur-admin03', 'Collaborateur', ['niveau_confidentialite' => 1]);

    $courrierNiveau3 = Courrier::create([
        'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999),
        'sens' => 'entrant',
        'date_mouvement' => now(),
        'objet' => TaggedFactory::tag($seed, 'Dossier confidentiel niveau 3 — ressources humaines'),
        'type_document' => 'Lettre',
        'mode_reception' => 'email',
        'confidentialite' => 3,
        'statut' => 'traite',
    ]);
    $memoire->enregistrerEntite('Courrier', $courrierNiveau3->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrierNiveau3->numero_reference, 'created_by_role' => $role]);
    CourrierHistorique::create(['courrier_id' => $courrierNiveau3->id, 'auteur_id' => $admin->id, 'action' => 'creation']);

    // view() est un ET entre confidentialité ET un privilège de portée
    // (créateur/affecté/service) — pour isoler la confidentialité comme
    // SEULE variable, il faut que le collaborateur soit par ailleurs dans
    // son périmètre normal (affecté à ce courrier).
    \App\Models\Affectation::create(['courrier_id' => $courrierNiveau3->id, 'user_id' => $collaborateur->id, 'affecte_par_id' => $admin->id]);

    $accesAvant = $collaborateur->can('view', $courrierNiveau3);

    Livewire::actingAs($admin);
    $morceaux = explode(' ', $collaborateur->name, 2);

    Livewire::test(UserList::class)
        ->call('ouvrirEdition', $collaborateur->id)
        ->set('editionNom', $morceaux[0] ?? $collaborateur->name)
        ->set('editionPrenom', $morceaux[1] ?? 'X')
        ->set('editionEmail', $collaborateur->email)
        ->set('editionNiveauConfidentialite', 3)
        ->call('enregistrerEdition');

    $collaborateur = User::find($collaborateur->id); // instance neuve, jamais le cache en mémoire
    $accesApres = $collaborateur->can('view', $courrierNiveau3);

    $correct = ! $accesAvant && $accesApres && $collaborateur->niveau_confidentialite === 3;

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'livewire_call', 'component' => UserList::class, 'method' => 'enregistrerEdition',
        'target_entity' => ['type' => 'User', 'id' => $collaborateur->id],
        'result' => $correct ? 'success' : 'failure',
        'acces_avant' => $accesAvant, 'acces_apres' => $accesApres, 'niveau_final' => $collaborateur->niveau_confidentialite,
    ]);

    if (! $correct) {
        $bugId = $memoire->signalerBug([
            'title' => 'Élever le niveau de confidentialité d\'un utilisateur ne change pas réellement son accès',
            'severity' => 'P0',
            'priority' => 'P0',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Administrateur"],
            'user_role' => 'Administrateur',
            'preconditions' => "Collaborateur id={$collaborateur->id} niveau 1, courrier id={$courrierNiveau3->id} niveau 3.",
            'steps_to_reproduce' => ["UserList::class->call('ouvrirEdition', {$collaborateur->id})->set('editionNiveauConfidentialite', 3)->call('enregistrerEdition')"],
            'expected_result' => 'Accès refusé avant (niveau 1), accordé après (niveau 3).',
            'actual_result' => 'avant='.($accesAvant ? 'autorisé' : 'refusé').', après='.($accesApres ? 'autorisé' : 'refusé').', niveau final='.$collaborateur->niveau_confidentialite,
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (enregistrerEdition, scenario admin-03)'],
            'affected_module_url' => 'App\\Livewire\\Backend\\UserList / App\\Policies\\CourrierPolicy',
            'related_data' => ['user_id' => $collaborateur->id, 'courrier_id' => $courrierNiveau3->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Impossible de donner/retirer un accès confidentiel de façon fiable — risque de conformité majeur dans une compagnie d\'assurance.',
            'security_impact' => 'Accès à du contenu confidentiel potentiellement incorrect (sur ou sous-autorisé).',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier User::niveauConfidentialiteEffectif() et le cache mémoire de l\'instance utilisateur.',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — changement de confidentialité sans effet réel.\n";
    } else {
        echo "OK — accès refusé avant, accordé après le changement de niveau.\n";
    }

    return $collaborateur;
});
