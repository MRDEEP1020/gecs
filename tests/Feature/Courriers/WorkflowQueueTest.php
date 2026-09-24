<?php

namespace Tests\Feature\Courriers;

use App\Livewire\Backend\WorkflowQueue;
use App\Models\Affectation;
use App\Models\Courrier;
use App\Models\Profil;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkflowQueueTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateur(string $nomProfil, ?Service $service = null): User
    {
        return User::factory()->create([
            'profil_id' => Profil::firstOrCreate(['nom' => $nomProfil])->id,
            'service_id' => $service?->id,
        ]);
    }

    private function courrier(Service $service, array $attributs = []): Courrier
    {
        return Courrier::create(array_merge([
            'numero_reference' => 'GEC-'.now()->year.'-TST-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'sens' => 'entrant',
            'date_mouvement' => now(),
            'objet' => 'Courrier',
            'type_document' => 'Lettre',
            'mode_reception' => 'email',
            'service_id' => $service->id,
        ], $attributs));
    }

    public function test_un_responsable_ne_voit_que_les_courriers_actifs_de_son_service(): void
    {
        $responsable = $this->utilisateur('Responsable de service');
        $monService = Service::factory()->create(['responsable_id' => $responsable->id, 'code' => 'TST']);
        $autreService = Service::factory()->create(['code' => 'AUT']);

        $aTraiter = $this->courrier($monService, ['statut' => 'enregistre']);
        $closTure = $this->courrier($monService, ['statut' => 'traite']); // clôturé, hors file
        $autreServiceCourrier = $this->courrier($autreService, ['statut' => 'enregistre']); // hors périmètre

        $this->actingAs($responsable);

        Livewire::test(WorkflowQueue::class)
            ->assertSee($aTraiter->numero_reference)
            ->assertDontSee($closTure->numero_reference)
            ->assertDontSee($autreServiceCourrier->numero_reference);
    }

    public function test_un_collaborateur_ne_voit_que_les_courriers_qui_lui_sont_affectes(): void
    {
        $service = Service::factory()->create(['code' => 'TST']);
        $collaborateur = $this->utilisateur('Collaborateur', $service);
        $autreCollaborateur = $this->utilisateur('Collaborateur', $service);

        $lemien = $this->courrier($service, ['statut' => 'en_traitement']);
        Affectation::create(['courrier_id' => $lemien->id, 'user_id' => $collaborateur->id]);

        $pasLeMien = $this->courrier($service, ['statut' => 'en_traitement']);
        Affectation::create(['courrier_id' => $pasLeMien->id, 'user_id' => $autreCollaborateur->id]);

        $this->actingAs($collaborateur);

        Livewire::test(WorkflowQueue::class)
            ->assertSee($lemien->numero_reference)
            ->assertDontSee($pasLeMien->numero_reference);
    }

    public function test_une_dga_ne_voit_que_les_courriers_en_attente_de_sa_validation(): void
    {
        // Module 1/4 — demande explicite de l'utilisateur (2026-09-08) :
        // rôle global (pas de service_id), scopé par statut plutôt que par
        // service — voir DECISIONS.md "Circuit courrier entrant : validation DGA/ADJ".
        // 'en_cours_de_transfert' (2026-09-15, voir DECISIONS.md,
        // synchronisation SRS-GEC.pdf) : le DGA n'a rien à faire tant que la
        // réceptionniste n'a pas cliqué "Transférer".
        $service = Service::factory()->create(['code' => 'TST']);
        $enAttente = $this->courrier($service, ['statut' => 'en_cours_de_transfert']);
        $dejaValide = $this->courrier($service, ['statut' => 'enregistre']);

        $this->actingAs($this->utilisateur('DGA'));

        Livewire::test(WorkflowQueue::class)
            ->assertSee($enAttente->numero_reference)
            ->assertDontSee($dejaValide->numero_reference);
    }

    public function test_un_agent_na_pas_acces_a_la_file_dattente(): void
    {
        $this->actingAs($this->utilisateur('Agent'));

        Livewire::test(WorkflowQueue::class)->assertForbidden();
    }

    public function test_un_administrateur_voit_les_courriers_actifs_de_tous_les_services(): void
    {
        $serviceA = Service::factory()->create(['code' => 'AAA']);
        $serviceB = Service::factory()->create(['code' => 'BBB']);
        $courrierA = $this->courrier($serviceA, ['statut' => 'enregistre']);
        $courrierB = $this->courrier($serviceB, ['statut' => 'affecte']);

        $this->actingAs($this->utilisateur('Administrateur'));

        Livewire::test(WorkflowQueue::class)
            ->assertSee($courrierA->numero_reference)
            ->assertSee($courrierB->numero_reference);
    }
}
