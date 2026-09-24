<?php

namespace App\Livewire\Backend;

use App\Models\Courrier;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Courriers à traiter')]
class WorkflowQueue extends Component
{
    use WithPagination;

    // Module 4 — "le courrier enregistré arrive dans la file d'attente du
    // responsable du service concerné" ; côté Collaborateur, les courriers
    // qui lui sont actuellement affectés.

    // Panneau latéral "Aperçu du courrier" TOUJOURS affiché (même motif que
    // CourrierList, demande explicite de l'utilisateur étendue à cette
    // page) : un seul id à la fois (Règle n°2), rechargé et réautorisé à
    // chaque requête via courrierApercu() ci-dessous.
    public ?int $courrierApercuId = null;

    public function mount(): void
    {
        $this->authorize('voirFileAttente', Courrier::class);
    }

    public function ouvrirApercu(int $courrierId): void
    {
        $this->courrierApercuId = $courrierId;
    }

    // Sans sélection explicite, le PREMIER résultat de la page courante fait
    // office de défaut (même règle que CourrierList::courrierApercu()) —
    // rechargé avec ses propres relations plutôt que de réutiliser
    // l'instance de courriers() telle quelle (elle n'a pas piecesJointes).
    #[Computed]
    public function courrierApercu(): ?Courrier
    {
        $id = $this->courrierApercuId ?? $this->courriers->first()?->id;

        if ($id === null) {
            return null;
        }

        $courrier = Courrier::query()->with(['service', 'piecesJointes'])->find($id);

        if ($courrier === null || Auth::user()->cannot('view', $courrier)) {
            return null;
        }

        return $courrier;
    }

    // Toujours paginé (Règle n°3), eager loading systématique — jamais de
    // ::all() sur une table qui va grossir.
    #[Computed]
    public function courriers()
    {
        // Périmètre (responsable du service, collaborateur affecté, DGA
        // destinataire du transfert...) : Courrier::scopeVisiblePar(), miroir
        // de CourrierPolicy::view() — 2026-09-23, remplace une copie locale
        // qui ignorait le niveau de confidentialité, l'accès dossier et le
        // périmètre organisationnel.
        return Courrier::query()
            ->visiblePar(Auth::user())
            ->whereIn('statut', WorkflowService::statutsActifs())
            ->with(['service', 'affectationCourante.collaborateur'])
            ->orderBy('date_mouvement')
            // 10 par page (demande explicite de l'utilisateur, 2026-09-14),
            // même changement que CourrierList — auparavant 20.
            ->paginate(10);
    }

    public function render()
    {
        return view('frontend::workflowQueue');
    }
}
