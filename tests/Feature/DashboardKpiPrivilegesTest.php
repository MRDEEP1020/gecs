<?php

namespace Tests\Feature;

use App\Livewire\Backend\Dashboard;
use App\Models\Privilege;
use App\Models\Profil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// "on tableau de board all kpi and card there should be permission"
// (2026-09-23, voir DECISIONS.md "Chaque carte du tableau de bord sa
// propre permission") : chaque carte/KPI du tableau de bord — y compris
// les cartes décoratives "Notifications"/"Calendrier" — a désormais sa
// propre clé, remplaçant l'ancienne dashboard.statistiques partagée par
// les 5 cartes principales. Assertions sur les computed properties
// (Livewire::test()->get(...)) plutôt que sur du texte brut : "Notifications"/
// "Calendrier" apparaissent aussi dans la sidebar (menus general.notifications/
// general.aide, sans rapport) — Livewire::test() ne rend que le composant,
// pas le layout complet, donc pas de collision, mais rester sur les valeurs
// PHP est plus robuste et suit le même patron que DashboardTest.php.
class DashboardKpiPrivilegesTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateur(string $profil): User
    {
        return User::factory()->create(['profil_id' => Profil::where('nom', $profil)->value('id')]);
    }

    private function retirer(string $cle, string $profil): void
    {
        Privilege::where('cle', $cle)->firstOrFail()->profils()->detach(Profil::where('nom', $profil)->value('id'));
    }

    private function remettre(string $cle, string $profil): void
    {
        Privilege::where('cle', $cle)->firstOrFail()->profils()->syncWithoutDetaching(Profil::where('nom', $profil)->value('id'));
    }

    public function test_toutes_les_cartes_visibles_par_defaut_pour_un_agent(): void
    {
        $this->actingAs($this->utilisateur('Agent'));

        $component = Livewire::test(Dashboard::class);

        $this->assertNotNull($component->get('courrierEntrantAujourdhui'));
        $this->assertNotNull($component->get('courrierSortantAujourdhui'));
        $this->assertNotNull($component->get('enAttenteDeTraitement'));
        $this->assertNotNull($component->get('courriersUrgents'));
        $this->assertNotNull($component->get('courriersEnRetard'));
        $this->assertTrue($component->get('peutVoirNotifications'));
        $this->assertTrue($component->get('peutVoirCalendrier'));
    }

    // Chaque carte principale (les 5 KPI) dépend d'une clé INDÉPENDANTE des
    // autres — jamais un bloc partagé comme l'ancienne dashboard.statistiques.
    public function test_chaque_carte_kpi_a_sa_propre_cle_independante(): void
    {
        $cartes = [
            'dashboard.courrier_entrant' => 'courrierEntrantAujourdhui',
            'dashboard.courrier_sortant' => 'courrierSortantAujourdhui',
            'dashboard.en_attente' => 'enAttenteDeTraitement',
            'dashboard.urgents' => 'courriersUrgents',
            'dashboard.en_retard' => 'courriersEnRetard',
        ];

        $agent = $this->utilisateur('Agent');

        foreach ($cartes as $cle => $computed) {
            $this->retirer($cle, 'Agent');
            $this->actingAs(User::find($agent->id));

            $component = Livewire::test(Dashboard::class);
            $this->assertNull($component->get($computed), "{$computed} devrait être absent sans {$cle}");

            // Les 4 autres cartes restent visibles — retrait vraiment indépendant.
            foreach ($cartes as $autreCle => $autreComputed) {
                if ($autreCle !== $cle) {
                    $this->assertNotNull($component->get($autreComputed), "{$autreComputed} ne devrait pas être affecté par le retrait de {$cle}");
                }
            }

            $this->remettre($cle, 'Agent');
        }
    }

    public function test_sans_aucune_carte_principale_ni_delai_moyen_la_rangee_disparait(): void
    {
        $agent = $this->utilisateur('Agent');
        foreach (['dashboard.courrier_entrant', 'dashboard.courrier_sortant', 'dashboard.en_attente', 'dashboard.urgents', 'dashboard.en_retard'] as $cle) {
            $this->retirer($cle, 'Agent');
        }
        $this->actingAs(User::find($agent->id));

        $component = Livewire::test(Dashboard::class)->assertOk();

        $this->assertNull($component->get('courrierEntrantAujourdhui'));
        $this->assertNull($component->get('courrierSortantAujourdhui'));
        $this->assertNull($component->get('enAttenteDeTraitement'));
        $this->assertNull($component->get('courriersUrgents'));
        $this->assertNull($component->get('courriersEnRetard'));
    }

    // Notifications et Calendrier : cartes décoratives, mais chacune sa
    // propre clé, indépendante l'une de l'autre.
    public function test_notifications_et_calendrier_ont_des_cles_independantes(): void
    {
        $agent = $this->utilisateur('Agent');
        $this->retirer('dashboard.notifications', 'Agent');
        $this->actingAs(User::find($agent->id));

        $component = Livewire::test(Dashboard::class);
        $this->assertFalse($component->get('peutVoirNotifications'));
        $this->assertTrue($component->get('peutVoirCalendrier'), 'Calendrier ne doit pas être affecté par le retrait de Notifications');

        $this->remettre('dashboard.notifications', 'Agent');
        $this->retirer('dashboard.calendrier', 'Agent');
        $this->actingAs(User::find($agent->id));

        $component = Livewire::test(Dashboard::class);
        $this->assertTrue($component->get('peutVoirNotifications'), 'Notifications ne doit pas être affecté par le retrait de Calendrier');
        $this->assertFalse($component->get('peutVoirCalendrier'));
    }

    public function test_ladministrateur_garde_toutes_les_cartes(): void
    {
        $this->actingAs($this->utilisateur('Administrateur'));

        $component = Livewire::test(Dashboard::class);

        $this->assertNotNull($component->get('courrierEntrantAujourdhui'));
        $this->assertNotNull($component->get('courrierSortantAujourdhui'));
        $this->assertNotNull($component->get('enAttenteDeTraitement'));
        $this->assertNotNull($component->get('courriersUrgents'));
        $this->assertNotNull($component->get('courriersEnRetard'));
        $this->assertTrue($component->get('peutVoirNotifications'));
        $this->assertTrue($component->get('peutVoirCalendrier'));
    }

    // L'ancienne clé partagée n'existe plus dans le catalogue (remplacée
    // par les 5 clés par carte, supprimée explicitement dans le seeder).
    public function test_lancienne_cle_partagee_a_ete_retiree_du_catalogue(): void
    {
        $this->assertDatabaseMissing('privileges', ['cle' => 'dashboard.statistiques']);
    }
}
