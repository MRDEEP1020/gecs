{{-- Module 7 — centre de notifications in-app, page complète (2026-10-05). --}}
<section class="w-full">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate>{{ __('Accueil') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Notifications') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-4">
        <flux:heading level="1">{{ __('Notifications') }}</flux:heading>

        @if ($notifications->contains(fn ($n) => $n->read_at === null))
            <flux:button size="sm" variant="outline" wire:click="marquerToutesCommeLues">
                {{ __('Tout marquer comme lu') }}
            </flux:button>
        @endif
    </div>

    {{-- Retour utilisateur (2026-10-05, capture d'écran : "make it to look
         atleast presentable") — la 1ère version (icône nue, ligne lue
         simplement assombrie à l'opacité) manquait de hiérarchie visuelle.
         Icône dans un rond de couleur pleine (même patron que l'en-tête de
         fiche courrier, voir showCourrier.blade.php : rond bg-brand-blue +
         icône blanche) ; une notification NON lue se distingue par un fond
         teinté + une bordure gauche en couleur de marque, plutôt que l'opacité
         inverse (dimmer les lues) qui fait paraître les lues "désactivées". --}}
    <div class="mt-6 divide-y divide-brand-border overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
        @forelse ($notifications as $notification)
            <a
                href="{{ route('courriers.show', $notification->data['courrier_id']) }}"
                wire:navigate
                wire:click="marquerCommeLue('{{ $notification->id }}')"
                @class([
                    'flex items-center gap-4 border-s-4 p-4 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800',
                    'border-brand-blue bg-brand-blue-pale/40 dark:bg-brand-blue/10' => ! $notification->read_at,
                    'border-transparent' => $notification->read_at,
                ])
            >
                <div @class([
                    'flex size-10 shrink-0 items-center justify-center rounded-full text-white',
                    'bg-brand-danger' => $notification->data['en_retard'],
                    'bg-brand-warning' => ! $notification->data['en_retard'],
                ])>
                    <flux:icon :icon="$notification->data['en_retard'] ? 'exclamation-triangle' : 'clock'" class="size-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text @class(['font-semibold text-brand-text-primary' => ! $notification->read_at, 'font-medium text-brand-text-primary' => $notification->read_at])>
                        {{ $notification->data['en_retard']
                            ? __(':ref est en retard', ['ref' => $notification->data['numero_reference']])
                            : __(':ref arrive à échéance', ['ref' => $notification->data['numero_reference']]) }}
                    </flux:text>
                    @if ($notification->data['objet'] ?? null)
                        <flux:text class="block truncate text-sm text-zinc-500">{{ $notification->data['objet'] }}</flux:text>
                    @endif
                </div>
                <flux:text class="shrink-0 text-xs text-zinc-400">{{ $notification->created_at->diffForHumans() }}</flux:text>
            </a>
        @empty
            <div class="flex flex-col items-center justify-center gap-2 p-10 text-center">
                <div class="flex size-14 items-center justify-center rounded-full bg-brand-surface-soft">
                    <flux:icon.bell class="size-6 text-brand-text-muted" />
                </div>
                <flux:text class="text-zinc-500">{{ __('Aucune notification pour l\'instant.') }}</flux:text>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $notifications->links() }}</div>
</section>
