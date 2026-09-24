<?php

namespace App\Livewire\Backend;

use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\DossierClassement;
use App\Models\MotCle;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Services\ClassificationService;
use App\Services\DossierClassementService;
use App\Services\WorkflowService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

#[Title('Détail du courrier')]
class ShowCourrier extends Component
{
    // Propriété : ID primitif uniquement (pas de modèle Eloquent complet) — Règle n°2.
    public int $courrierId;

    // 2026-09-22, demande explicite de l'utilisateur (diagnostic en direct :
    // "why do i have to navigate there first where is that shortcut one") —
    // l'onglet affiché reste piloté par Alpine côté client (aucun
    // changement du mécanisme existant, voir la vue), mais sa valeur
    // INITIALE peut désormais être fixée via ?onglet=circuit dans l'URL —
    // pour que WorkflowQueue (file "Transferts") et le panneau "Actions
    // rapides" puissent ouvrir directement sur "Circuit de traitement" au
    // lieu de forcer un clic supplémentaire sur chaque courrier. Valeur
    // non validée contre une liste blanche : une valeur inconnue ne
    // correspond simplement à aucun bouton d'onglet et Alpine affiche alors
    // celui déjà actif par défaut dans la vue (x-show sur chaque panneau),
    // aucun risque d'injection HTML (jamais affiché tel quel, seulement
    // comparé en JS).
    #[Url(as: 'onglet')]
    public string $ongletInitial = 'general';

    // Liste blanche des clés d'onglet réellement gérées par la vue
    // (x-show="onglet === '...'") — voir mount() ci-dessous, qui retombe sur
    // 'general' si la valeur reçue via ?onglet= n'en fait pas partie.
    private const ONGLETS_VALIDES = ['general', 'pieces-jointes', 'circuit', 'historique'];

    // Module 3 — saisie manuelle d'un tag secondaire.
    public string $nouveauMotCle = '';

    // Module 4/6 — propriétés primitives du panneau Circuit (Règle n°2 : pas
    // de Form Object, saisie très simple — un id + un motif/commentaire selon
    // l'action, jamais plusieurs champs croisés comme CourrierForm).
    public ?int $collaborateurSelectionne = null;

    // Module 1/4 — panneau "Valider le service" (DGA/ADJ), demande explicite
    // de l'utilisateur (2026-09-08). Module "Organisation" v2 (2026-09-22,
    // spec §16) — remplace le sélecteur plat unique par une cascade
    // Site → Département → Service/Unité, naviguant dans la vraie structure
    // organisationnelle. La résolution finale (Service::id RÉEL écrit sur
    // courriers.service_id) passe par le PONT organization_units.service_id
    // — voir uniteFinale()/validerService() ci-dessous. Le reste du circuit
    // (WorkflowService::validerService(), segmentClassement()) est
    // INCHANGÉ, aucune modification de la génération du numéro de référence
    // ni des chemins de stockage.
    public ?int $siteSelectionneId = null;

    public ?int $departementSelectionneId = null;

    public ?int $uniteSelectionneeId = null;

    // 2026-09-21 — demande explicite de l'utilisateur : la DGA confirme (ou
    // corrige) le niveau de confidentialité RÉEL du courrier en même temps
    // qu'elle confirme le service, voir WorkflowService::validerService().
    // Pré-rempli avec le choix de la réceptionniste à l'enregistrement
    // (mount() ci-dessous), jamais vide.
    public int $confidentialiteSelectionnee = 1;

    // Module 1/4 — modale "Transférer à" (2026-09-15, voir DECISIONS.md
    // "Destinataires de transfert") — id brut, revérifié contre
    // Auth::user()->destinatairesTransfert() avant d'agir (Règle n°6).
    public ?int $destinataireTransfertChoisi = null;

    // Un champ par action plutôt qu'un seul "motifCirculation" partagé : pour
    // un Administrateur, plusieurs formulaires d'actions différentes peuvent
    // s'afficher en même temps sur un même statut (ex. Réaffecter + Mettre en
    // attente) — un champ commun s'écrirait dans les deux à la fois.
    public string $motifReaffectation = '';

    public string $motifRenvoi = '';

    public string $motifAttente = '';

    public string $motifRejet = '';

    public string $commentaireCirculation = '';

    // Module 3/9 — modale "Classer dans un dossier" (2026-09-22, demande
    // explicite de l'utilisateur : "les trois" points d'entrée). Id brut,
    // revérifié contre $this->dossiersAccessibles avant d'agir (Règle n°6).
    public ?int $dossierAClasserId = null;

    public function mount(int $courrierId): void
    {
        $courrier = Courrier::with('service')->findOrFail($courrierId);

        // Règle n°6 — jamais confiance en un ID client sans vérifier les droits côté serveur.
        $this->authorize('view', $courrier);

        $this->courrierId = $courrierId;

        if (! in_array($this->ongletInitial, self::ONGLETS_VALIDES, true)) {
            $this->ongletInitial = 'general';
        }

        // Module 6 — "affecte automatiquement au collaborateur le moins
        // chargé" : présélection RÉELLE du premier (moins chargé) de la
        // liste. Le commentaire de collaborateursDuService() ci-dessous
        // prétendait déjà que "le premier de la liste est présélectionné
        // dans la vue", mais rien ne le faisait réellement — le menu
        // restait vide (placeholder), donc cliquer sur "Affecter" sans
        // choisir manuellement quelqu'un déclenchait l'erreur de validation
        // "Choisissez un collaborateur du service." — bug réel constaté par
        // l'utilisateur le 2026-09-04, voir DECISIONS.md.
        if ($courrier->statut === 'enregistre' && ($premier = $this->collaborateursDuService->first())) {
            $this->collaborateurSelectionne = $premier['id'];
        }

        // Module 1/4 — présélection RÉELLE (pas seulement un commentaire qui
        // le prétend, voir le bug corrigé ci-dessus pour l'affectation) : la
        // proposition de Module 3 si elle existe, sinon le service saisi à
        // l'enregistrement. 'en_cours_de_transfert', pas
        // 'en_attente_de_transfert' (2026-09-15, voir DECISIONS.md,
        // synchronisation SRS-GEC.pdf) — le panneau "Valider le service" du
        // DGA n'a de sens qu'une fois la réceptionniste passée par
        // "Transférer".
        if ($courrier->statut === 'en_cours_de_transfert') {
            $this->preselectionnerCascadeDepuisService($courrier->service_propose_id ?? $courrier->service_id);
            $this->confidentialiteSelectionnee = $courrier->confidentialite;
        }
    }

    // Retrouve, à partir d'un `services.id` réel (proposition Module 3 ou
    // service déjà saisi), le nœud organization_units PONTÉ à ce service —
    // s'il existe — et remonte ses ancêtres pour pré-remplir la cascade
    // Site/Département/Service. Aucun nœud ponté trouvé = cascade vide,
    // l'utilisateur choisit manuellement (aucune donnée fabriquée).
    private function preselectionnerCascadeDepuisService(?int $serviceId): void
    {
        if ($serviceId === null) {
            return;
        }

        $courant = OrganizationUnit::where('service_id', $serviceId)->first();

        // Remonte $noeud lui-même PUIS ses ancêtres — un Département peut
        // être directement ponté à un service (spec §1 : "un département
        // peut avoir directement des utilisateurs sans service"), il faut
        // alors le reconnaître comme tel, pas seulement ses ancêtres.
        while ($courant !== null) {
            match ($courant->type) {
                OrganizationUnit::TYPE_SUB_SERVICE, OrganizationUnit::TYPE_SERVICE => $this->uniteSelectionneeId = $courant->id,
                OrganizationUnit::TYPE_DEPARTMENT => $this->departementSelectionneId = $courant->id,
                OrganizationUnit::TYPE_SITE => $this->siteSelectionneId = $courant->id,
                default => null,
            };

            $courant = $courant->parent;
        }
    }

    // Cascade Site → Département → Service/Unité (spec §16) — changer un
    // niveau vide les niveaux dépendants, jamais un choix incohérent laissé
    // affiché après un changement de niveau supérieur.
    public function updated($nom): void
    {
        if ($nom === 'siteSelectionneId') {
            $this->reset('departementSelectionneId', 'uniteSelectionneeId');
        } elseif ($nom === 'departementSelectionneId') {
            $this->reset('uniteSelectionneeId');
        }
    }

    // Rechargé côté serveur à chaque requête à partir du seul ID (Règle n°2),
    // avec eager loading pour éviter le N+1 sur la timeline (Règle n°3).
    #[Computed]
    public function courrier(): Courrier
    {
        $courrier = Courrier::with([
            'service',
            'servicePropose',
            'serviceResponsable',
            'regleClassement',
            'motsCles',
            'historiques.auteur',
            'affectationCourante.collaborateur',
            'affectationCourante.affectePar',
            'piecesJointes',
        ])->findOrFail($this->courrierId);

        $this->authorize('view', $courrier);

        return $courrier;
    }

    // Droit de modification évalué une seule fois par rendu (la Policy interroge
    // l'historique) au lieu d'un @can par tag dans la vue — Règle n°3, pas de N+1.
    #[Computed]
    public function peutModifier(): bool
    {
        return Auth::user()?->can('update', $this->courrier) ?? false;
    }

    // Module 4/6 — mêmes principes que peutModifier : une seule évaluation
    // par rendu, jamais un @can répété dans la vue.
    #[Computed]
    public function peutAffecter(): bool
    {
        return Auth::user()?->can('affecter', $this->courrier) ?? false;
    }

    #[Computed]
    public function peutTraiter(): bool
    {
        return Auth::user()?->can('traiter', $this->courrier) ?? false;
    }

    #[Computed]
    public function peutValider(): bool
    {
        return Auth::user()?->can('valider', $this->courrier) ?? false;
    }

    // Module 1/4 — la réceptionniste clique "Transférer" (2026-09-15, voir
    // DECISIONS.md, synchronisation SRS-GEC.pdf) : même principe que les
    // autres peut*() ci-dessus.
    #[Computed]
    public function peutTransferer(): bool
    {
        return Auth::user()?->can('transferer', $this->courrier) ?? false;
    }

    #[Computed]
    public function peutValiderService(): bool
    {
        return Auth::user()?->can('validerService', $this->courrier) ?? false;
    }

    // Module 3/9 — "Classer dans un dossier" : même principe que peutModifier
    // ci-dessus, une seule évaluation par rendu.
    #[Computed]
    public function peutClasser(): bool
    {
        return Auth::user()?->can('classer', $this->courrier) ?? false;
    }

    // "Chaque action / lecture = un privilège" (2026-09-23) : droits fins de
    // la fiche, évalués une seule fois par rendu (même principe que peut*()).
    #[Computed]
    public function droits(): array
    {
        $user = Auth::user();
        $courrier = $this->courrier;

        return collect([
            'reaffecter', 'soumettreValidation', 'renvoyerCorrection', 'rejeter',
            'mettreEnAttente', 'reprendre', 'validerClassement', 'gererMotsCles',
            'voirHistorique', 'voirTexteOcr', 'voirPiecesJointes',
        ])->mapWithKeys(fn (string $abilite) => [$abilite => (bool) $user?->can($abilite, $courrier)])->all();
    }

    // Un seul dossier par utilisateur non-gerer_tout : créateur, responsable,
    // ou partagé explicitement — même requête que
    // DossierClassementList::tousLesDossiersAccessibles() (dupliquée à
    // l'identique, même convention déjà suivie pour services() dans ce
    // composant ET dans CourrierList/DossierClassementList).
    #[Computed]
    public function dossiersAccessibles()
    {
        $user = Auth::user();

        return DossierClassement::query()
            ->when(! $user->hasPrivilege('dossiers_classement.gerer_tout'), fn ($q) => $q->where(fn ($q) => $q
                ->where('cree_par_id', $user->id)
                ->orWhere('responsable_id', $user->id)
                ->orWhereHas('utilisateursAutorises', fn ($q) => $q->where('users.id', $user->id))))
            ->orderBy('nom')
            ->get();
    }

    // Module 1/4 — liste affichée dans la modale "Transférer à" : curatée
    // par l'administrateur pour CET utilisateur précis (voir DECISIONS.md
    // "Destinataires de transfert"), pas dérivée d'un profil/privilège.
    #[Computed]
    public function destinatairesTransfert()
    {
        return Auth::user()->destinatairesTransfert()->orderBy('name')->get(['users.id', 'users.name']);
    }

    // Collaborateurs du service du courrier, triés par charge de travail
    // croissante (Module 6 — "affecte automatiquement au collaborateur le
    // moins chargé") : le premier de la liste est présélectionné dans la vue,
    // le responsable reste libre de choisir un autre nom.
    #[Computed]
    public function collaborateursDuService()
    {
        $charge = app(WorkflowService::class)->chargeParCollaborateur($this->courrier->service_id);

        return User::query()
            ->where('service_id', $this->courrier->service_id)
            ->whereHas('profil', fn ($q) => $q->where('nom', 'Collaborateur'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'charge' => $charge[$u->id] ?? 0])
            ->sortBy('charge')
            ->values();
    }

    // Module "Organisation" v2 (2026-09-22, spec §16) — cascade Site →
    // Département → Service/Unité pour le panneau "Valider le service"
    // (DGA/ADJ), remplace l'ancien sélecteur plat unique. Chaque niveau ne
    // charge que les enfants actifs du niveau au-dessus (voir
    // OrganizationUnit::enfantsActifsDe()).
    #[Computed]
    public function sitesDisponibles()
    {
        return OrganizationUnit::query()
            ->where('type', OrganizationUnit::TYPE_SITE)
            ->where('status', OrganizationUnit::STATUT_ACTIF)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function departementsDisponibles()
    {
        // 2026-09-23, clarification explicite de l'utilisateur : le Site est
        // OPTIONNEL (des Départements réels existent déjà en base sans
        // aucun Site au-dessus, ex. "Direction des Sinistres", en attendant
        // le vrai nom du/des site(s) réel(s) — jamais fabriqué). Aucun
        // retour anticipé ici : $this->siteSelectionneId === null est un
        // parent_id valide (racine), enfantsActifsDe() le gère nativement
        // (where('parent_id', null) → whereNull, comportement standard
        // Eloquent) — pas un cas d'erreur à filtrer.
        return OrganizationUnit::enfantsActifsDe($this->siteSelectionneId, OrganizationUnit::TYPE_DEPARTMENT);
    }

    // Niveau "Service/Unité" — optionnel (spec §1 : un Département peut
    // recevoir directement des courriers sans Service en dessous). Inclut
    // service ET sub_service : un Sous-service ponté à un vrai service peut
    // aussi être choisi comme destination finale.
    #[Computed]
    public function unitesDisponibles()
    {
        if ($this->departementSelectionneId === null) {
            return collect();
        }

        return OrganizationUnit::query()
            ->where('parent_id', $this->departementSelectionneId)
            ->whereIn('type', [OrganizationUnit::TYPE_SERVICE, OrganizationUnit::TYPE_SUB_SERVICE])
            ->where('status', OrganizationUnit::STATUT_ACTIF)
            ->orderBy('name')
            ->get();
    }

    // Nœud RÉELLEMENT retenu pour la résolution finale : le Service/
    // Sous-service choisi s'il y en a un, sinon le Département lui-même
    // (spec §1 — un Département peut être la destination finale). Null si
    // rien n'est encore choisi.
    private function uniteFinale(): ?OrganizationUnit
    {
        if ($this->uniteSelectionneeId) {
            return OrganizationUnit::find($this->uniteSelectionneeId);
        }

        if ($this->departementSelectionneId) {
            return OrganizationUnit::find($this->departementSelectionneId);
        }

        return null;
    }

    // Maquette "Détail du courrier" (2026-09-18, demande explicite de
    // l'utilisateur — "use this design exactly", puis "on the parcour add
    // transfere after creation") — 6 étapes génériques (Création/Transfert/
    // Affectation/Traitement/Réponse/Clôture) : purement AFFICHÉES d'après
    // le statut réel et l'historique réel (aucune nouvelle transition ici,
    // voir l'onglet "Circuit de traitement" pour les actions elles-mêmes).
    // "archive" est traité comme équivalent de "traite" pour cette frise
    // (les deux signifient "Clôture" atteinte) — pas une 7e étape distincte.
    // Les clés du tableau ci-dessous sont des étapes CONCEPTUELLES, pas
    // toujours identiques au statut brut en base : 'enregistre' seul est
    // ambigu (il désigne aussi bien "juste créé, jamais transféré" — sortant
    // ou sinistre auto-routé, voir RegistrationForm::enregistrer() — que
    // "transfert validé par la DGA" — WorkflowService::validerService())
    // donc traité comme équivalent à "Transfert atteint" dans les deux cas
    // (jamais un courrier n'est "orphelin" entre Création et Transfert).
    // Chaque étape atteinte affiche la date RÉELLE de l'entrée d'historique
    // correspondante (voir WorkflowService pour les noms d'action exacts),
    // jamais une date inventée. Calculé côté composant plutôt qu'en
    // @php inline dans la vue : un bloc @php/@endphp à cet endroit précis
    // ne compilait pas correctement (restait littéralement "@php" dans le
    // HTML rendu, laissant $etapesParcours indéfinie — cause exacte non
    // identifiée avec certitude, contournée en évitant l'inline PHP dans
    // la vue pour ce calcul).
    #[Computed]
    public function etapesParcours(): array
    {
        $etapes = [
            'creation' => ['libelle' => __('Création'), 'action' => 'creation'],
            'transfert' => ['libelle' => __('Transfert'), 'action' => 'service_valide_dga'],
            'affecte' => ['libelle' => __('Affectation'), 'action' => 'affectation'],
            'en_traitement' => ['libelle' => __('Traitement'), 'action' => 'traitement_demarre'],
            'en_validation' => ['libelle' => __('Réponse'), 'action' => 'soumis_validation'],
            'traite' => ['libelle' => __('Clôture'), 'action' => 'validation_acceptee'],
        ];

        $ordre = array_keys($etapes);

        // Un courrier actuellement sur une piste ANNEXE (attente
        // d'information, rejet) a quand même déjà franchi certaines des
        // étapes standard AVANT de bifurquer — jamais "rien n'est encore
        // arrivé" juste parce que le statut littéral ne figure pas dans
        // $ordre. Constaté par l'utilisateur (capture d'écran) : un
        // courrier "en_attente_de_transfert" affichait "Création" comme
        // NON franchie, alors que le courrier existe forcément déjà.
        // 'enregistre'/'en_attente_de_transfert'/'en_cours_de_transfert'
        // sont TOUS mappés sur "Transfert" — un courrier fraîchement créé
        // (jamais affecté) est toujours au moins "en train d'être
        // transféré" (ou l'a déjà été/n'en avait pas besoin), jamais avant.
        $statutEffectif = match ($this->courrier->statut) {
            'archive' => 'traite',
            'enregistre', 'en_attente_de_transfert', 'en_cours_de_transfert' => 'transfert',
            'en_attente_information', 'rejete' => $this->dernierePositionAvantPause(),
            default => $this->courrier->statut,
        };

        $indexActuel = array_search($statutEffectif, $ordre, true);

        $resultat = [];

        foreach ($etapes as $statut => $definition) {
            $indexEtape = array_search($statut, $ordre, true);
            // "Création" (index 0) est TOUJOURS franchie pour une fiche
            // consultable — le courrier existe déjà, indépendamment de la
            // piste (standard ou annexe) sur laquelle il se trouve
            // actuellement.
            $atteinte = $indexEtape === 0 || ($indexActuel !== false && $indexEtape <= $indexActuel);
            // "Transfert" est un cas particulier : contrairement aux autres
            // étapes, `$statutEffectif === 'transfert'` reste vrai même une
            // fois le statut réel repassé à 'enregistre' (validé OU jamais
            // requis, voir plus haut) — cette étape n'est alors plus
            // "courante", juste franchie (sinon "Non applicable" ci-dessous
            // ne s'afficherait jamais pour un courrier sortant/sinistre).
            // Elle reste courante uniquement pendant le transfert LITTÉRAL,
            // ou si le courrier a été rejeté pendant qu'il y était (même
            // logique de "dernière position avant la pause" que les autres
            // étapes).
            $courante = $statut === 'transfert'
                ? in_array($this->courrier->statut, ['en_attente_de_transfert', 'en_cours_de_transfert'], true)
                    || ($this->courrier->statut === 'rejete' && $statutEffectif === 'transfert')
                : $statut === $statutEffectif;

            $dateAtteinte = $atteinte
                ? $this->courrier->historiques->firstWhere('action', $definition['action'])?->created_at
                : null;

            $resultat[] = [
                'statut' => $statut,
                'libelle' => $definition['libelle'],
                'atteinte' => $atteinte,
                'courante' => $courante,
                // Sous-libellé affiché sous l'étape : date réelle si
                // atteinte et retrouvée dans l'historique, sinon un état
                // textuel (jamais une date fabriquée pour une étape non
                // encore franchie). "Transfert" est le seul cas où une
                // étape peut être atteinte SANS date ni être courante : un
                // courrier sortant ou un sinistre auto-routé (voir
                // RegistrationForm::enregistrer()) n'a jamais eu besoin de
                // transfert, "Non applicable" plutôt que le "En attente"
                // par défaut qui suggérerait à tort que quelque chose reste
                // encore à faire sur une étape déjà dépassée.
                'sousLibelle' => match (true) {
                    $dateAtteinte !== null => $dateAtteinte->format('d/m H:i'),
                    $courante => __('En cours'),
                    $statut === 'transfert' && $atteinte => __('Non applicable'),
                    $statut === 'en_validation' && ! $atteinte => __('En attente'),
                    $statut === 'traite' && ! $atteinte => __('Non clôturé'),
                    default => __('En attente'),
                },
            ];
        }

        return $resultat;
    }

    // Retrouve la dernière étape RÉELLEMENT franchie avant qu'un courrier ne
    // bascule sur "en_attente_information" ou "rejete" (deux statuts
    // atteignables depuis plusieurs points du circuit — voir
    // WorkflowService::TRANSITIONS) : parcourt l'historique (déjà trié du
    // plus récent au plus ancien, voir Courrier::historiques()) et retourne
    // le statut correspondant à la première action de progression
    // rencontrée, jamais une position inventée. "enregistre" par défaut si
    // aucune action de progression n'est trouvée (ex. mis en attente
    // immédiatement après affectation, sans étape intermédiaire tracée).
    private function dernierePositionAvantPause(): string
    {
        $actionVersEtape = [
            'creation' => 'creation',
            'transfert' => 'transfert',
            'service_valide_dga' => 'transfert',
            'affectation' => 'affecte',
            'reaffectation' => 'affecte',
            'traitement_demarre' => 'en_traitement',
            'validation_refusee' => 'en_traitement',
            'reprise' => 'en_traitement',
            'soumis_validation' => 'en_validation',
            'validation_acceptee' => 'traite',
        ];

        foreach ($this->courrier->historiques as $entree) {
            if (isset($actionVersEtape[$entree->action])) {
                return $actionVersEtape[$entree->action];
            }
        }

        return 'creation';
    }

    // Module 4 — demande explicite de l'utilisateur : "make the parcours du
    // courier work live in real time at each step" — WorkflowService diffuse
    // `statut.change` sur le même canal privé que ocrTermine()/
    // classementPropose() ci-dessous à chaque transition réelle (affecter/
    // transferer/validerService/toute transition simple), pour que la fiche
    // ouverte dans un AUTRE onglet/session (un collègue qui affecte/traite le
    // même courrier) se mette à jour sans rechargement manuel — pas
    // seulement pour l'auteur de l'action lui-même (qui voit déjà la mise à
    // jour via le cycle normal requête/rendu Livewire de son propre clic).
    // Toutes les propriétés #[Computed] qui dépendent du statut sont
    // invalidées, pas seulement $this->courrier — sinon "Parcours du
    // courrier" (etapesParcours) ou les boutons d'action (peutAffecter,
    // peutTraiter...) resteraient figés sur l'ancien statut jusqu'au
    // prochain rendu qui touche $this->courrier pour une autre raison.
    #[On('echo-private:courrier.{courrierId},.statut.change')]
    public function statutChange(): void
    {
        unset(
            $this->courrier,
            $this->etapesParcours,
            $this->peutModifier,
            $this->peutAffecter,
            $this->peutTraiter,
            $this->peutValider,
            $this->peutTransferer,
            $this->peutValiderService,
            $this->collaborateursDuService,
            $this->destinatairesTransfert,
        );

        Flux::toast(text: __('Ce courrier a été mis à jour par un autre utilisateur — la fiche vient d\'être actualisée.'));
    }

    // Règle n°1 (notification de fin de job) sans wire:poll (Règle n°2) : le job
    // OCR diffuse `ocr.termine` sur le canal privé du courrier (Echo + Reverb),
    // la fiche invalide sa donnée mise en cache et se re-rend.
    #[On('echo-private:courrier.{courrierId},.ocr.termine')]
    public function ocrTermine(array $evenement = []): void
    {
        unset($this->courrier);

        $statut = $evenement['statut'] ?? null;

        Flux::toast(
            variant: $statut === 'reussi' ? 'success' : 'warning',
            text: match ($statut) {
                'reussi' => __('Numérisation terminée : texte extrait (confiance :c %).', ['c' => $evenement['confiance'] ?? '—']),
                'echec_qualite' => __('Numérisation terminée : scan jugé illisible, un nouveau scan est recommandé.'),
                default => __('Numérisation terminée : le traitement OCR a échoué (voir l\'historique).'),
            },
        );
    }

    // Diffusé par IndexCourrierJob à chaque changement de la proposition
    // (nouvelle proposition, ou proposition retirée après modification du courrier).
    #[On('echo-private:courrier.{courrierId},.classement.propose')]
    public function classementPropose(): void
    {
        unset($this->courrier);

        Flux::toast(
            variant: 'success',
            text: $this->courrier->classement_statut === 'propose'
                ? __('Le système propose un classement — à valider ou ignorer.')
                : __('Analyse automatique terminée.'),
        );
    }

    // Module 3 — "l'agent peut valider ou corriger la classification proposée".
    // Valider applique la proposition ; ignorer garde les valeurs saisies ; pour
    // corriger vers une troisième valeur, l'agent passe par "Modifier".
    public function validerClassement(): void
    {
        $courrier = $this->courrierModifiable('validerClassement');

        if ($courrier->classement_statut !== 'propose') {
            return;
        }

        DB::transaction(function () use ($courrier) {
            $changements = [];

            if ($courrier->type_document_propose && $courrier->type_document_propose !== $courrier->type_document) {
                $changements[] = "type « {$courrier->type_document} » → « {$courrier->type_document_propose} »";
                $courrier->type_document = $courrier->type_document_propose;
            }

            if ($courrier->service_propose_id && $courrier->service_propose_id !== $courrier->service_id) {
                $changements[] = 'service modifié';
                $courrier->service_id = $courrier->service_propose_id;
            }

            $courrier->classement_statut = 'valide';
            $courrier->save();

            CourrierHistorique::create([
                'courrier_id' => $courrier->id,
                'auteur_id' => Auth::id(),
                'action' => 'classement_valide',
                'commentaire' => $changements ? implode(', ', $changements) : 'Proposition identique aux valeurs saisies.',
            ]);
        });

        unset($this->courrier);

        Flux::toast(variant: 'success', text: __('Classement validé.'));
    }

    public function ignorerClassement(): void
    {
        $courrier = $this->courrierModifiable('validerClassement');

        if ($courrier->classement_statut !== 'propose') {
            return;
        }

        $courrier->update(['classement_statut' => 'ignore']);

        CourrierHistorique::create([
            'courrier_id' => $courrier->id,
            'auteur_id' => Auth::id(),
            'action' => 'classement_ignore',
            'commentaire' => 'Valeurs saisies conservées.',
        ]);

        unset($this->courrier);

        Flux::toast(text: __('Proposition ignorée, valeurs saisies conservées.'));
    }

    public function ajouterMotCle(): void
    {
        $courrier = $this->courrierModifiable('gererMotsCles');

        $this->validate(['nouveauMotCle' => ['required', 'string', 'min:2', 'max:60']]);

        $libelle = ClassificationService::libelle($this->nouveauMotCle);
        $motCle = MotCle::firstOrCreate(['libelle' => $libelle]);

        if (! $courrier->motsCles()->where('mot_cle_id', $motCle->id)->exists()) {
            $courrier->motsCles()->attach($motCle->id, ['source' => 'manuel']);

            CourrierHistorique::create([
                'courrier_id' => $courrier->id,
                'auteur_id' => Auth::id(),
                'action' => 'mot_cle_ajoute',
                'commentaire' => $libelle,
            ]);
        }

        $this->reset('nouveauMotCle');
        unset($this->courrier);
    }

    public function retirerMotCle(int $motCleId): void
    {
        $courrier = $this->courrierModifiable('gererMotsCles');
        $motCle = $courrier->motsCles()->where('mot_cle_id', $motCleId)->first();

        if (! $motCle) {
            return;
        }

        $courrier->motsCles()->detach($motCleId);

        CourrierHistorique::create([
            'courrier_id' => $courrier->id,
            'auteur_id' => Auth::id(),
            'action' => 'mot_cle_retire',
            'commentaire' => $motCle->libelle,
        ]);

        unset($this->courrier);
    }

    // Module 6 — première affectation. Le collaborateur choisi doit
    // appartenir au service du courrier (vérifié ici, jamais fait confiance
    // à l'ID posté — Règle n°6, même si la liste affichée est déjà filtrée).
    public function affecter(WorkflowService $workflow): void
    {
        $courrier = $this->courrierPour('affecter');
        $collaborateur = $this->collaborateurDuServiceOuEchec($courrier);

        if (! $collaborateur) {
            return;
        }

        $this->executer(fn () => $workflow->affecter($courrier, $collaborateur, Auth::user()), __('Courrier affecté à :nom.', ['nom' => $collaborateur->name]));
    }

    // Module 1/4 — la réceptionniste clique "Transférer" pour envoyer
    // explicitement le courrier au DGA/ADJ DGA (2026-09-15, voir DECISIONS.md,
    // synchronisation SRS-GEC.pdf) — plus d'envoi automatique et silencieux
    // à l'enregistrement. Mise à jour (2026-09-15) : elle choisit désormais
    // QUI, dans la modale "Transférer à" — le choix est revérifié contre
    // ses destinataires autorisés, jamais fait confiance à l'ID posté
    // (Règle n°6).
    public function transferer(WorkflowService $workflow): void
    {
        $courrier = $this->courrierPour('transferer');
        $destinataire = $this->destinataireAutoriseOuEchec();

        if (! $destinataire) {
            return;
        }

        $this->executer(fn () => $workflow->transferer($courrier, $destinataire, Auth::user()), __('Courrier transféré à :nom.', ['nom' => $destinataire->name]));
    }

    // Module 1/4 — DGA/ADJ confirme ou change le service d'un courrier
    // entrant non-sinistre, demande explicite de l'utilisateur (2026-09-08).
    // Module "Organisation" v2 (2026-09-22, spec §16) — la cascade
    // Site/Département/Service résout un nœud organization_units, mais
    // WorkflowService::validerService() continue de recevoir un VRAI
    // `services.id` (via le pont OrganizationUnit::service_id) — aucune
    // modification de WorkflowService/segmentClassement (Règle n°6 : jamais
    // fait confiance à l'ID posté, même la cascade est revérifiée ici).
    public function validerService(WorkflowService $workflow): void
    {
        $courrier = $this->courrierPour('validerService');

        if (! $this->departementSelectionneId && ! $this->uniteSelectionneeId) {
            $this->addError('uniteSelectionneeId', __('Choisissez un département (et, si besoin, un service).'));

            return;
        }

        $unite = $this->uniteFinale();

        // Une unité restée en mémoire d'un choix précédent (sélecteur masqué
        // puis ré-affiché, requête différée) ne doit jamais l'emporter sur le
        // département affiché — constaté le 2026-09-23 : courrier validé vers
        // "Sinistre Santé" alors que l'écran montrait "Departement Informatique".
        if ($unite && $this->uniteSelectionneeId && $this->departementSelectionneId
            && $unite->parent_id !== $this->departementSelectionneId) {
            $this->reset('uniteSelectionneeId');
            $this->addError('uniteSelectionneeId', __('La sélection a changé — vérifiez le département et le service, puis confirmez à nouveau.'));

            return;
        }

        if (! $unite || ! $unite->service_id) {
            $this->addError('uniteSelectionneeId', __('Choisissez une entité déjà reliée à un service réel (configurez le lien depuis Organisation si besoin).'));

            return;
        }

        $service = $unite->service;

        $this->validate(['confidentialiteSelectionnee' => ['required', 'integer', 'between:1,'.User::niveauConfidentialiteMax()]]);

        $reussi = $this->executer(fn () => $workflow->validerService($courrier, $service->id, $this->confidentialiteSelectionnee, Auth::user()), __('Service confirmé : :nom.', ['nom' => $service->nom]));

        // Une fois le service validé, le courrier quitte "en cours de
        // transfert" : le DGA perd normalement le droit de le consulter
        // (voir CourrierPolicy::view(), branche voir_dga) — sans cette
        // redirection, le re-rendu affichait une page 403 juste après un
        // succès, donnant l'impression que l'action avait échoué.
        if ($reussi && Auth::user()->cannot('view', $courrier->fresh())) {
            $this->redirect(route('courriers.a-traiter'), navigate: true);
        }
    }

    // Module 3/9 — ouvre la modale, pré-remplie avec le dossier actuel s'il
    // y en a un (Règle n°6 — jamais fait confiance à un ID posté sans
    // revérifier au moment d'agir, voir classerDansDossier() ci-dessous).
    public function ouvrirClassement(): void
    {
        $this->authorize('classer', $this->courrier);

        $this->dossierAClasserId = $this->courrier->dossier_classement_id;

        Flux::modal('classement-dossier-modal')->show();
    }

    public function classerDansDossier(DossierClassementService $service): void
    {
        $courrier = Courrier::findOrFail($this->courrierId);
        $this->authorize('classer', $courrier);

        $dossier = $this->dossiersAccessibles->firstWhere('id', $this->dossierAClasserId);

        if (! $dossier || Auth::user()->cannot('view', $dossier)) {
            $this->addError('dossierAClasserId', __('Choisissez un dossier.'));

            return;
        }

        $service->classer($courrier, $dossier, Auth::user());

        $this->reset('dossierAClasserId');
        unset($this->courrier, $this->dossiersAccessibles);

        Flux::modal('classement-dossier-modal')->close();
        Flux::toast(variant: 'success', text: __('Courrier classé dans « :nom ».', ['nom' => $dossier->nom]));
    }

    public function retirerDuDossier(DossierClassementService $service): void
    {
        $courrier = Courrier::findOrFail($this->courrierId);
        $this->authorize('classer', $courrier);

        if ($courrier->dossier_classement_id !== null && Auth::user()->cannot('view', $courrier->dossierClassement)) {
            return;
        }

        $service->retirer($courrier, Auth::user());

        $this->reset('dossierAClasserId');
        unset($this->courrier, $this->dossiersAccessibles);

        Flux::modal('classement-dossier-modal')->close();
        Flux::toast(text: __('Courrier retiré du dossier.'));
    }

    public function reaffecter(WorkflowService $workflow): void
    {
        $courrier = $this->courrierPour('reaffecter');
        $collaborateur = $this->collaborateurDuServiceOuEchec($courrier);

        if (! $collaborateur) {
            return;
        }

        $this->validate(['motifReaffectation' => ['required', 'string', 'min:5', 'max:500']], [], ['motifReaffectation' => __('motif de la réaffectation')]);

        $this->executer(fn () => $workflow->reaffecter($courrier, $collaborateur, Auth::user(), $this->motifReaffectation), __('Courrier réaffecté à :nom.', ['nom' => $collaborateur->name]));
    }

    public function demarrerTraitement(WorkflowService $workflow): void
    {
        $courrier = $this->courrierPour('traiter');

        $this->executer(fn () => $workflow->demarrerTraitement($courrier, Auth::user()), __('Traitement démarré.'));
    }

    public function soumettrePourValidation(WorkflowService $workflow): void
    {
        $courrier = $this->courrierPour('soumettreValidation');

        $this->executer(fn () => $workflow->soumettrePourValidation($courrier, Auth::user(), $this->commentaireCirculation ?: null), __('Soumis pour validation.'));
    }

    public function valider(WorkflowService $workflow): void
    {
        $courrier = $this->courrierPour('valider');

        $this->executer(fn () => $workflow->valider($courrier, Auth::user(), $this->commentaireCirculation ?: null), __('Courrier validé — traité.'));
    }

    public function renvoyerPourCorrection(WorkflowService $workflow): void
    {
        $courrier = $this->courrierPour('renvoyerCorrection');

        $this->validate(['motifRenvoi' => ['required', 'string', 'min:5', 'max:500']], [], ['motifRenvoi' => __('motif du renvoi')]);

        $this->executer(fn () => $workflow->renvoyerPourCorrection($courrier, Auth::user(), $this->motifRenvoi), __('Renvoyé pour correction.'), 'warning');
    }

    public function mettreEnAttente(WorkflowService $workflow): void
    {
        $courrier = $this->courrierPour('mettreEnAttente');

        $this->validate(['motifAttente' => ['required', 'string', 'min:5', 'max:500']], [], ['motifAttente' => __('motif de la mise en attente')]);

        $this->executer(fn () => $workflow->mettreEnAttente($courrier, Auth::user(), $this->motifAttente), __('Courrier mis en attente d\'information.'), 'warning');
    }

    public function reprendre(WorkflowService $workflow): void
    {
        $courrier = $this->courrierPour('reprendre');

        $this->executer(fn () => $workflow->reprendre($courrier, Auth::user()), __('Traitement repris.'));
    }

    public function rejeter(WorkflowService $workflow): void
    {
        $courrier = $this->courrierPour('rejeter');

        $this->validate(['motifRejet' => ['required', 'string', 'min:5', 'max:500']], [], ['motifRejet' => __('motif du rejet')]);

        $this->executer(fn () => $workflow->rejeter($courrier, Auth::user(), $this->motifRejet), __('Courrier rejeté.'), 'danger');
    }

    // "Mettre en attente" et "Reprendre" restent ouverts au collaborateur
    // affecté ET au responsable (le spec ne réserve pas cette étape à la
    // seule hiérarchie) : c'est désormais CourrierPolicy::mettreEnAttente()/
    // reprendre() qui accepte la portée traiter() OU valider() (2026-09-23).

    // Toute transition passe par ici : la Policy a déjà autorisé l'ACTEUR,
    // mais WorkflowService revérifie que le STATUT actuel autorise encore la
    // transition (Règle n°6 — jamais fait confiance à l'état affiché côté
    // client, qui peut être périmé si un autre onglet/utilisateur a agi entre
    // temps).
    private function executer(\Closure $action, string $messageSucces, string $variantSucces = 'success'): bool
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            report($e);
            unset($this->courrier);
            Flux::toast(variant: 'danger', text: __('Action impossible : la fiche a changé entre-temps, elle vient d\'être actualisée.'));

            return false;
        }

        $this->reinitialiserCirculation();

        Flux::toast(variant: $variantSucces, text: $messageSucces);

        return true;
    }

    private function collaborateurDuServiceOuEchec(Courrier $courrier): ?User
    {
        $collaborateur = User::query()
            ->where('id', $this->collaborateurSelectionne)
            ->where('service_id', $courrier->service_id)
            ->whereHas('profil', fn ($q) => $q->where('nom', 'Collaborateur'))
            ->first();

        if (! $collaborateur) {
            $this->addError('collaborateurSelectionne', __('Choisissez un collaborateur du service.'));
        }

        return $collaborateur;
    }

    // Module 1/4 — même principe que collaborateurDuServiceOuEchec() :
    // jamais fait confiance à l'ID posté, même si la liste affichée dans la
    // modale vient déjà de $this->destinatairesTransfert (Règle n°6).
    private function destinataireAutoriseOuEchec(): ?User
    {
        $destinataire = Auth::user()->destinatairesTransfert()->where('users.id', $this->destinataireTransfertChoisi)->first();

        if (! $destinataire) {
            $this->addError('destinataireTransfertChoisi', __('Choisissez un destinataire.'));
        }

        return $destinataire;
    }

    private function reinitialiserCirculation(): void
    {
        $this->reset('collaborateurSelectionne', 'siteSelectionneId', 'departementSelectionneId', 'uniteSelectionneeId', 'confidentialiteSelectionnee', 'destinataireTransfertChoisi', 'motifReaffectation', 'motifRenvoi', 'motifAttente', 'motifRejet', 'commentaireCirculation');
        unset(
            $this->courrier,
            $this->etapesParcours,
            $this->peutModifier,
            $this->peutAffecter,
            $this->peutTraiter,
            $this->peutValider,
            $this->peutTransferer,
            $this->peutValiderService,
            $this->collaborateursDuService,
            $this->destinatairesTransfert,
            $this->sitesDisponibles,
            $this->departementsDisponibles,
            $this->unitesDisponibles,
        );
    }

    // Règle n°6 — droits revérifiés à chaque action, jamais déduits de l'état client.
    // $abilite : privilège d'action précis (2026-09-23), toujours cumulé
    // avec le droit de modifier ce courrier (voir CourrierPolicy).
    private function courrierModifiable(string $abilite = 'update'): Courrier
    {
        $courrier = Courrier::findOrFail($this->courrierId);

        $this->authorize($abilite, $courrier);

        return $courrier;
    }

    // Charge le courrier avec ce dont les policies affecter/traiter/valider
    // ont besoin (service, affectation courante) et vérifie l'habilité
    // demandée — jamais fait confiance à un bouton affiché côté client.
    private function courrierPour(string $abilite): Courrier
    {
        $courrier = Courrier::with(['service', 'affectationCourante'])->findOrFail($this->courrierId);

        $this->authorize($abilite, $courrier);

        return $courrier;
    }

    public function render()
    {
        return view('frontend::showCourrier');
    }
}
