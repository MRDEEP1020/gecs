<?php

namespace Tests\Feature\Admin;

use App\Livewire\Backend\OrganisationIndex;
use App\Models\OrganizationUnit;
use App\Models\Profil;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Services créés depuis l'Organisation (2026-09-23, voir DECISIONS.md) : un
// Service / Sous-service de l'organigramme crée ou relie automatiquement son
// service réel (table `services`, utilisée par le routage des courriers).
class ServiceReelSynchroniseurTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private OrganizationUnit $departement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['profil_id' => Profil::where('nom', 'Administrateur')->value('id')]);
        $this->departement = OrganizationUnit::factory()->create(['type' => OrganizationUnit::TYPE_DEPARTMENT, 'name' => 'Direction Technique']);
        $this->actingAs($this->admin);
    }

    private function creerService(string $nom, array $champs = [])
    {
        $test = Livewire::test(OrganisationIndex::class)
            ->call('ouvrirCreation', $this->departement->id)
            ->set('typeNoeud', OrganizationUnit::TYPE_SERVICE)
            ->set('nomNoeud', $nom);

        foreach ($champs as $champ => $valeur) {
            $test->set($champ, $valeur);
        }

        return $test->call('enregistrerNoeud')->assertHasNoErrors();
    }

    public function test_creer_un_service_cree_son_service_reel(): void
    {
        $chef = User::factory()->create();

        $this->creerService('Contentieux Auto', ['codeNoeud' => 'CTX-AUTO', 'responsableIdNoeud' => $chef->id]);

        $unite = OrganizationUnit::where('name', 'Contentieux Auto')->firstOrFail();
        $service = Service::where('nom', 'Contentieux Auto')->firstOrFail();

        $this->assertSame($service->id, $unite->service_id);
        $this->assertSame('CTX-AUTO', $service->code);
        $this->assertSame($chef->id, $service->responsable_id);
        $this->assertTrue($service->actif);
    }

    public function test_un_service_de_meme_nom_existant_est_relie_sans_doublon(): void
    {
        $existant = Service::factory()->create(['nom' => 'Informatique', 'code' => 'INF']);

        $this->creerService('Informatique');

        $this->assertSame(1, Service::where('nom', 'Informatique')->count());
        $this->assertSame($existant->id, OrganizationUnit::where('name', 'Informatique')->value('service_id'));
    }

    public function test_code_derive_du_nom_et_unique(): void
    {
        Service::factory()->create(['nom' => 'Autre', 'code' => 'RC']);

        $this->creerService('Recouvrement Contentieux');

        $this->assertSame('RC-2', Service::where('nom', 'Recouvrement Contentieux')->value('code'));
    }

    public function test_un_departement_ne_cree_pas_de_service(): void
    {
        $avant = Service::count();

        Livewire::test(OrganisationIndex::class)
            ->call('ouvrirCreation', $this->departement->parent_id)
            ->set('typeNoeud', OrganizationUnit::TYPE_DEPARTMENT)
            ->set('nomNoeud', 'Direction Commerciale')
            ->call('enregistrerNoeud')
            ->assertHasNoErrors();

        $this->assertSame($avant, Service::count());
    }

    public function test_renommer_un_service_cree_automatiquement_renomme_le_service_reel(): void
    {
        $this->creerService('Sinistres Auto');
        $unite = OrganizationUnit::where('name', 'Sinistres Auto')->firstOrFail();

        Livewire::test(OrganisationIndex::class)
            ->call('ouvrirModification', $unite->id)
            ->set('nomNoeud', 'Sinistres Automobile')
            ->call('enregistrerNoeud')
            ->assertHasNoErrors();

        $this->assertSame('Sinistres Automobile', $unite->fresh()->service->nom);
    }

    // Relié à la main à un service de nom différent : ne jamais le renommer.
    public function test_un_service_relie_a_la_main_nest_jamais_renomme(): void
    {
        $sinistres = Service::factory()->create(['nom' => 'Sinistres', 'code' => 'SIN']);
        $this->creerService('Sinistre Santé', ['servicePontNoeud' => $sinistres->id]);
        $unite = OrganizationUnit::where('name', 'Sinistre Santé')->firstOrFail();

        Livewire::test(OrganisationIndex::class)
            ->call('ouvrirModification', $unite->id)
            ->set('nomNoeud', 'Sinistres Santé & Prévoyance')
            ->call('enregistrerNoeud');

        $this->assertSame('Sinistres', $sinistres->fresh()->nom);
    }

    public function test_desactiver_le_noeud_desactive_le_service_sauf_sil_est_partage(): void
    {
        $this->creerService('Courrier Interne');
        $unite = OrganizationUnit::where('name', 'Courrier Interne')->firstOrFail();

        Livewire::test(OrganisationIndex::class)->call('basculerStatut', $unite->id);
        $this->assertFalse($unite->fresh()->service->actif);

        Livewire::test(OrganisationIndex::class)->call('basculerStatut', $unite->id);
        $this->assertTrue($unite->fresh()->service->actif);

        // Un second nœud actif partage le même service : il reste actif.
        OrganizationUnit::factory()->create(['type' => OrganizationUnit::TYPE_SERVICE, 'service_id' => $unite->service_id]);
        Livewire::test(OrganisationIndex::class)->call('basculerStatut', $unite->id);
        $this->assertTrue($unite->fresh()->service->actif);
    }

    public function test_definir_un_responsable_le_reporte_sur_le_service(): void
    {
        $this->creerService('Réclamations');
        $unite = OrganizationUnit::where('name', 'Réclamations')->firstOrFail();
        $chef = User::factory()->create();

        Livewire::test(OrganisationIndex::class)
            ->set('noeudId', $unite->id)
            ->call('definirCommeResponsable', $chef->id);

        $this->assertSame($chef->id, $unite->fresh()->service->responsable_id);
    }
}
