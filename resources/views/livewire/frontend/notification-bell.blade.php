{{-- Module 7 — centre de notifications in-app (2026-10-05), remplace le
     placeholder statique "Aucune notification pour l'instant." qui vivait
     directement dans sidebar.blade.php. `data-notifications-non-lues` ici :
     ce composant vit HORS du bloc @persist('app-sidebar') (voir
     sidebar.blade.php), donc il est toujours frais après un wire:navigate —
     le script en bas de sidebar.blade.php lit cette valeur pour
     resynchroniser le badge de l'item "Notifications" DANS la sidebar
     persistante, qui ne peut pas se recalculer lui-même. --}}
<div data-notifications-non-lues="{{ $this->nombreNonLues }}">
<flux:dropdown position="bottom" align="end">
    {{-- Retour utilisateur (2026-10-05, capture d'écran) : le badge
         précédent (offset -top-1/-end-1, pas de bordure) débordait trop loin
         du bouton et se fondait visuellement avec le reste de l'en-tête.
         Patron "badge de notification" standard : offset réduit (le badge
         reste posé SUR le coin de l'icône plutôt que de flotter à côté),
         `ring` de la couleur de fond de l'en-tête pour le détacher
         proprement (même technique qu'un avatar avec indicateur de statut),
         `pointer-events-none` pour ne jamais voler le clic au bouton. --}}
    <span class="relative inline-flex">
        <flux:button variant="ghost" icon="bell" size="sm" square :aria-label="__('Notifications')" />
        @if ($this->nombreNonLues > 0)
            <span class="badge-notif-pop pointer-events-none absolute -top-0.5 -end-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-danger px-1 text-[10px] font-semibold leading-none text-white ring-2 ring-white dark:ring-zinc-900">
                {{ $this->nombreNonLues > 9 ? '9+' : $this->nombreNonLues }}
            </span>
        @endif
    </span>

    <flux:menu class="w-80">
        <div class="flex items-center justify-between px-3 py-2">
            <flux:text class="font-medium text-brand-text-primary">{{ __('Notifications') }}</flux:text>
            @if ($this->nombreNonLues > 0)
                <button type="button" wire:click="marquerToutesCommeLues" class="text-xs text-brand-blue hover:underline">
                    {{ __('Tout marquer comme lu') }}
                </button>
            @endif
        </div>

        @forelse ($this->notifications as $notification)
            <flux:menu.item
                wire:click="marquerCommeLue('{{ $notification->id }}')"
                :href="route('courriers.show', $notification->data['courrier_id'])"
                wire:navigate
                icon="{{ $notification->data['en_retard'] ? 'exclamation-triangle' : 'clock' }}"
                @class(['!bg-brand-blue-pale/40 dark:!bg-brand-blue/10' => ! $notification->read_at])
            >
                <div class="flex min-w-0 flex-col">
                    <span @class(['truncate text-sm', 'font-semibold' => ! $notification->read_at])>
                        {{ $notification->data['en_retard']
                            ? __(':ref est en retard', ['ref' => $notification->data['numero_reference']])
                            : __(':ref arrive à échéance', ['ref' => $notification->data['numero_reference']]) }}
                    </span>
                    @if ($notification->data['objet'] ?? null)
                        <span class="truncate text-xs text-zinc-400">{{ $notification->data['objet'] }}</span>
                    @endif
                    <span class="text-xs text-zinc-400">{{ $notification->created_at->diffForHumans() }}</span>
                </div>
            </flux:menu.item>
        @empty
            <div class="flex flex-col items-center gap-2 px-3 py-6 text-center">
                <flux:icon.bell class="size-6 text-brand-text-muted" />
                <flux:text class="text-zinc-500">{{ __('Aucune notification pour l\'instant.') }}</flux:text>
            </div>
        @endforelse

        @if ($this->notifications->isNotEmpty())
            <flux:menu.separator />
            <flux:menu.item :href="route('notifications.index')" wire:navigate>
                {{ __('Voir toutes les notifications') }}
            </flux:menu.item>
        @endif
    </flux:menu>
</flux:dropdown>
</div>
