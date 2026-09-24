<?php

namespace App\Services;

use App\Models\OrganizationUnit;
use App\Models\Service;
use Illuminate\Support\Str;

// Module "Organisation" (2026-09-23, voir DECISIONS.md "Services créés depuis
// l'Organisation") : la page Organisation est le SEUL endroit où l'on gère
// les services — mais tout le reste de l'application (routage DGA, service
// d'un courrier, règles de classement, recherche, responsable qui reçoit les
// courriers et les alertes SLA) lit la table `services`. Sans ce pont, un
// service créé dans l'organigramme n'était sélectionnable nulle part.
//
// Appelé explicitement par OrganisationIndex (pas un événement de modèle :
// les factories/seeders de tests créent des nœuds sans vouloir créer de
// services).
class ServiceReelSynchroniseur
{
    public const TYPES_SYNCHRONISES = [OrganizationUnit::TYPE_SERVICE, OrganizationUnit::TYPE_SUB_SERVICE];

    // $avant : valeurs du nœud AVANT modification (null à la création), pour
    // ne propager un renommage / un changement de responsable QUE si nœud et
    // service étaient alignés — jamais renommer un service existant relié à
    // la main à un nœud de nom différent (ex. "Sinistre Santé" → "Sinistres").
    public function synchroniser(OrganizationUnit $unite, ?array $avant = null): void
    {
        if (! in_array($unite->type, self::TYPES_SYNCHRONISES, true)) {
            return;
        }

        if ($unite->service_id === null) {
            $this->relierOuCreer($unite);

            return;
        }

        $service = $unite->service;

        if ($service === null) {
            return;
        }

        $modifs = [];

        $ancienNom = $avant['name'] ?? $unite->name;
        if ($service->nom === $ancienNom && $service->nom !== $unite->name
            && ! Service::where('nom', $unite->name)->whereKeyNot($service->id)->exists()) {
            $modifs['nom'] = $unite->name;
        }

        $ancienResponsable = $avant['responsible_user_id'] ?? null;
        if ($unite->responsible_user_id !== null
            && ($service->responsable_id === null || $service->responsable_id === $ancienResponsable)) {
            $modifs['responsable_id'] = $unite->responsible_user_id;
        }

        $modifs['actif'] = $this->doitEtreActif($service, $unite);

        $service->update($modifs);
    }

    // Aucun service choisi à la main : un service de même nom existe déjà →
    // on le relie (pas de doublon, `services.nom` est unique) ; sinon on le crée.
    private function relierOuCreer(OrganizationUnit $unite): void
    {
        $service = Service::where('nom', $unite->name)->first()
            ?? Service::create([
                'nom' => $unite->name,
                'code' => $this->codeUnique($unite->code, $unite->name),
                'responsable_id' => $unite->responsible_user_id,
                'actif' => $unite->estActif(),
            ]);

        if ($service->responsable_id === null && $unite->responsible_user_id !== null) {
            $service->update(['responsable_id' => $unite->responsible_user_id]);
        }

        $unite->update(['service_id' => $service->id]);
    }

    // Un service partagé par plusieurs nœuds reste actif tant qu'au moins un
    // nœud actif l'utilise.
    private function doitEtreActif(Service $service, OrganizationUnit $unite): bool
    {
        if ($unite->estActif()) {
            return true;
        }

        return OrganizationUnit::where('service_id', $service->id)
            ->whereKeyNot($unite->id)
            ->where('status', OrganizationUnit::STATUT_ACTIF)
            ->exists();
    }

    // `services.code` : 10 caractères max, unique. Code du nœud s'il est
    // utilisable, sinon initiales du nom (ex. "Sinistre Santé" → "SS"),
    // suffixé -2, -3… en cas de collision.
    private function codeUnique(?string $codeNoeud, string $nom): string
    {
        $base = Str::upper(Str::ascii((string) $codeNoeud));
        $base = preg_replace('/[^A-Z0-9-]/', '', $base);

        if ($base === '' || strlen($base) > 10) {
            $mots = preg_split('/[^A-Za-z0-9]+/', Str::ascii($nom), -1, PREG_SPLIT_NO_EMPTY);
            $base = Str::upper(count($mots) > 1
                ? implode('', array_map(fn ($m) => $m[0], $mots))
                : substr($mots[0] ?? 'SRV', 0, 6));
            $base = substr($base, 0, 6) ?: 'SRV';
        }

        $code = $base;
        $i = 2;

        while (Service::where('code', $code)->exists()) {
            $suffixe = '-'.$i++;
            $code = substr($base, 0, 10 - strlen($suffixe)).$suffixe;
        }

        return $code;
    }
}
