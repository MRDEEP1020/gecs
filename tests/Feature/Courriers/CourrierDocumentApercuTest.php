<?php

namespace Tests\Feature\Courriers;

use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\Profil;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourrierDocumentApercuTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateurAvecProfil(string $nomProfil): User
    {
        $profil = Profil::firstOrCreate(['nom' => $nomProfil]);

        return User::factory()->create(['profil_id' => $profil->id]);
    }

    private function courrierEnregistrePar(User $auteur, array $attributs = []): Courrier
    {
        $courrier = Courrier::create(array_merge([
            'numero_reference' => 'GEC-'.now()->year.'-TST-000001',
            'sens' => 'entrant',
            'date_mouvement' => now(),
            'objet' => 'Courrier de test',
            'type_document' => 'Lettre',
            'mode_reception' => 'email',
            'service_id' => Service::factory()->create(['code' => 'TST'])->id,
        ], $attributs));

        CourrierHistorique::create([
            'courrier_id' => $courrier->id,
            'auteur_id' => $auteur->id,
            'action' => 'creation',
        ]);

        return $courrier;
    }

    public function test_le_createur_peut_apercevoir_le_document_sans_le_telecharger(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('courriers/2026/TST/GEC-2026-TST-000001.pdf', 'contenu du document');

        $agent = $this->utilisateurAvecProfil('Agent');
        $courrier = $this->courrierEnregistrePar($agent, ['fichier_path' => 'courriers/2026/TST/GEC-2026-TST-000001.pdf']);

        $this->actingAs($agent);

        $response = $this->get(route('courriers.document.apercu', $courrier));

        $response->assertOk();
        // "inline", pas "attachment" : c'est toute la différence avec
        // courriers.document (téléchargement forcé) — le navigateur affiche
        // le document plutôt que de proposer de l'enregistrer.
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
    }

    public function test_un_agent_tiers_ne_peut_pas_apercevoir_le_document(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('courriers/2026/TST/GEC-2026-TST-000001.pdf', 'contenu du document');

        $createur = $this->utilisateurAvecProfil('Agent');
        $courrier = $this->courrierEnregistrePar($createur, ['fichier_path' => 'courriers/2026/TST/GEC-2026-TST-000001.pdf']);

        $autreAgent = User::factory()->create(['profil_id' => $createur->profil_id]);
        $this->actingAs($autreAgent);

        $this->get(route('courriers.document.apercu', $courrier))->assertForbidden();
    }

    public function test_aucun_document_a_apercevoir_donne_une_404(): void
    {
        $agent = $this->utilisateurAvecProfil('Agent');
        $courrier = $this->courrierEnregistrePar($agent);

        $this->actingAs($agent);

        $this->get(route('courriers.document.apercu', $courrier))->assertNotFound();
    }
}
