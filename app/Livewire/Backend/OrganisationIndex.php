<?php

namespace App\Livewire\Backend;

use App\Models\OrganizationUnit;
use App\Models\Service;
use App\Models\User;
use App\Services\ServiceReelSynchroniseur;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

// Module "Organisation" v2 (2026-09-22, spec technique complète fournie par
// l'utilisateur) — remplace la page "Services" du tour précédent. Hiérarchie
// dynamique Company → Site → Department → Service → Sub-service (Service
// est OPTIONNEL, spec §1). UN seul composant Livewire (pas la répartition en
// 5 composants suggérée par la spec §21 — ce projet n'a jamais imbriqué de
// composants Livewire, Règle n°2 ; même patron que ServiceList/
// DossierClassementList/UserList : un composant + des composants Blade
// simples pour les parties récursives). Voir OrganizationUnit::estGerePar()
// pour le patron auto-référencé (copié de DossierClassement).
#[Title('Organisation')]
class OrganisationIndex extends Component
{
    use WithPagination;

    #[Url]
    public ?int $noeudId = null;

    public string $recherche = '';

    public string $filtreType = '';

    public string $filtreStatut = '';

    // Onglet du panneau central pour le nœud sélectionné (spec §10).
    public string $ongletDetail = 'utilisateurs';

    // ===== Modale "Ajouter/Modifier une entité" =====
    public ?int $noeudEnEditionId = null;

    public ?int $parentPourCreationId = null;

    public string $nomNoeud = '';

    public string $codeNoeud = '';

    public string $typeNoeud = OrganizationUnit::TYPE_SITE;

    public string $descriptionNoeud = '';

    public ?int $responsableIdNoeud = null;

    public ?int $servicePontNoeud = null;

    // ===== Modale "Déplacer" =====
    public ?int $noeudADeplacerId = null;

    public ?int $nouveauParentId = null;

    // ===== Onglet "Utilisateurs" du panneau central =====
    public ?int $utilisateurAAjouterId = null;

    public string $roleUtilisateurAAjouter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', OrganizationUnit::class);
    }

    public function updated($nom): void
    {
        if ($nom === 'recherche' || $nom === 'filtreType' || $nom === 'filtreStatut') {
            $this->resetPage();
        }

        // 2026-09-23, demande explicite de l'utilisateur ("make all the
        // tabs here to work") — le champ "Service réel lié" n'est affiché
        // que pour department/service (voir la vue) ; sans ce reset, un
        // pont choisi puis un changement de type vers site/sous_service
        // resterait en mémoire et serait quand même enregistré tel quel par
        // enregistrerNoeud(), invisible à l'écran.
        if ($nom === 'typeNoeud' && ! in_array($this->typeNoeud, [OrganizationUnit::TYPE_DEPARTMENT, OrganizationUnit::TYPE_SERVICE], true)) {
            $this->servicePontNoeud = null;
        }
    }

    // ===== Arborescence =====

    // 'organisation.view' est global (pas de périmètre par branche pour la
    // gestion de la structure elle-même, non demandé) — quiconque peut ouvrir
    // la page voit tout l'arbre.
    #[Computed]
    public function tousLesNoeuds(): Collection
    {
        return OrganizationUnit::query()
            ->withCount(['enfants', 'utilisateurs'])
            ->with(['responsable:id,name', 'service' => fn ($q) => $q->withCount('courriers')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function arbre(): array
    {
        $noeuds = $this->tousLesNoeuds;
        $parNiveauParent = $noeuds->groupBy('parent_id');

        $construire = function ($parentId) use (&$construire, $parNiveauParent) {
            return ($parNiveauParent->get($parentId) ?? collect())
                ->map(fn ($n) => ['noeud' => $n, 'enfants' => $construire($n->id)])
                ->values()->all();
        };

        return $construire(null);
    }

    // Recherche globale (spec §12) — dans le nom/code/description des
    // entités ET dans le nom des utilisateurs rattachés, avec le chemin
    // hiérarchique de chaque résultat.
    #[Computed]
    public function resultatsRecherche(): Collection
    {
        if (trim($this->recherche) === '') {
            return collect();
        }

        $tousLesNoeuds = $this->tousLesNoeuds;
        $recherche = mb_strtolower($this->recherche);

        return $tousLesNoeuds
            ->filter(function (OrganizationUnit $noeud) use ($recherche) {
                if (str_contains(mb_strtolower($noeud->name), $recherche)
                    || str_contains(mb_strtolower($noeud->code ?? ''), $recherche)
                    || str_contains(mb_strtolower($noeud->description ?? ''), $recherche)) {
                    return true;
                }

                return $noeud->utilisateurs->contains(fn (User $u) => str_contains(mb_strtolower($u->name), $recherche));
            })
            ->when($this->filtreType !== '', fn ($c) => $c->where('type', $this->filtreType))
            ->when($this->filtreStatut !== '', fn ($c) => $c->where('status', $this->filtreStatut))
            ->map(fn (OrganizationUnit $noeud) => ['noeud' => $noeud, 'chemin' => $noeud->cheminComplet($tousLesNoeuds)])
            ->values();
    }

    public function selectionnerNoeud(?int $id): void
    {
        $this->noeudId = $id;
        $this->ongletDetail = 'utilisateurs';
        $this->resetPage();
    }

    #[Computed]
    public function noeudSelectionne(): ?OrganizationUnit
    {
        if ($this->noeudId === null) {
            return null;
        }

        return OrganizationUnit::with(['responsable', 'service'])->find($this->noeudId);
    }

    // ===== Onglet "Utilisateurs" (spec §10) =====

    #[Computed]
    public function utilisateursDuNoeud()
    {
        if ($this->noeudSelectionne === null) {
            return null;
        }

        return $this->noeudSelectionne->utilisateurs()->orderBy('name')->paginate(10);
    }

    #[Computed]
    public function utilisateursDisponiblesPourAjout()
    {
        $noeud = $this->noeudSelectionne;

        if ($noeud === null) {
            return collect();
        }

        return User::query()->whereDoesntHave('organizationUnits', fn ($q) => $q->where('organization_unit_id', $noeud->id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function ajouterUtilisateur(): void
    {
        $noeud = $this->noeudSelectionne;
        $this->authorize('manageUsers', $noeud);

        if (! $this->utilisateurAAjouterId) {
            $this->addError('utilisateurAAjouterId', __('Choisissez un utilisateur.'));

            return;
        }

        $noeud->utilisateurs()->syncWithoutDetaching([
            $this->utilisateurAAjouterId => ['role_in_unit' => $this->roleUtilisateurAAjouter ?: null],
        ]);

        $this->reset('utilisateurAAjouterId', 'roleUtilisateurAAjouter');
        unset($this->utilisateursDuNoeud, $this->utilisateursDisponiblesPourAjout, $this->tousLesNoeuds, $this->arbre, $this->noeudSelectionne);
        Flux::toast(variant: 'success', text: __('Utilisateur rattaché.'));
    }

    public function retirerUtilisateur(int $userId): void
    {
        $noeud = $this->noeudSelectionne;
        $this->authorize('manageUsers', $noeud);

        $noeud->utilisateurs()->detach($userId);

        unset($this->utilisateursDuNoeud, $this->utilisateursDisponiblesPourAjout, $this->tousLesNoeuds, $this->arbre, $this->noeudSelectionne);
        Flux::toast(text: __('Utilisateur retiré de l\'unité.'));
    }

    public function definirCommeResponsable(int $userId, ServiceReelSynchroniseur $synchroniseur): void
    {
        $noeud = $this->noeudSelectionne;
        $this->authorize('update', $noeud);

        $avant = $noeud->only(['name', 'responsible_user_id']);
        $noeud->update(['responsible_user_id' => $userId]);

        // Le responsable du service réel suit (c'est lui qui reçoit les
        // courriers du service et les alertes SLA).
        $synchroniseur->synchroniser($noeud, $avant);

        unset($this->tousLesNoeuds, $this->arbre, $this->noeudSelectionne);
        Flux::toast(text: __('Responsable défini.'));
    }

    // ===== Créer / Modifier =====

    // Le type proposé par défaut découle du type du parent (spec §7/§8).
    public function ouvrirCreation(?int $parentId = null): void
    {
        $parent = $parentId ? OrganizationUnit::findOrFail($parentId) : null;
        $this->authorize('create', [OrganizationUnit::class, $parent]);

        $this->reset(['noeudEnEditionId', 'nomNoeud', 'codeNoeud', 'descriptionNoeud', 'responsableIdNoeud', 'servicePontNoeud']);
        $this->typeNoeud = $parent?->typeEnfantPropose() ?? OrganizationUnit::TYPE_SITE;
        $this->parentPourCreationId = $parentId;
        $this->resetValidation();

        Flux::modal('organisation-form')->show();
    }

    public function ouvrirModification(int $id): void
    {
        $noeud = OrganizationUnit::findOrFail($id);
        $this->authorize('update', $noeud);

        $this->noeudEnEditionId = $id;
        $this->parentPourCreationId = $noeud->parent_id;
        $this->nomNoeud = $noeud->name;
        $this->codeNoeud = $noeud->code ?? '';
        $this->typeNoeud = $noeud->type;
        $this->descriptionNoeud = $noeud->description ?? '';
        $this->responsableIdNoeud = $noeud->responsible_user_id;
        $this->servicePontNoeud = $noeud->service_id;
        $this->resetValidation();

        Flux::modal('organisation-form')->show();
    }

    public function enregistrerNoeud(ServiceReelSynchroniseur $synchroniseur): void
    {
        $noeud = $this->noeudEnEditionId ? OrganizationUnit::findOrFail($this->noeudEnEditionId) : null;
        $parent = $this->parentPourCreationId ? OrganizationUnit::findOrFail($this->parentPourCreationId) : null;

        $this->authorize($noeud ? 'update' : 'create', $noeud ?? [OrganizationUnit::class, $parent]);

        // Ne pas permettre des relations incohérentes (spec §7/§8) — vérifié
        // côté serveur, pas seulement dans l'UI (le sélecteur de type
        // n'affiche déjà que des choix cohérents, ceci est le filet de
        // sécurité réel).
        $typesValides = OrganizationUnit::ORDRE_TYPES;
        $indexParent = $parent ? array_search($parent->type, $typesValides, true) : -1;

        $data = $this->validate([
            'nomNoeud' => ['required', 'string', 'max:255'],
            'codeNoeud' => ['nullable', 'string', 'max:30', 'unique:organization_units,code,'.($noeud?->id ?: 'NULL')],
            'typeNoeud' => ['required', 'in:'.implode(',', $typesValides)],
            'descriptionNoeud' => ['nullable', 'string'],
            'responsableIdNoeud' => ['nullable', 'integer', 'exists:users,id'],
            'servicePontNoeud' => ['nullable', 'integer', 'exists:services,id'],
        ]);

        if (array_search($data['typeNoeud'], $typesValides, true) <= $indexParent) {
            $this->addError('typeNoeud', __('Ce type n\'est pas cohérent avec l\'entité parente sélectionnée.'));

            return;
        }

        $attributs = [
            'name' => $data['nomNoeud'],
            'code' => $data['codeNoeud'] ?: null,
            'type' => $data['typeNoeud'],
            'parent_id' => $parent?->id,
            'description' => $data['descriptionNoeud'] ?: null,
            'responsible_user_id' => $data['responsableIdNoeud'] ?: null,
            'service_id' => $data['servicePontNoeud'] ?: null,
        ];

        $avant = $noeud?->only(['name', 'responsible_user_id']);

        $noeud ? $noeud->update($attributs) : $noeud = OrganizationUnit::create($attributs);

        // Service / sous-service : crée ou relie le service réel (routage
        // des courriers) et y reporte nom/responsable/statut (2026-09-23).
        $synchroniseur->synchroniser($noeud, $avant);

        Flux::modal('organisation-form')->close();
        $this->selectionnerNoeud($noeud->id);
        unset($this->tousLesNoeuds, $this->arbre);
        Flux::toast(variant: 'success', text: __('Entité enregistrée.'));
    }

    // ===== Déplacer =====

    public function ouvrirDeplacement(int $id): void
    {
        $noeud = OrganizationUnit::findOrFail($id);
        $this->authorize('update', $noeud);

        $this->noeudADeplacerId = $id;
        $this->nouveauParentId = $noeud->parent_id;

        Flux::modal('organisation-deplacement')->show();
    }

    public function deplacerNoeud(): void
    {
        $noeud = OrganizationUnit::findOrFail($this->noeudADeplacerId);
        $nouveauParent = $this->nouveauParentId ? OrganizationUnit::findOrFail($this->nouveauParentId) : null;

        $this->authorize('deplacer', [$noeud, $nouveauParent]);

        if ($nouveauParent && ($nouveauParent->id === $noeud->id
            || $noeud->estDescendantDe($nouveauParent->id, $this->tousLesNoeuds))) {
            Flux::toast(variant: 'danger', text: __('Déplacement impossible : cette entité ne peut pas devenir sa propre sous-entité.'));

            return;
        }

        $noeud->update(['parent_id' => $nouveauParent?->id]);

        Flux::modal('organisation-deplacement')->close();
        unset($this->tousLesNoeuds, $this->arbre, $this->noeudSelectionne);
        Flux::toast(text: __('Entité déplacée.'));
    }

    // ===== Activer / Désactiver (spec §15 — jamais de suppression) =====

    public function basculerStatut(int $id, ServiceReelSynchroniseur $synchroniseur): void
    {
        $noeud = OrganizationUnit::findOrFail($id);
        $this->authorize('deactivate', $noeud);

        $noeud->update(['status' => $noeud->estActif() ? OrganizationUnit::STATUT_INACTIF : OrganizationUnit::STATUT_ACTIF]);

        // Le service réel suit (désactivé seulement si aucun autre nœud actif ne l'utilise).
        $synchroniseur->synchroniser($noeud, $noeud->only(['name', 'responsible_user_id']));

        unset($this->tousLesNoeuds, $this->arbre, $this->noeudSelectionne);
        Flux::toast(text: $noeud->estActif() ? __('Entité activée.') : __('Entité désactivée.'));
    }

    #[Computed]
    public function responsablesPotentiels()
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function servicesReels()
    {
        return Service::query()->where('actif', true)->orderBy('nom')->get(['id', 'nom']);
    }

    public function render()
    {
        return view('frontend::organisationIndex');
    }
}
