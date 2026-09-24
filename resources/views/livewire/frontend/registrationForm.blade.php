<section
    class="w-full max-w-6xl"
    x-data="{
        etape: {{ $derniereReference ? 5 : 1 }},
        aller(n) { this.etape = n; },
        suivant() {
            const formulaire = this.$root.querySelector('form');

            if (formulaire) {
                const champInvalide = Array.from(formulaire.elements).find(
                    (champ) => champ.offsetParent !== null && ! champ.checkValidity()
                );

                if (champInvalide) {
                    champInvalide.reportValidity();
                    return;
                }
            }

            this.etape = Math.min(this.etape + 1, 4);
            this.$wire.$refresh();
        },
        precedent() { this.etape = Math.max(this.etape - 1, 1); },
    }"
    x-on:courrier-enregistre.window="etape = 5"
>
    {{-- Maquette utilisateur du 2026-09-17 ("Enregistrer un courrier", vue
         en 5 étapes) — combine sur une seule page ce qui existait déjà
         séparément (scan-first, informations générales, OCR pré-rempli,
         pièce jointe, confirmation), réorganisé en étapes visuelles. Le
         formulaire Livewire sous-jacent reste UN SEUL submit
         (wire:submit="enregistrer") : les "étapes" ne sont qu'un habillage
         Alpine par-dessus les mêmes champs déjà réels, pas un vrai
         wizard multi-requêtes — aucun champ n'est retiré du DOM entre les
         étapes (x-show, pas de v-if), donc rien ne se perd en changeant
         d'étape. --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate>{{ __('Accueil') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Courrier') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Enregistrer un courrier') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="mt-3 flex items-center gap-3">
        <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-blue text-white">
            <flux:icon.envelope class="size-5" />
        </div>
        <div>
            <flux:heading level="1">{{ __('Enregistrer un courrier') }}</flux:heading>
            <flux:subheading>{{ __('Scannez ou importez le document, puis vérifiez et complétez les informations.') }}</flux:subheading>
        </div>
    </div>

    {{-- Indicateur d'étapes — purement visuel (voir commentaire ci-dessus),
         cliquable pour revenir en arrière une fois qu'une étape a déjà été
         atteinte (jamais en avant : la vérification réelle des champs reste
         côté serveur au submit, Règle n°6). --}}
    <div class="mt-6 overflow-x-auto rounded-2xl border border-brand-border bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex min-w-max items-center">
            @foreach ([
                1 => ['Document', 'Scan / Import'],
                2 => ['Informations', 'Vérification & correction'],
                3 => ['Pièces jointes', 'Ajout de fichiers'],
                4 => ['Validation', 'Enregistrement'],
                5 => ['Confirmation', 'Référence générée'],
            ] as $n => [$titre, $sous])
                <button
                    type="button"
                    x-on:click="if ({{ $n }} <= etape) aller({{ $n }})"
                    class="flex items-center gap-2 text-left"
                    x-bind:class="{{ $n }} > etape ? 'cursor-default' : 'cursor-pointer'"
                >
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                        x-bind:class="etape === {{ $n }} ? 'bg-brand-blue text-white' : (etape > {{ $n }} ? 'bg-brand-success text-white' : 'bg-brand-disabled-bg text-brand-disabled-text')"
                    >
                        <template x-if="etape > {{ $n }}"><flux:icon.check class="size-4" /></template>
                        <template x-if="etape <= {{ $n }}"><span>{{ $n }}</span></template>
                    </span>
                    <span class="hidden sm:block">
                        <span class="block text-sm font-medium" x-bind:class="etape === {{ $n }} ? 'text-brand-text-primary' : 'text-brand-text-secondary'">{{ __($titre) }}</span>
                        <span class="block text-xs text-brand-text-muted">{{ __($sous) }}</span>
                    </span>
                </button>
                @if ($n < 5)
                    <div class="mx-3 h-px w-8 shrink-0 bg-brand-border sm:w-12"></div>
                @endif
            @endforeach
        </div>
    </div>

    @assets
        @vite('resources/js/scan-watcher.js')
        @vite('resources/js/document-preview.js')
    @endassets

    <form wire:submit="enregistrer" class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="space-y-6">
            {{-- ÉTAPE 1 — Document (scan / import) --}}
            <div x-show="etape === 1" x-cloak class="space-y-4 rounded-2xl border border-brand-border bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center gap-2">
                    <flux:icon.document-text class="size-4 text-brand-text-secondary" />
                    <flux:heading level="3">{{ __('Scan / Import du document') }}</flux:heading>
                </div>

                @if ($this->brouillon)
                    <div class="flex items-start gap-3 rounded-xl bg-brand-success-light p-3">
                        <flux:icon.check-circle class="size-5 shrink-0 text-brand-success-dark" />
                        <div class="text-sm text-brand-success-dark">
                            <div class="font-medium">{{ $this->brouillon->nom_original }}</div>
                            @if ($this->brouillon->numero_tampon_detecte)
                                <div>{{ __('Numéro détecté sur le tampon (indicatif)') }} : {{ $this->brouillon->numero_tampon_detecte }}</div>
                            @elseif ($this->brouillon->ocr_statut === 'en_cours')
                                <div>{{ __('extraction en cours…') }}</div>
                            @endif
                        </div>
                        <flux:button type="button" size="sm" variant="ghost" icon="trash" wire:click="annulerDocument" class="ms-auto shrink-0">{{ __('Annuler') }}</flux:button>
                    </div>

                    @if ($champsProposesAutomatiquement)
                        <flux:text class="text-brand-warning">
                            {{ __('Certains champs (objet, destinataire, service, type de document, date, mode de réception, nom/organisation/téléphone/email/adresse de l\'expéditeur) ont été proposés automatiquement d\'après le texte détecté — vérifiez avant de confirmer.') }}
                        </flux:text>
                    @endif
                @else
                    {{-- Garde x-show/surveillanceActive : même précaution que
                         scanPremier.blade.php — deux <input> liés au MÊME
                         wire:model="document" (celui-ci + l'entrée cachée du
                         watcher plus bas) peuvent se marcher dessus si les
                         deux sont actionnables en même temps (voir son
                         commentaire "celui qui committe en dernier écrase
                         silencieusement l'autre"). Bouton distinct de
                         l'input (pas wire:change sur l'input lui-même) :
                         Livewire met en file l'upload puis l'appel serveur
                         dans l'ordre, plutôt que de risquer une course sur
                         le même évènement "change". --}}
                    <div
                        class="flex flex-wrap items-end gap-3"
                        x-data="{ surveillanceActive: false }"
                        x-on:scan-watcher-etat.window="surveillanceActive = ['chargement', 'en_surveillance'].includes($event.detail.etat)"
                        x-show="!surveillanceActive"
                    >
                        {{-- Page cible gardée par courriers.numeriser (2026-09-23). --}}
                        @can('numeriser', App\Models\Courrier::class)
                            <flux:button type="button" variant="primary" icon="camera" :href="route('courriers.numeriser-nouveau')" wire:navigate>{{ __('Scanner') }}</flux:button>
                        @endcan

                        <flux:input type="file" wire:model="document" :label="__('Importer un fichier')" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff" class="max-w-xs" />

                        <flux:button type="button" variant="outline" wire:click="importerFichier" wire:loading.attr="disabled" wire:target="document,importerFichier">
                            {{ __('Importer') }}
                        </flux:button>
                    </div>

                    <flux:text class="text-sm text-zinc-500">{{ __('Formats acceptés : PDF, JPG, PNG | Taille max : 10 Mo') }}</flux:text>
                    <div wire:loading wire:target="importerFichier" class="text-sm text-zinc-500">{{ __('Import en cours…') }}</div>
                    <flux:error name="document" />
                @endif

                @if ($this->brouillonsEnAttente->isNotEmpty())
                    @php $estAdministrateur = auth()->user()->hasPrivilege('brouillons.utiliser_tout'); @endphp
                    <flux:field>
                        <flux:select x-on:change="if ($event.target.value) { Livewire.navigate($event.target.value); }">
                            <flux:select.option value="">{{ __('— Choisir un autre document scanné en attente —') }}</flux:select.option>
                            @foreach ($this->brouillonsEnAttente as $autre)
                                <flux:select.option value="{{ route('courriers.nouveau', ['brouillonId' => $autre->id]) }}">
                                    {{ $autre->nom_original }} ({{ str_replace('_', ' ', $autre->ocr_statut) }}){{ $estAdministrateur ? ' — '.$autre->creePar->name : '' }} — {{ $autre->created_at->diffForHumans() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <div class="mt-2">{{ $this->brouillonsEnAttente->links() }}</div>
                    </flux:field>
                @endif

                @error('brouillonId') <flux:text class="text-sm text-brand-danger">{{ $message }}</flux:text> @enderror

                {{-- Dossier surveillé (voir DECISIONS.md) : UI minimale, seule
                     une ligne discrète si la surveillance tourne déjà —
                     inchangé, décision confirmée avec l'utilisateur. --}}
                <div
                    x-data="surveillanceDossier()"
                    x-init="init()"
                    x-effect="window.dispatchEvent(new CustomEvent('scan-watcher-etat', { detail: { etat } }))"
                    data-msg-droits-perdus="{{ __('Import automatique interrompu — contactez l\'administrateur.') }}"
                    data-msg-erreur-temporaire="{{ __('Échec temporaire — nouvel essai au prochain cycle.') }}"
                >
                    <input type="file" wire:model="document" id="scan-watcher-entree-cachee" class="hidden" tabindex="-1" aria-hidden="true">

                    <template x-if="etat === 'en_surveillance'">
                        <span class="inline-flex items-center rounded-full bg-brand-success-light px-2 py-0.5 text-xs font-medium text-brand-success-dark">{{ __('Import automatique actif') }}</span>
                    </template>

                    <template x-if="etat === 'en_pause_erreur'">
                        <flux:text class="text-xs text-brand-warning" x-text="messageErreur"></flux:text>
                    </template>
                </div>

                <div class="flex justify-end">
                    <flux:button type="button" variant="primary" x-on:click="suivant()">{{ __('Suivant') }} →</flux:button>
                </div>
            </div>

            {{-- ÉTAPE 2 — Informations générales + extraites (OCR) --}}
            <div x-show="etape === 2" x-cloak class="space-y-6 rounded-2xl border border-brand-border bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center gap-2">
                    <flux:icon.identification class="size-4 text-brand-text-secondary" />
                    <flux:heading level="3">{{ __('Informations générales') }}</flux:heading>
                </div>

                {{-- Champs identiques à l'ancien formulaire (mêmes wire:model,
                     mêmes labels, mêmes options) — seule la mise en page
                     change ici (regroupement en carte d'étape), pas les
                     contrôles eux-mêmes (demande explicite de
                     l'utilisateur, 2026-09-17 : "you have to use the same
                     input we already built"). --}}
                <div class="grid gap-6 sm:grid-cols-3">
                    <flux:select wire:model.live="form.sens" :label="__('Sens du courrier')" class="invalid:border-brand-danger valid:border-brand-success" required>
                        <flux:select.option value="entrant">{{ __('Entrant') }}</flux:select.option>
                        <flux:select.option value="sortant">{{ __('Sortant') }}</flux:select.option>
                    </flux:select>

                    <flux:select wire:model="form.priorite" :label="__('Priorité')" class="invalid:border-brand-danger valid:border-brand-success" required>
                        <flux:select.option value="basse">{{ __('Basse') }}</flux:select.option>
                        <flux:select.option value="normale">{{ __('Normale') }}</flux:select.option>
                        <flux:select.option value="haute">{{ __('Haute') }}</flux:select.option>
                        <flux:select.option value="urgente">{{ __('Urgente') }}</flux:select.option>
                    </flux:select>

                    <flux:select wire:model.live="form.confidentialite" :label="__('Confidentialité')" class="invalid:border-brand-danger valid:border-brand-success" required>
                        @foreach (range(1, \App\Models\User::niveauConfidentialiteMax()) as $niveau)
                            <flux:select.option value="{{ $niveau }}">{{ __('Niveau :n', ['n' => $niveau]) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:field>
                        <flux:input type="date" wire:model="form.date_mouvement" :label="__('Date de réception / d\'envoi')" class:input="invalid:border-brand-danger valid:border-brand-success" required />
                    </flux:field>

                    @if ($form->sens === 'sortant')
                        <div class="grid gap-3 sm:col-span-2 sm:grid-cols-3">
                            {{-- Module "Organisation" v2 (2026-09-22, spec §16)
                                 — cascade Site → Département → Service/Unité,
                                 remplace le sélecteur plat unique. --}}
                            {{-- 2026-09-23, clarification explicite de l'utilisateur :
                                 le Site est OPTIONNEL — des Départements réels
                                 existent déjà à la racine (sans Site au-dessus) en
                                 attendant le vrai nom du/des site(s) réel(s), jamais
                                 fabriqué. Plus "required" ici (le Département/Service
                                 reste, lui, obligatoire pour résoudre form.service_id)
                                 ; option vide RÉELLE pour revenir à "racine" une fois
                                 un site choisi (même piège que les 3 précédents cette
                                 session, flux:select "freeze"). --}}
                            <flux:field>
                                <flux:select wire:model.live="siteSelectionneId" :label="__('Site')" placeholder="{{ __('— Aucun (racine) —') }}" class="invalid:border-brand-danger valid:border-brand-success">
                                    <flux:select.option value="">{{ __('— Aucun (racine) —') }}</flux:select.option>
                                    @foreach ($this->sitesDisponibles as $site)
                                        <flux:select.option value="{{ $site->id }}">{{ $site->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                            @if ($this->departementsDisponibles->isNotEmpty())
                                <flux:field>
                                    <flux:select wire:model.live="departementSelectionneId" :label="__('Département')" placeholder="{{ __('Choisir un département') }}" required>
                                        @foreach ($this->departementsDisponibles as $departement)
                                            <flux:select.option value="{{ $departement->id }}">{{ $departement->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </flux:field>
                            @endif
                            @if ($departementSelectionneId && $this->unitesDisponibles->isNotEmpty())
                                <flux:field>
                                    <flux:select wire:model.live="uniteSelectionneeId" :label="__('Service / Unité')" placeholder="{{ __('— Aucun (le département reçoit directement) —') }}">
                                        <flux:select.option value="">{{ __('— Aucun (le département reçoit directement) —') }}</flux:select.option>
                                        @foreach ($this->unitesDisponibles as $unite)
                                            <flux:select.option value="{{ $unite->id }}">{{ $unite->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </flux:field>
                            @endif
                            @if ($champsProposesAutomatiquement && $form->service_id)
                                <flux:description class="text-brand-warning sm:col-span-3">{{ __('Proposé automatiquement — à vérifier') }}</flux:description>
                            @endif
                            @error('form.service_id') <flux:text class="text-sm text-brand-danger sm:col-span-3">{{ $message }}</flux:text> @enderror
                        </div>
                    @else
                        <flux:text class="self-end pb-2 text-sm text-zinc-500">
                            {{ __('Le service sera choisi par le DGA/ADJ DGA au moment de valider le transfert.') }}
                        </flux:text>
                    @endif
                </div>

                <flux:field>
                    <flux:input wire:model="form.objet" :label="__('Objet du courrier')" class:input="invalid:border-brand-danger valid:border-brand-success" required />
                    @if ($champsProposesAutomatiquement && $form->objet)
                        <flux:description class="text-brand-warning">{{ __('Proposé automatiquement — à vérifier') }}</flux:description>
                    @elseif ($form->confidentialite > 1)
                        <flux:description>{{ __('Courrier confidentiel : l\'objet réel n\'a pas à être consulté par l\'agent qui enregistre — ce texte générique est normal.') }}</flux:description>
                    @endif
                </flux:field>

                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:field>
                        @if ($typeDocumentPersonnalise)
                            <flux:input wire:model="form.type_document" :label="__('Type de document')" placeholder="{{ __('Précisez le type de document…') }}" class:input="invalid:border-brand-danger valid:border-brand-success" required />
                            <flux:link href="#" wire:click.prevent="choisirTypeDocumentDansLaListe" class="text-sm">{{ __('Choisir dans la liste plutôt') }}</flux:link>
                        @else
                            <flux:select wire:model.live="form.type_document" :label="__('Type de document')" placeholder="{{ __('— Choisir —') }}" class="invalid:border-brand-danger valid:border-brand-success" required>
                                @foreach (\App\Livewire\Backend\Forms\CourrierForm::typesDocument() as $type)
                                    <flux:select.option value="{{ $type }}">{{ $type }}</flux:select.option>
                                @endforeach
                                <flux:select.option value="__autre__">{{ __('Autre (préciser)') }}</flux:select.option>
                            </flux:select>
                        @endif
                        @if ($champsProposesAutomatiquement && $form->type_document)
                            <flux:description class="text-brand-warning">{{ __('Proposé automatiquement — à vérifier') }}</flux:description>
                        @endif
                    </flux:field>

                    <flux:select wire:model="form.sous_type_sinistre" :label="__('Sous-type (si sinistre)')" placeholder="{{ __('— Sans objet —') }}">
                        <flux:select.option value="">{{ __('— Sans objet —') }}</flux:select.option>
                        <flux:select.option value="materiel">{{ __('Matériel') }}</flux:select.option>
                        <flux:select.option value="corporel">{{ __('Corporel') }}</flux:select.option>
                    </flux:select>

                    <flux:field>
                        <flux:select wire:model="form.mode_reception" :label="__('Mode de réception')" class="invalid:border-brand-danger valid:border-brand-success" required>
                            <flux:select.option value="depot_physique">{{ __('Dépôt physique') }}</flux:select.option>
                            <flux:select.option value="email">{{ __('Email') }}</flux:select.option>
                            <flux:select.option value="poste">{{ __('Poste') }}</flux:select.option>
                            <flux:select.option value="fax">{{ __('Fax') }}</flux:select.option>
                        </flux:select>
                        @if ($modeReceptionProposeAutomatiquement)
                            <flux:description class="text-brand-warning">{{ __('Proposé automatiquement — à vérifier') }}</flux:description>
                        @endif
                    </flux:field>
                </div>

                <flux:separator text="{{ __('Expéditeur') }}" />

                @if ($champsProposesAutomatiquement)
                    <flux:text class="text-brand-warning">{{ __('Champs ci-dessous proposés automatiquement depuis le texte détecté — vérifiez avant de confirmer.') }}</flux:text>
                @endif

                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:field>
                        <flux:input wire:model="form.expediteur_nom" :label="__('Nom')" />
                        @if ($champsProposesAutomatiquement && $form->expediteur_nom)
                            <flux:description class="text-brand-warning">{{ __('Proposé automatiquement — à vérifier') }}</flux:description>
                        @endif
                    </flux:field>
                    <flux:field>
                        <flux:input wire:model="form.expediteur_organisation" :label="__('Organisation')" />
                        @if ($champsProposesAutomatiquement && $form->expediteur_organisation)
                            <flux:description class="text-brand-warning">{{ __('Proposé automatiquement — à vérifier') }}</flux:description>
                        @endif
                    </flux:field>
                </div>
                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:field>
                        <flux:input wire:model="form.expediteur_telephone" :label="__('Téléphone')" />
                        @if ($champsProposesAutomatiquement && $form->expediteur_telephone)
                            <flux:description class="text-brand-warning">{{ __('Proposé automatiquement — à vérifier') }}</flux:description>
                        @endif
                    </flux:field>
                    <flux:field>
                        <flux:input type="email" wire:model="form.expediteur_email" :label="__('Email')" />
                        @if ($champsProposesAutomatiquement && $form->expediteur_email)
                            <flux:description class="text-brand-warning">{{ __('Proposé automatiquement — à vérifier') }}</flux:description>
                        @endif
                    </flux:field>
                </div>
                <flux:field>
                    <flux:input wire:model="form.expediteur_adresse" :label="__('Adresse')" />
                    @if ($champsProposesAutomatiquement && $form->expediteur_adresse)
                        <flux:description class="text-brand-warning">{{ __('Proposé automatiquement — à vérifier') }}</flux:description>
                    @endif
                </flux:field>

                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:field>
                        <flux:input wire:model="form.expediteur_rc" :label="__('RC (Registre du Commerce)')" />
                        @if ($champsProposesAutomatiquement && $form->expediteur_rc)
                            <flux:description class="text-brand-warning">{{ __('Proposé automatiquement — à vérifier') }}</flux:description>
                        @endif
                    </flux:field>
                    <flux:field>
                        <flux:input wire:model="form.expediteur_niu" :label="__('NIU (Numéro d\'Identifiant Unique)')" />
                        @if ($champsProposesAutomatiquement && $form->expediteur_niu)
                            <flux:description class="text-brand-warning">{{ __('Proposé automatiquement — à vérifier') }}</flux:description>
                        @endif
                    </flux:field>
                </div>

                <flux:field>
                    <flux:textarea wire:model="form.destinataire" :label="__('Destinataire(s)')" rows="2" />
                    @if ($champsProposesAutomatiquement && $form->destinataire)
                        <flux:description class="text-brand-warning">{{ __('Proposé automatiquement — à vérifier') }}</flux:description>
                    @endif
                </flux:field>

                <div class="flex justify-between">
                    <flux:button type="button" variant="outline" x-on:click="precedent()">← {{ __('Précédent') }}</flux:button>
                    <flux:button type="button" variant="primary" x-on:click="suivant()">{{ __('Suivant') }} →</flux:button>
                </div>
            </div>

            {{-- ÉTAPE 3 — Pièces jointes --}}
            <div x-show="etape === 3" x-cloak class="space-y-4 rounded-2xl border border-brand-border bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center gap-2">
                    <flux:icon.paper-clip class="size-4 text-brand-text-secondary" />
                    <flux:heading level="3">{{ __('Informations complémentaires') }}</flux:heading>
                </div>

                <flux:field>
                    <flux:label>
                        {{ $this->brouillon ? __('Fichier supplémentaire (optionnel)') : __('Fichier (optionnel — copie déjà numérique, ex. pièce jointe d\'un email)') }}
                    </flux:label>
                    <input type="file" wire:model="pieceJointe" accept=".pdf,.jpg,.jpeg,.png"
                        class="block w-full text-sm text-zinc-600 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:text-zinc-300 dark:file:bg-zinc-700 dark:hover:file:bg-zinc-600" />
                    <flux:description>
                        @if ($this->brouillon)
                            {{ __('Le document scanné ci-dessus sera automatiquement rattaché comme document principal — inutile de le re-téléverser ici. Ce champ sert uniquement à joindre un fichier EN PLUS (ex. pièce d\'identité, justificatif).') }}
                        @else
                            {{ __('PDF, JPG ou PNG, 10 Mo maximum. Un courrier physique sans copie numérique sera numérisé au Module 2.') }}
                        @endif
                    </flux:description>
                    <flux:error name="pieceJointe" />
                    <div wire:loading wire:target="pieceJointe" class="mt-1 text-sm text-zinc-500">{{ __('Envoi en cours…') }}</div>
                </flux:field>

                <flux:field>
                    <flux:textarea wire:model.live.debounce.300ms="commentaire" :label="__('Commentaires (optionnel)')" rows="3" placeholder="{{ __('Ajouter un commentaire…') }}" maxlength="500" />
                    <flux:error name="commentaire" />
                    <flux:description class="text-end">{{ strlen($commentaire) }}/500</flux:description>
                </flux:field>

                <div class="flex justify-between">
                    <flux:button type="button" variant="outline" x-on:click="precedent()">← {{ __('Précédent') }}</flux:button>
                    <flux:button type="button" variant="primary" x-on:click="suivant()">{{ __('Suivant') }} →</flux:button>
                </div>
            </div>

            {{-- ÉTAPE 4 — Validation (récapitulatif avant enregistrement réel) --}}
            <div x-show="etape === 4" x-cloak class="space-y-4 rounded-2xl border border-brand-border bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center gap-2">
                    <flux:icon.check-badge class="size-4 text-brand-text-secondary" />
                    <flux:heading level="3">{{ __('Validation') }}</flux:heading>
                </div>

                <flux:text class="text-sm text-zinc-500">{{ __('Vérifiez le récapitulatif avant l\'enregistrement définitif — le numéro de référence sera généré à la confirmation.') }}</flux:text>

                <dl class="grid gap-3 rounded-xl border border-brand-border p-4 text-sm sm:grid-cols-2 dark:border-zinc-700">
                    <div><dt class="text-brand-text-secondary">{{ __('Sens') }}</dt><dd class="font-medium">{{ $form->sens === 'entrant' ? __('Entrant') : __('Sortant') }}</dd></div>
                    <div><dt class="text-brand-text-secondary">{{ __('Type de document') }}</dt><dd class="font-medium">{{ $form->type_document ?: '—' }}</dd></div>
                    <div><dt class="text-brand-text-secondary">{{ __('Date de réception') }}</dt><dd class="font-medium">{{ $form->date_mouvement ?: '—' }}</dd></div>
                    <div><dt class="text-brand-text-secondary">{{ __('Priorité') }}</dt><dd class="font-medium capitalize">{{ $form->priorite }}</dd></div>
                    <div><dt class="text-brand-text-secondary">{{ __('Confidentialité') }}</dt><dd class="font-medium">{{ __('Niveau :n', ['n' => $form->confidentialite]) }}</dd></div>
                    <div><dt class="text-brand-text-secondary">{{ __('Objet') }}</dt><dd class="font-medium">{{ $form->objet ?: '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-brand-text-secondary">{{ __('Expéditeur') }}</dt><dd class="font-medium">{{ $form->expediteur_nom ?: $form->expediteur_organisation ?: '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-brand-text-secondary">{{ __('Destinataire(s)') }}</dt><dd class="font-medium">{{ $form->destinataire ?: '—' }}</dd></div>
                </dl>

                <div class="flex items-center justify-between">
                    <flux:button type="button" variant="outline" x-on:click="precedent()">← {{ __('Précédent') }}</flux:button>
                    <div class="flex items-center gap-4">
                        @if ($derniereCourrierId)
                            <flux:button :href="route('courriers.show', $derniereCourrierId)" wire:navigate>{{ __('Voir le courrier') }}</flux:button>
                        @endif
                        <flux:button variant="primary" type="submit">{{ __('Enregistrer le courrier') }}</flux:button>
                    </div>
                </div>
            </div>

            {{-- ÉTAPE 5 — Confirmation --}}
            <div x-show="etape === 5" x-cloak class="space-y-4 rounded-2xl border border-brand-border bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                @if ($derniereReference)
                    <div class="flex flex-col items-center gap-3 py-6 text-center">
                        <div class="flex size-14 items-center justify-center rounded-full bg-brand-success-light">
                            <flux:icon.check-circle class="size-7 text-brand-success-dark" />
                        </div>
                        <flux:heading level="2">{{ __('Courrier enregistré') }}</flux:heading>
                        <flux:text>{{ __('Référence attribuée :') }} <strong>{{ $derniereReference }}</strong></flux:text>
                        <div class="mt-2 flex gap-3">
                            @if ($derniereCourrierId)
                                <flux:button :href="route('courriers.show', $derniereCourrierId)" wire:navigate>{{ __('Voir le courrier') }}</flux:button>
                            @endif
                            <flux:button variant="primary" x-on:click="aller(1)" wire:click="nouveauCourrier">{{ __('Enregistrer un autre courrier') }}</flux:button>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex justify-start">
                <flux:button type="button" variant="ghost" :href="route('dashboard')" wire:navigate>
                    {{ __('Enregistrer et continuer plus tard') }}
                </flux:button>
            </div>
        </div>

        {{-- Colonne latérale : aperçu du document, rappel des règles, procédure
             confidentiel. `sticky top-20 self-start` sur CETTE colonne
             directement (demande explicite de l'utilisateur, revenu de
             `position: fixed` — voir CHANGELOG-AGENT.md, 2026-09-17/18 :
             `fixed` réglait le décrochage près de la navbar mais
             l'utilisateur lui trouvait un aspect "flottant" indésirable) —
             posé sur la colonne ENTIÈRE (panneau ET boîte "Courrier
             confidentiel" ci-dessous ensemble), pas seulement sur le
             panneau : posé uniquement sur le panneau (essayé juste avant),
             la boîte "Courrier confidentiel" restait un élément normal du
             flux, défilait donc SEULE et venait chevaucher/passer derrière
             le panneau désormais figé au-dessus d'elle (constaté par
             l'utilisateur, "make the courrier confidentiel sticky too").
             Le "containing block" d'un élément sticky est la boîte de son
             PARENT direct — ici le <form class="grid"> à deux colonnes,
             toujours au moins aussi haute que l'étape active la plus
             longue — plutôt que de dépendre d'un étirement CSS Grid
             implicite de cette colonne pour donner de la place à un enfant
             sticky. `top-20` (80px, pas `top-6`) : la navbar
             (<flux:header sticky> dans le layout) est elle aussi fixe en
             haut de l'écran — un offset plus petit ferait passer cette
             colonne SOUS la navbar au lieu de s'arrêter juste en dessous
             avec un espace visible. Limite connue et acceptée par
             l'utilisateur : sur une étape du wizard plus courte que cette
             colonne, `sticky` peut décrocher avant la fin réelle du
             défilement — contrepartie du choix explicite de ne pas
             utiliser `position: fixed`. --}}
        <div class="sticky top-20 self-start space-y-6">
            @php
                $estImageApercu = $this->brouillon?->fichier_path
                    && in_array(strtolower(pathinfo($this->brouillon->fichier_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true);
            @endphp
            <div class="rounded-2xl border border-brand-border bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mb-3 flex items-center gap-2">
                    <flux:icon.information-circle class="size-4 text-brand-blue" />
                    <flux:heading level="3">{{ __('Aperçu du document') }}</flux:heading>
                </div>

                @if ($this->brouillon?->fichier_path)
                    @if ($estImageApercu)
                        {{-- Barre d'outils réduite au téléchargement pour une
                             image : le zoom/la recherche n'ont de sens que
                             pour un PDF rendu sur canvas ci-dessous. --}}
                        <div class="mb-2 flex justify-end">
                            @can('telecharger', $this->brouillon) <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('brouillons.telecharger', $this->brouillon)" :aria-label="__('Télécharger')" /> @endcan
                        </div>
                        <img src="{{ route('brouillons.apercu', $this->brouillon) }}" alt="{{ $this->brouillon->nom_original }}" class="h-74 w-full rounded-lg border border-brand-border object-contain dark:border-zinc-700">
                    @else
                        {{-- Barre d'outils intégrée directement au panneau
                             toujours visible (2026-09-17, maquette utilisateur
                             — "Tous les courriers") plutôt que masquée derrière
                             un bouton "Agrandir" ouvrant une modale séparée :
                             recherche/zoom-/pourcentage/zoom+/plein écran
                             (API Fullscreen native sur ce même panneau, pas
                             une modale à part)/télécharger, tout au même
                             endroit. Canvas (pdfjs-dist), pas <iframe> : le
                             lecteur PDF natif du navigateur habille sa page
                             d'un fond qui suit le thème sombre du SYSTÈME,
                             impossible à forcer en blanc depuis notre CSS.
                             Toute la logique (y compris try/catch) vit dans
                             des méthodes de x-data, jamais inlinée
                             directement comme valeur d'un attribut
                             x-init/x-on — un try/catch inliné fait planter
                             l'évaluateur d'expressions d'Alpine ("Unexpected
                             token 'try'", vu en conditions réelles). --}}
                        {{-- IMPORTANT : l'instance pdf.js (this.$el._apercu)
                             n'est JAMAIS stockée comme propriété de x-data —
                             Alpine enveloppe toute propriété de x-data dans
                             un Proxy réactif, et les classes pdf.js internes
                             utilisent de vrais champs privés ECMAScript
                             (#champ) ; appeler une méthode sur un Proxy qui
                             enveloppe un objet à champs privés lève
                             "TypeError: Cannot read private member #n from
                             an object whose class did not declare it" (vu en
                             conditions réelles) — le Proxy n'est pas la même
                             identité d'objet que l'instance réelle, et
                             l'accès à un champ privé natif exige l'identité
                             exacte. Stockée sur l'élément DOM brut
                             (this.$el, jamais suivi par la réactivité
                             d'Alpine) plutôt que sur `this`. --}}
                        {{-- wire:ignore : cette page a de nombreux champs
                             wire:model.live qui déclenchent chacun un
                             aller-retour réseau et un remorphage Livewire de
                             tout le composant — sans wire:ignore, Livewire
                             peut recréer ce nœud (ou un de ses descendants)
                             à l'occasion d'un de ces remorphages, ce qui
                             efface silencieusement this.$el._apercu (une
                             propriété JS posée à la main, pas suivie par
                             Livewire/Alpine) : le clic sur zoomer semble
                             alors "ne rien faire" (le garde-fou
                             `if (! this.$el._apercu) return` s'active en
                             silence) alors que le POURCENTAGE affiché, lui,
                             continue de changer normalement puisqu'il vit
                             dans une propriété Alpine ordinaire, réévaluée
                             sans dépendre de _apercu. wire:key ci-dessous,
                             indexé sur l'id du brouillon : si l'agent change
                             de document (liste déroulante "choisir un autre
                             document scanné en attente"), la clé change et
                             Livewire recrée bel et bien l'élément (une clé
                             différente prime sur wire:ignore) — seuls les
                             remorphages SANS changement de document réel
                             sont ignorés. --}}
                        <div
                            wire:key="apercu-document-{{ $this->brouillon->id }}"
                            wire:ignore
                            x-data="{
                                erreur: null,
                                zoom: 100,
                                url: @js(route('brouillons.apercu', $this->brouillon)),
                                rechercheOuverte: false,
                                requeteRecherche: '',
                                nbResultats: 0,
                                pageActuelle: 1,
                                numPages: 1,
                                // obtenir(), pas creer() : cette instance vit
                                // dans un registre au niveau du module (voir
                                // document-preview.js), PAS sur ce nœud DOM —
                                // elle survit donc même si Livewire recrée
                                // cet élément à l'occasion d'un remorphage
                                // déclenché par un AUTRE champ du formulaire.
                                // Appelée à CHAQUE action (pas une seule fois
                                // à l'init, jamais mise en cache dans une
                                // propriété) et resynchronise à chaque fois
                                // boite/conteneur sur les $refs ACTUELS :
                                // sans ça, l'instance restait valide mais
                                // continuait de dessiner dans l'ancien nœud
                                // DOM détaché par un remorphage précédent —
                                // le rendu réussissait bien, juste invisible.
                                instance() {
                                    const apercu = window.DocumentPreview.obtenir(this.url + ':panneau');
                                    apercu.boite = this.$refs.corps;
                                    apercu.conteneur = this.$refs.conteneurPdf;

                                    return apercu;
                                },
                                async init() {
                                    try {
                                        const apercu = this.instance();
                                        await apercu.charger(this.url, apercu.boite, apercu.conteneur);
                                        this.zoom = Math.round((apercu.echelle / apercu.echelleBase) * 100);
                                        this.pageActuelle = apercu.pageActuelle;
                                        this.numPages = apercu.numPages;
                                    } catch (e) {
                                        console.error('[aperçu document]', e);
                                        this.erreur = e.message ?? String(e);
                                    }
                                },
                                async zoomer(nouveauZoom) {
                                    this.zoom = nouveauZoom;

                                    try {
                                        await this.instance().zoomer(this.zoom);
                                    } catch (e) {
                                        console.error('[aperçu document] zoom', e);
                                        this.erreur = e.message ?? String(e);
                                    }

                                    if (this.requeteRecherche) { this.rechercher(); }
                                },
                                zoomIn() { this.zoomer(Math.min(this.zoom + 25, 200)); },
                                zoomOut() { this.zoomer(Math.max(this.zoom - 25, 50)); },
                                async pageSuivante() {
                                    try {
                                        const apercu = this.instance();
                                        await apercu.pageSuivante();
                                        this.pageActuelle = apercu.pageActuelle;
                                    } catch (e) {
                                        console.error('[aperçu] page suivante', e);
                                        this.erreur = e.message ?? String(e);
                                    }
                                },
                                async pagePrecedente() {
                                    try {
                                        const apercu = this.instance();
                                        await apercu.pagePrecedente();
                                        this.pageActuelle = apercu.pageActuelle;
                                    } catch (e) {
                                        console.error('[aperçu] page précédente', e);
                                        this.erreur = e.message ?? String(e);
                                    }
                                },
                                basculerRecherche() {
                                    this.rechercheOuverte = ! this.rechercheOuverte;

                                    if (! this.rechercheOuverte) {
                                        this.requeteRecherche = '';
                                        this.rechercher();
                                    }
                                },
                                rechercher() {
                                    const apercu = this.instance();
                                    this.nbResultats = apercu.rechercher(this.requeteRecherche);

                                    if (this.nbResultats > 0) { apercu.allerAuPremierResultat(); }
                                },
                            }"
                            x-init="init()"
                        >
                            <div class="mb-2 flex items-center justify-between">
                                <flux:button size="sm" variant="ghost" icon="magnifying-glass" x-on:click="basculerRecherche()" :aria-label="__('Rechercher dans le document')" />
                                <div class="flex items-center gap-1">
                                    <flux:button size="sm" variant="ghost" icon="minus" x-on:click="zoomOut()" :aria-label="__('Zoom -')" />
                                    <span class="w-10 text-center text-xs text-zinc-500" x-text="zoom + '%'"></span>
                                    <flux:button size="sm" variant="ghost" icon="plus" x-on:click="zoomIn()" :aria-label="__('Zoom +')" />
                                    <flux:button size="sm" variant="ghost" icon="arrows-pointing-out" x-on:click="$dispatch('modal-show', { name: 'apercu-agrandi' })" :aria-label="__('Agrandir')" />
                                    @can('telecharger', $this->brouillon) <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('brouillons.telecharger', $this->brouillon)" :aria-label="__('Télécharger')" /> @endcan
                                </div>
                            </div>

                            <div x-show="rechercheOuverte" x-cloak class="mb-2 flex items-center gap-2">
                                <flux:input size="sm" x-model="requeteRecherche" x-on:input.debounce.300ms="rechercher()" placeholder="{{ __('Rechercher…') }}" />
                                <flux:text class="shrink-0 text-xs text-zinc-500" x-show="requeteRecherche">
                                    <span x-text="nbResultats"></span> {{ __('résultat(s)') }}
                                </flux:text>
                            </div>

                            {{-- Navigation par page, pas de défilement à
                                 travers plusieurs pages empilées — demande
                                 explicite de l'utilisateur. Visible
                                 seulement si le document en a plus d'une. --}}
                            <div x-show="numPages > 1" x-cloak class="mb-2 flex items-center justify-center gap-3">
                                <flux:button size="xs" variant="ghost" icon="chevron-left" x-on:click="pagePrecedente()" x-bind:disabled="pageActuelle <= 1" :aria-label="__('Page précédente')" />
                                <flux:text class="text-xs text-zinc-500">
                                    <span x-text="pageActuelle"></span> / <span x-text="numPages"></span>
                                </flux:text>
                                <flux:button size="xs" variant="ghost" icon="chevron-right" x-on:click="pageSuivante()" x-bind:disabled="pageActuelle >= numPages" :aria-label="__('Page suivante')" />
                            </div>

                            {{-- overflow conditionné au niveau de zoom : à
                                 100 % ou moins, la page tient par
                                 construction dans cette boîte — hidden,
                                 aucune barre de défilement visible. Au-delà
                                 de 100 %, le contenu dépasse forcément la
                                 largeur fixe de cette colonne — auto, pour
                                 que l'excédent soit atteignable en
                                 défilant plutôt qu'invisible (recadré),
                                 ce qui donnait l'impression que les
                                 boutons de zoom ne faisaient rien. --}}
                            <div x-ref="corps" class="flex h-74 w-full items-center justify-center rounded-lg border border-brand-border bg-white dark:border-zinc-700" x-bind:class="zoom > 100 ? 'overflow-auto' : 'overflow-hidden'">
                                <p x-show="erreur" x-text="erreur" class="p-2 text-sm text-brand-danger"></p>
                                <div x-ref="conteneurPdf"></div>
                            </div>
                        </div>

                        {{-- Modale "Agrandir" — instance pdf.js séparée de
                             celle de la miniature ci-dessus (même raison que
                             partout ailleurs sur cette page : chaque
                             composant Alpine a son propre état de rendu ;
                             l'instance reste sur this.$el, jamais sur
                             this, pour la même raison "champs privés
                             natifs" que la miniature). Ouverte via
                             $dispatch('modal-show', ...) plutôt que
                             flux:modal.trigger : le bouton déclencheur vit
                             DANS le composant Alpine de la miniature
                             ci-dessus, pas comme enfant direct de cette
                             modale. --}}
                        {{-- wire:ignore + wire:key : même raison que la
                             miniature ci-dessus, voir son commentaire et
                             CHANGELOG-AGENT.md. --}}
                        <div
                            wire:key="apercu-agrandi-{{ $this->brouillon->id }}"
                            wire:ignore
                            x-data="{
                                erreur: null,
                                zoom: 100,
                                url: @js(route('brouillons.apercu', $this->brouillon)),
                                rechercheOuverte: false,
                                requeteRecherche: '',
                                nbResultats: 0,
                                pageActuelle: 1,
                                numPages: 1,
                                instance() {
                                    const apercu = window.DocumentPreview.obtenir(this.url + ':agrandi');
                                    apercu.boite = document.getElementById('apercu-agrandi-corps');
                                    apercu.conteneur = document.getElementById('apercu-agrandi-conteneur');

                                    return apercu;
                                },
                                async rendre() {
                                    try {
                                        const apercu = this.instance();
                                        await apercu.charger(this.url, apercu.boite, apercu.conteneur, true, true);
                                        this.zoom = Math.round((apercu.echelle / apercu.echelleBase) * 100);
                                        this.pageActuelle = apercu.pageActuelle;
                                        this.numPages = apercu.numPages;
                                    } catch (e) {
                                        console.error('[aperçu agrandi]', e);
                                        this.erreur = e.message ?? String(e);
                                    }
                                },
                                async zoomer(nouveauZoom) {
                                    this.zoom = nouveauZoom;

                                    try {
                                        await this.instance().zoomer(this.zoom);
                                    } catch (e) {
                                        console.error('[aperçu agrandi] zoom', e);
                                        this.erreur = e.message ?? String(e);
                                    }

                                    if (this.requeteRecherche) { this.rechercher(); }
                                },
                                zoomIn() { this.zoomer(Math.min(this.zoom + 25, 200)); },
                                zoomOut() { this.zoomer(Math.max(this.zoom - 25, 50)); },
                                async pageSuivante() {
                                    try {
                                        const apercu = this.instance();
                                        await apercu.pageSuivante();
                                        this.pageActuelle = apercu.pageActuelle;
                                    } catch (e) {
                                        console.error('[aperçu] page suivante', e);
                                        this.erreur = e.message ?? String(e);
                                    }
                                },
                                async pagePrecedente() {
                                    try {
                                        const apercu = this.instance();
                                        await apercu.pagePrecedente();
                                        this.pageActuelle = apercu.pageActuelle;
                                    } catch (e) {
                                        console.error('[aperçu] page précédente', e);
                                        this.erreur = e.message ?? String(e);
                                    }
                                },
                                basculerRecherche() {
                                    this.rechercheOuverte = ! this.rechercheOuverte;

                                    if (! this.rechercheOuverte) {
                                        this.requeteRecherche = '';
                                        this.rechercher();
                                    }
                                },
                                rechercher() {
                                    const apercu = this.instance();
                                    this.nbResultats = apercu.rechercher(this.requeteRecherche);

                                    if (this.nbResultats > 0) { apercu.allerAuPremierResultat(); }
                                },
                            }"
                            x-on:modal-show.document="if ($event.detail.name === 'apercu-agrandi') { rendre(); }"
                        >
                            <flux:modal name="apercu-agrandi" variant="bare" class="w-full max-w-2xl!">
                                <div class="overflow-hidden rounded-xl bg-white shadow-lg">
                                    <div class="flex items-center justify-between border-b border-brand-border px-4 py-3">
                                        <flux:heading level="2">{{ __('Aperçu du courrier') }}</flux:heading>
                                        <div class="flex items-center gap-1">
                                            <flux:button size="sm" variant="ghost" icon="magnifying-glass" x-on:click="basculerRecherche()" :aria-label="__('Rechercher dans le document')" />
                                            <flux:button size="sm" variant="ghost" icon="minus" x-on:click="zoomOut()" :aria-label="__('Zoom -')" />
                                            <span class="w-12 text-center text-sm text-zinc-500" x-text="zoom + '%'"></span>
                                            <flux:button size="sm" variant="ghost" icon="plus" x-on:click="zoomIn()" :aria-label="__('Zoom +')" />
                                            @can('telecharger', $this->brouillon) <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('brouillons.telecharger', $this->brouillon)" :aria-label="__('Télécharger')" /> @endcan
                                            <flux:modal.close>
                                                <flux:button size="sm" variant="ghost" icon="x-mark" :aria-label="__('Fermer')" />
                                            </flux:modal.close>
                                        </div>
                                    </div>

                                    <div x-show="numPages > 1" x-cloak class="flex items-center justify-center gap-3 border-b border-brand-border px-4 py-2">
                                        <flux:button size="xs" variant="ghost" icon="chevron-left" x-on:click="pagePrecedente()" x-bind:disabled="pageActuelle <= 1" :aria-label="__('Page précédente')" />
                                        <flux:text class="text-sm text-zinc-500">
                                            {{ __('Page') }} <span x-text="pageActuelle"></span> / <span x-text="numPages"></span>
                                        </flux:text>
                                        <flux:button size="xs" variant="ghost" icon="chevron-right" x-on:click="pageSuivante()" x-bind:disabled="pageActuelle >= numPages" :aria-label="__('Page suivante')" />
                                    </div>

                                    <div x-show="rechercheOuverte" x-cloak class="flex items-center gap-3 border-b border-brand-border px-4 py-2">
                                        <flux:input size="sm" x-model="requeteRecherche" x-on:input.debounce.300ms="rechercher()" placeholder="{{ __('Rechercher dans ce document…') }}" class="max-w-xs" />
                                        <flux:text class="text-xs text-zinc-500" x-show="requeteRecherche">
                                            <span x-text="nbResultats"></span> {{ __('résultat(s)') }}
                                        </flux:text>
                                    </div>

                                    <div id="apercu-agrandi-corps" class="flex h-[70vh] w-full items-center justify-center bg-white p-4" x-bind:class="zoom > 100 ? 'overflow-auto' : 'overflow-hidden'">
                                        <p x-show="erreur" x-text="erreur" class="text-sm text-brand-danger"></p>
                                        <div id="apercu-agrandi-conteneur"></div>
                                    </div>
                                </div>
                            </flux:modal>
                        </div>
                    @endif
                @else
                    <div class="flex h-74 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-brand-border bg-brand-surface-soft text-center">
                        <flux:icon.document class="size-8 text-brand-text-muted" />
                        <span class="text-sm text-brand-text-secondary">{{ __('Aucun document pour l\'instant') }}</span>
                    </div>
                @endif
            </div>

            {{-- Même privilège que la page cible (courriers.creer_confidentiel, 2026-09-23). --}}
            @can('creerConfidentiel', App\Models\Courrier::class)
                <div class="rounded-2xl bg-brand-navy p-4 text-white shadow-sm">
                    <div class="mb-2 flex items-center gap-2">
                        <flux:icon.lock-closed class="size-4" />
                        <flux:heading level="3" class="text-white!">{{ __('Courrier confidentiel ?') }}</flux:heading>
                    </div>
                    <p class="text-sm text-white/80">
                        {{ __('Ne pas ouvrir, ne pas scanner le contenu. Seul le nom du destinataire (visible sur l\'enveloppe) sera enregistré.') }}
                    </p>
                    <flux:button size="sm" class="mt-3" variant="outline" icon="arrow-top-right-on-square" :href="route('courriers.confidentiel')" wire:navigate>
                        {{ __('Voir la procédure') }}
                    </flux:button>
                </div>
            @endcan
        </div>
    </form>
</section>
