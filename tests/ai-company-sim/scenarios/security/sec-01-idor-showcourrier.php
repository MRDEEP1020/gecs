<?php

// Security Engineer — scénario 1 : rejouer le patron IDOR déjà documenté
// dans ce projet (CHANGELOG-AGENT.md/DECISIONS.md, 2026-09-24) — forcer un
// id de courrier hors périmètre via ->set() plutôt que le binding de route,
// exactement la classe de bug déjà trouvée une fois dans EditForm::courrier().
//
// Usage : php tests/ai-company-sim/scenarios/security/sec-01-idor-showcourrier.php <run_id>

require __DIR__.'/../../bootstrap.php';

use AiCompanySim\CompanyMemory;
use AiCompanySim\Scenario;
use AiCompanySim\TaggedFactory;
use App\Livewire\Backend\ShowCourrier;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\Service;
use Livewire\Livewire;

$runId = $argv[1] ?? null;
if (! $runId) {
    fwrite(STDERR, "Usage : sec-01-idor-showcourrier.php <run_id>\n");
    exit(1);
}

$memoire = CompanyMemory::ouvrirRunExistant($runId);
$seed = $memoire->lireRunInfo()['seed'];
$role = 'security_engineer';

Scenario::executer($memoire, $role, 'sec-01-idor-showcourrier', function () use ($memoire, $seed, $role) {
    $attaquant = TaggedFactory::utilisateur($memoire, $seed, 'attaquant-sec01', 'Collaborateur');
    $victime = TaggedFactory::utilisateur($memoire, $seed, 'victime-sec01', 'Collaborateur');

    $serviceVictime = Service::create([
        'nom' => TaggedFactory::tag($seed, 'Service Victime Sec01'),
        'code' => TaggedFactory::codeCourt($seed, 'sec-01-service'),
        'actif' => true,
    ]);
    $memoire->enregistrerEntite('Service', $serviceVictime->id, ['tag' => "AICO-{$seed}"]);
    $victime->update(['service_id' => $serviceVictime->id]);

    $courrierVictime = Courrier::create([
        'numero_reference' => 'AICO-'.$seed.'-'.random_int(100000, 999999),
        'sens' => 'entrant', 'date_mouvement' => now(),
        'objet' => TaggedFactory::tag($seed, 'Dossier confidentiel de la victime — hors périmètre de l\'attaquant'),
        'type_document' => 'Lettre', 'mode_reception' => 'email',
        'service_id' => $serviceVictime->id, 'statut' => 'affecte', 'confidentialite' => 2,
    ]);
    $memoire->enregistrerEntite('Courrier', $courrierVictime->id, ['tag' => "AICO-{$seed}", 'numero_reference' => $courrierVictime->numero_reference, 'created_by_role' => $role]);
    CourrierHistorique::create(['courrier_id' => $courrierVictime->id, 'auteur_id' => $victime->id, 'action' => 'creation']);
    \App\Models\Affectation::create(['courrier_id' => $courrierVictime->id, 'user_id' => $victime->id, 'affecte_par_id' => $victime->id]);

    Livewire::actingAs($attaquant);

    $accesObtenu = true;
    try {
        Livewire::test(ShowCourrier::class, ['courrierId' => $courrierVictime->id])->assertForbidden();
        $accesObtenu = false;
    } catch (\Throwable $e) {
        $accesObtenu = true;
    }

    $memoire->enregistrerEvenement([
        'actor_role' => $role, 'action' => 'idor_attempt', 'target_entity' => ['type' => 'Courrier', 'id' => $courrierVictime->id],
        'result' => $accesObtenu ? 'failure' : 'success',
        'details' => "Collaborateur hors service tente d'ouvrir ShowCourrier sur un courrier confidentiel d'un autre service via l'id direct.",
    ]);

    if ($accesObtenu) {
        $bugId = $memoire->signalerBug([
            'title' => 'IDOR : un collaborateur accède à un courrier confidentiel hors de son service via l\'id direct',
            'severity' => 'P0',
            'priority' => 'P0',
            'detected_by' => ['role' => $role, 'agent_label' => "AICO-{$seed} Security Engineer"],
            'user_role' => 'Collaborateur',
            'preconditions' => "Attaquant id={$attaquant->id} (aucun lien avec le service {$serviceVictime->id}). Courrier id={$courrierVictime->id} appartient à ce service, affecté à un autre utilisateur.",
            'steps_to_reproduce' => ["Livewire::actingAs(attaquant)->test(ShowCourrier::class, ['courrierId' => {$courrierVictime->id}])"],
            'expected_result' => '403 Forbidden.',
            'actual_result' => 'Accès obtenu (pas de 403).',
            'evidence' => ['type' => 'event_log', 'ref' => 'events.jsonl (idor_attempt, scenario sec-01)'],
            'affected_module_url' => 'App\\Livewire\\Backend\\ShowCourrier / App\\Policies\\CourrierPolicy::view',
            'related_data' => ['courrier_id' => $courrierVictime->id, 'attaquant_id' => $attaquant->id],
            'frequency' => 'à confirmer par QA',
            'business_impact' => 'Fuite de données confidentielles entre services.',
            'security_impact' => 'CRITIQUE — IDOR confirmé, classe de bug déjà rencontrée une fois dans ce projet (EditForm::courrier(), 2026-09-24).',
            'regression_status' => 'new',
            'recommended_fix' => 'Revoir CourrierPolicy::view() pour ce chemin — vérifier qu\'aucune ability ne fait confiance à courrierId sans revalider le périmètre à chaque appel.',
        ]);
        echo "BUG SIGNALÉ ({$bugId}) — IDOR confirmé sur ShowCourrier.\n";
    } else {
        echo "OK — accès refusé (403), aucun IDOR sur ShowCourrier pour ce cas.\n";
    }

    return $courrierVictime;
});
