<?php

// Product Manager — scénario 4 (reformulé après investigation réelle du
// code) : il n'existe AUCUNE notification applicative sur un simple
// transfert DGA (grep de ->notify() dans app/ : zéro résultat hors
// SendMailAlertJob) — la seule vraie notification in-app de GEC est
// l'alerte SLA "courrier en retard" (Module 7). On teste donc CELLE qui
// existe réellement plutôt qu'une fonctionnalité qui n'a jamais été
// construite (voir persona Product Manager : signaler une exigence
// incomplète/absente n'est pas la même chose qu'un bug).
//
// Usage : php tests/ai-company-sim/scenarios/pm/pm-04-sla-alert-really-notifies.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Jobs\SendMailAlertJob;
use App\Models\Affectation;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\Service;
use App\Notifications\CourrierEnRetardNotification;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : pm-04-sla-alert-really-notifies.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'product_manager';

Scenario::executer($memoire, $role, 'pm-04-sla-alert-really-notifies', function () use ($memoire, $seed, $role) {
    $responsable = TaggedFactory::utilisateur($memoire, $seed, 'responsable-pm04', 'Responsable de service');
    $collaborateur = TaggedFactory::utilisateur($memoire, $seed, 'collaborateur-pm04', 'Collaborateur');

    $service = Service::create([
        'nom' => TaggedFactory::tag($seed, 'Service SLA Test'),
        'code' => TaggedFactory::codeCourt($seed, 'pm-04-service'),
        'actif' => true,
        'responsable_id' => $responsable->id,
    ]);
    $memoire->enregistrerEntite('Service', $service->id, ['tag' => "AICO-{$seed}"]);
    $collaborateur->update(['service_id' => $service->id]);

    $courrier = Courrier::create([
        'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999),
        'sens' => 'entrant',
        'date_mouvement' => now()->subDays(10),
        'date_limite' => now()->subDays(2), // déjà en retard
        'objet' => TaggedFactory::tag($seed, 'Réclamation client en attente de réponse — déjà en retard'),
        'type_document' => 'Lettre',
        'mode_reception' => 'email',
        'service_id' => $service->id,
        'statut' => 'affecte',
    ]);
    $memoire->enregistrerEntite('Courrier', $courrier->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrier->numero_reference, 'created_by_role' => $role]);
    CourrierHistorique::create(['courrier_id' => $courrier->id, 'auteur_id' => $responsable->id, 'action' => 'creation']);

    $affectation = Affectation::create(['courrier_id' => $courrier->id, 'user_id' => $collaborateur->id, 'affecte_par_id' => $responsable->id]);

    (new SendMailAlertJob)->handle();

    $collaborateur = \App\Models\User::find($collaborateur->id); // relation notifications non mise en cache
    $aEteNotifie = $collaborateur->notifications()->where('type', CourrierEnRetardNotification::class)->exists();

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'job_run', 'component' => SendMailAlertJob::class,
        'target_entity' => ['type' => 'Courrier', 'id' => $courrier->id],
        'result' => $aEteNotifie ? 'success' : 'failure',
        'details' => 'Courrier en retard, collaborateur affecté doit recevoir CourrierEnRetardNotification.',
    ]);

    if (! $aEteNotifie) {
        $bugId = $memoire->signalerBug([
            'title' => 'Un courrier en retard, affecté à un collaborateur, ne déclenche pas de notification pour lui',
            'severity' => 'P1',
            'priority' => 'P1',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Product Manager"],
            'user_role' => 'Collaborateur',
            'preconditions' => "Courrier id={$courrier->id} affecté à {$collaborateur->id}, date_limite dépassée de 2 jours, statut='affecte'.",
            'steps_to_reproduce' => ['(new SendMailAlertJob)->handle()'],
            'expected_result' => 'Le collaborateur affecté reçoit une CourrierEnRetardNotification.',
            'actual_result' => 'Aucune notification trouvée pour ce collaborateur.',
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (job_run, scenario pm-04)'],
            'affected_module_url' => 'App\\Jobs\\SendMailAlertJob',
            'related_data' => ['courrier_id' => $courrier->id, 'user_id' => $collaborateur->id, 'affectation_id' => $affectation->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Un courrier en retard ne remonterait jamais à la personne censée le traiter — tout le Module 7 (alertes) serait silencieux.',
            'security_impact' => 'none',
            'regression_status' => 'new',
            'recommended_fix' => 'Vérifier SendMailAlertJob::destinataires()/alerter() et Courrier::scopeEnRetard().',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — alerte SLA non reçue.\n";
    } else {
        echo "OK — le collaborateur affecté a bien reçu la notification de retard.\n";
    }

    // Constat produit explicite (pas un bug) — aucune notification de
    // transfert DGA n'existe dans l'app aujourd'hui, à documenter dans le
    // rapport final comme lacune fonctionnelle potentielle plutôt que défaut.
    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'product_observation',
        'details' => 'Aucune notification applicative n\'existe sur un transfert DGA (seule l\'alerte SLA "en retard" est une vraie notification in-app) — constat produit, pas un bug, à inclure dans les recommandations du rapport final.',
    ]);

    return $courrier;
});
