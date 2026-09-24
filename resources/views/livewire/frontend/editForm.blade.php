{{-- Nouvelle maquette "Modifier le courrier" fournie par l'utilisateur
     (2026-09-18) — remplace ENTIÈREMENT l'ancienne mise en page à une
     colonne ("this is how i want the modify/edit design to look like...
     drop that orther one"). Clarifié avec l'utilisateur (AskUserQuestion) :
     (1) les champs génuinement nouveaux de la maquette (Direction d'origine,
     Échéance, Type de traitement, Fonction de l'expéditeur, Commentaire/
     Note interne) sont ajoutés pour de vrai en base (voir la migration
     2026_09_18_070000) plutôt que fabriqués côté vue — TOUS nullable, sans
     astérisque "obligatoire" puisqu'aucune règle métier ne les impose
     encore ; (2) "Type"/"Catégorie"/"Service émetteur" de la maquette sont
     de simples relibellés des champs existants sens/type_document/
     service_id — jamais dupliqués sous un nouveau nom ; (3) seuls 2 onglets
     réels ("Informations générales" et "Pièces jointes") — Documents/
     Historique/Affectation & Transfert/Traitement & Workflow/Suivi &
     Traçabilité de la maquette dupliqueraient la logique de circuit qui
     vit déjà sur showCourrier.blade.php, hors périmètre de cette demande. --}}
@assets
    @vite('resources/js/document-preview.js')
@endassets

<section class="w-full" x-data="{ onglet: 'general' }">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate></flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('courriers.rechercher')" wire:navigate>{{ __('Courriers') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('courriers.rechercher')" wire:navigate>{{ __('Tous les courriers') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('courriers.show', $courrierId)" wire:navigate>{{ $numeroReference }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Modifier') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <form wire:submit="enregistrerModification">
        <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-blue text-white">
                    <flux:icon.envelope class="size-5" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <flux:heading level="1">{{ __('Modifier le courrier') }}</flux:heading>
                        <x-statut-badge :statut="$this->courrier->statut" />
                    </div>
                    <flux:subheading>{{ $numeroReference }} — {{ __('le numéro de référence ne peut pas être modifié.') }}</flux:subheading>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button variant="outline" icon="arrow-left" :href="route('courriers.show', $courrierId)" wire:navigate>
                    {{ __('Retour') }}
                </flux:button>
                <flux:button variant="primary" icon="check" type="submit">
                    {{ __('Enregistrer') }}
                </flux:button>
                <flux:button variant="outline" icon="x-mark" :href="route('courriers.show', $courrierId)" wire:navigate>
                    {{ __('Annuler') }}
                </flux:button>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <div>
                <div class="overflow-x-auto rounded-2xl border border-brand-border bg-white p-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex min-w-max items-center gap-1">
                        <flux:button size="sm" x-on:click="onglet = 'general'" x-bind:variant="onglet === 'general' ? 'primary' : 'ghost'" icon="document-text">
                            {{ __('Informations générales') }}
                        </flux:button>
                        <flux:button size="sm" x-on:click="onglet = 'pieces-jointes'" x-bind:variant="onglet === 'pieces-jointes' ? 'primary' : 'ghost'" icon="paper-clip">
                            {{ __('Pièces jointes') }} ({{ $this->courrier->piecesJointes->count() }})
                        </flux:button>
                    </div>
                </div>

                {{-- ONGLET INFORMATIONS GÉNÉRALES --}}
                <div x-show="onglet === 'general'" x-cloak class="mt-4 space-y-6">
                    <div class="rounded-2xl border border-brand-border bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="mb-4 flex items-center gap-2">
                            <flux:icon.information-circle class="size-4 text-brand-blue" />
                            <flux:heading level="3">{{ __('Informations générales') }}</flux:heading>
                        </div>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <flux:input value="{{ $numeroReference }}" :label="__('Numéro du courrier')" disabled />

                            <flux:select wire:model="directionOrigineId" :label="__('Direction d\'origine')" placeholder="{{ __('— Non renseignée —') }}">
                                @foreach ($this->services as $service)
                                    <flux:select.option value="{{ $service->id }}">{{ $service->nom }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            {{-- "Type" de la maquette = notre champ "Sens" existant
                                 (Entrant/Sortant) — relibellé, pas dupliqué. --}}
                            <flux:select wire:model.live="form.sens" :label="__('Type')" required>
                                <flux:select.option value="entrant">{{ __('Courrier entrant') }}</flux:select.option>
                                <flux:select.option value="sortant">{{ __('Courrier sortant') }}</flux:select.option>
                            </flux:select>

                            {{-- Le service n'est plus saisi/modifié par l'agent pour un
                                 courrier ENTRANT (2026-09-15, voir DECISIONS.md
                                 "Synchronisation avec le nouveau document SRS-GEC.pdf") —
                                 c'est le DGA/ADJ DGA qui le choisit au moment de valider le
                                 transfert. Le sortant garde le champ, requis comme avant.
                                 "Service émetteur" de la maquette = notre champ
                                 "service_id" existant, relibellé. --}}
                            @if ($form->sens === 'sortant')
                                <flux:select wire:model="form.service_id" :label="__('Service émetteur')" placeholder="{{ __('Sélectionner un service') }}" required>
                                    @foreach ($this->services as $service)
                                        <flux:select.option value="{{ $service->id }}">{{ $service->nom }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <flux:text class="self-end pb-2 text-sm text-zinc-500">
                                    {{ __('Le service sera choisi par le DGA/ADJ DGA au moment de valider le transfert.') }}
                                </flux:text>
                            @endif

                            <flux:input wire:model="form.objet" :label="__('Objet')" required />

                            {{-- "Catégorie" de la maquette = notre champ "Type de
                                 document" existant, relibellé (specifications-modules-
                                 GEC.md emploie déjà "catégorie" comme synonyme). --}}
                            <flux:field>
                                @if ($typeDocumentPersonnalise)
                                    <flux:input wire:model="form.type_document" :label="__('Catégorie')" placeholder="{{ __('Précisez la catégorie…') }}" required />
                                    <flux:link href="#" wire:click.prevent="choisirTypeDocumentDansLaListe" class="text-sm">{{ __('Choisir dans la liste plutôt') }}</flux:link>
                                @else
                                    <flux:select wire:model.live="form.type_document" :label="__('Catégorie')" placeholder="{{ __('— Choisir —') }}" required>
                                        @foreach (\App\Livewire\Backend\Forms\CourrierForm::typesDocument() as $type)
                                            <flux:select.option value="{{ $type }}">{{ $type }}</flux:select.option>
                                        @endforeach
                                        <flux:select.option value="__autre__">{{ __('Autre (préciser)') }}</flux:select.option>
                                    </flux:select>
                                @endif
                            </flux:field>

                            <flux:input type="date" wire:model="form.date_mouvement" :label="__('Date de réception / d\'envoi')" required />

                            <flux:select wire:model="form.priorite" :label="__('Priorité')" required>
                                <flux:select.option value="basse">{{ __('Basse') }}</flux:select.option>
                                <flux:select.option value="normale">{{ __('Normale') }}</flux:select.option>
                                <flux:select.option value="haute">{{ __('Haute') }}</flux:select.option>
                                <flux:select.option value="urgente">{{ __('Urgente') }}</flux:select.option>
                            </flux:select>

                            <flux:select wire:model="form.mode_reception" :label="__('Mode de réception')" required>
                                <flux:select.option value="depot_physique">{{ __('Dépôt physique') }}</flux:select.option>
                                <flux:select.option value="email">{{ __('Email') }}</flux:select.option>
                                <flux:select.option value="poste">{{ __('Poste') }}</flux:select.option>
                                <flux:select.option value="fax">{{ __('Fax') }}</flux:select.option>
                            </flux:select>

                            <flux:input type="date" wire:model="echeance" :label="__('Échéance')" />

                            <flux:select wire:model="form.confidentialite" :label="__('Confidentialité')" required>
                                @foreach (range(1, \App\Models\User::niveauConfidentialiteMax()) as $niveau)
                                    <flux:select.option value="{{ $niveau }}">{{ __('Niveau :n', ['n' => $niveau]) }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="form.sous_type_sinistre" :label="__('Sous-type (si sinistre)')" placeholder="{{ __('— Sans objet —') }}">
                                <flux:select.option value="">{{ __('— Sans objet —') }}</flux:select.option>
                                <flux:select.option value="materiel">{{ __('Matériel') }}</flux:select.option>
                                <flux:select.option value="corporel">{{ __('Corporel') }}</flux:select.option>
                            </flux:select>

                            <flux:field>
                                <flux:label>{{ __('État actuel') }}</flux:label>
                                <div class="pt-1.5"><x-statut-badge :statut="$this->courrier->statut" /></div>
                            </flux:field>

                            <flux:input wire:model="typeTraitement" :label="__('Type de traitement')" placeholder="{{ __('ex. Traitement classique, urgent…') }}" />

                            <flux:input wire:model="referenceExterne" :label="__('Référence externe')" placeholder="{{ __('Référence donnée par l\'expéditeur, si connue') }}" />

                            <flux:input wire:model="dossierReference" :label="__('Dossier lié')" placeholder="{{ __('ex. DOS-2026-0001') }}" />

                            <flux:input type="number" wire:model="slaJours" :label="__('SLA (jours)')" min="0" max="365" />

                            <flux:select wire:model="serviceResponsableId" :label="__('Service responsable')" placeholder="{{ __('— Non renseigné —') }}">
                                @foreach ($this->services as $service)
                                    <flux:select.option value="{{ $service->id }}">{{ $service->nom }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-brand-border bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="mb-4 flex items-center gap-2">
                            <flux:icon.user-plus class="size-4 text-brand-blue" />
                            <flux:heading level="3">{{ __('Expéditeur / Destinataire') }}</flux:heading>
                        </div>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <flux:input wire:model="form.expediteur_nom" :label="__('Expéditeur')" />
                            <flux:input wire:model="expediteurFonction" :label="__('Fonction')" placeholder="{{ __('ex. Chef de service') }}" />
                        </div>
                        <div class="mt-6 grid gap-6 sm:grid-cols-2">
                            <flux:input wire:model="form.expediteur_organisation" :label="__('Organisation')" />
                            <flux:input wire:model="form.expediteur_telephone" :label="__('Téléphone')" />
                        </div>
                        <div class="mt-6 grid gap-6 sm:grid-cols-2">
                            <flux:input type="email" wire:model="form.expediteur_email" :label="__('Email')" />
                            <flux:input wire:model="form.expediteur_adresse" :label="__('Adresse')" />
                        </div>
                        <div class="mt-6 grid gap-6 sm:grid-cols-2">
                            <flux:input wire:model="form.expediteur_rc" :label="__('RC (Registre du Commerce)')" />
                            <flux:input wire:model="form.expediteur_niu" :label="__('NIU (Numéro d\'Identifiant Unique)')" />
                        </div>

                        <flux:textarea wire:model="form.destinataire" :label="__('Destinataire(s)')" rows="2" class="mt-6" />
                    </div>

                    <div class="rounded-2xl border border-brand-border bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="mb-4 flex items-center gap-2">
                            <flux:icon.chat-bubble-bottom-center-text class="size-4 text-brand-blue" />
                            <flux:heading level="3">{{ __('Résumé / Commentaire') }}</flux:heading>
                        </div>

                        <flux:field>
                            <flux:textarea wire:model="noteInterne" :label="__('Commentaire / Note interne')" rows="3" maxlength="1000" placeholder="{{ __('Note visible uniquement par les agents internes…') }}" />
                            <flux:description class="text-end">{{ strlen($noteInterne ?? '') }}/1000</flux:description>
                        </flux:field>
                    </div>
                </div>

                {{-- ONGLET PIÈCES JOINTES --}}
                <div x-show="onglet === 'pieces-jointes'" x-cloak class="mt-4 rounded-2xl border border-brand-border bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    @if (auth()->user()->cannot('voirPiecesJointes', $this->courrier))
                        {{-- courriers.voir_pieces_jointes (2026-09-23). --}}
                        <flux:text class="text-zinc-500">{{ __('Vous n\'avez pas accès aux pièces jointes de ce courrier.') }}</flux:text>
                    @elseif ($this->courrier->piecesJointes->isEmpty())
                        <flux:text class="text-zinc-500">{{ __('Aucune pièce jointe pour ce courrier.') }}</flux:text>
                    @else
                        {{-- Liens de téléchargement derrière courriers.telecharger (2026-09-23). --}}
                        @php $peutTelecharger = auth()->user()->can('telecharger', $this->courrier); @endphp
                        <ul class="space-y-2">
                            @foreach ($this->courrier->piecesJointes as $pieceJointe)
                                <li class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <flux:icon name="paper-clip" class="size-4 shrink-0 text-zinc-400" />
                                        @if ($peutTelecharger)
                                            <flux:link :href="route('pieces-jointes.telecharger', $pieceJointe)">{{ $pieceJointe->nom_original }}</flux:link>
                                        @else
                                            <span>{{ $pieceJointe->nom_original }}</span>
                                        @endif
                                    </div>
                                    @if ($peutTelecharger)
                                        <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('pieces-jointes.telecharger', $pieceJointe)" :aria-label="__('Télécharger')" />
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <flux:separator class="my-4" text="{{ __('Ajouter une pièce jointe') }}" />

                    <flux:field>
                        <flux:label>{{ __('Fichier (optionnel — copie déjà numérique, ex. pièce jointe d\'un email)') }}</flux:label>
                        <input type="file" wire:model="pieceJointe" accept=".pdf,.jpg,.jpeg,.png"
                            class="block w-full text-sm text-zinc-600 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:text-zinc-300 dark:file:bg-zinc-700 dark:hover:file:bg-zinc-600" />
                        <flux:description>{{ __('PDF, JPG ou PNG, 10 Mo maximum.') }}</flux:description>
                        <flux:error name="pieceJointe" />
                        <div wire:loading wire:target="pieceJointe" class="mt-1 text-sm text-zinc-500">{{ __('Envoi en cours…') }}</div>
                    </flux:field>
                </div>
            </div>

            {{-- Colonne latérale : "Document principal" (aperçu, même motif
                 canevas pdfjs-dist/document-preview.js que showCourrier.blade.php
                 et "Tous les courriers") + résumé complémentaire, TOUJOURS
                 affichée, jamais masquée — demande explicite de l'utilisateur
                 étendue à toutes les pages à panneau aperçu, rendue sticky
                 (voir CHANGELOG-AGENT.md, 2026-09-18 05:30). --}}
            <div class="sticky top-20 self-start space-y-6">
                <div class="rounded-2xl border border-brand-border bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center gap-2 border-b border-brand-border p-4 dark:border-zinc-700">
                        <flux:icon.document class="size-4 text-brand-blue" />
                        <flux:heading level="3">{{ __('Document principal') }}</flux:heading>
                    </div>

                    <div class="p-4">
                        @if ($this->courrier->fichier_path)
                            <div
                                wire:ignore
                                x-data="{
                                    erreur: null,
                                    zoom: 100,
                                    rechercheOuverte: false,
                                    requeteRecherche: '',
                                    nbResultats: 0,
                                    url: @js(route('courriers.document.apercu', $this->courrier)),
                                    instance() {
                                        const apercu = window.DocumentPreview.obtenir(this.url + ':edition');
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
                                            console.error('[aperçu édition courrier]', e);
                                            this.erreur = e.message ?? String(e);
                                        }
                                    },
                                    async zoomer(nouveauZoom) {
                                        this.zoom = nouveauZoom;

                                        try {
                                            await this.instance().zoomer(this.zoom);
                                        } catch (e) {
                                            console.error('[aperçu édition courrier] zoom', e);
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
                                        @can('telecharger', $this->courrier)
                                            <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('courriers.document', $this->courrier)" :aria-label="__('Télécharger')" />
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
                    </div>
                </div>

                <div class="rounded-2xl border border-brand-border bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-3 flex items-center gap-2">
                        <flux:icon.squares-2x2 class="size-4 text-brand-blue" />
                        <flux:heading level="3">{{ __('Informations complémentaires') }}</flux:heading>
                    </div>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs uppercase text-zinc-500">{{ __('Demandeur') }}</dt>
                            <dd class="font-medium">{{ $this->courrier->expediteur_nom ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-zinc-500">{{ __('Téléphone') }}</dt>
                            <dd class="font-medium">{{ $this->courrier->expediteur_telephone ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-zinc-500">{{ __('Pièce(s) jointe(s)') }}</dt>
                            <dd class="font-medium">{{ __(':n fichier(s)', ['n' => $this->courrier->piecesJointes->count()]) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-zinc-500">{{ __('Confidentialité') }}</dt>
                            <dd class="mt-0.5">
                                {{-- Niveau NUMÉRIQUE (2026-09-21, "numbers ... not
                                     confidential or whatever", puis "THE LABEL SHOULD BE
                                     NIVEAU 1 OR LEVEL 1"). --}}
                                @if ($this->courrier->confidentialite <= 1)
                                    <span class="inline-flex items-center rounded-full bg-brand-success-light px-2 py-0.5 text-xs font-medium text-brand-success-dark">{{ __('Niveau :n', ['n' => $this->courrier->confidentialite]) }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-brand-navy px-2 py-0.5 text-xs font-medium text-white">
                                        {{ __('Niveau :n', ['n' => $this->courrier->confidentialite]) }}
                                    </span>
                                @endif
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-xs uppercase text-zinc-500">{{ __('Référence interne') }}</dt>
                            <dd class="font-medium">{{ $numeroReference }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="flex items-start gap-2 rounded-2xl bg-brand-blue-pale p-4 text-sm text-brand-blue-medium">
                    <flux:icon.information-circle class="size-4 shrink-0" />
                    <span>{{ __('Les modifications seront enregistrées dans l\'historique du courrier et tracées dans le journal des actions.') }}</span>
                </div>
            </div>
        </div>
    </form>
</section>
