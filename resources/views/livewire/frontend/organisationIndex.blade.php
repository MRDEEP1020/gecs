{{-- Module "Organisation" v2 (2026-09-22, spec technique complète fournie
     par l'utilisateur) — hiérarchie dynamique Company → Site → Department →
     Service → Sub-service. Arbre (gauche) + contenu du nœud sélectionné
     (centre, avec onglets) + panneau "Détails de l'élément sélectionné"
     (droite, sticky — RÈGLE PERMANENTE, voir memory apercu_panel_sticky_pages),
     même patron que "Dossiers & Archives"/"Services". --}}
<section class="w-full">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate></flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Administration') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Organisation') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-navy text-white">
                <flux:icon.building-office-2 class="size-5" />
            </div>
            <div>
                <flux:heading level="1">{{ __('Organisation') }}</flux:heading>
                <flux:subheading>{{ __('Gérez la structure organisationnelle : sites, départements, services et sous-services.') }}</flux:subheading>
            </div>
        </div>

        @can('create', App\Models\OrganizationUnit::class)
            <flux:button variant="primary" icon="plus" wire:click="ouvrirCreation">
                {{ __('Ajouter un élément') }}
            </flux:button>
        @endcan
    </div>

    {{-- Recherche globale + filtres (spec §12/§13) --}}
    <div class="mt-6 rounded-2xl border border-brand-border bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:input wire:model.live.debounce.300ms="recherche" icon="magnifying-glass" :placeholder="__('Rechercher dans l\'organisation… (nom, code, utilisateur)')" />

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <flux:select wire:model.live="filtreType" :label="__('Type')">
                <flux:select.option value="">{{ __('Tous les types') }}</flux:select.option>
                <flux:select.option value="{{ App\Models\OrganizationUnit::TYPE_SITE }}">{{ __('Sites') }}</flux:select.option>
                <flux:select.option value="{{ App\Models\OrganizationUnit::TYPE_DEPARTMENT }}">{{ __('Départements') }}</flux:select.option>
                <flux:select.option value="{{ App\Models\OrganizationUnit::TYPE_SERVICE }}">{{ __('Services') }}</flux:select.option>
                <flux:select.option value="{{ App\Models\OrganizationUnit::TYPE_SUB_SERVICE }}">{{ __('Sous-services') }}</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="filtreStatut" :label="__('Statut')">
                <flux:select.option value="">{{ __('Tous les status') }}</flux:select.option>
                <flux:select.option value="{{ App\Models\OrganizationUnit::STATUT_ACTIF }}">{{ __('Actifs') }}</flux:select.option>
                <flux:select.option value="{{ App\Models\OrganizationUnit::STATUT_INACTIF }}">{{ __('Inactifs') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    {{-- Résultats de recherche (spec §12) — remplace la vue arbre/détails
         tant qu'une recherche est active. --}}
    @if (trim($recherche) !== '')
        <div class="mt-6 rounded-2xl border border-brand-border bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-brand-border p-4 dark:border-zinc-700">
                <flux:heading level="2" class="text-base">{{ __(':n résultat(s) pour ":recherche"', ['n' => $this->resultatsRecherche->count(), 'recherche' => $recherche]) }}</flux:heading>
            </div>
            <ul class="divide-y divide-brand-border dark:divide-zinc-700">
                @forelse ($this->resultatsRecherche as $resultat)
                    <li wire:key="resultat-{{ $resultat['noeud']->id }}" class="flex cursor-pointer items-center justify-between gap-2 p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/60" wire:click="$set('recherche', ''); selectionnerNoeud({{ $resultat['noeud']->id }})">
                        <div class="min-w-0">
                            <div class="font-medium">{{ $resultat['noeud']->name }}</div>
                            <div class="truncate text-xs text-zinc-500">{{ $resultat['chemin'] }}</div>
                        </div>
                        <flux:icon.chevron-right class="size-4 shrink-0 text-zinc-400" />
                    </li>
                @empty
                    <li class="p-6 text-center text-sm text-zinc-500">{{ __('Aucun résultat.') }}</li>
                @endforelse
            </ul>
        </div>
    @else
        <div class="mt-6 grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)_360px]">
            {{-- ===== Colonne gauche — arborescence ===== --}}
            <div class="sticky top-20 self-start space-y-3">
                <div class="rounded-2xl border border-brand-border bg-white p-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-2 flex items-center justify-between px-1">
                        <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('Structure de l\'organisation') }}</flux:text>
                    </div>

                    <nav class="space-y-[2px]">
                        @foreach ($this->arbre as $racine)
                            <x-organization-tree-node :noeud="$racine" :noeud-id-selectionne="$noeudId" :tous-les-noeuds="$this->tousLesNoeuds" wire:key="unite-racine-{{ $racine['noeud']->id }}" />
                        @endforeach

                        @if (count($this->arbre) === 0)
                            <flux:text class="px-2 py-3 text-xs text-zinc-400">{{ __('Aucune entité pour l\'instant.') }}</flux:text>
                        @endif
                    </nav>
                </div>
            </div>

            {{-- ===== Colonne centrale — contenu du nœud sélectionné ===== --}}
            <div class="space-y-4">
                @if ($this->noeudSelectionne)
                    @php($unite = $this->noeudSelectionne)
                    <div class="rounded-2xl border border-brand-border bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <flux:heading level="2">{{ $unite->name }}</flux:heading>
                                    <span class="rounded-full bg-brand-blue-pale px-2 py-0.5 text-xs font-medium text-brand-blue">
                                        {{ match ($unite->type) {
                                            App\Models\OrganizationUnit::TYPE_COMPANY => __('Entreprise'),
                                            App\Models\OrganizationUnit::TYPE_SITE => __('Site / Agence'),
                                            App\Models\OrganizationUnit::TYPE_DEPARTMENT => __('Département'),
                                            App\Models\OrganizationUnit::TYPE_SUB_SERVICE => __('Sous-service'),
                                            default => __('Service'),
                                        } }}
                                    </span>
                                </div>
                                <flux:text class="mt-1 text-xs text-zinc-500">{{ $unite->cheminComplet($this->tousLesNoeuds) }}</flux:text>
                            </div>
                            @can('update', $unite)
                                <flux:button size="sm" variant="outline" icon="pencil-square" wire:click="ouvrirModification({{ $unite->id }})">{{ __('Modifier') }}</flux:button>
                            @endcan
                        </div>

                        {{-- Onglets du nœud sélectionné (spec §10) --}}
                        <div class="mt-4 flex gap-4 border-b border-brand-border text-sm dark:border-zinc-700">
                            @foreach (['utilisateurs' => __('Utilisateurs'), 'responsable' => __('Responsable'), 'informations' => __('Informations'), 'parametres' => __('Paramètres')] as $cle => $libelle)
                                <button type="button" wire:click="$set('ongletDetail', '{{ $cle }}')" class="border-b-2 pb-2 {{ $ongletDetail === $cle ? 'border-brand-blue font-medium text-brand-blue' : 'border-transparent text-zinc-500 hover:text-zinc-700' }}">
                                    {{ $libelle }}
                                </button>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            @if ($ongletDetail === 'utilisateurs')
                                @can('manageUsers', $unite)
                                    <div class="mb-4 flex flex-wrap items-end gap-2 rounded-xl bg-brand-blue-soft p-3 dark:bg-zinc-800">
                                        <flux:select wire:model="utilisateurAAjouterId" size="sm" :label="__('Rattacher un utilisateur')">
                                            <flux:select.option value="">{{ __('Choisir un utilisateur') }}</flux:select.option>
                                            @foreach ($this->utilisateursDisponiblesPourAjout as $u)
                                                <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                        <flux:input wire:model="roleUtilisateurAAjouter" size="sm" :label="__('Fonction / rôle')" placeholder="{{ __('ex. Collaborateur') }}" class="max-w-xs" />
                                        <flux:button size="sm" variant="primary" wire:click="ajouterUtilisateur">{{ __('Rattacher') }}</flux:button>
                                    </div>
                                    @error('utilisateurAAjouterId') <flux:text class="mb-2 text-sm text-brand-danger">{{ $message }}</flux:text> @enderror
                                @endcan

                                <div class="overflow-x-auto rounded-xl border border-brand-border dark:border-zinc-700">
                                    <table class="w-full text-sm">
                                        <thead class="bg-brand-blue-pale text-left text-sm font-medium text-zinc-600 dark:bg-zinc-800">
                                            <tr>
                                                <th class="py-2 pl-3 pr-2">{{ __('Nom') }}</th>
                                                <th class="py-2 pr-2">{{ __('Fonction') }}</th>
                                                <th class="py-2 pr-2">{{ __('Statut') }}</th>
                                                <th class="py-2 pr-3 text-right">{{ __('Actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-brand-border dark:divide-zinc-700">
                                            @forelse ($this->utilisateursDuNoeud as $u)
                                                <tr wire:key="membre-{{ $u->id }}">
                                                    <td class="py-2 pl-3 pr-2 font-medium">{{ $u->name }}</td>
                                                    <td class="py-2 pr-2 text-zinc-500">{{ $u->pivot->role_in_unit ?? '—' }}</td>
                                                    <td class="py-2 pr-2">
                                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $u->actif ? 'text-brand-success' : 'text-zinc-500' }}">
                                                            <span class="size-2 rounded-full {{ $u->actif ? 'bg-brand-success' : 'bg-zinc-400' }}"></span>
                                                            {{ $u->actif ? __('Actif') : __('Désactivé') }}
                                                        </span>
                                                    </td>
                                                    <td class="py-2 pr-3 text-right">
                                                        <flux:dropdown position="bottom" align="end">
                                                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" square :aria-label="__('Actions')" />
                                                            <flux:menu>
                                                                <flux:menu.item icon="user" :href="route('admin.utilisateurs')" wire:navigate>{{ __('Gérer cet utilisateur') }}</flux:menu.item>
                                                                @can('update', $unite)
                                                                    <flux:menu.item icon="star" wire:click="definirCommeResponsable({{ $u->id }})">{{ __('Définir comme responsable') }}</flux:menu.item>
                                                                @endcan
                                                                @can('manageUsers', $unite)
                                                                    <flux:menu.separator />
                                                                    <flux:menu.item icon="x-mark" variant="danger" wire:click="retirerUtilisateur({{ $u->id }})" wire:confirm="{{ __('Retirer cet utilisateur de l\'unité ?') }}">{{ __('Retirer de l\'unité') }}</flux:menu.item>
                                                                @endcan
                                                            </flux:menu>
                                                        </flux:dropdown>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="p-6 text-center text-zinc-500">{{ __('Aucun utilisateur rattaché.') }}</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                @if ($this->utilisateursDuNoeud)
                                    <div class="mt-3">{{ $this->utilisateursDuNoeud->links() }}</div>
                                @endif
                            @elseif ($ongletDetail === 'responsable')
                                <div class="flex items-center gap-3">
                                    <div class="flex size-10 items-center justify-center rounded-full bg-brand-blue-pale text-brand-blue">
                                        <flux:icon.user class="size-5" />
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ $unite->responsable?->name ?? __('Aucun responsable défini') }}</div>
                                        <flux:text class="text-xs text-zinc-500">{{ $unite->responsable?->email ?? '—' }}</flux:text>
                                    </div>
                                </div>
                            @elseif ($ongletDetail === 'informations')
                                <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                                    <div><dt class="text-xs text-zinc-500">{{ __('Code') }}</dt><dd>{{ $unite->code ?? '—' }}</dd></div>
                                    <div><dt class="text-xs text-zinc-500">{{ __('Service réel lié') }}</dt><dd>{{ $unite->service?->nom ?? '—' }}</dd></div>
                                    <div class="sm:col-span-2"><dt class="text-xs text-zinc-500">{{ __('Description') }}</dt><dd>{{ $unite->description ?: '—' }}</dd></div>
                                    <div><dt class="text-xs text-zinc-500">{{ __('Créée le') }}</dt><dd>{{ $unite->created_at->format('d/m/Y') }}</dd></div>
                                    <div><dt class="text-xs text-zinc-500">{{ __('Modifiée le') }}</dt><dd>{{ $unite->updated_at->format('d/m/Y') }}</dd></div>
                                </dl>
                            @elseif ($ongletDetail === 'parametres')
                                <div class="flex items-center justify-between rounded-xl border border-brand-border p-3 dark:border-zinc-700">
                                    <div>
                                        <div class="text-sm font-medium">{{ __('Statut de l\'entité') }}</div>
                                        <flux:text class="text-xs text-zinc-500">{{ __('Aucune suppression physique n\'est possible — seulement activer/désactiver.') }}</flux:text>
                                    </div>
                                    @can('deactivate', $unite)
                                        <flux:button size="sm" variant="{{ $unite->estActif() ? 'danger' : 'primary' }}" wire:click="basculerStatut({{ $unite->id }})">
                                            {{ $unite->estActif() ? __('Désactiver') : __('Activer') }}
                                        </flux:button>
                                    @endcan
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="rounded-2xl border border-brand-border bg-white p-10 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:icon.building-office-2 class="mx-auto size-8 text-zinc-300" />
                        <flux:text class="mt-2 text-sm text-zinc-500">{{ __('Sélectionnez une entité dans l\'arborescence pour voir son contenu.') }}</flux:text>
                    </div>
                @endif
            </div>

            {{-- ===== Colonne droite — "Détails de l'élément sélectionné" ===== --}}
            <div class="sticky top-20 self-start space-y-4">
                @if ($this->noeudSelectionne)
                    @php($unite = $this->noeudSelectionne)
                    <div class="rounded-2xl border border-brand-border bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:text class="mb-2 text-xs font-medium uppercase text-zinc-500">{{ __('Détails de l\'élément sélectionné') }}</flux:text>
                        <div class="font-medium">{{ $unite->name }}</div>
                        <span class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium {{ $unite->estActif() ? 'text-brand-success' : 'text-zinc-500' }}">
                            <span class="size-2 rounded-full {{ $unite->estActif() ? 'bg-brand-success' : 'bg-zinc-400' }}"></span>
                            {{ $unite->estActif() ? __('Actif') : __('Désactivé') }}
                        </span>

                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex items-start gap-2">
                                <flux:icon.arrow-turn-up-left class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                                <div class="min-w-0">
                                    <dt class="text-xs text-zinc-500">{{ __('Parent') }}</dt>
                                    <dd class="truncate">{{ $unite->parent?->name ?? '—' }}</dd>
                                </div>
                            </div>
                            <div class="flex items-start gap-2">
                                <flux:icon.user class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                                <div class="min-w-0">
                                    <dt class="text-xs text-zinc-500">{{ __('Responsable') }}</dt>
                                    <dd class="truncate">{{ $unite->responsable?->name ?? '—' }}</dd>
                                </div>
                            </div>
                            <div class="flex items-start gap-2">
                                <flux:icon.document-text class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                                <div class="min-w-0">
                                    <dt class="text-xs text-zinc-500">{{ __('Description') }}</dt>
                                    <dd class="truncate">{{ $unite->description ?: '—' }}</dd>
                                </div>
                            </div>
                        </dl>

                        <div class="mt-4 grid grid-cols-3 gap-2 rounded-xl bg-brand-blue-soft p-3 text-center dark:bg-zinc-800">
                            <div>
                                <div class="text-lg font-bold">{{ $unite->utilisateurs_count }}</div>
                                <flux:text class="text-xs text-zinc-500">{{ __('Utilisateurs') }}</flux:text>
                            </div>
                            <div>
                                <div class="text-lg font-bold">{{ $unite->responsable ? 1 : 0 }}</div>
                                <flux:text class="text-xs text-zinc-500">{{ __('Responsable') }}</flux:text>
                            </div>
                            <div>
                                <div class="text-lg font-bold">{{ $unite->enfants->where('type', App\Models\OrganizationUnit::TYPE_SUB_SERVICE)->count() }}</div>
                                <flux:text class="text-xs text-zinc-500">{{ __('Sous-services') }}</flux:text>
                            </div>
                        </div>

                        <div class="mt-4 flex gap-2">
                            @can('update', $unite)
                                <flux:button size="sm" variant="outline" icon="pencil-square" wire:click="ouvrirModification({{ $unite->id }})" class="flex-1">{{ __('Modifier') }}</flux:button>
                            @endcan
                            @can('deactivate', $unite)
                                <flux:button size="sm" variant="{{ $unite->estActif() ? 'danger' : 'primary' }}" wire:click="basculerStatut({{ $unite->id }})" class="flex-1">
                                    {{ $unite->estActif() ? __('Désactiver') : __('Activer') }}
                                </flux:button>
                            @endcan
                        </div>
                    </div>

                    <div class="rounded-2xl border border-brand-border bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:text class="mb-2 text-xs font-medium uppercase text-zinc-500">{{ __('Actions rapides') }}</flux:text>
                        <div class="space-y-1">
                            @if ($unite->typeEnfantPropose())
                                @can('create', [App\Models\OrganizationUnit::class, $unite])
                                    <button type="button" wire:click="ouvrirCreation({{ $unite->id }})" class="flex w-full items-center justify-between rounded-lg px-2 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                                        <span class="flex items-center gap-2"><flux:icon.plus class="size-4 text-zinc-400" />{{ __('Ajouter une sous-entité') }}</span>
                                        <flux:icon.chevron-right class="size-4 text-zinc-400" />
                                    </button>
                                @endcan
                            @endif
                            @can('update', $unite)
                                <button type="button" wire:click="ouvrirDeplacement({{ $unite->id }})" class="flex w-full items-center justify-between rounded-lg px-2 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                                    <span class="flex items-center gap-2"><flux:icon.arrows-right-left class="size-4 text-zinc-400" />{{ __('Déplacer') }}</span>
                                    <flux:icon.chevron-right class="size-4 text-zinc-400" />
                                </button>
                            @endcan
                        </div>
                    </div>
                @else
                    <div class="rounded-2xl border border-brand-border bg-white p-6 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:icon.information-circle class="mx-auto size-8 text-zinc-300" />
                        <flux:text class="mt-2 text-sm text-zinc-500">{{ __('Sélectionnez une entité pour voir ses détails.') }}</flux:text>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ===== Modale "Ajouter/Modifier une entité" ===== --}}
    {{-- max-w-xl (2026-09-23, "make all the tabs here to work") — max-w-lg
         était trop étroit pour les 4 libellés du sélecteur "Type" segmenté
         ci-dessous, forçant un défilement horizontal qui coupait "Sous-service". --}}
    <flux:modal name="organisation-form" class="w-full max-w-xl">
        <form wire:submit="enregistrerNoeud" class="space-y-6">
            <flux:heading level="2">{{ $noeudEnEditionId ? __('Modifier l\'entité') : __('Ajouter un élément') }}</flux:heading>

            {{-- 2026-09-23, demande explicite de l'utilisateur ("make all the
                 tabs here to work") — .live obligatoire : la visibilité du
                 champ "Service réel lié" ci-dessous dépend d'un @if PHP sur
                 $typeNoeud, qui ne se réévalue qu'à un aller-retour serveur.
                 Sans .live, le radio bouton bascule visuellement (comportement
                 natif du <input type=radio>) mais le formulaire ne se
                 rafraîchit jamais tant qu'aucun autre champ .live n'est
                 touché. --}}
            <flux:radio.group wire:model.live="typeNoeud" :label="__('Type')" variant="segmented">
                <flux:radio value="{{ App\Models\OrganizationUnit::TYPE_SITE }}" :label="__('Site / Agence')" />
                <flux:radio value="{{ App\Models\OrganizationUnit::TYPE_DEPARTMENT }}" :label="__('Département')" />
                <flux:radio value="{{ App\Models\OrganizationUnit::TYPE_SERVICE }}" :label="__('Service / Unité')" />
                <flux:radio value="{{ App\Models\OrganizationUnit::TYPE_SUB_SERVICE }}" :label="__('Sous-service')" />
            </flux:radio.group>
            @error('typeNoeud') <flux:text class="text-sm text-brand-danger">{{ $message }}</flux:text> @enderror

            {{-- 2026-09-23, demande explicite de l'utilisateur ("on the form
                 it shows the same tabs") — Département et Service/Unité
                 affichent le même champ "Service réel lié" (les deux peuvent
                 légitimement être pontés, spec §1), donc rien ne confirmait
                 visuellement que le changement d'onglet avait bien pris —
                 cette description change avec $typeNoeud pour le rendre visible. --}}
            <flux:text class="text-xs text-zinc-500">
                {{ match ($typeNoeud) {
                    App\Models\OrganizationUnit::TYPE_SITE => __('Un site/agence physique — le niveau le plus haut, sans parent.'),
                    App\Models\OrganizationUnit::TYPE_DEPARTMENT => __('Un département/direction — peut recevoir les courriers directement, ou contenir des Services.'),
                    App\Models\OrganizationUnit::TYPE_SERVICE => __('Un service/unité — rattaché à un département, peut lui-même contenir des Sous-services.'),
                    App\Models\OrganizationUnit::TYPE_SUB_SERVICE => __('Un sous-service — le niveau le plus fin, rattaché à un Service.'),
                    default => '',
                } }}
            </flux:text>

            <flux:input wire:model="nomNoeud" :label="__('Nom')" required />
            <flux:input wire:model="codeNoeud" :label="__('Code')" placeholder="{{ __('ex. SIN-SANTE') }}" />

            <flux:select wire:model="responsableIdNoeud" :label="__('Responsable')" placeholder="{{ __('— Aucun pour l\'instant —') }}">
                <flux:select.option value="">{{ __('— Aucun pour l\'instant —') }}</flux:select.option>
                @foreach ($this->responsablesPotentiels as $u)
                    <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                @endforeach
            </flux:select>

            {{-- 2026-09-23 : un Service / Sous-service sans lien choisi crée
                 (ou relie, s'il existe déjà sous ce nom) automatiquement son
                 service réel — voir App\Services\ServiceReelSynchroniseur. --}}
            @php($creationAuto = in_array($typeNoeud, App\Services\ServiceReelSynchroniseur::TYPES_SYNCHRONISES))
            @if (in_array($typeNoeud, [App\Models\OrganizationUnit::TYPE_DEPARTMENT, App\Models\OrganizationUnit::TYPE_SERVICE, App\Models\OrganizationUnit::TYPE_SUB_SERVICE]))
                <div>
                    <flux:select wire:model="servicePontNoeud" :label="__('Service réel lié')">
                        <flux:select.option value="">{{ $creationAuto ? __('— Créer automatiquement —') : __('— Aucun —') }}</flux:select.option>
                        @foreach ($this->servicesReels as $s)
                            <flux:select.option value="{{ $s->id }}">{{ $s->nom }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:text class="mt-1 text-xs text-zinc-500">
                        @if ($creationAuto)
                            {{ __('Laissez "Créer automatiquement" pour que ce service reçoive des courriers (routage DGA, règles, recherche). Nom et responsable seront repris ; choisissez un service existant seulement pour le regrouper avec lui.') }}
                        @else
                            {{ __('Relie cette entité à un service réel — nécessaire pour qu\'elle soit sélectionnable lors d\'un transfert de courrier.') }}
                        @endif
                    </flux:text>
                </div>
            @endif

            <flux:textarea wire:model="descriptionNoeud" :label="__('Description')" rows="2" />

            <div class="flex justify-end gap-2 border-t border-brand-border pt-4 dark:border-zinc-700">
                <flux:modal.close><flux:button variant="ghost">{{ __('Annuler') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">{{ $noeudEnEditionId ? __('Enregistrer') : __('Créer') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- ===== Modale "Déplacer" ===== --}}
    <flux:modal name="organisation-deplacement" class="w-full max-w-lg">
        <div class="space-y-6">
            <flux:heading level="2">{{ __('Déplacer l\'entité') }}</flux:heading>
            <flux:select wire:model="nouveauParentId" :label="__('Nouveau parent')" :placeholder="__('— Racine —')">
                <flux:select.option value="">{{ __('— Racine —') }}</flux:select.option>
                @foreach ($this->tousLesNoeuds as $candidat)
                    @if ($candidat->id !== $noeudADeplacerId)
                        <flux:select.option value="{{ $candidat->id }}">{{ $candidat->cheminComplet($this->tousLesNoeuds) }}</flux:select.option>
                    @endif
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2 border-t border-brand-border pt-4 dark:border-zinc-700">
                <flux:modal.close><flux:button variant="ghost">{{ __('Annuler') }}</flux:button></flux:modal.close>
                <flux:button variant="primary" icon="check" wire:click="deplacerNoeud">{{ __('Déplacer') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
