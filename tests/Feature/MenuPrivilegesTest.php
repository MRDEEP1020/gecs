<?php

namespace Tests\Feature;

use App\Livewire\Backend\DossierClassementList;
use App\Livewire\Backend\ScanPremier;
use App\Livewire\Backend\UserList;
use App\Models\Privilege;
use App\Models\Profil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

// Menus pilotés par privilège (2026-09-23, voir DECISIONS.md) : chaque
// entrée de la sidebar dépend d'un privilège, chaque groupe n'apparaît que
// si au moins une de ses entrées est visible. Vérifié sur la page
// "Paramètres" (seule page sans contenu qui répète ces libellés).
class MenuPrivilegesTest extends TestCase
{
    use RefreshDatabase;

    private function avecProfil(string $nom): User
    {
        return User::factory()->create(['profil_id' => Profil::where('nom', $nom)->value('id')]);
    }

    private function avecPrivileges(array $cles): User
    {
        $user = User::factory()->create(['profil_id' => null]);
        $user->privilegesDirectes()->attach(Privilege::whereIn('cle', $cles)->pluck('id'));

        return User::find($user->id);
    }

    private function sidebar(User $user)
    {
        return $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }

    // Défauts = exactement ce que chaque profil voyait avant (aucun accès
    // retiré ni ajouté par la migration vers les privilèges de menu).
    public function test_chaque_profil_voit_ses_menus_par_defaut(): void
    {
        $attendus = [
            'Agent' => [
                'voit' => ['Tableau de bord', 'Tous les courriers', 'Enregistrer un courrier', 'Numérisation & OCR', 'Tous les dossiers', 'Archives', 'Recherche avancée', 'Notifications', 'Aide / Documentation'],
                'pas' => ['Transferts', 'Affectations', 'Créer un dossier', 'Administration', 'Statistiques & Rapports'],
            ],
            'Collaborateur' => [
                'voit' => ['Tableau de bord', 'Transferts', 'Traitement & Réponse', 'Créer un dossier', 'Archives'],
                'pas' => ['Enregistrer un courrier', 'Numérisation & OCR', 'Affectations', 'Administration', 'Statistiques & Rapports'],
            ],
            'Responsable de service' => [
                'voit' => ['Transferts', 'Affectations', 'Statistiques & Rapports', 'Rapports'],
                'pas' => ['Enregistrer un courrier', 'Numérisation & OCR', 'Administration'],
            ],
            'DGA' => [
                'voit' => ['Transferts', 'Statistiques & Rapports'],
                'pas' => ['Enregistrer un courrier', 'Numérisation & OCR', 'Affectations', 'Administration'],
            ],
            'Administrateur' => [
                'voit' => ['Administration', 'Utilisateurs & Accès', 'Profils', 'Organisation', 'Référentiels (règles de classement)', 'Automatisation', 'Workflows', 'Paramètres système', 'Sécurité & Audit', 'Numérisation & OCR'],
                'pas' => [],
            ],
        ];

        foreach ($attendus as $profil => ['voit' => $voit, 'pas' => $pas]) {
            $page = $this->sidebar($this->avecProfil($profil));

            foreach ($voit as $libelle) {
                $page->assertSee($libelle);
            }
            foreach ($pas as $libelle) {
                $page->assertDontSee($libelle);
            }
        }
    }

    // Aucun privilège : ni entrée ni titre de groupe vide — seuls
    // "Paramètres" et "Déconnexion" restent (ne jamais enfermer quelqu'un).
    public function test_sans_privilege_seuls_parametres_et_deconnexion_restent(): void
    {
        $this->sidebar($this->avecPrivileges([]))
            ->assertSee('Paramètres')
            ->assertSee('Déconnexion')
            ->assertDontSee('Général')
            ->assertDontSee('Courriers')
            ->assertDontSee('Dossiers & Archives')
            ->assertDontSee('Notifications')
            ->assertDontSee('Aide / Documentation');
    }

    // Un seul privilège suffit à faire apparaître son entrée ET son groupe
    // (corrige aussi l'ancien oubli : "Organisation" seul n'ouvrait pas
    // le groupe "Administration").
    public function test_un_seul_privilege_fait_apparaitre_son_groupe(): void
    {
        $this->sidebar($this->avecPrivileges(['organisation.view']))
            ->assertSee('Administration')
            ->assertSee('Organisation')
            ->assertDontSee('Utilisateurs & Accès');

        $this->sidebar($this->avecPrivileges(['administration.audit']))
            ->assertSee('Administration')
            ->assertSee('Sécurité & Audit')
            ->assertDontSee('Workflows');
    }

    public function test_sans_dashboard_voir_le_tableau_de_bord_redirige_vers_les_parametres(): void
    {
        $this->actingAs($this->avecPrivileges(['courriers.rechercher']))
            ->get(route('dashboard'))
            ->assertRedirect(route('profile.edit'));
    }

    // courriers.numeriser est distinct de courriers.creer.
    public function test_un_operateur_de_scan_numerise_sans_pouvoir_enregistrer(): void
    {
        Storage::fake('s3');
        Queue::fake();
        $operateur = $this->avecPrivileges(['courriers.numeriser']);
        $this->actingAs($operateur);

        $this->get(route('courriers.numeriser-nouveau'))->assertOk();
        $this->get(route('courriers.nouveau'))->assertForbidden();

        Livewire::test(ScanPremier::class)
            ->set('document', UploadedFile::fake()->create('scan.pdf', 500, 'application/pdf'))
            ->call('numeriser')
            ->assertNoRedirect();

        $this->assertDatabaseCount('courrier_brouillons', 1);
    }

    public function test_enregistrer_sans_numeriser_refuse_la_page_de_scan(): void
    {
        $this->actingAs($this->avecPrivileges(['courriers.creer']));

        $this->get(route('courriers.numeriser-nouveau'))->assertForbidden();
        $this->get(route('courriers.confidentiel'))->assertForbidden();
    }

    public function test_le_noeud_archives_exige_son_privilege_cote_serveur(): void
    {
        $this->actingAs($this->avecPrivileges(['dossiers_classement.voir']));

        Livewire::test(DossierClassementList::class)
            ->call('selectionnerNoeud', 'archives')
            ->assertSet('noeud', '');

        $this->actingAs($this->avecPrivileges(['dossiers_classement.voir', 'dossiers_classement.archives']));

        Livewire::test(DossierClassementList::class)
            ->call('selectionnerNoeud', 'archives')
            ->assertSet('noeud', 'archives');
    }

    // utilisateurs.gerer seul : gérer les comptes, jamais les droits.
    public function test_gerer_les_comptes_sans_gerer_les_privileges(): void
    {
        $gestionnaire = $this->avecPrivileges(['utilisateurs.gerer']);
        $agent = $this->avecProfil('Agent');
        $admin = $this->avecProfil('Administrateur');
        $this->actingAs($gestionnaire);

        // Le bouton (pas le titre de la modale, toujours présent dans le DOM).
        $this->get(route('admin.utilisateurs'))->assertOk()->assertDontSee('wire:click="ouvrirAjout"', false);
        $this->get(route('admin.profils'))->assertForbidden();

        // Coordonnées modifiables, profil et niveau ignorés même si postés.
        Livewire::test(UserList::class)
            ->call('ouvrirEdition', $agent->id)
            ->set('editionPoste', 'Accueil')
            ->set('editionProfilId', $admin->profil_id)
            ->set('editionNiveauConfidentialite', 5)
            ->call('enregistrerEdition')
            ->assertHasNoErrors();

        $agent->refresh();
        $this->assertSame('Accueil', $agent->poste);
        $this->assertNotSame($admin->profil_id, $agent->profil_id);
        $this->assertSame(1, $agent->niveau_confidentialite);

        Livewire::test(UserList::class)->call('ouvrirAjout')->assertForbidden();
        Livewire::test(UserList::class)->call('ouvrirPermissions', $agent->id)->assertForbidden();
        // Un compte administrateur reste intouchable (prise de contrôle via email + reset).
        Livewire::test(UserList::class)->call('ouvrirEdition', $admin->id)->assertForbidden();
        Livewire::test(UserList::class)->call('reinitialiserMotDePasse', $admin->id)->assertForbidden();
    }

    public function test_sans_utilisateurs_gerer_la_page_est_refusee(): void
    {
        $this->actingAs($this->avecProfil('Agent'))
            ->get(route('admin.utilisateurs'))
            ->assertForbidden();
    }
}
