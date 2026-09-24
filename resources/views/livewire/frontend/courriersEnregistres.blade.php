<section class="w-full max-w-5xl">
    <div class="flex items-center gap-3">
        <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-blue text-white">
            <flux:icon.archive-box class="size-5" />
        </div>
        <div>
            <flux:heading level="1">{{ __('Courriers enregistrés') }}</flux:heading>
            <flux:subheading>{{ __('Les courriers entrants déjà enregistrés dans le système, par étape du transfert vers le DGA/ADJ DGA.') }}</flux:subheading>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-2">
        <flux:button size="sm" :variant="$onglet === 'en_attente_de_transfert' ? 'primary' : 'ghost'" wire:click="changerOnglet('en_attente_de_transfert')">
            {{ __('En attente de transfert') }}
        </flux:button>
        <flux:button size="sm" :variant="$onglet === 'en_cours_de_transfert' ? 'primary' : 'ghost'" wire:click="changerOnglet('en_cours_de_transfert')">
            {{ __('En cours de transfert') }}
        </flux:button>
        <flux:button size="sm" :variant="$onglet === 'enregistre' ? 'primary' : 'ghost'" wire:click="changerOnglet('enregistre')">
            {{ __('Transféré') }}
        </flux:button>
    </div>

    <flux:separator class="mt-6" />

    @if ($this->courriers->isEmpty())
        <flux:text class="mt-6 text-zinc-500">
            @if ($onglet === 'en_attente_de_transfert')
                {{ __('Aucun courrier en attente de transfert.') }}
            @elseif ($onglet === 'en_cours_de_transfert')
                {{ __('Aucun courrier en cours de transfert.') }}
            @else
                {{ __('Aucun courrier transféré pour l\'instant.') }}
            @endif
        </flux:text>
    @else
        <flux:heading level="2" class="mt-4 text-base">{{ __('Liste des courriers') }} ({{ $this->courriers->total() }})</flux:heading>

        <div class="mt-3 overflow-x-auto rounded-2xl border border-brand-border bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <table class="w-full text-sm">
                <thead class="bg-brand-blue-pale text-left text-sm font-medium text-zinc-600 dark:bg-zinc-800">
                    <tr>
                        <th class="py-3 pl-4 pr-3">{{ __('Référence') }}</th>
                        <th class="py-3 pr-3">{{ __('Objet') }}</th>
                        <th class="py-3 pr-3">{{ __('Type') }}</th>
                        <th class="py-3 pr-3">{{ __('Service') }}</th>
                        <th class="py-3 pr-4">{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($this->courriers as $courrier)
                        <tr class="cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800" onclick="window.location='{{ route('courriers.show', $courrier->id) }}'">
                            <td class="py-3 pl-4 pr-3 font-medium">
                                <flux:link :href="route('courriers.show', $courrier->id)" wire:navigate class="text-brand-blue">{{ $courrier->numero_reference }}</flux:link>
                            </td>
                            <td class="py-3 pr-3">{{ $courrier->objet }}</td>
                            <td class="py-3 pr-3 text-zinc-500">{{ $courrier->type_document }}</td>
                            <td class="py-3 pr-3 text-zinc-500">{{ $courrier->service?->nom ?? __('—') }}</td>
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
</section>
