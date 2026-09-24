<?php

namespace App\Services;

use App\Events\CourrierStatutChange;
use App\Models\Affectation;
use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class WorkflowService
{
    // Module 4 — circuit générique unique en phase 1 (PRD.md §3/§4 : "pas de
    // circuits multiples par type" est IN SCOPE, "règles de workflow avancées
    // différenciées par type de courrier" est explicitement HORS SCOPE) :
    // une seule machine à états pour tous les courriers, pas de configuration
    // par type ni par administrateur. Chaque transition est vérifiée ici
    // (défense en profondeur, indépendamment des boutons affichés côté vue)
    // et trace une entrée d'historique immuable (Règle n°5).
    //
    // "Traité" → "Archivé" n'est pas une transition manuelle : le spec Module 9
    // dit "un courrier clôturé est automatiquement transféré vers l'archivage"
    // — voir archiverAutomatiquement() plus bas, appelée uniquement par
    // ArchiverCourriersTraitesJob (2026-09-21, "Dossiers & Archives").
    // 2026-09-08 — ajout d'une étape de validation DGA/ADJ : un courrier
    // entrant NON-sinistre passe par cette validation (confirme/change le
    // service proposé par Module 3) avant de rejoindre la file du
    // responsable de service — un courrier sinistre confirmé par la
    // réceptionniste saute directement cette étape (reste 'enregistre' dès
    // la création, voir RegistrationForm::enregistrer()). Toujours une seule
    // machine à états générique (voir commentaire ci-dessus) : cette étape
    // n'est PAS un circuit différent par type de courrier, seulement une
    // transition initiale conditionnelle décidée une fois, à l'enregistrement.
    //
    // 2026-09-15 (synchronisation SRS-GEC.pdf, voir DECISIONS.md) : cette
    // étape unique ('en_attente_validation_dga') est scindée en 2
    // sous-statuts explicites, comme demandé par le client — la
    // réceptionniste doit cliquer "Transférer" (transferer() ci-dessous)
    // pour faire passer le courrier de "pas encore transféré" à "en attente
    // du DGA", au lieu d'un envoi automatique et silencieux à
    // l'enregistrement. Le 3e sous-statut du document ("Transféré") est le
    // 'enregistre' déjà existant — pas de nouvelle valeur pour lui (décision
    // du 2026-09-08 de ne jamais renommer une valeur d'ENUM déjà en place).
    private const TRANSITIONS = [
        'enregistre' => ['affecte', 'rejete'],
        'en_attente_de_transfert' => ['en_cours_de_transfert', 'rejete'],
        'en_cours_de_transfert' => ['enregistre', 'rejete'],
        'affecte' => ['en_traitement', 'en_attente_information', 'rejete'],
        'en_traitement' => ['en_validation', 'en_attente_information', 'rejete'],
        'en_validation' => ['traite', 'en_traitement', 'en_attente_information', 'rejete'],
        'en_attente_information' => ['en_traitement'],
        // Module 9 — ajoutée le 2026-09-21 : "Traité" → "Archivé" devient
        // enfin une vraie transition, déclenchée par ArchiverCourriersTraitesJob
        // (voir archiverAutomatiquement() plus bas), pas par un bouton.
        'traite' => ['archive'],
    ];

    // 2026-09-21 — 'traite' a désormais une transition sortante (vers
    // 'archive', voir archiverAutomatiquement() plus bas) mais n'est PAS un
    // statut actif pour autant : un courrier clôturé ne doit pas être
    // recompté comme "en cours" (dashboard, file d'attente, charge par
    // collaborateur) simplement parce qu'il a un devenir automatique.
    // array_keys(TRANSITIONS) seul suffisait tant que 'traite' était un
    // cul-de-sac ; explicitement exclu maintenant qu'il ne l'est plus.
    public static function statutsActifs(): array
    {
        return array_diff(array_keys(self::TRANSITIONS), ['traite']);
    }

    // Module 6 — "si un collaborateur a déjà des courriers en cours et un
    // autre n'en a aucun, le système affecte automatiquement au moins
    // chargé" : calcule la charge (courriers actifs actuellement affectés)
    // de chaque collaborateur d'un service, pour présélectionner le moins
    // chargé dans le formulaire d'affectation — le responsable choisit
    // toujours librement avant de valider (jamais d'automatisme imposé sans
    // validation, cohérent avec le reste du projet).
    //
    // @return Collection<int, int> user_id => nombre de courriers actifs
    public function chargeParCollaborateur(int $serviceId): Collection
    {
        return Courrier::query()
            ->whereIn('statut', self::statutsActifs())
            ->whereHas('affectationCourante.collaborateur', fn ($q) => $q->where('service_id', $serviceId))
            ->with('affectationCourante')
            ->get()
            ->groupBy(fn (Courrier $courrier) => $courrier->affectationCourante->user_id)
            ->map->count();
    }

    // Module 6 — première affectation d'un courrier fraîchement enregistré.
    public function affecter(Courrier $courrier, User $collaborateur, User $auteur, ?string $commentaire = null): void
    {
        $this->verifierTransition($courrier, 'affecte');

        DB::transaction(function () use ($courrier, $collaborateur, $auteur, $commentaire) {
            Affectation::create([
                'courrier_id' => $courrier->id,
                'user_id' => $collaborateur->id,
                'affecte_par_id' => $auteur->id,
                'motif_reaffectation' => null,
            ]);

            $courrier->update(['statut' => 'affecte']);

            CourrierHistorique::create([
                'courrier_id' => $courrier->id,
                'auteur_id' => $auteur->id,
                'action' => 'affectation',
                'commentaire' => trim($collaborateur->name.($commentaire ? " — {$commentaire}" : '')),
            ]);
        });

        $this->diffuserChangementStatut($courrier->id, 'affecte');
    }

    // Module 1/4 — la réceptionniste clique "Transférer" pour envoyer
    // explicitement le courrier au DGA/ADJ DGA (SRS-GEC.pdf, 2026-09-15) —
    // jusque-là ce transfert était automatique et silencieux à
    // l'enregistrement (voir DECISIONS.md) ; il faut désormais une action
    // réceptionniste distincte, tracée séparément de la création.
    // 2026-09-15 (mise à jour) — elle choisit désormais QUI parmi ses
    // destinataires autorisés (DGA, ADJ DGA, ou un autre superviseur — voir
    // DECISIONS.md "Destinataires de transfert") : $destinataire n'est PAS
    // revérifié ici (WorkflowService reste la couche transition/historique,
    // pas la couche autorisation) — c'est à l'appelant (ShowCourrier/
    // MesCourriers) de vérifier que $destinataire fait bien partie de
    // $auteur->destinatairesTransfert avant d'appeler cette méthode (Règle
    // n°6, jamais fait confiance à un ID posté).
    public function transferer(Courrier $courrier, User $destinataire, User $auteur): void
    {
        $this->verifierTransition($courrier, 'en_cours_de_transfert');

        DB::transaction(function () use ($courrier, $destinataire, $auteur) {
            $courrier->update([
                'statut' => 'en_cours_de_transfert',
                'destinataire_transfert_id' => $destinataire->id,
            ]);

            CourrierHistorique::create([
                'courrier_id' => $courrier->id,
                'auteur_id' => $auteur->id,
                'action' => 'transfert',
                'commentaire' => "Transféré à {$destinataire->name}",
            ]);
        });

        $this->diffuserChangementStatut($courrier->id, 'en_cours_de_transfert');
    }

    // Module 1/4 — demande explicite de l'utilisateur (2026-09-08, précisée le
    // 2026-09-15 par SRS-GEC.pdf, voir DECISIONS.md) : la DGA/ADJ choisit le
    // service pour un courrier entrant non-sinistre — le champ n'est plus
    // saisi par l'agent du tout, `service_id` vaut donc systématiquement null
    // avant cet appel (sauf sinistre auto-routé, qui ne passe jamais par
    // cette méthode). Correspond à l'étape "Transféré" du document (le
    // destinataire clique "Valider/Accepter le transfert") — devenue
    // 'enregistre' (statut déjà existant, pas de renommage). Suit le même
    // schéma qu'affecter() ci-dessus (transaction, historique immuable)
    // plutôt que réutiliser transitionnerSimple() : contrairement aux autres
    // transitions, celle-ci modifie aussi service_id, pas seulement statut.
    // 2026-09-21 — demande explicite de l'utilisateur : le niveau de
    // confidentialité RÉELLEMENT opposable (comparé à User::niveau_confidentialite,
    // voir CourrierPolicy::niveauSuffisant()) n'est plus figé au choix de la
    // réceptionniste à l'enregistrement — la DGA le confirme ou le
    // corrige ici, en même temps qu'elle confirme le service. Le choix de
    // la réceptionniste reste la valeur de DÉPART (pré-remplie dans le
    // formulaire, voir ShowCourrier::mount()) — indispensable pour un
    // courrier réellement confidentiel dès l'enveloppe, jamais ouvert par
    // la réceptionniste (RegistrationFormConfidentiel) : elle DOIT pouvoir
    // le marquer confidentiel dès l'enregistrement, sans attendre la DGA.
    public function validerService(Courrier $courrier, int $serviceId, int $confidentialite, User $auteur): void
    {
        $this->verifierTransition($courrier, 'enregistre');

        // Comparé à la PROPOSITION de classement (Module 3), pas à
        // service_id (toujours null à ce stade) — c'est la seule référence
        // pertinente pour distinguer "la DGA a suivi la suggestion" de "la
        // DGA a choisi autre chose".
        $suitLaProposition = $serviceId === $courrier->service_propose_id;
        $confidentialiteModifiee = $confidentialite !== $courrier->confidentialite;

        DB::transaction(function () use ($courrier, $serviceId, $confidentialite, $auteur, $suitLaProposition, $confidentialiteModifiee) {
            $courrier->update([
                'statut' => 'enregistre',
                'service_id' => $serviceId,
                'confidentialite' => $confidentialite,
            ]);

            CourrierHistorique::create([
                'courrier_id' => $courrier->id,
                'auteur_id' => $auteur->id,
                'action' => 'service_valide_dga',
                'commentaire' => $suitLaProposition ? 'Service confirmé par la DGA' : 'Service choisi par la DGA (différent de la proposition)',
            ]);

            // Traçabilité distincte (Règle n°5) — seulement si la DGA a
            // réellement changé le niveau proposé par la réceptionniste,
            // jamais une ligne "confirmé" bruyante pour rien.
            if ($confidentialiteModifiee) {
                CourrierHistorique::create([
                    'courrier_id' => $courrier->id,
                    'auteur_id' => $auteur->id,
                    'action' => 'confidentialite_modifiee_dga',
                    'commentaire' => "Niveau de confidentialité changé en « {$confidentialite} » par la DGA",
                ]);
            }
        });

        $this->diffuserChangementStatut($courrier->id, 'enregistre');

        // Fichiers rangés sous le segment provisoire "_en_attente" (voir
        // Courrier::segmentClassement()) tant que le service était inconnu —
        // déplacés maintenant vers le vrai dossier service. Hors transaction
        // (opération de stockage S3, non transactionnelle), non bloquante :
        // un échec laisse le fichier accessible à son ancien chemin plutôt
        // que de faire échouer la validation du service elle-même.
        $this->deplacerFichiersVersService($courrier->fresh(['service', 'piecesJointes']));
    }

    private function deplacerFichiersVersService(Courrier $courrier): void
    {
        $ancienSegment = Courrier::SEGMENT_SERVICE_EN_ATTENTE;
        $nouveauSegment = $courrier->segmentClassement();

        if ($nouveauSegment === $ancienSegment) {
            return;
        }

        if ($courrier->fichier_path !== null && str_contains($courrier->fichier_path, "/{$ancienSegment}/")) {
            $nouveauChemin = str_replace("/{$ancienSegment}/", "/{$nouveauSegment}/", $courrier->fichier_path);

            if ($this->deplacerFichier($courrier->fichier_path, $nouveauChemin)) {
                $courrier->update(['fichier_path' => $nouveauChemin]);
            }
        }

        foreach ($courrier->piecesJointes as $pieceJointe) {
            if (! str_contains($pieceJointe->fichier_path, "/{$ancienSegment}/")) {
                continue;
            }

            $nouveauChemin = str_replace("/{$ancienSegment}/", "/{$nouveauSegment}/", $pieceJointe->fichier_path);

            if ($this->deplacerFichier($pieceJointe->fichier_path, $nouveauChemin)) {
                $pieceJointe->update(['fichier_path' => $nouveauChemin]);
            }
        }
    }

    // Retourne true seulement si le déplacement a réellement eu lieu —
    // l'appelant ne doit mettre à jour fichier_path que dans ce cas, jamais
    // pointer vers un chemin où le fichier n'est pas réellement arrivé.
    private function deplacerFichier(string $ancien, string $nouveau): bool
    {
        try {
            if (! Storage::disk('s3')->exists($ancien)) {
                // Rien à déplacer (courrier pas encore scanné, ou déjà déplacé
                // par une requête concurrente) — pas une erreur.
                return false;
            }

            Storage::disk('s3')->move($ancien, $nouveau);

            // Règle n°4 (complétée) — même geste que RegistrationForm::finaliserBrouillon()
            // pour la copie de secours : jamais bloquant si absente/non configurée.
            if (filled(config('filesystems.disks.s3_backup.bucket')) && Storage::disk('s3_backup')->exists($ancien)) {
                Storage::disk('s3_backup')->move($ancien, $nouveau);
            }

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    // Module 6 — "réaffectation possible à tout moment, avec motif obligatoire".
    // Ne change pas le statut du circuit (une réaffectation en cours de
    // traitement reste "en_traitement", par ex.) : seule la ligne d'affectation
    // change, append-only comme le reste de la traçabilité (Règle n°5).
    public function reaffecter(Courrier $courrier, User $nouveauCollaborateur, User $auteur, string $motif): void
    {
        if (! in_array($courrier->statut, ['affecte', 'en_traitement', 'en_validation', 'en_attente_information'], true)) {
            throw new RuntimeException("Réaffectation impossible depuis le statut « {$courrier->statut} ».");
        }

        DB::transaction(function () use ($courrier, $nouveauCollaborateur, $auteur, $motif) {
            Affectation::create([
                'courrier_id' => $courrier->id,
                'user_id' => $nouveauCollaborateur->id,
                'affecte_par_id' => $auteur->id,
                'motif_reaffectation' => $motif,
            ]);

            CourrierHistorique::create([
                'courrier_id' => $courrier->id,
                'auteur_id' => $auteur->id,
                'action' => 'reaffectation',
                'commentaire' => "{$nouveauCollaborateur->name} — motif : {$motif}",
            ]);
        });
    }

    // Module 4 — le collaborateur affecté commence effectivement le traitement.
    public function demarrerTraitement(Courrier $courrier, User $auteur): void
    {
        $this->transitionnerSimple($courrier, 'en_traitement', $auteur, 'traitement_demarre');
    }

    // Module 4 — le collaborateur soumet sa réponse/action pour validation hiérarchique.
    public function soumettrePourValidation(Courrier $courrier, User $auteur, ?string $commentaire = null): void
    {
        $this->transitionnerSimple($courrier, 'en_validation', $auteur, 'soumis_validation', $commentaire);
    }

    // Module 4 — "un supérieur hiérarchique valide" → clôture (Traité).
    public function valider(Courrier $courrier, User $auteur, ?string $commentaire = null): void
    {
        $this->transitionnerSimple($courrier, 'traite', $auteur, 'validation_acceptee', $commentaire);
    }

    // Module 4 — "ou renvoie pour correction" → retour en traitement, motif obligatoire.
    public function renvoyerPourCorrection(Courrier $courrier, User $auteur, string $motif): void
    {
        $this->transitionnerSimple($courrier, 'en_traitement', $auteur, 'validation_refusee', $motif);
    }

    // Statut "En attente d'information" du spec Module 4 — ex. en attente
    // d'une pièce complémentaire de l'expéditeur. Motif obligatoire (traçabilité).
    public function mettreEnAttente(Courrier $courrier, User $auteur, string $motif): void
    {
        $this->transitionnerSimple($courrier, 'en_attente_information', $auteur, 'mise_en_attente', $motif);
    }

    public function reprendre(Courrier $courrier, User $auteur): void
    {
        $this->transitionnerSimple($courrier, 'en_traitement', $auteur, 'reprise');
    }

    // Statut "Rejeté" du spec Module 4 — dérogation explicite tracée (motif
    // obligatoire) plutôt qu'un simple abandon silencieux.
    public function rejeter(Courrier $courrier, User $auteur, string $motif): void
    {
        $this->transitionnerSimple($courrier, 'rejete', $auteur, 'rejet', $motif);
    }

    private function transitionnerSimple(Courrier $courrier, string $statutSuivant, User $auteur, string $action, ?string $commentaire = null): void
    {
        $this->verifierTransition($courrier, $statutSuivant);

        DB::transaction(function () use ($courrier, $statutSuivant, $auteur, $action, $commentaire) {
            $courrier->update(['statut' => $statutSuivant]);

            CourrierHistorique::create([
                'courrier_id' => $courrier->id,
                'auteur_id' => $auteur->id,
                'action' => $action,
                'commentaire' => $commentaire,
            ]);
        });

        $this->diffuserChangementStatut($courrier->id, $statutSuivant);
    }

    // Diffusion "au mieux" (best-effort) du changement de statut — jamais
    // laisser un souci de canal temps réel (Reverb non joignable, coupure
    // réseau...) faire échouer la transition elle-même, qui est déjà commitée
    // en base à ce stade. Constaté le 2026-09-18 : `CourrierStatutChange`
    // est `ShouldBroadcastNow` (diffusion synchrone) et
    // `Illuminate\Broadcasting\BroadcastException` étend `RuntimeException`
    // — sans ce garde-fou, une simple coupure du serveur Reverb en local
    // remontait jusqu'à `ShowCourrier::executer()` et était affichée à
    // l'utilisateur comme "la fiche a changé entre-temps", alors que
    // l'action avait en réalité parfaitement réussi. Même principe que
    // `deplacerFichier()` ci-dessus pour les échecs de déplacement S3.
    // Module 9 — clôture automatique "Traité" → "Archivé" ("un courrier
    // clôturé est automatiquement transféré vers l'archivage"). Aucun
    // auteur humain : convention "système" déjà utilisée par
    // ProcessDocumentOcr (auteur_id: null). Appelée UNIQUEMENT par
    // ArchiverCourriersTraitesJob — jamais par un contrôleur/composant. Pas
    // d'autorisation ici : ce n'est pas CourrierPolicy::archive() (réservée
    // à un futur archivage manuel), et l'appelant est le système lui-même,
    // pas un utilisateur agissant (même principe déjà en place partout
    // ailleurs dans ce service : l'autorisation est la responsabilité de
    // l'appelant, WorkflowService reste la couche transition/historique).
    public function archiverAutomatiquement(Courrier $courrier): void
    {
        $this->verifierTransition($courrier, 'archive');

        DB::transaction(function () use ($courrier) {
            $courrier->update(['statut' => 'archive']);

            CourrierHistorique::create([
                'courrier_id' => $courrier->id,
                'auteur_id' => null,
                'action' => 'archivage_automatique',
                'commentaire' => 'Archivé automatiquement (courrier traité).',
            ]);
        });

        $this->diffuserChangementStatut($courrier->id, 'archive');
    }

    private function diffuserChangementStatut(int $courrierId, string $statut): void
    {
        try {
            CourrierStatutChange::dispatch($courrierId, $statut);
        } catch (Throwable $e) {
            report($e);
        }
    }

    // "Un courrier ne peut pas sauter une étape obligatoire" (Règle métier
    // Module 4) : vérifié ici indépendamment de ce que la vue affiche — un ID
    // de courrier/statut ne doit jamais être fait confiance côté client (Règle n°6).
    private function verifierTransition(Courrier $courrier, string $statutSuivant): void
    {
        $autorises = self::TRANSITIONS[$courrier->statut] ?? [];

        if (! in_array($statutSuivant, $autorises, true)) {
            throw new RuntimeException("Transition « {$courrier->statut} » → « {$statutSuivant} » non autorisée.");
        }
    }
}
