<?php

namespace Tests\Feature\Dossiers;

use App\Livewire\Backend\DossierClassementList;
use App\Models\Courrier;
use App\Models\DossierClassement;
use App\Models\Privilege;
use App\Models\Profil;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Module 3/9 — "Dossiers & Archives" (2026-09-21). Voir DECISIONS.md "Module
// 3 — dossiers de classement" : l'arbre démarre VIDE (aucun dossier
// fabriqué), le partage est un octroi par utilisateur (pas un privilège).
class DossierClassementListTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateurAvecProfil(string $nomProfil, array $attributs = []): User
    {
        $profil = Profil::firstOrCreate(['nom' => $nomProfil]);

        return User::factory()->create(array_merge(['profil_id' => $profil->id], $attributs));
    }

    private function accorderCreer(User $user): void
    {
        Privilege::where('cle', 'dossiers_classement.creer')->firstOrFail()->users()->attach($user->id);
    }

    private function accorderGererTout(User $user): void
    {
        Privilege::where('cle', 'dossiers_classement.gerer_tout')->firstOrFail()->users()->attach($user->id);
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

    // ===== Accès à la page =====

    public function test_un_agent_sans_aucun_privilege_lie_ne_peut_pas_ouvrir_la_page(): void
    {
        $agent = $this->utilisateurAvecProfil('Agent');
        // Depuis le 2026-09-23 : dossiers_classement.voir (plus courriers.rechercher).
        Privilege::where('cle', 'dossiers_classement.voir')->firstOrFail()->profils()->detach();

        $this->actingAs($agent);

        Livewire::test(DossierClassementList::class)->assertForbidden();
    }

    public function test_un_utilisateur_avec_le_privilege_creer_peut_ouvrir_la_page(): void
    {
        $utilisateur = $this->utilisateurAvecProfil('Collaborateur');
        $this->accorderCreer($utilisateur);
        $this->actingAs($utilisateur);

        Livewire::test(DossierClassementList::class)->assertOk();
    }

    // ===== Création =====

    public function test_creer_un_dossier_racine(): void
    {
        $utilisateur = $this->utilisateurAvecProfil('Responsable de service');
        $this->accorderCreer($utilisateur);
        $this->actingAs($utilisateur);

        Livewire::test(DossierClassementList::class)
            ->call('ouvrirCreation')
            ->set('nomDossier', 'Direction Générale')
            ->call('creerDossier')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dossiers_classement', [
            'nom' => 'Direction Générale',
            'parent_id' => null,
            'cree_par_id' => $utilisateur->id,
        ]);
    }

    public function test_creer_un_sous_dossier(): void
    {
        $utilisateur = $this->utilisateurAvecProfil('Responsable de service');
        $this->accorderCreer($utilisateur);
        $parent = DossierClassement::create(['nom' => 'Direction Générale', 'cree_par_id' => $utilisateur->id]);

        $this->actingAs($utilisateur);

        Livewire::test(DossierClassementList::class)
            ->call('ouvrirCreation', $parent->id)
            ->set('nomDossier', 'Bureau du DGA')
            ->call('creerDossier')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dossiers_classement', ['nom' => 'Bureau du DGA', 'parent_id' => $parent->id]);
    }

    public function test_un_utilisateur_sans_privilege_creer_ne_peut_pas_creer(): void
    {
        // Agent : contrairement à Collaborateur/Responsable de service, ce
        // profil n'a PAS dossiers_classement.creer par défaut (voir
        // PrivilegeSeeder.php) — nécessaire pour isoler ce cas de refus.
        $utilisateur = $this->utilisateurAvecProfil('Agent');
        Privilege::where('cle', 'courriers.rechercher')->firstOrFail()->users()->attach($utilisateur->id);
        $this->actingAs($utilisateur);

        Livewire::test(DossierClassementList::class)
            ->call('ouvrirCreation')
            ->assertForbidden();
    }

    // ===== Renommer =====

    public function test_le_createur_peut_renommer_son_dossier(): void
    {
        $utilisateur = $this->utilisateurAvecProfil('Responsable de service');
        $this->accorderCreer($utilisateur);
        $dossier = DossierClassement::create(['nom' => 'Ancien nom', 'cree_par_id' => $utilisateur->id]);

        $this->actingAs($utilisateur);

        Livewire::test(DossierClassementList::class)
            ->call('ouvrirRenommage', $dossier->id)
            ->set('nomRenommage', 'Nouveau nom')
            ->call('renommerDossier')
            ->assertHasNoErrors();

        $this->assertSame('Nouveau nom', $dossier->fresh()->nom);
    }

    public function test_un_autre_utilisateur_sans_gerer_tout_ne_peut_pas_renommer(): void
    {
        $createur = $this->utilisateurAvecProfil('Responsable de service');
        $dossier = DossierClassement::create(['nom' => 'Dossier', 'cree_par_id' => $createur->id]);

        $autre = $this->utilisateurAvecProfil('Collaborateur');
        $this->accorderCreer($autre);
        $this->actingAs($autre);

        Livewire::test(DossierClassementList::class)
            ->call('ouvrirRenommage', $dossier->id)
            ->assertForbidden();
    }

    // ===== Déplacer (anti-cycle) =====

    public function test_deplacer_un_dossier_vers_un_autre_parent(): void
    {
        $utilisateur = $this->utilisateurAvecProfil('Responsable de service');
        $this->accorderCreer($utilisateur);
        $racineA = DossierClassement::create(['nom' => 'Racine A', 'cree_par_id' => $utilisateur->id]);
        $racineB = DossierClassement::create(['nom' => 'Racine B', 'cree_par_id' => $utilisateur->id]);
        $enfant = DossierClassement::create(['nom' => 'Enfant', 'parent_id' => $racineA->id, 'cree_par_id' => $utilisateur->id]);

        $this->actingAs($utilisateur);

        Livewire::test(DossierClassementList::class)
            ->call('ouvrirDeplacement', $enfant->id)
            ->set('nouveauParentId', $racineB->id)
            ->call('deplacerDossier');

        $this->assertSame($racineB->id, $enfant->fresh()->parent_id);
    }

    public function test_un_dossier_ne_peut_pas_devenir_son_propre_sous_dossier(): void
    {
        $utilisateur = $this->utilisateurAvecProfil('Responsable de service');
        $this->accorderCreer($utilisateur);
        $parent = DossierClassement::create(['nom' => 'Parent', 'cree_par_id' => $utilisateur->id]);
        $enfant = DossierClassement::create(['nom' => 'Enfant', 'parent_id' => $parent->id, 'cree_par_id' => $utilisateur->id]);

        $this->actingAs($utilisateur);

        Livewire::test(DossierClassementList::class)
            ->call('ouvrirDeplacement', $parent->id)
            ->set('nouveauParentId', $enfant->id)
            ->call('deplacerDossier');

        // Le déplacement est refusé, la hiérarchie ne change pas.
        $this->assertNull($parent->fresh()->parent_id);
    }

    // ===== Partager (deux boîtes) =====

    public function test_ajouter_et_retirer_un_partage_individuellement(): void
    {
        $createur = $this->utilisateurAvecProfil('Responsable de service');
        $this->accorderCreer($createur);
        $dossier = DossierClassement::create(['nom' => 'Dossier', 'cree_par_id' => $createur->id]);
        $beneficiaire = $this->utilisateurAvecProfil('Collaborateur');

        $this->actingAs($createur);

        $test = Livewire::test(DossierClassementList::class)
            ->call('ouvrirPartage', $dossier->id)
            ->call('ajouterPartage', $beneficiaire->id);

        $this->assertTrue($dossier->utilisateursAutorises()->where('users.id', $beneficiaire->id)->exists());

        $test->call('retirerPartage', $beneficiaire->id);

        $this->assertFalse($dossier->utilisateursAutorises()->where('users.id', $beneficiaire->id)->exists());
    }

    public function test_ajouter_une_selection_en_masse(): void
    {
        $createur = $this->utilisateurAvecProfil('Responsable de service');
        $this->accorderCreer($createur);
        $dossier = DossierClassement::create(['nom' => 'Dossier', 'cree_par_id' => $createur->id]);
        $b1 = $this->utilisateurAvecProfil('Collaborateur');
        $b2 = $this->utilisateurAvecProfil('Collaborateur');

        $this->actingAs($createur);

        Livewire::test(DossierClassementList::class)
            ->call('ouvrirPartage', $dossier->id)
            ->set('selectionPartageDisponibles', [$b1->id, $b2->id])
            ->call('ajouterSelectionPartage');

        $this->assertSame(2, $dossier->utilisateursAutorises()->count());
    }

    public function test_un_utilisateur_partage_voit_desormais_le_dossier(): void
    {
        $createur = $this->utilisateurAvecProfil('Responsable de service');
        $this->accorderCreer($createur);
        $dossier = DossierClassement::create(['nom' => 'Dossier confidentiel', 'cree_par_id' => $createur->id]);

        $beneficiaire = $this->utilisateurAvecProfil('Collaborateur');
        $this->accorderCreer($beneficiaire);

        $this->assertFalse($beneficiaire->can('view', $dossier));

        $dossier->utilisateursAutorises()->attach($beneficiaire->id);

        $this->assertTrue($beneficiaire->fresh()->can('view', $dossier->fresh()));
    }

    // ===== Suppression (bloquée si non vide) =====

    public function test_suppression_bloquee_si_le_dossier_contient_des_courriers(): void
    {
        $utilisateur = $this->utilisateurAvecProfil('Responsable de service');
        $this->accorderCreer($utilisateur);
        $service = Service::factory()->create();
        $dossier = DossierClassement::create(['nom' => 'Dossier', 'cree_par_id' => $utilisateur->id]);
        $this->courrier($service, ['dossier_classement_id' => $dossier->id]);

        $this->actingAs($utilisateur);

        Livewire::test(DossierClassementList::class)
            ->call('ouvrirSuppression', $dossier->id)
            ->call('supprimerDossier');

        $this->assertNotSoftDeleted($dossier);
    }

    public function test_suppression_bloquee_si_le_dossier_a_des_sous_dossiers(): void
    {
        $utilisateur = $this->utilisateurAvecProfil('Responsable de service');
        $this->accorderCreer($utilisateur);
        $parent = DossierClassement::create(['nom' => 'Parent', 'cree_par_id' => $utilisateur->id]);
        DossierClassement::create(['nom' => 'Enfant', 'parent_id' => $parent->id, 'cree_par_id' => $utilisateur->id]);

        $this->actingAs($utilisateur);

        Livewire::test(DossierClassementList::class)
            ->call('ouvrirSuppression', $parent->id)
            ->call('supprimerDossier');

        $this->assertNotSoftDeleted($parent);
    }

    // ===== Ajouter/retirer des courriers (2026-09-22, "les trois" points
    // d'entrée pour ranger un courrier dans un dossier) =====

    public function test_la_recherche_de_courriers_a_ajouter_exclut_ceux_deja_dans_le_dossier(): void
    {
        // Administrateur plutôt que "Responsable de service" : ce dernier
        // est scopé par Courrier::scopeVisiblePar() à son PROPRE service
        // (indépendamment de tout privilège voir_*) — hors sujet ici, ce
        // test cible uniquement l'exclusion "déjà dans ce dossier".
        $utilisateur = $this->utilisateurAvecProfil('Administrateur');
        $service = Service::factory()->create();
        $dossier = DossierClassement::create(['nom' => 'Dossier', 'cree_par_id' => $utilisateur->id]);

        $dejaClasse = $this->courrier($service, ['numero_reference' => 'GEC-2026-TST-000001', 'dossier_classement_id' => $dossier->id]);
        $nonClasse = $this->courrier($service, ['numero_reference' => 'GEC-2026-TST-000002']);

        $this->actingAs($utilisateur);

        $resultats = Livewire::test(DossierClassementList::class)
            ->call('selectionnerDossier', $dossier->id)
            ->set('rechercheCourriersAAjouter', 'GEC-2026-TST')
            ->get('courriersDisponiblesPourAjout');

        $this->assertFalse($resultats->pluck('id')->contains($dejaClasse->id));
        $this->assertTrue($resultats->pluck('id')->contains($nonClasse->id));
    }

    // Signalé explicitement par l'utilisateur (2026-09-22, "PAGINATIONS") :
    // le picker limitait silencieusement à 15 résultats sans pagination.
    public function test_la_recherche_de_courriers_a_ajouter_est_paginee(): void
    {
        $utilisateur = $this->utilisateurAvecProfil('Administrateur');
        $service = Service::factory()->create();
        $dossier = DossierClassement::create(['nom' => 'Dossier', 'cree_par_id' => $utilisateur->id]);

        for ($i = 1; $i <= 12; $i++) {
            $this->courrier($service, ['numero_reference' => 'GEC-2026-TST-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT)]);
        }

        $this->actingAs($utilisateur);

        $resultats = Livewire::test(DossierClassementList::class)
            ->call('selectionnerDossier', $dossier->id)
            ->set('rechercheCourriersAAjouter', 'GEC-2026-TST')
            ->get('courriersDisponiblesPourAjout');

        $this->assertSame(10, $resultats->perPage());
        $this->assertSame(12, $resultats->total());
    }

    public function test_ajouter_une_selection_de_courriers_les_classe_dans_le_dossier_ouvert(): void
    {
        $utilisateur = $this->utilisateurAvecProfil('Responsable de service');
        $this->accorderCreer($utilisateur);
        Privilege::where('cle', 'courriers.voir_tout')->firstOrFail()->users()->attach($utilisateur->id);
        $service = Service::factory()->create();
        $dossier = DossierClassement::create(['nom' => 'Dossier', 'cree_par_id' => $utilisateur->id]);

        $premier = $this->courrier($service);
        $second = $this->courrier($service);

        $this->actingAs(User::find($utilisateur->id));

        Livewire::test(DossierClassementList::class)
            ->call('selectionnerDossier', $dossier->id)
            ->call('ouvrirAjoutCourriers')
            ->set('courriersAAjouter', [$premier->id, $second->id])
            ->call('ajouterCourriersSelection');

        $this->assertSame($dossier->id, $premier->refresh()->dossier_classement_id);
        $this->assertSame($dossier->id, $second->refresh()->dossier_classement_id);
    }

    // Confirme la décision utilisateur (AskUserQuestion, 2026-09-22) : un
    // utilisateur simplement PARTAGÉ (ni créateur, ni responsable) peut lui
    // aussi ajouter des courriers — pas seulement consulter, et sans qu'aucun
    // privilège global ne soit nécessaire pour ça.
    public function test_un_utilisateur_partage_peut_ajouter_des_courriers_au_dossier(): void
    {
        $createur = $this->utilisateurAvecProfil('Responsable de service');
        $this->accorderCreer($createur);
        $dossier = DossierClassement::create(['nom' => 'Dossier partagé', 'cree_par_id' => $createur->id]);

        $beneficiaire = $this->utilisateurAvecProfil('Collaborateur');
        Privilege::where('cle', 'courriers.voir_tout')->firstOrFail()->users()->attach($beneficiaire->id);
        $dossier->utilisateursAutorises()->attach($beneficiaire->id);
        $beneficiaireFrais = User::find($beneficiaire->id);

        $service = Service::factory()->create();
        $courrier = $this->courrier($service);

        $this->actingAs($beneficiaireFrais);

        Livewire::test(DossierClassementList::class)
            ->call('selectionnerDossier', $dossier->id)
            ->call('ouvrirAjoutCourriers')
            ->set('courriersAAjouter', [$courrier->id])
            ->call('ajouterCourriersSelection');

        $this->assertSame($dossier->id, $courrier->refresh()->dossier_classement_id);
    }

    public function test_retirer_un_courrier_du_dossier_vide_le_champ(): void
    {
        $utilisateur = $this->utilisateurAvecProfil('Responsable de service');
        $this->accorderCreer($utilisateur);
        Privilege::where('cle', 'courriers.voir_tout')->firstOrFail()->users()->attach($utilisateur->id);
        $service = Service::factory()->create();
        $dossier = DossierClassement::create(['nom' => 'Dossier', 'cree_par_id' => $utilisateur->id]);
        $courrier = $this->courrier($service, ['dossier_classement_id' => $dossier->id]);

        $this->actingAs(User::find($utilisateur->id));

        Livewire::test(DossierClassementList::class)
            ->call('retirerCourrierDuDossier', $courrier->id);

        $this->assertNull($courrier->refresh()->dossier_classement_id);
    }

    public function test_suppression_autorisee_si_le_dossier_est_vide(): void
    {
        $utilisateur = $this->utilisateurAvecProfil('Responsable de service');
        $this->accorderCreer($utilisateur);
        $dossier = DossierClassement::create(['nom' => 'Dossier vide', 'cree_par_id' => $utilisateur->id]);

        $this->actingAs($utilisateur);

        Livewire::test(DossierClassementList::class)
            ->call('ouvrirSuppression', $dossier->id)
            ->call('supprimerDossier');

        $this->assertSoftDeleted($dossier);
    }

    // ===== Nœuds virtuels =====

    public function test_le_noeud_courriers_generaux_ne_montre_que_les_non_classes(): void
    {
        $utilisateur = $this->utilisateurAvecProfil('Administrateur');
        $service = Service::factory()->create();
        $dossier = DossierClassement::create(['nom' => 'Dossier', 'cree_par_id' => $utilisateur->id]);
        $classe = $this->courrier($service, ['dossier_classement_id' => $dossier->id]);
        $general = $this->courrier($service);

        $this->actingAs($utilisateur);

        $test = Livewire::test(DossierClassementList::class)->call('selectionnerNoeud', 'generaux');

        $ids = $test->get('courriersDuNoeud')->pluck('id');
        $this->assertTrue($ids->contains($general->id));
        $this->assertFalse($ids->contains($classe->id));
    }

    public function test_le_noeud_archives_ne_montre_que_les_courriers_archives(): void
    {
        $utilisateur = $this->utilisateurAvecProfil('Administrateur');
        $service = Service::factory()->create();
        $archive = $this->courrier($service, ['statut' => 'archive']);
        $actif = $this->courrier($service, ['statut' => 'enregistre']);

        $this->actingAs($utilisateur);

        $test = Livewire::test(DossierClassementList::class)->call('selectionnerNoeud', 'archives');

        $ids = $test->get('courriersDuNoeud')->pluck('id');
        $this->assertTrue($ids->contains($archive->id));
        $this->assertFalse($ids->contains($actif->id));
    }

    public function test_selectionner_un_dossier_precis_filtre_ses_courriers(): void
    {
        $utilisateur = $this->utilisateurAvecProfil('Administrateur');
        $service = Service::factory()->create();
        $dossierA = DossierClassement::create(['nom' => 'A', 'cree_par_id' => $utilisateur->id]);
        $dossierB = DossierClassement::create(['nom' => 'B', 'cree_par_id' => $utilisateur->id]);
        $dansA = $this->courrier($service, ['dossier_classement_id' => $dossierA->id]);
        $dansB = $this->courrier($service, ['dossier_classement_id' => $dossierB->id]);

        $this->actingAs($utilisateur);

        $test = Livewire::test(DossierClassementList::class)->call('selectionnerDossier', $dossierA->id);

        $ids = $test->get('courriersDuNoeud')->pluck('id');
        $this->assertTrue($ids->contains($dansA->id));
        $this->assertFalse($ids->contains($dansB->id));
    }

    // ===== Autorisation view() d'un dossier précis =====

    public function test_un_dossier_non_partage_nest_pas_visible_dans_les_details(): void
    {
        $createur = $this->utilisateurAvecProfil('Responsable de service');
        $dossier = DossierClassement::create(['nom' => 'Privé', 'cree_par_id' => $createur->id]);

        $autre = $this->utilisateurAvecProfil('Collaborateur');
        $this->accorderCreer($autre);
        $this->actingAs($autre);

        $test = Livewire::test(DossierClassementList::class)->call('selectionnerDossier', $dossier->id);

        $this->assertNull($test->get('dossierSelectionne'));
    }
}
