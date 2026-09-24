<?php

namespace Tests\Feature\Courriers;

use App\Jobs\IndexCourrierJob;
use App\Livewire\Backend\EditForm;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\PieceJointe;
use App\Models\Profil;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EditFormTest extends TestCase
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
            'objet' => 'Objet initial',
            'type_document' => 'Lettre',
            'mode_reception' => 'email',
            'priorite' => 'normale',
            'confidentialite' => 1,
            'service_id' => Service::factory()->create(['code' => 'TST'])->id,
        ], $attributs));

        CourrierHistorique::create([
            'courrier_id' => $courrier->id,
            'auteur_id' => $auteur->id,
            'action' => 'creation',
            'commentaire' => null,
        ]);

        return $courrier;
    }

    // Module 1 — "Type de document" en liste déroulante (demande explicite
    // de l'utilisateur, 2026-09-08). Un courrier existant peut avoir une
    // valeur hors de cette liste (enregistré avant ce changement, ou classé
    // automatiquement via une règle texte libre — Module 3) : elle doit
    // rester visible et modifiable, jamais perdue silencieusement.

    public function test_un_type_de_document_hors_liste_reste_visible_en_champ_libre(): void
    {
        $agent = $this->utilisateurAvecProfil('Agent');
        $courrier = $this->courrierEnregistrePar($agent, ['type_document' => 'Attestation de non-gage']);

        $this->actingAs($agent);

        Livewire::test(EditForm::class, ['courrierId' => $courrier->id])
            ->assertSet('typeDocumentPersonnalise', true)
            ->assertSet('form.type_document', 'Attestation de non-gage');
    }

    public function test_les_coordonnees_de_lexpediteur_sont_chargees_a_lecran_de_modification(): void
    {
        // Bug réel trouvé le 2026-09-17 en séparant expediteur_coordonnees en
        // téléphone/email/adresse (voir CHANGELOG-AGENT.md) : mount() ne
        // chargeait déjà pas expediteur_rc/expediteur_niu dans le formulaire
        // (valeurs enregistrées mais jamais réaffichées à l'écran de
        // modification) — corrigé au passage pour les 5 champs de ce même
        // bloc, plutôt que de répéter l'oubli pour les 3 nouveaux champs.
        $agent = $this->utilisateurAvecProfil('Agent');
        $courrier = $this->courrierEnregistrePar($agent, [
            'expediteur_telephone' => '(00237)658239075',
            'expediteur_email' => 'contact@itsc-sarl.cm',
            'expediteur_adresse' => 'BP 2138 Yaoundé Cameroun',
            'expediteur_rc' => 'RC/YAO/2019/B/433',
            'expediteur_niu' => 'M051912784615T',
        ]);

        $this->actingAs($agent);

        Livewire::test(EditForm::class, ['courrierId' => $courrier->id])
            ->assertSet('form.expediteur_telephone', '(00237)658239075')
            ->assertSet('form.expediteur_email', 'contact@itsc-sarl.cm')
            ->assertSet('form.expediteur_adresse', 'BP 2138 Yaoundé Cameroun')
            ->assertSet('form.expediteur_rc', 'RC/YAO/2019/B/433')
            ->assertSet('form.expediteur_niu', 'M051912784615T');
    }

    public function test_un_type_de_document_de_la_liste_naffiche_pas_le_champ_libre(): void
    {
        $agent = $this->utilisateurAvecProfil('Agent');
        $courrier = $this->courrierEnregistrePar($agent, ['type_document' => 'Lettre']);

        $this->actingAs($agent);

        Livewire::test(EditForm::class, ['courrierId' => $courrier->id])
            ->assertSet('typeDocumentPersonnalise', false);
    }

    public function test_lagent_createur_peut_modifier_son_courrier(): void
    {
        $agent = $this->utilisateurAvecProfil('Agent');
        $courrier = $this->courrierEnregistrePar($agent);

        $this->actingAs($agent);

        Livewire::test(EditForm::class, ['courrierId' => $courrier->id])
            ->set('form.objet', 'Objet corrigé')
            ->set('form.priorite', 'urgente')
            ->call('enregistrerModification')
            ->assertHasNoErrors();

        $courrier->refresh();

        $this->assertSame('Objet corrigé', $courrier->objet);
        $this->assertSame('urgente', $courrier->priorite);
        $this->assertSame('GEC-'.now()->year.'-TST-000001', $courrier->numero_reference);

        $this->assertSame(1, CourrierHistorique::where('courrier_id', $courrier->id)
            ->where('action', 'modification')
            ->where('auteur_id', $agent->id)
            ->count());
    }

    public function test_modifier_lobjet_relance_le_classement_sur_la_queue_indexation(): void
    {
        Queue::fake();

        $agent = $this->utilisateurAvecProfil('Agent');
        $courrier = $this->courrierEnregistrePar($agent);
        $this->actingAs($agent);

        Livewire::test(EditForm::class, ['courrierId' => $courrier->id])
            ->set('form.objet', 'Objet corrigé')
            ->call('enregistrerModification')
            ->assertHasNoErrors();

        Queue::assertPushedOn('indexation', IndexCourrierJob::class, fn (IndexCourrierJob $job) => $job->courrier->id === $courrier->id);
    }

    public function test_modifier_un_champ_hors_objet_expediteur_ne_relance_pas_le_classement(): void
    {
        Queue::fake();

        $agent = $this->utilisateurAvecProfil('Agent');
        $courrier = $this->courrierEnregistrePar($agent);
        $this->actingAs($agent);

        Livewire::test(EditForm::class, ['courrierId' => $courrier->id])
            ->set('form.priorite', 'urgente')
            ->call('enregistrerModification')
            ->assertHasNoErrors();

        Queue::assertNotPushed(IndexCourrierJob::class);
    }

    public function test_un_administrateur_peut_modifier_nimporte_quel_courrier(): void
    {
        $agent = $this->utilisateurAvecProfil('Agent');
        $courrier = $this->courrierEnregistrePar($agent);

        $admin = $this->utilisateurAvecProfil('Administrateur');
        $this->actingAs($admin);

        Livewire::test(EditForm::class, ['courrierId' => $courrier->id])
            ->set('form.objet', 'Objet corrigé par admin')
            ->call('enregistrerModification')
            ->assertHasNoErrors();

        $this->assertSame('Objet corrigé par admin', $courrier->refresh()->objet);
    }

    public function test_un_agent_ne_peut_pas_modifier_le_courrier_dun_autre(): void
    {
        $createur = $this->utilisateurAvecProfil('Agent');
        $courrier = $this->courrierEnregistrePar($createur);

        $autreAgent = User::factory()->create(['profil_id' => $createur->profil_id]);
        $this->actingAs($autreAgent);

        Livewire::test(EditForm::class, ['courrierId' => $courrier->id])
            ->assertForbidden();
    }

    public function test_aucune_entree_dhistorique_si_rien_nest_modifie(): void
    {
        $agent = $this->utilisateurAvecProfil('Agent');
        $courrier = $this->courrierEnregistrePar($agent);

        $this->actingAs($agent);

        Livewire::test(EditForm::class, ['courrierId' => $courrier->id])
            ->call('enregistrerModification')
            ->assertHasNoErrors();

        $this->assertSame(0, CourrierHistorique::where('courrier_id', $courrier->id)
            ->where('action', 'modification')
            ->count());
    }

    public function test_on_peut_ajouter_une_piece_jointe_lors_dune_modification(): void
    {
        Storage::fake('s3');

        $agent = $this->utilisateurAvecProfil('Agent');
        $courrier = $this->courrierEnregistrePar($agent);

        $this->actingAs($agent);

        Livewire::test(EditForm::class, ['courrierId' => $courrier->id])
            ->set('pieceJointe', UploadedFile::fake()->create('copie.pdf', 100, 'application/pdf'))
            ->call('enregistrerModification')
            ->assertHasNoErrors();

        $pieceJointe = $courrier->piecesJointes()->firstOrFail();

        $this->assertSame('copie.pdf', $pieceJointe->nom_original);
        Storage::disk('s3')->assertExists($pieceJointe->fichier_path);

        $this->assertSame(1, CourrierHistorique::where('courrier_id', $courrier->id)
            ->where('action', 'ajout_piece_jointe')
            ->count());
    }

    public function test_les_nouveaux_champs_de_la_maquette_sont_charges_a_lecran_de_modification(): void
    {
        // Maquette "Modifier le courrier" (2026-09-18) — direction_origine_id/
        // echeance/type_traitement/expediteur_fonction/note_interne sont de
        // vrais nouveaux champs (voir migration 2026_09_18_070000), pas de la
        // vue seulement : doivent être rechargés au montage comme le reste.
        $agent = $this->utilisateurAvecProfil('Agent');
        $directionOrigine = Service::factory()->create(['code' => 'DRH']);
        $courrier = $this->courrierEnregistrePar($agent, [
            'direction_origine_id' => $directionOrigine->id,
            'echeance' => '2026-09-25',
            'type_traitement' => 'Traitement classique',
            'expediteur_fonction' => 'Chef de service',
            'note_interne' => 'À traiter en priorité.',
        ]);

        $this->actingAs($agent);

        Livewire::test(EditForm::class, ['courrierId' => $courrier->id])
            ->assertSet('directionOrigineId', $directionOrigine->id)
            ->assertSet('echeance', '2026-09-25')
            ->assertSet('typeTraitement', 'Traitement classique')
            ->assertSet('expediteurFonction', 'Chef de service')
            ->assertSet('noteInterne', 'À traiter en priorité.');
    }

    public function test_les_nouveaux_champs_de_la_maquette_sont_enregistres_et_traces_dans_lhistorique(): void
    {
        $agent = $this->utilisateurAvecProfil('Agent');
        $directionOrigine = Service::factory()->create(['code' => 'DRH']);
        $courrier = $this->courrierEnregistrePar($agent);

        $this->actingAs($agent);

        Livewire::test(EditForm::class, ['courrierId' => $courrier->id])
            ->set('directionOrigineId', $directionOrigine->id)
            ->set('echeance', '2026-09-25')
            ->set('typeTraitement', 'Traitement urgent')
            ->set('expediteurFonction', 'Chef de service')
            ->set('noteInterne', 'Note interne de test.')
            ->call('enregistrerModification')
            ->assertHasNoErrors();

        $courrier->refresh();

        $this->assertSame($directionOrigine->id, $courrier->direction_origine_id);
        $this->assertSame('2026-09-25', $courrier->echeance->format('Y-m-d'));
        $this->assertSame('Traitement urgent', $courrier->type_traitement);
        $this->assertSame('Chef de service', $courrier->expediteur_fonction);
        $this->assertSame('Note interne de test.', $courrier->note_interne);

        $this->assertSame(1, CourrierHistorique::where('courrier_id', $courrier->id)
            ->where('action', 'modification')
            ->count());
    }

    public function test_les_nouveaux_champs_de_la_maquette_ne_sont_pas_obligatoires(): void
    {
        // Aucune règle métier n'impose ces champs à ce jour (voir
        // EditForm::enregistrerModification()) — un courrier existant sans
        // aucune de ces valeurs doit rester modifiable normalement.
        $agent = $this->utilisateurAvecProfil('Agent');
        $courrier = $this->courrierEnregistrePar($agent);

        $this->actingAs($agent);

        Livewire::test(EditForm::class, ['courrierId' => $courrier->id])
            ->set('form.objet', 'Objet corrigé')
            ->call('enregistrerModification')
            ->assertHasNoErrors();

        $this->assertSame('Objet corrigé', $courrier->refresh()->objet);
    }

    public function test_on_peut_ajouter_une_deuxieme_piece_jointe(): void
    {
        Storage::fake('s3');

        $agent = $this->utilisateurAvecProfil('Agent');
        $courrier = $this->courrierEnregistrePar($agent);

        PieceJointe::create([
            'courrier_id' => $courrier->id,
            'fichier_path' => 'courriers/'.now()->year.'/TST/'.$courrier->numero_reference.'/premiere.pdf',
            'nom_original' => 'premiere.pdf',
            'type_mime' => 'application/pdf',
            'taille' => 100,
        ]);

        $this->actingAs($agent);

        Livewire::test(EditForm::class, ['courrierId' => $courrier->id])
            ->set('pieceJointe', UploadedFile::fake()->create('deuxieme.pdf', 100, 'application/pdf'))
            ->call('enregistrerModification')
            ->assertHasNoErrors();

        $this->assertSame(2, $courrier->piecesJointes()->count());
    }
}
