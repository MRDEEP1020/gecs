<?php

namespace Tests\Feature\Courriers;

use App\Livewire\Backend\ShowCourrier;
use App\Models\Affectation;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\OrganizationUnit;
use App\Models\Profil;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CircuitCourrierTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateur(string $nomProfil, ?Service $service = null): User
    {
        return User::factory()->create([
            'profil_id' => Profil::firstOrCreate(['nom' => $nomProfil])->id,
            'service_id' => $service?->id,
        ]);
    }

    // Module "Organisation" v2 (2026-09-22) — la cascade Site/Département de
    // ShowCourrier résout un VRAI service_id via ce pont (voir
    // OrganizationUnit::service_id) ; ces tests créent un Département ponté
    // directement (spec §1 : un Département peut être la destination finale
    // sans Service en dessous), suffisant pour ce qui est testé ici.
    private function departementPonte(Service $service): OrganizationUnit
    {
        $site = OrganizationUnit::factory()->create(['type' => OrganizationUnit::TYPE_SITE]);

        return OrganizationUnit::factory()->create([
            'type' => OrganizationUnit::TYPE_DEPARTMENT,
            'parent_id' => $site->id,
            'service_id' => $service->id,
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

    public function test_le_responsable_affecte_un_collaborateur_de_son_service(): void
    {
        $responsable = $this->utilisateur('Responsable de service');
        $service = Service::factory()->create(['responsable_id' => $responsable->id, 'code' => 'TST']);
        $collaborateur = $this->utilisateur('Collaborateur', $service);
        $courrier = $this->courrier($service);
        $this->actingAs($responsable);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->assertSee('Pas encore affecté')
            ->set('collaborateurSelectionne', $collaborateur->id)
            ->call('affecter')
            ->assertHasNoErrors()
            ->assertSee($collaborateur->name);

        $courrier->refresh();

        $this->assertSame('affecte', $courrier->statut);
        $this->assertSame($collaborateur->id, $courrier->affectationCourante->user_id);
        $this->assertSame(1, CourrierHistorique::where('courrier_id', $courrier->id)->where('action', 'affectation')->count());
    }

    // Module 1/4 — validation DGA/ADJ du service, demande explicite de
    // l'utilisateur (2026-09-08) : voir DECISIONS.md "Circuit courrier
    // entrant : validation DGA/ADJ".

    public function test_une_dga_valide_le_service_dun_courrier(): void
    {
        $dga = $this->utilisateur('DGA');
        $serviceInitial = Service::factory()->create(['code' => 'TST']);
        $serviceRetenu = Service::factory()->create(['code' => 'AUT']);
        $departement = $this->departementPonte($serviceRetenu);
        // 'en_cours_de_transfert' (2026-09-15, voir DECISIONS.md,
        // synchronisation SRS-GEC.pdf) : validerService() n'est plus
        // atteignable qu'après que la réceptionniste ait cliqué "Transférer".
        $courrier = $this->courrier($serviceInitial, ['statut' => 'en_cours_de_transfert']);
        $this->actingAs($dga);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->set('siteSelectionneId', $departement->parent_id)
            ->set('departementSelectionneId', $departement->id)
            ->call('validerService')
            ->assertHasNoErrors();

        $courrier->refresh();

        $this->assertSame('enregistre', $courrier->statut);
        $this->assertSame($serviceRetenu->id, $courrier->service_id);
        $this->assertSame(1, CourrierHistorique::where('courrier_id', $courrier->id)->where('action', 'service_valide_dga')->count());
    }

    // 2026-09-23, clarification explicite de l'utilisateur (cas réel
    // rencontré : "Direction des Sinistres" créée en base SANS Site
    // au-dessus, faute de nom de site réel confirmé — "leave the site
    // unassigned/null for now") : un Département RACINE (parent_id null)
    // doit rester sélectionnable/utilisable pour valider un service, sans
    // qu'un Site soit choisi au préalable.
    public function test_une_dga_valide_le_service_via_un_departement_racine_sans_site(): void
    {
        $dga = $this->utilisateur('DGA');
        $serviceInitial = Service::factory()->create(['code' => 'TST']);
        $serviceRetenu = Service::factory()->create(['code' => 'AUT']);
        $departementRacine = OrganizationUnit::factory()->create([
            'type' => OrganizationUnit::TYPE_DEPARTMENT,
            'parent_id' => null,
            'service_id' => $serviceRetenu->id,
        ]);
        $courrier = $this->courrier($serviceInitial, ['statut' => 'en_cours_de_transfert']);
        $this->actingAs($dga);

        $composant = Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->assertSet('siteSelectionneId', null)
            ->call('validerService')
            ->assertHasErrors('uniteSelectionneeId');

        $this->assertTrue(collect($composant->get('departementsDisponibles'))->pluck('id')->contains($departementRacine->id));

        $composant
            ->set('departementSelectionneId', $departementRacine->id)
            ->call('validerService')
            ->assertHasNoErrors();

        $this->assertSame($serviceRetenu->id, $courrier->refresh()->service_id);
    }

    // 2026-09-21 — demande explicite de l'utilisateur : la DGA confirme/
    // corrige le niveau de confidentialité RÉEL en même temps que le
    // service (voir WorkflowService::validerService()).
    public function test_le_niveau_de_confidentialite_de_la_receptionniste_est_presilectionne_et_modifiable_par_la_dga(): void
    {
        // niveau_confidentialite explicite (2026-09-21, correction de
        // l'utilisateur — "the dga profile will have ... niveau elevated") :
        // un compte DGA correctement configuré a un niveau élevé, ce n'est
        // plus une exception cachée du code (voir User::niveauConfidentialiteEffectif()).
        $dga = User::factory()->create(['profil_id' => Profil::firstOrCreate(['nom' => 'DGA'])->id, 'niveau_confidentialite' => 3]);
        $service = Service::factory()->create(['code' => 'TST']);
        $departement = $this->departementPonte($service);
        $courrier = $this->courrier($service, ['statut' => 'en_cours_de_transfert', 'confidentialite' => 2]);
        $this->actingAs($dga);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->assertSet('confidentialiteSelectionnee', 2)
            ->set('siteSelectionneId', $departement->parent_id)
            ->set('departementSelectionneId', $departement->id)
            ->set('confidentialiteSelectionnee', 3)
            ->call('validerService')
            ->assertHasNoErrors();

        $this->assertSame(3, $courrier->refresh()->confidentialite);
    }

    public function test_le_service_propose_est_preselectionne_pour_la_validation_dga(): void
    {
        // Même principe que le_moins_charge_est_preselectionne... ci-dessous
        // pour l'affectation : présélection RÉELLE, pas seulement la
        // prétention d'un commentaire. Module "Organisation" v2 — la
        // présélection reconstruit la cascade Site/Département depuis le
        // nœud ponté au service proposé (voir
        // ShowCourrier::preselectionnerCascadeDepuisService()).
        $dga = $this->utilisateur('DGA');
        $serviceInitial = Service::factory()->create(['code' => 'TST']);
        $serviceHumainePropose = Service::factory()->create(['code' => 'AUT']);
        $departement = $this->departementPonte($serviceHumainePropose);
        $courrier = $this->courrier($serviceInitial, [
            'statut' => 'en_cours_de_transfert',
            'service_propose_id' => $serviceHumainePropose->id,
        ]);
        $this->actingAs($dga);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->assertSet('siteSelectionneId', $departement->parent_id)
            ->assertSet('departementSelectionneId', $departement->id)
            ->call('validerService')
            ->assertHasNoErrors();

        $this->assertSame($serviceHumainePropose->id, $courrier->refresh()->service_id);
    }

    // Bug réel constaté le 2026-09-23 : après une validation RÉUSSIE, le
    // courrier quitte "en cours de transfert", la DGA perd le droit de le
    // consulter (branche voir_dga de CourrierPolicy::view()) et le re-rendu
    // affichait une page 403 — l'action semblait avoir échoué.
    public function test_apres_validation_la_dga_est_redirigee_au_lieu_dune_page_403(): void
    {
        $dga = $this->utilisateur('DGA');
        $service = Service::factory()->create(['code' => 'TST']);
        $departement = $this->departementPonte($service);
        $courrier = $this->courrier($service, ['statut' => 'en_cours_de_transfert', 'service_id' => null]);
        $this->actingAs($dga);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->set('siteSelectionneId', $departement->parent_id)
            ->set('departementSelectionneId', $departement->id)
            ->call('validerService')
            ->assertHasNoErrors()
            ->assertRedirect(route('courriers.a-traiter'));

        $this->assertSame($service->id, $courrier->refresh()->service_id);
    }

    // Bug réel constaté le 2026-09-23 : courrier validé vers "Sinistre Santé"
    // (unité d'un AUTRE département, restée en mémoire) alors que l'écran
    // affichait "Departement Informatique".
    public function test_une_unite_perimee_dun_autre_departement_est_refusee(): void
    {
        $dga = $this->utilisateur('DGA');
        $serviceSinistres = Service::factory()->create(['code' => 'SIN']);
        $serviceSante = Service::factory()->create(['code' => 'SAN']);
        $serviceInfo = Service::factory()->create(['code' => 'INF']);
        $sinistres = OrganizationUnit::factory()->create(['type' => OrganizationUnit::TYPE_DEPARTMENT, 'parent_id' => null, 'service_id' => $serviceSinistres->id]);
        $sante = OrganizationUnit::factory()->create(['type' => OrganizationUnit::TYPE_SERVICE, 'parent_id' => $sinistres->id, 'service_id' => $serviceSante->id]);
        $info = OrganizationUnit::factory()->create(['type' => OrganizationUnit::TYPE_DEPARTMENT, 'parent_id' => null, 'service_id' => $serviceInfo->id]);
        $courrier = $this->courrier($serviceInfo, ['statut' => 'en_cours_de_transfert', 'service_id' => null]);
        $this->actingAs($dga);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->set('departementSelectionneId', $info->id)
            ->set('uniteSelectionneeId', $sante->id)
            ->call('validerService')
            ->assertHasErrors('uniteSelectionneeId')
            ->assertSet('uniteSelectionneeId', null);

        $this->assertSame('en_cours_de_transfert', $courrier->refresh()->statut);
        $this->assertNull($courrier->service_id);
    }

    public function test_sans_departement_choisi_le_message_le_dit_clairement(): void
    {
        $dga = $this->utilisateur('DGA');
        $service = Service::factory()->create(['code' => 'TST']);
        $this->departementPonte($service);
        $courrier = $this->courrier($service, ['statut' => 'en_cours_de_transfert', 'service_id' => null]);
        $this->actingAs($dga);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->call('validerService')
            ->assertHasErrors('uniteSelectionneeId')
            ->assertSee(__('Choisissez un département (et, si besoin, un service).'));
    }

    public function test_un_responsable_de_service_ne_peut_pas_valider_le_service(): void
    {
        $responsable = $this->utilisateur('Responsable de service');
        $service = Service::factory()->create(['responsable_id' => $responsable->id, 'code' => 'TST']);
        $courrier = $this->courrier($service, ['statut' => 'en_cours_de_transfert']);
        $this->actingAs($responsable);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->call('validerService')
            ->assertForbidden();

        $this->assertSame('en_cours_de_transfert', $courrier->refresh()->statut);
    }

    // Module 1/4 — la réceptionniste transfère explicitement le courrier au
    // DGA/ADJ DGA (2026-09-15, voir DECISIONS.md, synchronisation SRS-GEC.pdf).

    public function test_lagent_createur_peut_transferer_son_courrier_au_dga(): void
    {
        $agent = $this->utilisateur('Agent');
        $dga = $this->utilisateur('DGA');
        $agent->destinatairesTransfert()->attach($dga->id);
        $service = Service::factory()->create(['code' => 'TST']);
        $courrier = $this->courrier($service, ['statut' => 'en_attente_de_transfert', 'service_id' => null]);
        CourrierHistorique::create(['courrier_id' => $courrier->id, 'auteur_id' => $agent->id, 'action' => 'creation']);
        $this->actingAs($agent);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->set('destinataireTransfertChoisi', $dga->id)
            ->call('transferer')
            ->assertHasNoErrors();

        $courrier->refresh();

        $this->assertSame('en_cours_de_transfert', $courrier->statut);
        $this->assertSame($dga->id, $courrier->destinataire_transfert_id);
        $this->assertSame(1, CourrierHistorique::where('courrier_id', $courrier->id)->where('action', 'transfert')->count());
    }

    // Module 1/4 — mise à jour (2026-09-15, voir DECISIONS.md "Destinataires
    // de transfert") : un destinataire qui n'est pas dans SA liste autorisée
    // est refusé, même s'il existe bel et bien en base (Règle n°6 — jamais
    // fait confiance à un ID posté).
    public function test_un_destinataire_non_autorise_est_refuse(): void
    {
        $agent = $this->utilisateur('Agent');
        $dga = $this->utilisateur('DGA');
        // Pas de attach() ici : $dga n'est PAS dans la liste autorisée de $agent.
        $service = Service::factory()->create(['code' => 'TST']);
        $courrier = $this->courrier($service, ['statut' => 'en_attente_de_transfert', 'service_id' => null]);
        CourrierHistorique::create(['courrier_id' => $courrier->id, 'auteur_id' => $agent->id, 'action' => 'creation']);
        $this->actingAs($agent);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->set('destinataireTransfertChoisi', $dga->id)
            ->call('transferer')
            ->assertHasErrors('destinataireTransfertChoisi');

        $this->assertSame('en_attente_de_transfert', $courrier->refresh()->statut);
    }

    public function test_un_autre_agent_ne_peut_pas_transferer_le_courrier_dun_collegue(): void
    {
        $createur = $this->utilisateur('Agent');
        $autreAgent = $this->utilisateur('Agent');
        $service = Service::factory()->create(['code' => 'TST']);
        $courrier = $this->courrier($service, ['statut' => 'en_attente_de_transfert', 'service_id' => null]);
        CourrierHistorique::create(['courrier_id' => $courrier->id, 'auteur_id' => $createur->id, 'action' => 'creation']);
        $this->actingAs($autreAgent);

        // Bloqué dès mount() (view : ni créateur, ni voir_tout/voir_dga
        // applicable) — pas seulement au clic sur "Transférer" : cet autre
        // agent n'a même pas accès à la fiche.
        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])->assertForbidden();

        $this->assertSame('en_attente_de_transfert', $courrier->refresh()->statut);
    }

    public function test_le_moins_charge_est_preselectionne_pour_laffectation_sans_rien_choisir(): void
    {
        // Bug réel constaté par l'utilisateur le 2026-09-04 : le commentaire
        // de collaborateursDuService() prétend "le premier de la liste est
        // présélectionné dans la vue", mais rien ne le faisait réellement —
        // cliquer "Affecter" sans toucher au menu déroulant échouait
        // toujours avec "Choisissez un collaborateur du service.". Les
        // autres tests de ce fichier contournaient le bug en fixant
        // collaborateurSelectionne à la main avant d'appeler affecter() —
        // celui-ci reproduit le vrai scénario, sans y toucher.
        $responsable = $this->utilisateur('Responsable de service');
        $service = Service::factory()->create(['responsable_id' => $responsable->id, 'code' => 'TST']);
        $moinsCharge = $this->utilisateur('Collaborateur', $service);
        $plusCharge = $this->utilisateur('Collaborateur', $service);
        // Deux courriers déjà en cours pour $plusCharge, aucun pour $moinsCharge.
        foreach (range(1, 2) as $_) {
            $enCours = $this->courrier($service, ['statut' => 'en_traitement']);
            Affectation::create(['courrier_id' => $enCours->id, 'user_id' => $plusCharge->id]);
        }
        $courrier = $this->courrier($service);
        $this->actingAs($responsable);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->assertSet('collaborateurSelectionne', $moinsCharge->id)
            ->call('affecter')
            ->assertHasNoErrors()
            ->assertSee($moinsCharge->name);

        $this->assertSame($moinsCharge->id, $courrier->refresh()->affectationCourante->user_id);
    }

    public function test_le_collaborateur_affecte_demarre_le_traitement_et_soumet_pour_validation(): void
    {
        $responsable = $this->utilisateur('Responsable de service');
        $service = Service::factory()->create(['responsable_id' => $responsable->id, 'code' => 'TST']);
        $collaborateur = $this->utilisateur('Collaborateur', $service);
        $courrier = $this->courrier($service, ['statut' => 'affecte']);
        Affectation::create(['courrier_id' => $courrier->id, 'user_id' => $collaborateur->id, 'affecte_par_id' => $responsable->id]);
        $this->actingAs($collaborateur);

        $composant = Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->call('demarrerTraitement')
            ->assertHasNoErrors();

        $this->assertSame('en_traitement', $courrier->refresh()->statut);

        $composant->set('commentaireCirculation', 'Réponse envoyée par courrier.')
            ->call('soumettrePourValidation')
            ->assertHasNoErrors();

        $this->assertSame('en_validation', $courrier->refresh()->statut);
    }

    public function test_le_responsable_valide_le_courrier_soumis(): void
    {
        $responsable = $this->utilisateur('Responsable de service');
        $service = Service::factory()->create(['responsable_id' => $responsable->id, 'code' => 'TST']);
        $collaborateur = $this->utilisateur('Collaborateur', $service);
        $courrier = $this->courrier($service, ['statut' => 'en_validation']);
        Affectation::create(['courrier_id' => $courrier->id, 'user_id' => $collaborateur->id]);
        $this->actingAs($responsable);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->call('valider')
            ->assertHasNoErrors();

        $this->assertSame('traite', $courrier->refresh()->statut);
    }

    public function test_le_responsable_renvoie_pour_correction_avec_motif_obligatoire(): void
    {
        $responsable = $this->utilisateur('Responsable de service');
        $service = Service::factory()->create(['responsable_id' => $responsable->id, 'code' => 'TST']);
        $collaborateur = $this->utilisateur('Collaborateur', $service);
        $courrier = $this->courrier($service, ['statut' => 'en_validation']);
        Affectation::create(['courrier_id' => $courrier->id, 'user_id' => $collaborateur->id]);
        $this->actingAs($responsable);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->call('renvoyerPourCorrection')
            ->assertHasErrors(['motifRenvoi']);

        $this->assertSame('en_validation', $courrier->refresh()->statut, 'sans motif, aucune transition ne doit avoir lieu');

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->set('motifRenvoi', 'Il manque la pièce justificative.')
            ->call('renvoyerPourCorrection')
            ->assertHasNoErrors();

        $this->assertSame('en_traitement', $courrier->refresh()->statut);
    }

    public function test_le_motif_du_renvoi_est_visible_en_haut_de_la_fiche(): void
    {
        // Demande explicite de l'utilisateur (2026-09-08) : le collaborateur
        // doit voir tout de suite pourquoi son courrier revient, pas
        // seulement le retrouver dans l'historique en bas de page.
        $responsable = $this->utilisateur('Responsable de service');
        $service = Service::factory()->create(['responsable_id' => $responsable->id, 'code' => 'TST']);
        $collaborateur = $this->utilisateur('Collaborateur', $service);
        $courrier = $this->courrier($service, ['statut' => 'en_validation']);
        Affectation::create(['courrier_id' => $courrier->id, 'user_id' => $collaborateur->id]);
        $this->actingAs($responsable);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->set('motifRenvoi', 'Il manque la pièce justificative.')
            ->call('renvoyerPourCorrection');

        $this->actingAs($collaborateur);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->assertSee('Renvoyé pour correction')
            ->assertSee('Il manque la pièce justificative.')
            ->assertSee($responsable->name);
    }

    public function test_le_motif_du_renvoi_disparait_une_fois_resoumis(): void
    {
        $responsable = $this->utilisateur('Responsable de service');
        $service = Service::factory()->create(['responsable_id' => $responsable->id, 'code' => 'TST']);
        $collaborateur = $this->utilisateur('Collaborateur', $service);
        $courrier = $this->courrier($service, ['statut' => 'en_validation']);
        Affectation::create(['courrier_id' => $courrier->id, 'user_id' => $collaborateur->id]);
        $this->actingAs($responsable);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->set('motifRenvoi', 'Il manque la pièce justificative.')
            ->call('renvoyerPourCorrection');

        $this->actingAs($collaborateur);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->call('soumettrePourValidation')
            ->assertDontSee('Renvoyé pour correction');
    }

    public function test_reaffectation_avec_motif_obligatoire(): void
    {
        $responsable = $this->utilisateur('Responsable de service');
        $service = Service::factory()->create(['responsable_id' => $responsable->id, 'code' => 'TST']);
        $premier = $this->utilisateur('Collaborateur', $service);
        $second = $this->utilisateur('Collaborateur', $service);
        $courrier = $this->courrier($service, ['statut' => 'en_traitement']);
        Affectation::create(['courrier_id' => $courrier->id, 'user_id' => $premier->id]);
        $this->actingAs($responsable);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->set('collaborateurSelectionne', $second->id)
            ->call('reaffecter')
            ->assertHasErrors(['motifReaffectation']);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->set('collaborateurSelectionne', $second->id)
            ->set('motifReaffectation', 'Le premier collaborateur est en congé.')
            ->call('reaffecter')
            ->assertHasNoErrors();

        $this->assertSame($second->id, $courrier->refresh()->affectationCourante->user_id);
        $this->assertSame(2, Affectation::where('courrier_id', $courrier->id)->count());
    }

    public function test_mise_en_attente_puis_reprise(): void
    {
        $responsable = $this->utilisateur('Responsable de service');
        $service = Service::factory()->create(['responsable_id' => $responsable->id, 'code' => 'TST']);
        $collaborateur = $this->utilisateur('Collaborateur', $service);
        $courrier = $this->courrier($service, ['statut' => 'en_traitement']);
        Affectation::create(['courrier_id' => $courrier->id, 'user_id' => $collaborateur->id]);
        $this->actingAs($collaborateur);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->set('motifAttente', 'En attente d\'une pièce complémentaire.')
            ->call('mettreEnAttente')
            ->assertHasNoErrors();

        $this->assertSame('en_attente_information', $courrier->refresh()->statut);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->call('reprendre')
            ->assertHasNoErrors();

        $this->assertSame('en_traitement', $courrier->refresh()->statut);
    }

    public function test_le_rejet_exige_un_motif(): void
    {
        $responsable = $this->utilisateur('Responsable de service');
        $service = Service::factory()->create(['responsable_id' => $responsable->id, 'code' => 'TST']);
        $courrier = $this->courrier($service, ['statut' => 'enregistre']);
        $this->actingAs($responsable);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->call('rejeter')
            ->assertHasErrors(['motifRejet']);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->set('motifRejet', 'Courrier hors périmètre du service.')
            ->call('rejeter')
            ->assertHasNoErrors();

        $this->assertSame('rejete', $courrier->refresh()->statut);
    }

    public function test_un_agent_createur_ne_peut_effectuer_aucune_action_de_circuit(): void
    {
        $agent = $this->utilisateur('Agent');
        $service = Service::factory()->create(['code' => 'TST']);
        $courrier = $this->courrier($service);
        CourrierHistorique::create(['courrier_id' => $courrier->id, 'auteur_id' => $agent->id, 'action' => 'creation']);
        $this->actingAs($agent);

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->set('collaborateurSelectionne', 999)
            ->call('affecter')
            ->assertForbidden();

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->call('demarrerTraitement')
            ->assertForbidden();

        Livewire::test(ShowCourrier::class, ['courrierId' => $courrier->id])
            ->call('valider')
            ->assertForbidden();
    }
}
