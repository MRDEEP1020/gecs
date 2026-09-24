{{-- Plus de plafond max-w-6xl (2026-09-18, demande explicite de
     l'utilisateur — "use the complete space on that dashboard main") : le
     tableau de bord doit occuper toute la largeur disponible de la zone
     de contenu principal, pas seulement une colonne centrale étroite. --}}
<section class="w-full">
    {{-- Bannière de bienvenue en Light Blue (2026-09-17, système de
         couleurs GEC, --color-brand-blue-pale = #EFF6FF). --}}
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-brand-blue-pale p-4">
        <div class="flex items-center gap-3">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-blue text-white">
                <flux:icon.envelope class="size-5" />
            </div>
            <div>
                <flux:heading level="1">{{ __('Bonjour :nom', ['nom' => explode(' ', auth()->user()->name)[0]]) }}</flux:heading>
                <flux:subheading>
                    {{ auth()->user()->profil?->nom ?? __('—') }} — {{ __('Bienvenue sur votre espace GEC') }}
                </flux:subheading>
            </div>
        </div>
        <flux:text class="text-zinc-500">{{ now()->translatedFormat('l j F Y') }}</flux:text>
    </div>

    {{-- Convention de mise en page du panneau (voir memory
         apercu_panel_layout_convention/apercu_panel_sticky_pages,
         appliquée ici le 2026-09-18 sur demande explicite de
         l'utilisateur avec capture d'écran — "not the same thing the
         panel should be side by side with the kpi too but the welcome
         message should be full width") : SEULE la bannière de bienvenue
         ci-dessus reste hors grille — KPI, actions rapides ET "Derniers
         courriers enregistrés" partagent désormais la même colonne de
         gauche, avec le panneau latéral (Tâches du jour/Notifications/
         Calendrier) comme sibling sur toute cette hauteur. --}}
    {{-- Colonne de droite en largeur FIXE (320px), pas 1/3 de la page
         (2026-09-18, comparaison avec la maquette GPT du 2026-09-16 —
         "look at your design/style layout ... it should resemble exactly",
         scope confirmé par l'utilisateur : uniquement la largeur du
         panneau, pas les cartes KPI ni la sidebar) : même motif que
         editForm/workflowQueue/showCourrier (`lg:grid-cols-[minmax(0,1fr)_360px]`),
         un peu plus étroit ici (320px) pour rester proche de la maquette. --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-6">
    {{-- 4 cartes de statistiques — même périmètre de visibilité que
         CourrierList::resultats() pour chaque profil (voir
         Dashboard::courriersVisibles()) : ce ne sont pas des chiffres
         globaux à l'échelle de l'entreprise, seulement ce que CET
         utilisateur pourrait ouvrir individuellement. Couleurs = système
         de couleurs GEC (2026-09-17, voir DECISIONS.md "GEC Master Color
         System") : brand-blue (entrant), brand-success (sortant),
         brand-warning (en attente), brand-urgent — alias de brand-danger
         (rouge), clarifié avec l'utilisateur le 2026-09-17 car "Courriers
         urgents" n'a pas de couleur dédiée dans le système fourni. Cartes
         bg-white + shadow-sm sur fond de page --color-brand-surface
         (#F5F8FC, distinct du blanc des cartes). --}}
    {{-- Cartes KPI compactées (2026-09-18, demande explicite de
         l'utilisateur — "the kpis reduce it") : même traitement déjà
         appliqué aux cartes KPI de courrierList.blade.php — icône plus
         petite (size-9, rounded-lg au lieu de rounded-full), libellé +
         chiffre regroupés sur une même ligne à côté de l'icône plutôt que
         chiffre en dessous, carte moins haute (p-3, rounded-xl). --}}
    {{-- 2026-09-23 : 6 cartes (ajout "En retard" + "Délai moyen de
         traitement", PRD §3.10) — 2 rangées de 3 plutôt qu'une rangée de 6,
         trop étroite à côté du panneau latéral de 320px. --}}
    {{-- 2026-09-23, demande explicite de l'utilisateur ("on tableau de
         board all kpi and card there should be permission") : CHAQUE carte
         a désormais sa propre clé (dashboard.courrier_entrant, ..._sortant,
         .en_attente, .urgents, .en_retard, .delai_moyen — plus d'ancienne
         dashboard.statistiques partagée). Chaque computed retourne `null`
         sans son privilège ; la rangée entière ne s'affiche que si au moins
         une carte l'est. --}}
    @if ($this->courrierEntrantAujourdhui !== null || $this->courrierSortantAujourdhui !== null || $this->enAttenteDeTraitement !== null || $this->courriersUrgents !== null || $this->courriersEnRetard !== null || $this->delaiMoyen !== null)
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @if ($this->courrierEntrantAujourdhui !== null)
        <div class="rounded-xl border border-brand-border bg-white p-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center gap-2.5">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-blue/10 text-brand-blue">
                    <flux:icon.inbox-arrow-down class="size-4" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text class="truncate text-xs text-zinc-500">{{ __('Courrier entrant') }}</flux:text>
                    <div class="text-xl font-semibold leading-tight">{{ $this->courrierEntrantAujourdhui['total'] }}</div>
                </div>
            </div>
            <flux:text class="mt-2 flex items-center gap-1 text-xs">
                @if ($this->courrierEntrantAujourdhui['delta'] > 0)
                    <flux:icon.arrow-trending-up class="size-3.5 text-brand-success" />
                    <span class="truncate text-brand-success">{{ __(':n de plus qu\'hier', ['n' => $this->courrierEntrantAujourdhui['delta']]) }}</span>
                @elseif ($this->courrierEntrantAujourdhui['delta'] < 0)
                    <flux:icon.arrow-trending-down class="size-3.5 text-zinc-400" />
                    <span class="truncate text-zinc-500">{{ __(':n de moins qu\'hier', ['n' => abs($this->courrierEntrantAujourdhui['delta'])]) }}</span>
                @else
                    <span class="truncate text-zinc-500">{{ __('Comme hier') }}</span>
                @endif
            </flux:text>
        </div>
        @endif

        @if ($this->courrierSortantAujourdhui !== null)
        <div class="rounded-xl border border-brand-border bg-white p-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center gap-2.5">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-success/10 text-brand-success">
                    <flux:icon.paper-airplane class="size-4" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text class="truncate text-xs text-zinc-500">{{ __('Courrier sortant') }}</flux:text>
                    <div class="text-xl font-semibold leading-tight">{{ $this->courrierSortantAujourdhui['total'] }}</div>
                </div>
            </div>
            <flux:text class="mt-2 flex items-center gap-1 text-xs">
                @if ($this->courrierSortantAujourdhui['delta'] > 0)
                    <flux:icon.arrow-trending-up class="size-3.5 text-brand-success" />
                    <span class="truncate text-brand-success">{{ __(':n de plus qu\'hier', ['n' => $this->courrierSortantAujourdhui['delta']]) }}</span>
                @elseif ($this->courrierSortantAujourdhui['delta'] < 0)
                    <flux:icon.arrow-trending-down class="size-3.5 text-zinc-400" />
                    <span class="truncate text-zinc-500">{{ __(':n de moins qu\'hier', ['n' => abs($this->courrierSortantAujourdhui['delta'])]) }}</span>
                @else
                    <span class="truncate text-zinc-500">{{ __('Comme hier') }}</span>
                @endif
            </flux:text>
        </div>
        @endif

        @if ($this->enAttenteDeTraitement !== null)
        <div class="rounded-xl border border-brand-border bg-white p-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center gap-2.5">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-warning/10 text-brand-warning">
                    <flux:icon.clock class="size-4" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text class="truncate text-xs text-zinc-500">{{ __('En attente de traitement') }}</flux:text>
                    <div class="text-xl font-semibold leading-tight">{{ $this->enAttenteDeTraitement }}</div>
                </div>
            </div>
            <flux:text class="mt-2 truncate text-xs text-zinc-500">{{ __('courrier(s)') }}</flux:text>
        </div>
        @endif

        @if ($this->courriersUrgents !== null)
        <div class="rounded-xl border border-brand-border bg-white p-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center gap-2.5">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-urgent/10 text-brand-urgent">
                    <flux:icon.exclamation-triangle class="size-4" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text class="truncate text-xs text-zinc-500">{{ __('Courriers urgents') }}</flux:text>
                    <div class="text-xl font-semibold leading-tight">{{ $this->courriersUrgents }}</div>
                </div>
            </div>
            <flux:text class="mt-2 truncate text-xs text-zinc-500">{{ __('courrier(s)') }}</flux:text>
        </div>
        @endif

        {{-- Module 5/10 — date limite SLA dépassée (Dashboard::courriersEnRetard()). --}}
        @if ($this->courriersEnRetard !== null)
        <div class="rounded-xl border border-brand-border bg-white p-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center gap-2.5">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-urgent/10 text-brand-urgent">
                    <flux:icon.bell-alert class="size-4" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text class="truncate text-xs text-zinc-500">{{ __('En retard') }}</flux:text>
                    <div class="text-xl font-semibold leading-tight">{{ $this->courriersEnRetard }}</div>
                </div>
            </div>
            <flux:text class="mt-2 truncate text-xs text-zinc-500">{{ __('date limite dépassée') }}</flux:text>
        </div>
        @endif

        {{-- Module 10 — pré-calculé par RefreshDashboardStatsJob ; masqué
             pour un profil sans périmètre de service (Dashboard::delaiMoyen()). --}}
        @if ($this->delaiMoyen !== null)
            <div class="rounded-xl border border-brand-border bg-white p-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-blue-medium/10 text-brand-blue-medium">
                        <flux:icon.chart-bar class="size-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <flux:text class="truncate text-xs text-zinc-500">{{ __('Délai moyen de traitement') }}</flux:text>
                        <div class="text-xl font-semibold leading-tight">
                            {{ $this->delaiMoyen['jours'] !== null ? trans_choice(':n jour|:n jours', $this->delaiMoyen['jours'], ['n' => $this->delaiMoyen['jours']]) : '—' }}
                        </div>
                    </div>
                </div>
                <flux:text class="mt-2 truncate text-xs text-zinc-500">
                    {{ $this->delaiMoyen['en_calcul'] ? __('Calcul en cours…') : __('courriers clôturés, 90 derniers jours') }}
                </flux:text>
            </div>
        @endif
    </div>
    @endif

    {{-- Actions rapides — maquette : exactement 4 tuiles (Enregistrer un
         courrier / Scanner-Importer / Courrier confidentiel / Mes
         enregistrements), rien de plus — "Courriers à traiter" et
         "Rechercher un courrier" restent accessibles depuis la sidebar
         (groupes "Mes enregistrements"/"Recherche") sans être dupliqués ici
         (2026-09-16, demande explicite : reproduire l'arrangement exact de
         la maquette). Mêmes privilèges que les pages cibles — CHAQUE tuile a
         son propre @can depuis le 2026-09-23 (numérisation et courrier
         confidentiel ont chacun leur privilège) ; la rangée n'apparaît que
         si au moins une tuile est autorisée. --}}
    @php
        $peutCreer = auth()->user()->can('create', App\Models\Courrier::class);
        $peutNumeriser = auth()->user()->can('numeriser', App\Models\Courrier::class);
        $peutConfidentiel = auth()->user()->can('creerConfidentiel', App\Models\Courrier::class);
        $peutMesCourriers = auth()->user()->can('voirMesCourriers', App\Models\Courrier::class);
    @endphp
    @if ($peutCreer || $peutNumeriser || $peutConfidentiel || $peutMesCourriers)
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @if ($peutCreer)
                <a href="{{ route('courriers.nouveau') }}" wire:navigate class="rounded-2xl border border-brand-border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex size-11 items-center justify-center rounded-xl bg-brand-blue/10 text-brand-blue">
                        <flux:icon.document-plus class="size-5" />
                    </div>
                    <div class="mt-3 text-sm font-medium">{{ __('Enregistrer un courrier') }}</div>
                    <div class="text-xs text-zinc-500">{{ __('Entrant ou sortant') }}</div>
                </a>
            @endif
            @if ($peutNumeriser)
                <a href="{{ route('courriers.numeriser-nouveau') }}" wire:navigate class="rounded-2xl border border-brand-border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex size-11 items-center justify-center rounded-xl bg-brand-blue-medium/10 text-brand-blue-medium">
                        <flux:icon.camera class="size-5" />
                    </div>
                    <div class="mt-3 text-sm font-medium">{{ __('Scanner / Importer') }}</div>
                    <div class="text-xs text-zinc-500">{{ __('PDF, image, autres formats') }}</div>
                </a>
            @endif
            @if ($peutConfidentiel)
                <a href="{{ route('courriers.confidentiel') }}" wire:navigate class="rounded-2xl border border-brand-border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex size-11 items-center justify-center rounded-xl bg-brand-secure/10 text-brand-secure">
                        <flux:icon.lock-closed class="size-5" />
                    </div>
                    <div class="mt-3 text-sm font-medium">{{ __('Courrier confidentiel') }}</div>
                    <div class="text-xs text-zinc-500">{{ __('Enregistrement sécurisé') }}</div>
                </a>
            @endif
            @if ($peutMesCourriers)
                <a href="{{ route('courriers.mes-courriers') }}" wire:navigate class="rounded-2xl border border-brand-border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex size-11 items-center justify-center rounded-xl bg-brand-urgent/10 text-brand-urgent">
                        <flux:icon.paper-airplane class="size-5" />
                    </div>
                    <div class="mt-3 text-sm font-medium">{{ __('Mes enregistrements') }}</div>
                    <div class="text-xs text-zinc-500">{{ __('Suivi de vos courriers') }}</div>
                </a>
            @endif
        </div>
    @endif

        {{-- dashboard.derniers_courriers (2026-09-23). --}}
        @if ($this->derniersCourriers !== null)
        <div>
            <div class="flex items-center justify-between">
                <flux:heading level="2">{{ __('Derniers courriers enregistrés') }}</flux:heading>
                @can('rechercher', App\Models\Courrier::class)
                    <flux:link :href="route('courriers.rechercher')" wire:navigate>{{ __('Voir tous') }} →</flux:link>
                @endcan
            </div>

            @if ($this->derniersCourriers->isEmpty())
                <flux:text class="mt-4 text-zinc-500">{{ __('Aucun courrier pour l\'instant.') }}</flux:text>
            @else
                <div class="mt-4 overflow-x-auto rounded-2xl border border-brand-border bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="w-full text-sm">
                        <thead class="bg-brand-blue-pale text-left text-sm font-medium text-zinc-600 dark:bg-zinc-800">
                            <tr>
                                <th class="py-3 pl-4 pr-3">{{ __('N° Référence') }}</th>
                                <th class="py-3 pr-3">{{ __('Type') }}</th>
                                <th class="py-3 pr-3">{{ __('Sens') }}</th>
                                <th class="py-3 pr-3">{{ __('Expéditeur / Destinataire') }}</th>
                                <th class="py-3 pr-3">{{ __('Objet') }}</th>
                                <th class="py-3 pr-3">{{ __('Date') }}</th>
                                <th class="py-3 pr-4">{{ __('Statut') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($this->derniersCourriers as $courrier)
                                <tr class="cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800" onclick="window.location='{{ route('courriers.show', $courrier->id) }}'">
                                    <td class="py-3 pl-4 pr-3 font-medium">
                                        <flux:link :href="route('courriers.show', $courrier->id)" wire:navigate class="text-brand-blue">{{ $courrier->numero_reference }}</flux:link>
                                    </td>
                                    <td class="py-3 pr-3 text-zinc-500">{{ $courrier->type_document }}</td>
                                    <td class="py-3 pr-3 text-zinc-500">{{ $courrier->sens === 'entrant' ? __('Entrant') : __('Sortant') }}</td>
                                    <td class="py-3 pr-3 text-zinc-500">
                                        {{ $courrier->sens === 'entrant'
                                            ? ($courrier->expediteur_organisation ?: $courrier->expediteur_nom ?: __('—'))
                                            : ($courrier->destinataire ?: __('—')) }}
                                    </td>
                                    <td class="py-3 pr-3">{{ $courrier->objet }}</td>
                                    <td class="py-3 pr-3 text-zinc-500">{{ $courrier->date_mouvement->format('d/m/Y') }}</td>
                                    <td class="py-3 pr-4">
                                        <x-statut-badge :statut="$courrier->statut" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @endif
        </div>

        <div class="sticky top-20 self-start space-y-6">
            {{-- Module 10 — "Tâches du jour" : réel (pas "bientôt
                 disponible"), voir Dashboard::tachesDuJour() — aperçu de la
                 file d'attente du jour, gardé derrière le privilège
                 dashboard.taches_du_jour (Collaborateur par défaut,
                 Responsable de service au cas par cas). --}}
            @if ($this->tachesDuJour !== null)
                <div class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center gap-2 border-b border-brand-border bg-brand-blue-pale px-4 py-3 dark:border-zinc-700 dark:bg-brand-blue/10">
                        <flux:icon.check-circle class="size-4 text-brand-blue" />
                        <flux:heading level="3">{{ __('Tâches du jour') }}</flux:heading>
                    </div>
                    @if ($this->tachesDuJour->isEmpty())
                        <flux:text class="p-4 text-sm text-zinc-500">{{ __('Rien à traiter pour l\'instant.') }}</flux:text>
                    @else
                        {{-- Case à gauche de chaque tâche (2026-09-18,
                             "keep it real, restyle only" — voir la question
                             posée à l'utilisateur après comparaison avec la
                             maquette) : purement décorative (pas de vraie
                             case à cocher/état "fait", aucune fonctionnalité
                             fabriquée — Règle n°6) — juste le repère visuel
                             de la maquette devant une tâche RÉELLE, jamais
                             un pourcentage/quota inventé. --}}
                        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->tachesDuJour as $courrier)
                                <li>
                                    <flux:link :href="route('courriers.show', $courrier->id)" wire:navigate class="flex items-start gap-2.5 px-4 py-2 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                                        <span class="mt-0.5 size-3.5 shrink-0 rounded-sm border-2 border-zinc-300 dark:border-zinc-600"></span>
                                        <span class="min-w-0 flex-1">
                                            <div class="font-medium">{{ $courrier->numero_reference }}</div>
                                            <div class="truncate text-xs text-zinc-500">{{ $courrier->objet }}</div>
                                        </span>
                                    </flux:link>
                                </li>
                            @endforeach
                        </ul>
                        <div class="border-t border-brand-border p-2 text-center dark:border-zinc-700">
                            <flux:link :href="route('courriers.a-traiter')" wire:navigate>{{ __('Voir tout') }} →</flux:link>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Style de carte aligné sur "Tâches du jour"/"Calendrier"
                 ci-dessous (2026-09-18, "the notification panel design
                 doesn't match the image" — la maquette montre une carte
                 blanche avec bandeau d'en-tête, pas une zone en pointillés)
                 — le CONTENU reste honnête ("Bientôt disponible", aucune
                 notification fabriquée : Module 7/SendMailAlertJob
                 n'existe toujours pas), seul le style visuel change.
                 dashboard.notifications (2026-09-23). --}}
            @if ($this->peutVoirNotifications)
                <div class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center gap-2 border-b border-brand-border bg-brand-blue-pale px-4 py-3 dark:border-zinc-700 dark:bg-brand-blue/10">
                        <flux:icon.bell class="size-4 text-brand-blue" />
                        <flux:heading level="3">{{ __('Notifications') }}</flux:heading>
                    </div>
                    <flux:text class="p-4 text-sm text-zinc-500">{{ __('Bientôt disponible.') }}</flux:text>
                </div>
            @endif

            {{-- Calendrier — maquette : mini calendrier du mois avec le jour
                 courant mis en évidence. Pas de données métier (aucun
                 événement/tâche fabriqué) : purement de l'arithmétique de
                 date côté Alpine, donc pas de propriété Livewire dédiée
                 (Règle n°2) ni de "bientôt disponible" nécessaire, la date
                 du jour étant une donnée réelle et déjà disponible. Noms
                 localisés via Carbon (FR/EN, cohérent avec le switcher de
                 langue existant) plutôt que des clés de traduction sur des
                 lettres seules (ambigu : "M" = Mardi ET Mercredi, collision
                 quasi garantie avec d'autres __() courts ailleurs dans le
                 projet). Calculés dans un bloc PHP séparé ci-dessous plutôt
                 qu'inline dans la directive JSON : l'expression Carbon
                 imbriquée (virgules dans create(), chaînage de flèches) fait
                 planter le parseur d'arguments de cette directive, repéré
                 via un rendu direct de la vue qui renvoyait une erreur de
                 syntaxe PHP. Même piège que celui déjà documenté dans
                 CHANGELOG-AGENT.md pour le script de thème du 2026-09-16 :
                 écrire le nom littéral d'une directive Blade en toutes
                 lettres dans CE commentaire cassait le commentaire
                 lui-même — Blade scanne le texte brut du fichier, y compris
                 à l'intérieur d'un commentaire.
                 dashboard.calendrier (2026-09-23). --}}
            @if ($this->peutVoirCalendrier)
            @php
                $nomsMoisCalendrier = collect(range(0, 11))->map(
                    fn ($m) => \Illuminate\Support\Carbon::create(2000, $m + 1, 1)->translatedFormat('F')
                );
                $joursSemaineCalendrier = collect(range(0, 6))->map(
                    fn ($d) => mb_substr(\Illuminate\Support\Carbon::now()->startOfWeek()->addDays($d)->translatedFormat('D'), 0, 1)
                );
            @endphp
            <div
                class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
                x-data="{
                    mois: {{ now()->month - 1 }},
                    annee: {{ now()->year }},
                    moisAujourdhui: {{ now()->month - 1 }},
                    anneeAujourdhui: {{ now()->year }},
                    aujourdhui: {{ now()->day }},
                    nomsMois: @json($nomsMoisCalendrier),
                    joursSemaine: @json($joursSemaineCalendrier),
                    get joursDuMois() { return new Date(this.annee, this.mois + 1, 0).getDate(); },
                    get premierJourSemaine() { const d = new Date(this.annee, this.mois, 1).getDay(); return d === 0 ? 6 : d - 1; },
                    precedent() { if (this.mois === 0) { this.mois = 11; this.annee--; } else { this.mois--; } },
                    suivant() { if (this.mois === 11) { this.mois = 0; this.annee++; } else { this.mois++; } },
                }"
            >
                <div class="flex items-center justify-between border-b border-brand-border px-4 py-3 dark:border-zinc-700">
                    <flux:heading level="3">{{ __('Calendrier') }}</flux:heading>
                    <div class="flex items-center gap-1">
                        <button type="button" x-on:click="precedent()" class="rounded p-1 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800" aria-label="{{ __('Mois précédent') }}">
                            <flux:icon.chevron-left class="size-4" />
                        </button>
                        <span class="min-w-28 text-center text-sm font-medium" x-text="nomsMois[mois] + ' ' + annee"></span>
                        <button type="button" x-on:click="suivant()" class="rounded p-1 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800" aria-label="{{ __('Mois suivant') }}">
                            <flux:icon.chevron-right class="size-4" />
                        </button>
                    </div>
                </div>
                <div class="p-3">
                    <div class="grid grid-cols-7 text-center text-xs text-zinc-400">
                        <template x-for="(j, index) in joursSemaine" :key="index">
                            <div x-text="j"></div>
                        </template>
                    </div>
                    <div class="mt-1 grid grid-cols-7 gap-y-1 text-center text-sm">
                        <template x-for="vide in premierJourSemaine" :key="'vide-' + vide">
                            <div></div>
                        </template>
                        <template x-for="jour in joursDuMois" :key="jour">
                            <div class="flex items-center justify-center py-1">
                                <span
                                    class="flex size-7 items-center justify-center rounded-full"
                                    x-bind:class="(jour === aujourdhui && mois === moisAujourdhui && annee === anneeAujourdhui) ? 'bg-brand-blue font-semibold text-white' : 'hover:bg-zinc-100 dark:hover:bg-zinc-800'"
                                    x-text="jour"
                                ></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</section>
