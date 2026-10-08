<?php

namespace AiCompanySim;

use App\Models\OrganizationUnit;
use App\Models\Profil;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Point UNIQUE de création des données taguées AICO-<seed> — toute donnée
// créée par ce système passe par ici (pour les comptes utilisateurs,
// toujours) ou enregistre elle-même son id dans le manifest juste après
// création réelle (pour les entités métier, créées via les VRAIS composants
// Livewire/Services de l'app, jamais dupliquées ici).
class TaggedFactory
{
    public static function tag(int $seed, string $texte): string
    {
        return "[AICO-{$seed}] {$texte}";
    }

    // services.code est limité à 10 caractères (migration
    // 2026_09_03_100007) — jamais assez de place pour un préfixe AICO +
    // seed + libellé lisible. Code court mais déterministe par (seed, label).
    public static function codeCourt(int $seed, string $label): string
    {
        return 'A'.strtoupper(substr(md5($seed.'-'.$label), 0, 7));
    }

    // Réutilise le compte s'il existe déjà (idempotent — permet de relancer
    // un scénario sans dupliquer les comptes d'un même run). Le couple
    // check-puis-create n'est PAS atomique : sous vraie concurrence
    // (plusieurs scénarios lancés en processus simultanés qui partagent le
    // même rôle_acio, ex. "ops-agent" utilisé par op-01/op-02/op-03),
    // plusieurs processus peuvent tous voir "n'existe pas encore" au même
    // instant et se faire concurrence sur l'INSERT — constat réel fait en
    // lançant ce système en vrai parallèle (demande explicite de
    // l'utilisateur). La contrainte unique sur `email` reste le vrai
    // filet de sécurité (même principe que NumeroReferenceGenerator) :
    // on retente une lecture après un échec de contrainte plutôt que de
    // planter, exactement le patron déjà utilisé ailleurs dans ce projet
    // pour la génération du numéro de référence.
    public static function utilisateur(CompanyMemory $memoire, int $seed, string $roleAcio, string $nomProfil, array $attributs = []): User
    {
        $profil = Profil::firstOrCreate(['nom' => $nomProfil]);
        $email = "aico-{$seed}-{$roleAcio}@gec.local";

        $utilisateur = User::where('email', $email)->first();

        if ($utilisateur) {
            return $utilisateur;
        }

        try {
            $utilisateur = User::create(array_merge([
                'name' => self::tag($seed, ucfirst(str_replace('_', ' ', $roleAcio))),
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
                'profil_id' => $profil->id,
                'email_verified_at' => now(),
            ], $attributs));
        } catch (QueryException $e) {
            // Code 23000 = violation de contrainte d'unicité (toutes bases
            // supportées ici) — un autre processus a gagné la course entre
            // notre lecture et notre écriture ; son compte est tout aussi
            // valide que celui qu'on aurait créé, on le réutilise.
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            return User::where('email', $email)->first();
        }

        $memoire->enregistrerEntite('User', $utilisateur->id, [
            'tag' => "AICO-{$seed}",
            'role_acio' => $roleAcio,
            'email' => $email,
            'profil' => $nomProfil,
        ]);

        $memoire->enregistrerEvenement([
            'actor_role' => $roleAcio,
            'action' => 'user_created',
            'target_entity' => ['type' => 'User', 'id' => $utilisateur->id],
            'details' => "Compte {$nomProfil} créé pour le rôle {$roleAcio}",
        ]);

        return $utilisateur;
    }

    // Réutilisé par plusieurs scénarios (comme CircuitCourrierTest::
    // departementPonte()) — un Site + un Département pontés à un VRAI
    // service tagué, nécessaire pour valider un service DGA/ADJ via la
    // cascade Site/Département de ShowCourrier.
    public static function departementPonte(CompanyMemory $memoire, int $seed, string $labelPool, ?string $codeService = null): OrganizationUnit
    {
        $service = Service::create([
            'nom' => self::tag($seed, $labelPool),
            'code' => $codeService ?? self::codeCourt($seed, $labelPool),
            'actif' => true,
        ]);
        $memoire->enregistrerEntite('Service', $service->id, ['tag' => "AICO-{$seed}"]);

        $site = OrganizationUnit::create([
            'type' => OrganizationUnit::TYPE_SITE,
            'name' => self::tag($seed, 'Site Test'),
        ]);
        $memoire->enregistrerEntite('OrganizationUnit', $site->id, ['tag' => "AICO-{$seed}"]);

        $departement = OrganizationUnit::create([
            'type' => OrganizationUnit::TYPE_DEPARTMENT,
            'name' => self::tag($seed, $labelPool),
            'parent_id' => $site->id,
            'service_id' => $service->id,
        ]);
        $memoire->enregistrerEntite('OrganizationUnit', $departement->id, ['tag' => "AICO-{$seed}"]);

        return $departement;
    }
}
