<?php

// Nettoyage idempotent d'un run de l'"AI Employee Company" — JAMAIS
// automatique, toujours invoqué manuellement. Dry-run par défaut (affiche
// ce qui SERAIT supprimé) ; --confirm pour réellement supprimer. Ne
// supprime QUE les ids exacts du manifest.json de CE run — jamais une
// correspondance par tag seule, pour qu'il soit physiquement impossible de
// toucher une ligne réelle non créée par ce run.
//
// Usage : php tests/ai-company-sim/cleanup.php <run_id> [--confirm]

use App\Models\Affectation;
use App\Models\Courrier;
use App\Models\CourrierBrouillon;
use App\Models\CourrierHistorique;
use App\Models\Decharge;
use App\Models\DelegationDga;
use App\Models\DossierClassement;
use App\Models\OrganizationUnit;
use App\Models\RegleClassement;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/bootstrap.php';

$runId = $argv[1] ?? null;
$confirmer = in_array('--confirm', $argv, true);

if (! $runId) {
    fwrite(STDERR, "Usage : php cleanup.php <run_id> [--confirm]\n");
    fwrite(STDERR, "Runs disponibles :\n");
    foreach (glob(AICO_ROOT.'/runs/*') as $d) {
        fwrite(STDERR, '  - '.basename($d)."\n");
    }
    exit(1);
}

$runDir = AICO_ROOT."/runs/{$runId}";

if (! is_dir($runDir)) {
    fwrite(STDERR, "ABANDON : run introuvable '{$runDir}'.\n");
    exit(1);
}

$manifest = json_decode(file_get_contents($runDir.'/manifest.json'), true) ?: [];

echo $confirmer ? "=== SUPPRESSION RÉELLE ===\n" : "=== DRY-RUN (ajoutez --confirm pour supprimer réellement) ===\n";
echo "Run : {$runId}\n\n";

// Ordre sûr : enfants avant parents. Chaque entrée = [type manifest => [modèle, colonne FK optionnelle à nettoyer en premier]].
$ordre = [
    'Decharge' => Decharge::class,
    'Affectation' => Affectation::class,
    'CourrierHistorique' => CourrierHistorique::class,
    'DelegationDga' => DelegationDga::class,
    'CourrierBrouillon' => CourrierBrouillon::class,
    'Courrier' => Courrier::class,
    'DossierClassement' => DossierClassement::class,
    'OrganizationUnit' => OrganizationUnit::class,
    'RegleClassement' => RegleClassement::class,
    'Service' => Service::class,
    'User' => User::class,
];

$totalSupprimes = 0;
$fichiersSupprimes = 0;

// CourrierHistorique/Affectation sont TOUJOURS des enfants d'un Courrier
// tracé, mais un scénario qui les crée directement (CourrierHistorique::
// create(...), comme le font plusieurs scénarios de ce système) ne les
// enregistre pas forcément individuellement dans le manifest — on les
// dérive donc des ids Courrier trackés, jamais seulement de ce que le
// manifest liste explicitement pour ces deux types.
$courrierIds = array_column($manifest['Courrier'] ?? [], 'id');

if (! empty($courrierIds)) {
    $nHisto = CourrierHistorique::whereIn('courrier_id', $courrierIds)->count();
    $nAffect = Affectation::whereIn('courrier_id', $courrierIds)->count();
    echo "CourrierHistorique (dérivé des Courrier ci-dessus) : {$nHisto} ligne(s)\n";
    echo "Affectation (dérivé des Courrier ci-dessus) : {$nAffect} ligne(s)\n";

    if ($confirmer) {
        CourrierHistorique::whereIn('courrier_id', $courrierIds)->delete();
        Affectation::whereIn('courrier_id', $courrierIds)->delete();
        $totalSupprimes += $nHisto + $nAffect;
    }
}

DB::transaction(function () use ($manifest, $ordre, $confirmer, &$totalSupprimes, &$fichiersSupprimes) {
    foreach ($ordre as $type => $classe) {
        if (in_array($type, ['CourrierHistorique', 'Affectation'], true)) {
            continue; // déjà traités ci-dessus, dérivés des Courrier.
        }

        $entrees = $manifest[$type] ?? [];

        if (empty($entrees)) {
            continue;
        }

        $ids = array_column($entrees, 'id');
        echo "{$type} : ".count($ids)." ligne(s) ciblée(s) (ids : ".implode(', ', array_slice($ids, 0, 10)).(count($ids) > 10 ? '...' : '').")\n";

        if (! $confirmer) {
            continue;
        }

        // method_exists() sur le MODÈLE ne détecte jamais withTrashed()
        // (c'est un macro ajouté au Builder par SoftDeletes::bootSoftDeletes(),
        // jamais une méthode réelle de la classe elle-même) — seul
        // in_array(SoftDeletes::class, class_uses_recursive(...)) est fiable.
        // Piège déjà rencontré 2x sur ce même fichier (User puis
        // CourrierBrouillon) avec un ::withTrashed() codé en dur.
        $utiliseSoftDeletes = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($classe), true);
        $methode = $utiliseSoftDeletes ? 'withTrashed' : 'query';

        // Fichiers de stockage avant suppression des lignes (Courrier/CourrierBrouillon).
        if (in_array($type, ['Courrier', 'CourrierBrouillon'], true)) {
            $lignes = $classe::{$methode}()->whereIn('id', $ids)->get(['id', 'fichier_path']);
            foreach ($lignes as $ligne) {
                if ($ligne->fichier_path && Storage::disk('s3')->exists($ligne->fichier_path)) {
                    Storage::disk('s3')->delete($ligne->fichier_path);
                    $fichiersSupprimes++;
                }
            }
        }

        $supprimes = $classe::{$methode}()->whereIn('id', $ids)->forceDelete();
        $totalSupprimes += $supprimes;

        if ($type === 'User') {
            DB::table('sessions')->whereIn('user_id', $ids)->delete();
        }
    }
});

if (! $confirmer) {
    echo "\nAucune suppression effectuée (dry-run). Relancez avec --confirm.\n";
    exit(0);
}

echo "\nLignes supprimées définitivement : {$totalSupprimes}\n";
echo "Fichiers S3 supprimés : {$fichiersSupprimes}\n";

// ===== Vérification de propreté =====
echo "\n--- Vérification ---\n";
$residu = 0;

if (! empty($courrierIds)) {
    $restantsHisto = CourrierHistorique::whereIn('courrier_id', $courrierIds)->count();
    $restantsAffect = Affectation::whereIn('courrier_id', $courrierIds)->count();

    if ($restantsHisto > 0) {
        echo "!!! CourrierHistorique : {$restantsHisto} ligne(s) restante(s) !!!\n";
        $residu += $restantsHisto;
    }

    if ($restantsAffect > 0) {
        echo "!!! Affectation : {$restantsAffect} ligne(s) restante(s) !!!\n";
        $residu += $restantsAffect;
    }
}

foreach ($ordre as $type => $classe) {
    if (in_array($type, ['CourrierHistorique', 'Affectation'], true)) {
        continue;
    }

    $ids = array_column($manifest[$type] ?? [], 'id');

    if (empty($ids)) {
        continue;
    }

    $utiliseSoftDeletes = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($classe), true);
    $methode = $utiliseSoftDeletes ? 'withTrashed' : 'query';
    $restants = $classe::{$methode}()->whereIn('id', $ids)->count();

    if ($restants > 0) {
        echo "!!! {$type} : {$restants} ligne(s) restante(s) !!!\n";
        $residu += $restants;
    }
}

// Deuxième ligne de contrôle : le tag du run ne doit plus apparaître nulle
// part, même sur une entité que le manifest aurait ratée par un bug du harnais.
$seed = json_decode(file_get_contents($runDir.'/run.json'), true)['seed'] ?? null;
if ($seed) {
    $tag = "AICO-{$seed}";
    $restesTag = Courrier::withTrashed()->where('objet', 'like', "%{$tag}%")->count()
        + User::where('name', 'like', "%{$tag}%")->count();

    if ($restesTag > 0) {
        echo "!!! {$restesTag} ligne(s) portant encore le tag '{$tag}' (hors manifest) !!!\n";
        $residu += $restesTag;
    }
}

echo $residu === 0 ? "RÉSULTAT : PASS — aucun résidu.\n" : "RÉSULTAT : FAIL — résidu détecté, voir ci-dessus.\n";
