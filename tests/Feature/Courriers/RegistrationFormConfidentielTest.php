<?php

namespace Tests\Feature\Courriers;

use App\Livewire\Backend\RegistrationFormConfidentiel;
use App\Livewire\Backend\ScanForm;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\Profil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

// Module 1 — "Cas particulier : courrier confidentiel" (specifications-modules-GEC.md,
// voir DECISIONS.md "Courrier confidentiel") : jamais scanné, jamais d'OCR,
// jamais de classification, envoyé directement à un destinataire choisi.
class RegistrationFormConfidentielTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateurAvecProfil(string $nomProfil): User
    {
        return User::factory()->create(['profil_id' => Profil::firstOrCreate(['nom' => $nomProfil])->id]);
    }

    public function test_un_agent_enregistre_un_courrier_confidentiel_sans_jamais_le_scanner(): void
    {
        $agent = $this->utilisateurAvecProfil('Agent');
        $dga = $this->utilisateurAvecProfil('DGA');
        $agent->destinatairesTransfert()->attach($dga->id);
        $this->actingAs($agent);

        Livewire::test(RegistrationFormConfidentiel::class)
            ->set('nomEnveloppe', 'Monsieur le Directeur Général')
            ->set('dateReception', '2026-09-15')
            ->set('niveauConfidentialite', 3)
            ->set('destinataireSystemeId', $dga->id)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $courrier = Courrier::latest('id')->firstOrFail();

        $this->assertSame('Monsieur le Directeur Général', $courrier->destinataire);
        $this->assertSame('entrant', $courrier->sens);
        $this->assertSame(3, $courrier->confidentialite);
        $this->assertSame('enregistre', $courrier->statut);
        $this->assertNull($courrier->service_id);
        $this->assertNull($courrier->fichier_path);
        $this->assertNull($courrier->texte_ocr);
        $this->assertSame($dga->id, $courrier->destinataire_transfert_id);
        $this->assertSame(2, CourrierHistorique::where('courrier_id', $courrier->id)->count());
    }

    // "Un accusé de réception est généré et remis au déposant" : la
    // réceptionniste qui vient d'enregistrer le courrier doit pouvoir
    // l'imprimer même si son niveau est inférieur à celui du courrier
    // (2026-09-23 — auparavant 403) ; un autre agent, non.
    public function test_lagent_createur_imprime_laccuse_mais_pas_un_autre_agent(): void
    {
        $agent = $this->utilisateurAvecProfil('Agent');
        $dga = $this->utilisateurAvecProfil('DGA');
        $agent->destinatairesTransfert()->attach($dga->id);
        $this->actingAs($agent);

        Livewire::test(RegistrationFormConfidentiel::class)
            ->set('nomEnveloppe', 'Monsieur le Directeur Général')
            ->set('niveauConfidentialite', 3)
            ->set('destinataireSystemeId', $dga->id)
            ->call('enregistrer');

        $courrier = Courrier::latest('id')->firstOrFail();

        $this->get(route('courriers.accuse-reception', $courrier))->assertOk();
        // La fiche, elle, reste refusée (niveau 1 < 3).
        $this->get(route('courriers.show', $courrier->id))->assertForbidden();

        $this->actingAs($this->utilisateurAvecProfil('Agent'))
            ->get(route('courriers.accuse-reception', $courrier))
            ->assertForbidden();
    }

    // Règle n°6 — même garde-fou que ShowCourrier/MesCourriers : un
    // destinataire hors de la liste autorisée de l'agent est refusé.
    public function test_un_destinataire_non_autorise_est_refuse(): void
    {
        $agent = $this->utilisateurAvecProfil('Agent');
        $dga = $this->utilisateurAvecProfil('DGA');
        // Pas de attach() : $dga n'est pas dans la liste autorisée de $agent.
        $this->actingAs($agent);

        Livewire::test(RegistrationFormConfidentiel::class)
            ->set('nomEnveloppe', 'Madame la Directrice')
            ->set('dateReception', '2026-09-15')
            ->set('destinataireSystemeId', $dga->id)
            ->call('enregistrer')
            ->assertHasErrors('destinataireSystemeId');

        $this->assertSame(0, Courrier::count());
    }

    public function test_le_nom_sur_lenveloppe_est_obligatoire(): void
    {
        $agent = $this->utilisateurAvecProfil('Agent');
        $dga = $this->utilisateurAvecProfil('DGA');
        $agent->destinatairesTransfert()->attach($dga->id);
        $this->actingAs($agent);

        Livewire::test(RegistrationFormConfidentiel::class)
            ->set('dateReception', '2026-09-15')
            ->set('destinataireSystemeId', $dga->id)
            ->call('enregistrer')
            ->assertHasErrors('nomEnveloppe');

        $this->assertSame(0, Courrier::count());
    }

    public function test_un_collaborateur_na_pas_acces_au_formulaire(): void
    {
        $this->actingAs($this->utilisateurAvecProfil('Collaborateur'));

        Livewire::test(RegistrationFormConfidentiel::class)->assertForbidden();
    }

    // Défense en profondeur (voir ScanForm::numeriser()) : même un courrier
    // déjà enregistré ne peut pas être scanné après coup si sa
    // confidentialité change (ex. via EditForm — hors périmètre de cette
    // tâche, voir DECISIONS.md, mais le garde-fou protège quand même ce cas).
    public function test_un_courrier_confidentiel_ne_peut_pas_etre_scanne(): void
    {
        Storage::fake('s3');
        // niveau_confidentialite explicite (2026-09-21, voir
        // CourrierConfidentialiteTest) : sans clearance suffisante, l'agent
        // ne pourrait même plus OUVRIR ce courrier 'confidentiel' (niveau 2)
        // — CourrierPolicy::update() le bloquerait avant d'atteindre le
        // garde-fou $estConfidentiel que ce test vise réellement à prouver.
        $agent = User::factory()->create(['profil_id' => Profil::firstOrCreate(['nom' => 'Agent'])->id, 'niveau_confidentialite' => 2]);
        $courrier = Courrier::create([
            'numero_reference' => 'GEC-2026-000001',
            'sens' => 'entrant',
            'date_mouvement' => now(),
            'objet' => 'Correspondance confidentielle (non ouverte)',
            'type_document' => 'Correspondance confidentielle',
            'mode_reception' => 'depot_physique',
            'confidentialite' => 2,
            'statut' => 'enregistre',
        ]);
        CourrierHistorique::create(['courrier_id' => $courrier->id, 'auteur_id' => $agent->id, 'action' => 'creation']);
        $this->actingAs($agent);

        Livewire::test(ScanForm::class, ['courrierId' => $courrier->id])
            ->set('document', UploadedFile::fake()->image('scan.jpg', 1700, 2200))
            ->call('numeriser');

        $this->assertNull($courrier->refresh()->fichier_path);
        $this->assertSame('non_traite', $courrier->ocr_statut);
    }
}
