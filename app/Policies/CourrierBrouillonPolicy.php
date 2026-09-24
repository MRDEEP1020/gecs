<?php

namespace App\Policies;

use App\Models\CourrierBrouillon;
use App\Models\User;

class CourrierBrouillonPolicy
{
    // Module 1/2 — "panier personnel" : un brouillon n'est utilisable que par
    // l'agent qui l'a scanné, ou qui a le privilège `brouillons.utiliser_tout`
    // (Règle n°6 — vérifié côté serveur à l'ouverture du formulaire pré-rempli
    // ET à la finalisation, jamais fait confiance à l'ID passé en query
    // string). 2026-09-15 — Système de privilèges (voir DECISIONS.md) : la
    // branche "propriétaire" reste un `||` en dur, ce n'est pas un privilège
    // assignable (tout le monde peut utiliser SES PROPRES brouillons).
    public function utiliser(User $user, CourrierBrouillon $brouillon): bool
    {
        return $user->hasPrivilege('brouillons.utiliser_tout') || $user->id === $brouillon->cree_par_id;
    }

    // Télécharger le fichier d'un brouillon (2026-09-23) : l'utiliser ET
    // avoir courriers.telecharger — même privilège que pour un courrier.
    public function telecharger(User $user, CourrierBrouillon $brouillon): bool
    {
        return $user->hasPrivilege('courriers.telecharger') && $this->utiliser($user, $brouillon);
    }
}
