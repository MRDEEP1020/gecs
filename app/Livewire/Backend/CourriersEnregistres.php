<?php

namespace App\Livewire\Backend;

use App\Models\Courrier;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

// Module 1/4/8 — "Courriers enregistrés" (maquette GPT, 2026-09-16, voir
// DECISIONS.md "Navigation (navbar + sidebar)") : clarifié avec
// l'utilisateur — PAS un simple lien de plus vers CourrierList, une vraie
// page reprenant la logique des 3 onglets de MesCourriers (en attente / en
// cours / transféré), mais à l'échelle de ce que l'utilisateur peut voir
// (même périmètre que CourrierList::resultats()), pas limitée à ses
// PROPRES courriers comme MesCourriers. Vue seule — pas de transfert en
// masse ici (ça reste le rôle de MesCourriers pour la réceptionniste sur
// ses propres courriers).
#[Title('Courriers enregistrés')]
class CourriersEnregistres extends Component
{
    use WithPagination;

    public const ONGLETS = ['en_attente_de_transfert', 'en_cours_de_transfert', 'enregistre'];

    #[Url]
    public string $onglet = 'en_attente_de_transfert';

    public function mount(): void
    {
        // Privilège de lecture dédié courriers.voir_enregistres (2026-09-23).
        $this->authorize('voirEnregistres', Courrier::class);

        if (! in_array($this->onglet, self::ONGLETS, true)) {
            $this->onglet = 'en_attente_de_transfert';
        }
    }

    public function changerOnglet(string $onglet): void
    {
        if (! in_array($onglet, self::ONGLETS, true)) {
            return;
        }

        $this->onglet = $onglet;
        $this->resetPage();
    }

    // Même périmètre que CourrierPolicy::view() via Courrier::scopeVisiblePar()
    // (2026-09-23 — remplace une copie locale qui ignorait le niveau de
    // confidentialité, l'accès dossier et le périmètre organisationnel).
    // 'entrant' uniquement : les 3 sous-statuts de
    // transfert n'existent que pour un courrier entrant (un sortant démarre
    // directement à 'enregistre'), inclure le sortant noierait l'onglet
    // "Transféré" sous des courriers qui n'ont jamais été "transférés".
    #[Computed]
    public function courriers()
    {
        return Courrier::query()
            ->visiblePar(Auth::user())
            ->where('sens', 'entrant')
            ->where('statut', $this->onglet)
            ->with('service')
            ->latest('date_mouvement')
            ->paginate(10);
    }

    public function render()
    {
        return view('frontend::courriersEnregistres');
    }
}
