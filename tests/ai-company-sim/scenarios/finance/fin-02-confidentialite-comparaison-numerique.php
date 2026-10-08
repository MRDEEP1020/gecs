<?php

// Finance (rôle adapté) : scénario 2 — la comparaison de confidentialité
// est une comparaison NUMÉRIQUE (>=), pas une simple paire autorisé/refusé.
// On vérifie précisément la frontière : égalité exacte autorisée, un cran
// en dessous refusé.
//
// Usage : php tests/ai-company-sim/scenarios/finance/fin-02-confidentialite-comparaison-numerique.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Models\Affectation;
use App\Models\Courrier;
use App\Models\CourrierHistorique;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : fin-02-confidentialite-comparaison-numerique.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'finance_employee';

Scenario::executer($memoire, $role, 'fin-02-confidentialite-comparaison-numerique', function () use ($memoire, $seed, $role) {
    $createur = TaggedFactory::utilisateur($memoire, $seed, 'createur-fin02', 'Administrateur');
    $niveauEgal = TaggedFactory::utilisateur($memoire, $seed, 'niveau-egal-fin02', 'Collaborateur', ['niveau_confidentialite' => 3]);
    $niveauInferieur = TaggedFactory::utilisateur($memoire, $seed, 'niveau-inferieur-fin02', 'Collaborateur', ['niveau_confidentialite' => 2]);

    // Deux courriers DISTINCTS, pas un seul partagé : un courrier n'a
    // qu'UNE affectation "courante" à la fois (la plus récente) — réutiliser
    // le même courrier pour les deux utilisateurs aurait fait de l'un
    // d'eux un non-affecté, contaminant le test (constat fait en vérifiant
    // ce scénario avant de le faire tourner dans le run complet).
    $faireCourrier = function (string $suffixe) use ($memoire, $seed, $role, $createur) {
        $courrier = Courrier::create([
            'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999),
            'sens' => 'entrant', 'date_mouvement' => now(),
            'objet' => TaggedFactory::tag($seed, "Document niveau 3 — test frontière numérique ({$suffixe})"),
            'type_document' => 'Lettre', 'mode_reception' => 'email',
            'confidentialite' => 3, 'statut' => 'traite',
        ]);
        $memoire->enregistrerEntite('Courrier', $courrier->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier->numero_reference, 'created_by_role' => $role]);
        CourrierHistorique::create(['courrier_id' => $courrier->id, 'auteur_id' => $createur->id, 'action' => 'creation']);

        return $courrier;
    };

    $courrierEgal = $faireCourrier('niveau égal');
    $courrierInferieur = $faireCourrier('niveau inférieur');
    Affectation::create(['courrier_id' => $courrierEgal->id, 'user_id' => $niveauEgal->id, 'affecte_par_id' => $createur->id]);
    Affectation::create(['courrier_id' => $courrierInferieur->id, 'user_id' => $niveauInferieur->id, 'affecte_par_id' => $createur->id]);

    $egalAutorise = $niveauEgal->can('view', $courrierEgal);
    $inferieurRefuse = ! $niveauInferieur->can('view', $courrierInferieur);

    $correct = $egalAutorise && $inferieurRefuse;

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'policy_check', 'result' => $correct ? 'success' : 'failure',
        'egal_autorise' => $egalAutorise, 'inferieur_refuse' => $inferieurRefuse,
    ]);

    if (! $correct) {
        $bugId = $memoire->signalerBug([
            'title' => 'La comparaison numérique de confidentialité ne respecte pas la frontière >= attendue',
            'severity' => 'P0',
            'priority' => 'P0',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Finance"],
            'user_role' => 'Collaborateur',
            'preconditions' => "Deux courriers niveau 3 distincts (id={$courrierEgal->id} affecté à l'utilisateur niveau 3 id={$niveauEgal->id} ; id={$courrierInferieur->id} affecté à l'utilisateur niveau 2 id={$niveauInferieur->id}).",
            'steps_to_reproduce' => ['niveau3->can(\'view\', $courrierEgal) et niveau2->can(\'view\', $courrierInferieur)'],
            'expected_result' => 'niveau 3 = autorisé (égalité), niveau 2 = refusé (inférieur).',
            'actual_result' => 'niveau 3 autorisé='.($egalAutorise ? 'oui' : 'non').', niveau 2 refusé='.($inferieurRefuse ? 'oui' : 'non'),
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (policy_check, scenario fin-02)'],
            'affected_module_url' => 'App\\Policies\\CourrierPolicy',
            'related_data' => ['courrier_egal_id' => $courrierEgal->id, 'courrier_inferieur_id' => $courrierInferieur->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Une erreur de frontière numérique (> au lieu de >=, ou inversée) exposerait ou bloquerait le mauvais niveau d\'accès confidentiel.',
            'security_impact' => $egalAutorise ? 'none' : 'Accès refusé à un niveau légitime — faux négatif, moins grave qu\'une fuite mais bloquant.',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier niveauSuffisant() dans CourrierPolicy — doit être >=, jamais >.',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — frontière de confidentialité incorrecte.\n";
    } else {
        echo "OK — frontière numérique de confidentialité correcte (>=).\n";
    }

    return [$courrierEgal, $courrierInferieur];
});
