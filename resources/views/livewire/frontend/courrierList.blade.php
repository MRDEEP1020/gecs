{{-- Nouvelle maquette "Tous les courriers" fournie par l'utilisateur
     (2026-09-18) — remplace ENTIÈREMENT l'ancienne mise en page ("replace
     ALL with it"). Clarifié avec l'utilisateur (AskUserQuestion) sur deux
     points où la maquette ne correspondait à aucune donnée réelle :
     (1) les 4 cartes ("Total/En traitement/Terminés/En erreur") sont
     adaptées aux statuts RÉELS du schéma (voir CourrierList::statistiques()) ;
     (2) le filtre "Fonctionnalité" de la maquette ne correspond à aucun
     champ existant — remplacé par "Type de document", un filtre réel déjà
     construit. Boutons "Importer"/"Exporter" : aucune fonctionnalité
     réelle derrière à ce jour — désactivés plutôt qu'omis ou fabriqués
     (même principe que les entrées "Bientôt" de la sidebar). --}}
{{-- x-data partagé pour TOUTE la section filtres (bouton "Filtres" du
     bandeau supérieur ET lien "Filtres avancés" à côté de la recherche
     doivent piloter le MÊME panneau replié/déplié en dessous — deux scopes
     Alpine séparés, essayé d'abord, ne communiquaient jamais entre eux :
     cliquer sur "Filtres" en haut ne dépliait rien). --}}
<section
    class="w-full"
    x-data="{ avance: @js($typeDocument !== '' || $statut !== '' || $priorite !== '' || $periode !== '' || $objet !== '' || $expediteur !== '' || $contenu !== '' || $confidentialite !== '' || $serviceId !== null || $dateDebut !== '' || $dateFin !== '' || $sens !== '') }"
>
    {{-- document-preview.js définit window.DocumentPreview, utilisé par le
         panneau "Aperçu du courrier" plus bas — jamais chargé sur cette
         page jusqu'ici (copié depuis registrationForm.blade.php sans son
         propre @vite), d'où "Cannot read properties of undefined (reading
         'obtenir')" constaté par l'utilisateur en conditions réelles. --}}
    @assets
        @vite('resources/js/document-preview.js')
    @endassets

    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate></flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Courriers') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Tous les courriers') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-blue text-white">
                <flux:icon.envelope class="size-5" />
            </div>
            <div>
                <flux:heading level="1">{{ __('Tous les courriers') }}</flux:heading>
                <flux:subheading>{{ __('Consultez, gérez et suivez tous les courriers de votre organisation.') }}</flux:subheading>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @can('create', App\Models\Courrier::class)
                <flux:button variant="primary" icon="plus" :href="route('courriers.nouveau')" wire:navigate>
                    {{ __('Nouveau courrier') }}
                </flux:button>
            @endcan
            {{-- "Importer"/"Exporter" (maquette) : aucune fonctionnalité
                 réelle derrière ces boutons à ce jour — désactivés avec une
                 infobulle plutôt qu'omis (même principe que la sidebar). --}}
            <flux:tooltip content="{{ __('Bientôt disponible') }}">
                <flux:button variant="outline" icon="arrow-down-tray" disabled>{{ __('Importer') }}</flux:button>
            </flux:tooltip>
            <flux:tooltip content="{{ __('Bientôt disponible') }}">
                <flux:button variant="outline" icon="arrow-up-tray" disabled>{{ __('Exporter') }}</flux:button>
            </flux:tooltip>
            <flux:button variant="outline" icon="adjustments-horizontal" x-on:click="avance = !avance">
                {{ __('Filtres') }}
            </flux:button>
        </div>
    </div>

    {{-- Panneau "Aperçu du courrier" ouvert : TOUT le contenu de gauche
         (cartes statistiques, recherche/filtres, tableau) passe en 2
         colonnes face au panneau, qui démarre donc juste sous la ligne de
         boutons et court sur toute la hauteur — demande explicite de
         l'utilisateur ("i want the panel to start under the buttons and it
         should be side by side with the research form and the table and
         the kpi"), pas seulement à côté du tableau comme avant. --}}
    <div class="mt-6 grid gap-6 {{ $this->courrierApercu ? 'lg:grid-cols-[minmax(0,1fr)_380px]' : '' }}">
        <div class="space-y-6">
    {{-- 4 cartes — voir CourrierList::statistiques() pour le détail des
         statuts regroupés sous chaque libellé. Format compact (icône +
         libellé/chiffre groupés sur une seule ligne, tendance en dessous)
         — demande explicite de l'utilisateur ("reduce the kpi and arange
         them to well present those data inside it") : depuis que ces
         cartes partagent la colonne de gauche avec le panneau "Aperçu"
         (voir CHANGELOG-AGENT.md, entrée précédente), l'ancien format
         (icône+libellé sur une ligne, gros chiffre en dessous, 2 lignes de
         hauteur) tenait moins bien dans cette largeur réduite. --}}
    {{-- 2026-09-23, demande explicite de l'utilisateur ("same thing for all
         the pages, each card should be a permission") : CHAQUE carte a
         désormais sa propre clé (courriers.voir_carte_total/en_traitement/
         termines/en_erreur — plus d'ancienne courriers.voir_statistiques
         partagée). $this->statistiques ne contient que les clés autorisées
         (voir CourrierList::statistiques()) — la rangée entière ne
         s'affiche que si au moins une carte l'est. --}}
    @if ($this->statistiques !== [])
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['cle' => 'total', 'label' => __('Total courriers'), 'icon' => 'inbox', 'couleur' => 'blue'],
            ['cle' => 'enTraitement', 'label' => __('En traitement'), 'icon' => 'arrow-path', 'couleur' => 'warning'],
            ['cle' => 'termines', 'label' => __('Terminés'), 'icon' => 'check-circle', 'couleur' => 'success'],
            ['cle' => 'enErreur', 'label' => __('En erreur'), 'icon' => 'exclamation-triangle', 'couleur' => 'danger'],
        ] as $carte)
            @if (isset($this->statistiques[$carte['cle']]))
                @php $stat = $this->statistiques[$carte['cle']]; @endphp
                <div class="rounded-xl border border-brand-border bg-white p-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center gap-2.5">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-{{ $carte['couleur'] }} text-white">
                            <flux:icon :icon="$carte['icon']" class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <flux:text class="truncate text-xs text-zinc-500">{{ $carte['label'] }}</flux:text>
                            <div class="text-xl font-semibold leading-tight">{{ $stat['total'] }}</div>
                        </div>
                    </div>
                    <flux:text class="mt-2 flex items-center gap-1 text-xs">
                        @if ($stat['variation'] === null)
                            <span class="truncate text-zinc-400">{{ __('Pas de donnée le mois précédent') }}</span>
                        @elseif ($stat['variation'] > 0)
                            <flux:icon.arrow-trending-up class="size-3.5 shrink-0 text-brand-success" />
                            <span class="truncate text-brand-success">+{{ $stat['variation'] }}% {{ __('vs mois précédent') }}</span>
                        @elseif ($stat['variation'] < 0)
                            <flux:icon.arrow-trending-down class="size-3.5 shrink-0 text-brand-danger" />
                            <span class="truncate text-brand-danger">{{ $stat['variation'] }}% {{ __('vs mois précédent') }}</span>
                        @else
                            <span class="truncate text-zinc-400">{{ __('Stable vs mois précédent') }}</span>
                        @endif
                    </flux:text>
                </div>
            @endif
        @endforeach
    </div>
    @endif

    {{-- Résultat d'une recherche lancée depuis la barre de navigation
         (?q=..., voir DECISIONS.md "Navigation (navbar + sidebar)") —
         distincte des filtres avancés ci-dessous. --}}
    @if ($q !== '')
        <flux:callout icon="magnifying-glass">
            <flux:callout.text>{{ __('Résultats pour « :q »', ['q' => $q]) }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" variant="ghost" wire:click="$set('q', '')">{{ __('Effacer') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <div class="rounded-2xl border border-brand-border bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center gap-3">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.400ms="numero" icon="magnifying-glass" :placeholder="__('Rechercher un courrier, un expéditeur, une référence…')" />
            </div>
            <flux:button variant="ghost" size="sm" icon="adjustments-horizontal" x-on:click="avance = !avance">
                {{ __('Filtres avancés') }}
            </flux:button>
        </div>

        {{-- 2026-09-23 (demande explicite de l'utilisateur : "mask the
             research inputs only leave the long one and bring the tables
             up") : SEULE la barre de recherche reste visible ; TOUS les
             filtres (y compris ces 4 listes, auparavant toujours affichées)
             sont repliés derrière "Filtres" / "Filtres avancés" — le
             tableau remonte d'autant. Déplié automatiquement si un filtre
             est déjà actif (x-data de la section). --}}
        <div x-show="avance" x-cloak>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:select wire:model.live="typeDocument" :label="__('Fonctionnalité')">
                <flux:select.option value="">{{ __('Toutes') }}</flux:select.option>
                @foreach (\App\Livewire\Backend\Forms\CourrierForm::typesDocument() as $type)
                    <flux:select.option value="{{ $type }}">{{ $type }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statut" :label="__('Statut')">
                <flux:select.option value="">{{ __('Tous les statuts') }}</flux:select.option>
                @foreach (array_keys(\App\Models\Courrier::LIBELLES_STATUT) as $valeurStatut)
                    <flux:select.option value="{{ $valeurStatut }}">{{ \App\Models\Courrier::libelleStatut($valeurStatut) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="priorite" :label="__('Priorité')">
                <flux:select.option value="">{{ __('Toutes priorités') }}</flux:select.option>
                <flux:select.option value="basse">{{ __('Basse') }}</flux:select.option>
                <flux:select.option value="normale">{{ __('Normale') }}</flux:select.option>
                <flux:select.option value="haute">{{ __('Haute') }}</flux:select.option>
                <flux:select.option value="urgente">{{ __('Urgente') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="periode" :label="__('Période')">
                <flux:select.option value="">{{ __('Toute période') }}</flux:select.option>
                <flux:select.option value="aujourd_hui">{{ __('Aujourd\'hui') }}</flux:select.option>
                <flux:select.option value="cette_semaine">{{ __('Cette semaine') }}</flux:select.option>
                <flux:select.option value="ce_mois">{{ __('Ce mois') }}</flux:select.option>
                <flux:select.option value="cette_annee">{{ __('Cette année') }}</flux:select.option>
            </flux:select>
        </div>

        {{-- Recherche avancée repliée par défaut (demande explicite de
             l'utilisateur, 2026-09-14) — dépliée automatiquement si un filtre
             avancé est déjà actif (ex. lien partagé/mis en favori). --}}
        <div>
            <flux:separator class="my-4" />
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:input wire:model.live.debounce.400ms="objet" :label="__('Objet')" />
                <flux:input wire:model.live.debounce.400ms="expediteur" :label="__('Expéditeur (nom ou organisation)')" />
                {{-- courriers.voir_texte_ocr (2026-09-23), aussi ignoré côté serveur. --}}
                @if ($this->peutRechercherContenu)
                    <flux:input
                        wire:model.live.debounce.400ms="contenu"
                        :label="__('Contenu du document (texte scanné)')"
                        :description="__('Un mot ou un montant vu sur le document, même si vous ne vous souvenez plus de l\'expéditeur.')"
                        placeholder="{{ __('ex. capital, un montant, un nom cité...') }}"
                    />
                @endif

                <flux:select wire:model.live="confidentialite" :label="__('Confidentialité')" placeholder="{{ __('Tous les niveaux') }}">
                    @foreach (range(1, \App\Models\User::niveauConfidentialiteMax()) as $niveau)
                        <flux:select.option value="{{ $niveau }}">{{ __('Niveau :n', ['n' => $niveau]) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="serviceId" :label="__('Service')" placeholder="{{ __('Tous les services') }}">
                    @foreach ($this->services as $service)
                        <flux:select.option value="{{ $service->id }}">{{ $service->nom }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input type="date" wire:model.live="dateDebut" :label="__('Date de mouvement — du')" />
                <flux:input type="date" wire:model.live="dateFin" :label="__('Date de mouvement — au')" />

                <flux:select wire:model.live="sens" :label="__('Sens')" placeholder="{{ __('Entrant et sortant') }}">
                    <flux:select.option value="entrant">{{ __('Entrant') }}</flux:select.option>
                    <flux:select.option value="sortant">{{ __('Sortant') }}</flux:select.option>
                </flux:select>
            </div>
        </div>

        <div class="mt-4">
            <flux:button variant="ghost" size="sm" wire:click="reinitialiser">{{ __('Réinitialiser les filtres') }}</flux:button>
        </div>
        </div>
    </div>

    {{-- Transfert en masse (2026-09-22, demande explicite de l'utilisateur :
         "no button to transfer courier in bulk why") — bouton visible pour
         n'importe quel profil ayant au moins un privilège de transfert
         (jamais un rôle en dur, voir CourrierList::peutTransfererAuMoinsUnCourrier()) ;
         l'autorisation réelle reste vérifiée courrier par courrier côté
         serveur, ceci n'évite qu'un bouton qui échouerait silencieusement
         pour qui n'a aucun privilège de transfert. Même modale que
         MesCourriers, reciblée sur cette page. --}}
    {{-- Barre d'actions de sélection (2026-09-23, demande explicite de
         l'utilisateur : "make the two button transfer and classe appear
         only when we have selected") — n'apparaît qu'avec au moins un
         courrier coché (cases en wire:model.live, donc à jour à chaque
         clic) ; chaque bouton reste en plus soumis à son propre droit. --}}
    @if (! empty($selectionnes) && ($this->peutTransfererAuMoinsUnCourrier || $this->peutClasserAuMoinsUnCourrier))
        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-brand-border bg-brand-blue-pale px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
            <flux:text class="mr-auto text-sm font-medium text-brand-blue">{{ __(':n courrier(s) sélectionné(s)', ['n' => count($selectionnes)]) }}</flux:text>
            @if ($this->peutTransfererAuMoinsUnCourrier)
                <flux:modal.trigger name="transferer-selection-liste-modal">
                    <flux:button size="sm" variant="primary" icon="arrow-up-tray">{{ __('Transférer la sélection') }}</flux:button>
                </flux:modal.trigger>
            @endif
            @if ($this->peutClasserAuMoinsUnCourrier)
                <flux:modal.trigger name="classer-selection-liste-modal">
                    <flux:button size="sm" variant="outline" icon="folder">{{ __('Classer la sélection') }}</flux:button>
                </flux:modal.trigger>
            @endif
            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="$set('selectionnes', [])">{{ __('Désélectionner') }}</flux:button>
        </div>
    @endif

    @if ($this->peutTransfererAuMoinsUnCourrier)
        <flux:modal name="transferer-selection-liste-modal" class="w-full max-w-sm">
            <form wire:submit="transfererSelection" class="space-y-4">
                <flux:heading size="lg">{{ __('Transférer à') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __(':n courrier(s) sélectionné(s).', ['n' => count($selectionnes)]) }}</flux:text>

                @if ($this->destinatairesTransfert->isEmpty())
                    <flux:text class="text-zinc-500">{{ __('Aucun destinataire autorisé pour votre compte. Contactez un administrateur.') }}</flux:text>
                @else
                    <flux:radio.group wire:model="destinataireChoisi">
                        @foreach ($this->destinatairesTransfert as $destinataire)
                            <flux:radio value="{{ $destinataire->id }}" label="{{ $destinataire->name }}" />
                        @endforeach
                    </flux:radio.group>
                    @error('destinataireChoisi') <flux:text class="mt-1 text-sm text-brand-danger">{{ $message }}</flux:text> @enderror
                @endif

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Annuler') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" :disabled="$this->destinatairesTransfert->isEmpty()">{{ __('Confirmer le transfert') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    {{-- Module 3/9 — classement en masse (2026-09-22, demande explicite de
         l'utilisateur, "les trois" points d'entrée) : bouton visible dès que
         l'utilisateur a accès à au moins un dossier de classement (pas de
         privilège fixe, voir CourrierList::peutClasserAuMoinsUnCourrier()) ;
         l'autorisation réelle reste vérifiée courrier par courrier ET
         dossier par dossier côté serveur. --}}
    @if ($this->peutClasserAuMoinsUnCourrier)
        <flux:modal name="classer-selection-liste-modal" class="w-full max-w-sm">
            <form wire:submit="classerSelection" class="space-y-4">
                <flux:heading size="lg">{{ __('Classer dans un dossier') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __(':n courrier(s) sélectionné(s).', ['n' => count($selectionnes)]) }}</flux:text>

                <flux:select wire:model="dossierChoisi" :label="__('Dossier')" :placeholder="__('— Choisir un dossier —')">
                    @foreach ($this->dossiersAccessibles as $dossier)
                        <flux:select.option value="{{ $dossier->id }}">{{ $dossier->nom }}</flux:select.option>
                    @endforeach
                </flux:select>
                @error('dossierChoisi') <flux:text class="mt-1 text-sm text-brand-danger">{{ $message }}</flux:text> @enderror

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Annuler') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Confirmer le classement') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    <div>
        <flux:heading level="2" class="text-base">{{ __('Liste des courriers') }} ({{ $this->resultats->total() }})</flux:heading>

        <div class="mt-3">
            @if ($this->resultats->isEmpty())
                <flux:text class="text-zinc-500">{{ __('Aucun courrier ne correspond à ces critères.') }}</flux:text>
            @else
                <div class="overflow-x-auto rounded-2xl border border-brand-border bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="w-full text-sm">
                        <thead class="bg-brand-blue-pale text-left text-sm font-medium text-zinc-600 dark:bg-zinc-800">
                            <tr>
                                <th class="w-4 py-3 pl-4 pr-2" wire:click.stop>
                                    <input
                                        type="checkbox"
                                        class="rounded border-zinc-300"
                                        wire:click="basculerSelectionPage"
                                        @checked($this->resultats->isNotEmpty() && $this->resultats->pluck('id')->diff($selectionnes)->isEmpty())
                                    />
                                </th>
                                <th class="py-3 pr-3" wire:click.stop="trierPar('numero_reference')">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-zinc-800 dark:hover:text-zinc-300">
                                        {{ __('N° Courrier') }}
                                        <flux:icon.chevron-up-down class="size-3.5" />
                                    </button>
                                </th>
                                <th class="py-3 pr-3">{{ __('Expéditeur') }}</th>
                                <th class="py-3 pr-3">{{ __('Destinataire') }}</th>
                                <th class="py-3 pr-3">{{ __('Objet') }}</th>
                                @if ($this->contenu !== '' && $this->peutRechercherContenu)
                                    <th class="py-3 pr-3">{{ __('Extrait du document') }}</th>
                                @endif
                                <th class="py-3 pr-3">{{ __('Date d\'envoi') }}</th>
                                <th class="py-3 pr-3">{{ __('Statut') }}</th>
                                <th class="py-3 pr-4">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($this->resultats as $courrier)
                                <tr
                                    wire:key="ligne-{{ $courrier->id }}"
                                    wire:click="ouvrirApercu({{ $courrier->id }})"
                                    class="cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                    @class(['bg-brand-blue/5' => $this->courrierApercu?->id === $courrier->id])
                                >
                                    <td class="py-3 pl-4 pr-2" wire:click.stop>
                                        <input type="checkbox" class="rounded border-zinc-300" wire:model.live="selectionnes" value="{{ $courrier->id }}" />
                                    </td>
                                    <td class="py-3 pr-3 font-medium text-brand-blue">
                                        {{ $courrier->numero_reference }}
                                    </td>
                                    <td class="py-3 pr-3 text-zinc-600">{{ $courrier->expediteur_organisation ?: $courrier->expediteur_nom ?: __('—') }}</td>
                                    <td class="py-3 pr-3 text-zinc-600">{{ $courrier->destinataire ? Str::limit($courrier->destinataire, 40) : ($courrier->service?->nom ?? __('—')) }}</td>
                                    <td class="py-3 pr-3">
                                        {{ Str::limit($courrier->objet, 50) }}
                                        @if ($courrier->confidentialite > 1)
                                            {{-- Navy, pas teal (2026-09-17, système de couleurs GEC —
                                                 "confidentiel" n'a pas de couleur dédiée dans le
                                                 système fourni, clarifié avec l'utilisateur : rejoint
                                                 Navy, une caractéristique structurelle/d'accès, pas un
                                                 statut de workflow). Span habillé directement (pas
                                                 flux:badge color=, qui n'accepte pas nos hex exacts).
                                                 Niveau NUMÉRIQUE (2026-09-21, "numbers ... not
                                                 confidential or whatever", puis "THE LABEL SHOULD
                                                 BE NIVEAU 1 OR LEVEL 1"). --}}
                                            <span class="ml-1 inline-flex items-center rounded-full bg-brand-navy px-2 py-0.5 text-xs font-medium text-white">
                                                {{ __('Niveau :n', ['n' => $courrier->confidentialite]) }}
                                            </span>
                                        @endif
                                    </td>
                                    @if ($this->contenu !== '' && $this->peutRechercherContenu)
                                        <td class="py-3 pr-3 text-zinc-500 italic">{{ $this->extraitTexteOcr($courrier) ?? __('—') }}</td>
                                    @endif
                                    <td class="py-3 pr-3 text-zinc-600">{{ $courrier->date_mouvement->format('d/m/Y') }}</td>
                                    <td class="py-3 pr-3">
                                        <x-statut-badge :statut="$courrier->statut" />
                                    </td>
                                    <td class="py-3 pr-4" wire:click.stop>
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" square :aria-label="__('Actions')" />
                                            <flux:menu>
                                                <flux:menu.item icon="eye" :href="route('courriers.show', $courrier->id)" wire:navigate>
                                                    {{ __('Voir') }}
                                                </flux:menu.item>
                                                @can('update', $courrier)
                                                    <flux:menu.item icon="pencil-square" :href="route('courriers.modifier', $courrier->id)" wire:navigate>
                                                        {{ __('Modifier') }}
                                                    </flux:menu.item>
                                                @endcan
                                            </flux:menu>
                                        </flux:dropdown>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $this->resultats->links() }}
                </div>
            @endif
        </div>
    </div>
        </div>
        {{-- ^ ferme "Liste des courriers" (K) puis la colonne de gauche
             "space-y-6" (B) — le panneau ci-dessous devient un vrai sibling
             de cette colonne dans la grille A, pas un enfant imbriqué. --}}

        @if ($this->courrierApercu)
            @php $courrier = $this->courrierApercu; @endphp
            {{-- Même mécanisme pdf.js/document-preview.js que le panneau
                 "Aperçu du document" du formulaire d'enregistrement
                 (registrationForm.blade.php) — réutilisé tel quel, pointant
                 vers courriers.document.apercu (déjà existant) au lieu de
                 brouillons.apercu. wire:key sur l'id du courrier : changer
                 de courrier doit recharger un NOUVEAU document, jamais
                 réutiliser l'instance pdf.js du précédent. --}}
            <div wire:key="panneau-apercu-{{ $courrier->id }}" class="self-start rounded-2xl border border-brand-border bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                {{-- Panneau désormais TOUJOURS affiché (demande explicite de
                     l'utilisateur, 2026-09-18 : "those cards panel should
                     always be shown not mask") — plus de bouton "Fermer"/X,
                     qui n'aurait plus de sens : il n'y a plus d'état "masqué"
                     vers lequel revenir. --}}
                <div class="flex items-center gap-2 border-b border-brand-border p-4 dark:border-zinc-700">
                    <flux:icon.information-circle class="size-4 text-brand-blue" />
                    <flux:heading level="3">{{ __('Aperçu du courrier') }}</flux:heading>
                </div>

                <div class="p-4">
                    @if ($courrier->fichier_path)
                        <div
                            wire:ignore
                            x-data="{
                                erreur: null,
                                zoom: 100,
                                rechercheOuverte: false,
                                requeteRecherche: '',
                                nbResultats: 0,
                                url: @js(route('courriers.document.apercu', $courrier)),
                                instance() {
                                    const apercu = window.DocumentPreview.obtenir(this.url + ':liste');
                                    apercu.boite = this.$refs.corps;
                                    apercu.conteneur = this.$refs.conteneurPdf;

                                    return apercu;
                                },
                                async init() {
                                    try {
                                        const apercu = this.instance();
                                        await apercu.charger(this.url, apercu.boite, apercu.conteneur);
                                        this.zoom = Math.round((apercu.echelle / apercu.echelleBase) * 100);
                                    } catch (e) {
                                        console.error('[aperçu courrier liste]', e);
                                        this.erreur = e.message ?? String(e);
                                    }
                                },
                                async zoomer(nouveauZoom) {
                                    this.zoom = nouveauZoom;

                                    try {
                                        await this.instance().zoomer(this.zoom);
                                    } catch (e) {
                                        console.error('[aperçu courrier liste] zoom', e);
                                        this.erreur = e.message ?? String(e);
                                    }
                                },
                                zoomIn() { this.zoomer(Math.min(this.zoom + 25, 200)); },
                                zoomOut() { this.zoomer(Math.max(this.zoom - 25, 50)); },
                                basculerRecherche() {
                                    this.rechercheOuverte = ! this.rechercheOuverte;

                                    if (! this.rechercheOuverte) {
                                        this.requeteRecherche = '';
                                        this.rechercher();
                                    }
                                },
                                rechercher() {
                                    this.nbResultats = this.instance().rechercher(this.requeteRecherche);
                                },
                                pleinEcran() { this.$refs.corps.requestFullscreen?.(); },
                            }"
                            x-init="init()"
                        >
                            <div class="mb-2 flex items-center justify-between gap-1">
                                <flux:button size="sm" variant="ghost" icon="magnifying-glass" x-on:click="basculerRecherche()" :aria-label="__('Rechercher dans le document')" />
                                <div class="flex items-center gap-1">
                                    <flux:button size="sm" variant="ghost" icon="minus" x-on:click="zoomOut()" :aria-label="__('Zoom -')" />
                                    <span class="w-10 text-center text-xs text-zinc-500" x-text="zoom + '%'"></span>
                                    <flux:button size="sm" variant="ghost" icon="plus" x-on:click="zoomIn()" :aria-label="__('Zoom +')" />
                                    <flux:button size="sm" variant="ghost" icon="arrows-pointing-out" x-on:click="pleinEcran()" :aria-label="__('Plein écran')" />
                                    @can('telecharger', $courrier)
                                        <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('courriers.document', $courrier)" :aria-label="__('Télécharger')" />
                                    @endcan
                                </div>
                            </div>

                            <div x-show="rechercheOuverte" x-cloak class="mb-2 flex items-center gap-2">
                                <flux:input size="sm" x-model="requeteRecherche" x-on:input.debounce.300ms="rechercher()" placeholder="{{ __('Rechercher…') }}" />
                                <flux:text class="shrink-0 text-xs text-zinc-500" x-show="requeteRecherche">
                                    <span x-text="nbResultats"></span> {{ __('résultat(s)') }}
                                </flux:text>
                            </div>

                            <div x-ref="corps" class="flex h-64 w-full items-center justify-center overflow-auto rounded-lg border border-brand-border bg-white dark:border-zinc-700">
                                <p x-show="erreur" x-text="erreur" class="p-2 text-sm text-brand-danger"></p>
                                <div x-ref="conteneurPdf"></div>
                            </div>
                        </div>
                    @else
                        <div class="flex h-40 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-brand-border bg-brand-surface-soft text-center">
                            <flux:icon.document class="size-8 text-brand-text-muted" />
                            <span class="text-sm text-brand-text-secondary">{{ __('Aucun document numérisé pour ce courrier.') }}</span>
                        </div>
                    @endif

                    <flux:separator class="my-4" text="{{ __('Informations du courrier') }}" />

                    <dl class="space-y-3 text-sm">
                        <div class="flex items-start gap-2">
                            <flux:icon.user class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                            <div>
                                <dt class="text-zinc-500">{{ __('Expéditeur') }}</dt>
                                <dd class="font-medium">{{ $courrier->expediteur_organisation ?: $courrier->expediteur_nom ?: __('—') }}</dd>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <flux:icon.users class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                            <div>
                                <dt class="text-zinc-500">{{ __('Destinataire') }}</dt>
                                <dd class="font-medium">{{ $courrier->destinataire ?: __('—') }}</dd>
                            </div>
                        </div>
                        <div>
                            <dt class="text-zinc-500">{{ __('Objet') }}</dt>
                            <dd class="font-medium">{{ $courrier->objet }}</dd>
                        </div>
                        <div class="flex items-start gap-2">
                            <flux:icon.calendar class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                            <div>
                                <dt class="text-zinc-500">{{ __('Date d\'envoi') }}</dt>
                                <dd class="font-medium">{{ $courrier->date_mouvement->format('d/m/Y') }}</dd>
                            </div>
                        </div>
                        <div>
                            <dt class="text-zinc-500">{{ __('Statut') }}</dt>
                            <dd class="mt-1"><x-statut-badge :statut="$courrier->statut" /></dd>
                        </div>
                        @if ($courrier->piecesJointes->isNotEmpty() && auth()->user()->can('voirPiecesJointes', $courrier))
                            <div class="flex items-start gap-2">
                                <flux:icon.paper-clip class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                                <div>
                                    <dt class="text-zinc-500">{{ __('Pièce(s) jointe(s)') }}</dt>
                                    {{-- Nom seul (sans lien) sans courriers.telecharger (2026-09-23). --}}
                                    @php $peutTelecharger = auth()->user()->can('telecharger', $courrier); @endphp
                                    @foreach ($courrier->piecesJointes as $pieceJointe)
                                        <dd class="font-medium">
                                            @if ($peutTelecharger)
                                                <flux:link :href="route('pieces-jointes.telecharger', $pieceJointe)">{{ $pieceJointe->nom_original }}</flux:link>
                                            @else
                                                {{ $pieceJointe->nom_original }}
                                            @endif
                                        </dd>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </dl>

                    <div class="mt-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            @can('update', $courrier)
                                <flux:button size="sm" variant="primary" icon="pencil-square" :href="route('courriers.modifier', $courrier->id)" wire:navigate>
                                    {{ __('Éditer') }}
                                </flux:button>
                            @endcan
                            {{-- "Supprimer" (2026-09-23) : courriers.supprimer
                                 (Administrateur par défaut), suppression
                                 LOGIQUE avec motif obligatoire tracé dans
                                 l'historique, jamais un courrier archivé
                                 (Règle n°5, CourrierPolicy::delete()). --}}
                            @can('delete', $courrier)
                                <flux:modal.trigger name="courrier-suppression">
                                    <flux:button size="sm" variant="danger" icon="trash">{{ __('Supprimer') }}</flux:button>
                                </flux:modal.trigger>
                            @endcan
                        </div>
                    </div>

                    @can('delete', $courrier)
                        <flux:modal name="courrier-suppression" class="md:w-md">
                            <div class="space-y-4">
                                <flux:heading size="lg">{{ __('Supprimer le courrier :ref ?', ['ref' => $courrier->numero_reference]) }}</flux:heading>
                                <flux:text>{{ __('Le courrier disparaîtra des listes et des recherches. Il reste conservé en base avec tout son historique (suppression logique) ; le motif est enregistré dans l\'historique.') }}</flux:text>
                                <flux:textarea wire:model="motifSuppression" :label="__('Motif de la suppression')" rows="3" required />
                                <div class="flex justify-end gap-2">
                                    <flux:modal.close>
                                        <flux:button variant="ghost">{{ __('Annuler') }}</flux:button>
                                    </flux:modal.close>
                                    <flux:button variant="danger" icon="trash" wire:click="supprimerCourrier({{ $courrier->id }})">{{ __('Supprimer') }}</flux:button>
                                </div>
                            </div>
                        </flux:modal>
                    @endcan
                </div>
            </div>
        @endif
    </div>
</section>
