<?php

namespace Tests\Feature;

use App\Livewire\Backend\Dashboard;
use App\Models\Affectation;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\Profil;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Module 10 — tableau de bord (maquette GPT, 2026-09-16, voir DECISIONS.md
// "Tableau de bord") : une seule mise en page, contenu filtré par
// privilège/périmètre — même périmètre que CourrierList::resultats() pour
// les statistiques/la liste, pas des chiffres globaux à l'échelle de
// l'entreprise.
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateurAvecProfil(string $nomProfil, ?Service $service = null): User
    {
        return User::factory()->create([
            'profil_id' => Profil::firstOrCreate(['nom' => $nomProfil])->id,
            'service_id' => $service?->id,
        ]);
    }

    private function courrier(array $attributs = []): Courrier
    {
        return Courrier::create(array_merge([
            'numero_reference' => 'GEC-'.now()->year.'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'sens' => 'entrant',
            'date_mouvement' => now(),
            'objet' => 'Courrier',
            'type_document' => 'Lettre',
            'mode_reception' => 'email',
            'statut' => 'affecte',
        ], $attributs));
    }

    public function test_tout_utilisateur_connecte_accede_au_tableau_de_bord(): void
    {
        $this->actingAs($this->utilisateurAvecProfil('Collaborateur'));

        Livewire::test(Dashboard::class)->assertOk();
    }

    public function test_un_agent_ne_voit_dans_ses_statistiques_que_ses_propres_courriers(): void
    {
        $agent = $this->utilisateurAvecProfil('Agent');
        $autreAgent = $this->utilisateurAvecProfil('Agent');
        $service = Service::factory()->create();

        $lemien = $this->courrier(['sens' => 'entrant', 'date_mouvement' => today(), 'service_id' => $service->id]);
        CourrierHistorique::create(['courrier_id' => $lemien->id, 'auteur_id' => $agent->id, 'action' => 'creation']);

        $pasLeMien = $this->courrier(['sens' => 'entrant', 'date_mouvement' => today(), 'service_id' => $service->id]);
        CourrierHistorique::create(['courrier_id' => $pasLeMien->id, 'auteur_id' => $autreAgent->id, 'action' => 'creation']);

        $this->actingAs($agent);

        $component = Livewire::test(Dashboard::class);

        $this->assertSame(1, $component->get('courrierEntrantAujourdhui')['total']);
    }

    public function test_un_administrateur_voit_toutes_les_statistiques(): void
    {
        $service = Service::factory()->create();
        $this->courrier(['sens' => 'entrant', 'date_mouvement' => today(), 'service_id' => $service->id]);
        $this->courrier(['sens' => 'entrant', 'date_mouvement' => today(), 'service_id' => $service->id]);

        $this->actingAs($this->utilisateurAvecProfil('Administrateur'));

        $component = Livewire::test(Dashboard::class);

        $this->assertSame(2, $component->get('courrierEntrantAujourdhui')['total']);
    }

    public function test_le_delta_reflete_la_difference_avec_hier(): void
    {
        $admin = $this->utilisateurAvecProfil('Administrateur');
        $service = Service::factory()->create();

        $this->courrier(['sens' => 'sortant', 'date_mouvement' => today(), 'service_id' => $service->id]);
        $this->courrier(['sens' => 'sortant', 'date_mouvement' => today(), 'service_id' => $service->id]);
        $this->courrier(['sens' => 'sortant', 'date_mouvement' => today()->subDay(), 'service_id' => $service->id]);

        $this->actingAs($admin);

        $stats = Livewire::test(Dashboard::class)->get('courrierSortantAujourdhui');

        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['delta']);
    }

    public function test_courriers_urgents_ne_compte_que_les_courriers_actifs_de_priorite_urgente(): void
    {
        $admin = $this->utilisateurAvecProfil('Administrateur');
        $service = Service::factory()->create();

        $this->courrier(['priorite' => 'urgente', 'statut' => 'affecte', 'service_id' => $service->id]);
        $this->courrier(['priorite' => 'normale', 'statut' => 'affecte', 'service_id' => $service->id]);
        // Urgent mais déjà traité — ne doit pas compter (pas dans statutsActifs()).
        $this->courrier(['priorite' => 'urgente', 'statut' => 'traite', 'service_id' => $service->id]);

        $this->actingAs($admin);

        $this->assertSame(1, Livewire::test(Dashboard::class)->get('courriersUrgents'));
    }

    // Règle n°6 — même périmètre que WorkflowQueue pour Collaborateur : ne
    // voit que ce qui lui est actuellement affecté.
    public function test_taches_du_jour_est_scope_au_collaborateur(): void
    {
        $collaborateur = $this->utilisateurAvecProfil('Collaborateur');
        $autreCollaborateur = $this->utilisateurAvecProfil('Collaborateur');
        $service = Service::factory()->create();

        $lesien = $this->courrier(['statut' => 'affecte', 'service_id' => $service->id]);
        Affectation::create(['courrier_id' => $lesien->id, 'user_id' => $collaborateur->id, 'affecte_par_id' => $collaborateur->id]);

        $pasLeSien = $this->courrier(['statut' => 'affecte', 'service_id' => $service->id]);
        Affectation::create(['courrier_id' => $pasLeSien->id, 'user_id' => $autreCollaborateur->id, 'affecte_par_id' => $autreCollaborateur->id]);

        $this->actingAs($collaborateur);

        $taches = Livewire::test(Dashboard::class)->get('tachesDuJour');

        $this->assertNotNull($taches);
        $this->assertCount(1, $taches);
        $this->assertSame($lesien->id, $taches->first()->id);
    }

    // Privilège dashboard.taches_du_jour non assigné à Agent par défaut
    // (voir PrivilegeSeeder) — le widget doit rester absent (null), pas une
    // liste vide affichée à tort.
    public function test_taches_du_jour_est_absent_sans_le_privilege(): void
    {
        $this->actingAs($this->utilisateurAvecProfil('Agent'));

        $this->assertNull(Livewire::test(Dashboard::class)->get('tachesDuJour'));
    }

    public function test_les_actions_rapides_de_creation_ne_sapparaissent_qua_ceux_qui_ont_le_privilege(): void
    {
        $this->actingAs($this->utilisateurAvecProfil('Agent'));
        Livewire::test(Dashboard::class)->assertSee('Enregistrer un courrier');

        $this->actingAs($this->utilisateurAvecProfil('Collaborateur'));
        Livewire::test(Dashboard::class)->assertDontSee('Enregistrer un courrier');
    }
}
