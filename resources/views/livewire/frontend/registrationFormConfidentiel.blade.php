<section class="w-full max-w-2xl">
    <flux:heading level="1">{{ __('Enregistrer un courrier confidentiel') }}</flux:heading>
    <flux:subheading>{{ __('Le courrier reste fermé — vous ne relevez que le nom visible sur l\'enveloppe, sans jamais l\'ouvrir ni le scanner.') }}</flux:subheading>

    <flux:callout variant="warning" class="mt-6" icon="lock-closed">
        <flux:callout.text>{{ __('N\'ouvrez pas l\'enveloppe. Ce formulaire n\'a volontairement pas de champ pour joindre un document — un courrier confidentiel n\'est jamais scanné.') }}</flux:callout.text>
    </flux:callout>

    @if ($derniereReference)
        <flux:callout variant="success" class="mt-6" icon="check-circle">
            <flux:callout.heading>{{ __('Courrier confidentiel enregistré') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Référence attribuée :') }} <strong>{{ $derniereReference }}</strong></flux:callout.text>
            <x-slot name="actions">
                <flux:button href="{{ route('courriers.accuse-reception', $derniereCourrierId) }}" target="_blank" size="sm" icon="arrow-down-tray">
                    {{ __('Télécharger l\'accusé de réception') }}
                </flux:button>
                <flux:button :href="route('courriers.show', $derniereCourrierId)" wire:navigate size="sm" variant="ghost">
                    {{ __('Voir le courrier') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <form wire:submit="enregistrer" class="mt-6 space-y-6">
        <flux:input wire:model="nomEnveloppe" :label="__('Nom visible sur l\'enveloppe (destinataire)')" placeholder="{{ __('ex. Monsieur le Directeur Général') }}" />

        <flux:input type="date" wire:model="dateReception" :label="__('Date de réception')" />

        {{-- Niveaux 2-5 uniquement — jamais 1 (Normal), ce formulaire dédié
             implique déjà que le courrier est confidentiel. Options
             NUMÉRIQUES (2026-09-21, "numbers ... not confidential or
             whatever", puis "THE LABEL SHOULD BE NIVEAU 1 OR LEVEL 1"). --}}
        <flux:radio.group wire:model="niveauConfidentialite" :label="__('Niveau de confidentialité')">
            @foreach (range(2, \App\Models\User::niveauConfidentialiteMax()) as $niveau)
                <flux:radio value="{{ $niveau }}" label="{{ __('Niveau :n', ['n' => $niveau]) }}" />
            @endforeach
        </flux:radio.group>

        <div>
            @if ($this->destinatairesTransfert->isEmpty())
                <flux:text class="text-zinc-500">{{ __('Aucun destinataire autorisé pour votre compte. Contactez un administrateur.') }}</flux:text>
            @else
                <flux:radio.group wire:model="destinataireSystemeId" :label="__('Envoyer directement à')">
                    @foreach ($this->destinatairesTransfert as $destinataire)
                        <flux:radio value="{{ $destinataire->id }}" label="{{ $destinataire->name }}" />
                    @endforeach
                </flux:radio.group>
                @error('destinataireSystemeId') <flux:text class="mt-1 text-sm text-brand-danger">{{ $message }}</flux:text> @enderror
            @endif
        </div>

        <div class="flex items-center gap-4">
            @if ($derniereCourrierId)
                <flux:button :href="route('courriers.show', $derniereCourrierId)" wire:navigate>
                    {{ __('Voir le courrier') }}
                </flux:button>
            @endif
            <flux:button variant="primary" type="submit" :disabled="$this->destinatairesTransfert->isEmpty()" @class(['ms-auto' => $derniereCourrierId])>
                {{ __('Enregistrer le courrier confidentiel') }}
            </flux:button>
        </div>
    </form>
</section>
