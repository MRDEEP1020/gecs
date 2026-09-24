@assets
    @vite('resources/js/document-preview.js')
@endassets

<section class="w-full">
    <div class="flex items-center gap-3">
        <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-blue text-white">
            <flux:icon.arrow-path-rounded-square class="size-5" />
        </div>
        <div>
            <flux:heading level="1">{{ __('Courriers à traiter') }}</flux:heading>
            <flux:subheading>{{ __('Module 4 — file d\'attente du circuit de validation : vos courriers en cours, du plus ancien au plus récent.') }}</flux:subheading>
        </div>
    </div>

    {{-- Panneau "Aperçu du courrier" TOUJOURS affiché, jamais masqué — même
         principe que "Tous les courriers" : affiche le PREMIER résultat par
         défaut, sans sélection explicite. --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div>
            <flux:heading level="2" class="text-base">{{ __('Liste des courriers') }} ({{ $this->courriers->total() }})</flux:heading>

            @if ($this->courriers->isEmpty())
                <flux:text class="mt-3 text-zinc-500">{{ __('Aucun courrier en attente pour l\'instant.') }}</flux:text>
            @else
                <div class="mt-3 overflow-x-auto rounded-2xl border border-brand-border bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="w-full text-sm">
                        <thead class="bg-brand-blue-pale text-left text-sm font-medium text-zinc-600 dark:bg-zinc-800">
                            <tr>
                                <th class="py-3 pl-4 pr-3">{{ __('Référence') }}</th>
                                <th class="py-3 pr-3">{{ __('Objet') }}</th>
                                <th class="py-3 pr-3">{{ __('Service') }}</th>
                                <th class="py-3 pr-3">{{ __('Statut') }}</th>
                                <th class="py-3 pr-3">{{ __('Affecté à') }}</th>
                                <th class="py-3 pr-4">{{ __('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($this->courriers as $courrier)
                                <tr
                                    wire:click="ouvrirApercu({{ $courrier->id }})"
                                    @class([
                                        'cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800',
                                        'bg-brand-blue/5' => $this->courrierApercu?->id === $courrier->id,
                                    ])
                                >
                                    <td class="py-3 pl-4 pr-3 font-medium">
                                        <flux:link :href="route('courriers.show', ['courrierId' => $courrier->id, 'onglet' => 'circuit'])" wire:navigate wire:click.stop class="text-brand-blue">{{ $courrier->numero_reference }}</flux:link>
                                    </td>
                                    <td class="py-3 pr-3">{{ $courrier->objet }}</td>
                                    {{-- service null-safe : la file du DGA/ADJ DGA montre
                                         des courriers "en cours de transfert", donc sans
                                         service assigné (2026-09-15, voir DECISIONS.md,
                                         synchronisation SRS-GEC.pdf). --}}
                                    <td class="py-3 pr-3">{{ $courrier->service?->nom ?? __('—') }}</td>
                                    <td class="py-3 pr-3">
                                        <x-statut-badge :statut="$courrier->statut" />
                                    </td>
                                    <td class="py-3 pr-3 text-zinc-500">{{ $courrier->affectationCourante?->collaborateur->name ?? __('—') }}</td>
                                    <td class="py-3 pr-4 text-zinc-500">{{ $courrier->date_mouvement->format('d/m/Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $this->courriers->links() }}
                </div>
            @endif
        </div>

        <div class="sticky top-20 self-start space-y-6">
            @if ($this->courrierApercu)
                <div class="rounded-2xl border border-brand-border bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center gap-2 border-b border-brand-border p-4 dark:border-zinc-700">
                        <flux:icon.eye class="size-4 text-brand-blue" />
                        <flux:heading level="3">{{ __('Aperçu du courrier') }}</flux:heading>
                    </div>

                    <div class="p-4">
                        @if ($this->courrierApercu->fichier_path)
                            <div
                                wire:key="apercu-{{ $this->courrierApercu->id }}"
                                wire:ignore
                                x-data="{
                                    erreur: null,
                                    zoom: 100,
                                    rechercheOuverte: false,
                                    requeteRecherche: '',
                                    nbResultats: 0,
                                    url: @js(route('courriers.document.apercu', $this->courrierApercu)),
                                    instance() {
                                        const apercu = window.DocumentPreview.obtenir(this.url + ':a-traiter');
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
                                            console.error('[aperçu file de traitement]', e);
                                            this.erreur = e.message ?? String(e);
                                        }
                                    },
                                    async zoomer(nouveauZoom) {
                                        this.zoom = nouveauZoom;

                                        try {
                                            await this.instance().zoomer(this.zoom);
                                        } catch (e) {
                                            console.error('[aperçu file de traitement] zoom', e);
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
                                        @can('telecharger', $this->courrierApercu)
                                            <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('courriers.document', $this->courrierApercu)" :aria-label="__('Télécharger')" />
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

                    <div class="flex items-center justify-between gap-2 border-t border-brand-border p-4 dark:border-zinc-700">
                        <flux:text class="text-sm text-zinc-500">{{ $this->courrierApercu->numero_reference }}</flux:text>
                        <flux:button size="sm" variant="primary" :href="route('courriers.show', ['courrierId' => $this->courrierApercu->id, 'onglet' => 'circuit'])" wire:navigate>
                            {{ __('Voir la fiche complète') }}
                        </flux:button>
                    </div>
                </div>
            @else
                <div class="flex h-40 flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-brand-border bg-brand-surface-soft text-center">
                    <flux:icon.document class="size-8 text-brand-text-muted" />
                    <span class="text-sm text-brand-text-secondary">{{ __('Aucun courrier à prévisualiser.') }}</span>
                </div>
            @endif
        </div>
    </div>
</section>
