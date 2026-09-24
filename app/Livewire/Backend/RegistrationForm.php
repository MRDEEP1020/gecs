<?php

namespace App\Livewire\Backend;

use App\Jobs\IndexCourrierJob;
use App\Jobs\ProcessDocumentOcr;
use App\Jobs\ReplicateFichierJob;
use App\Livewire\Backend\Forms\CourrierForm;
use App\Models\Courrier;
use App\Models\CourrierBrouillon;
use App\Models\CourrierHistorique;
use App\Models\OrganizationUnit;
use App\Services\BrouillonScanService;
use App\Services\ClassificationService;
use App\Services\NumeroReferenceGenerator;
use App\Services\PieceJointeService;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;
use Throwable;

#[Title('Enregistrement d\'un courrier')]
class RegistrationForm extends Component
{
    use WithFileUploads;
    use WithPagination;

    // Module 1 — Enregistrement courrier entrant/sortant
    // Voir specifications-modules-GEC.md, section Module 1

    public CourrierForm $form;

    // Exception à la Règle n°2 (propriétés = primitifs) : c'est le mécanisme
    // d'upload natif de Livewire (TemporaryUploadedFile), pas un modèle Eloquent —
    // il n'existe pas d'alternative primitive pour recevoir un fichier en amont.
    public $pieceJointe = null;

    // Dossier surveillé (voir DECISIONS.md "Watcher automatique sur le
    // formulaire d'enregistrement") : même rôle que ScanPremier::$document,
    // rempli par resources/js/scan-watcher.js via l'input caché de
    // registrationForm.blade.php, jamais par l'agent directement.
    public $document = null;

    public ?string $derniereReference = null;

    public ?int $derniereCourrierId = null;

    // Module 1/2 — flux "scan d'abord" (voir DECISIONS.md "Flux scan-first") :
    // ID primitif uniquement (Règle n°2), jamais le modèle en propriété
    // publique — #[Url] pour recevoir ?brouillonId= depuis ScanPremier.
    // Non-nullable (0 = aucun) : le placeholder {brouillonId} de l'écouteur
    // echo-private ci-dessous ne peut pas s'interpoler sur une valeur null.
    #[Url]
    public int $brouillonId = 0;

    // Id de l'agent connecté, capturé dans mount() — sert uniquement au
    // placeholder {agentId} de l'écouteur echo-private de
    // brouillonOcrTermine() ci-dessous (canal privé par agent).
    public int $agentId = 0;

    // Module 1/3 — objet/service/type/destinataire/expéditeur pré-remplis
    // automatiquement depuis le texte du brouillon : jamais imposés sans
    // validation (même principe que le panneau Classement de ShowCourrier),
    // juste un indicateur pour que la vue rappelle à l'agent de vérifier
    // avant de confirmer.
    public bool $champsProposesAutomatiquement = false;

    // Même principe qu'un indicateur dédié (plutôt que de réutiliser
    // champsProposesAutomatiquement), appliqué au Mode de réception :
    // CourrierForm lui donne une valeur par défaut
    // ('depot_physique'), jamais vide — le garde-fou "indicateur partagé +
    // champ non vide" utilisé par les autres champs ne peut donc jamais
    // distinguer "non proposé" de "proposé", et afficherait "à vérifier" en
    // permanence dès qu'un AUTRE champ est proposé.
    public bool $modeReceptionProposeAutomatiquement = false;

    // Module 1 — "Type de document" est désormais une liste déroulante
    // (CourrierForm::TYPES_DOCUMENT, demande explicite de l'utilisateur,
    // 2026-09-08), pas un texte libre : une valeur proposée par le
    // classement automatique (Module 3, texte libre configuré par un admin
    // — voir preremplirDepuisBrouillon()) peut ne correspondre à AUCUNE
    // option de la liste. Plutôt que de l'écraser silencieusement ou de
    // laisser le select afficher un choix vide/trompeur, ce drapeau bascule
    // sur un champ texte libre montrant la vraie valeur proposée.
    public bool $typeDocumentPersonnalise = false;

    // Bug réel trouvé le 2026-09-10 (revue de code) : un courrier confidentiel
    // ne doit jamais afficher/enregistrer son objet réel ("l'objet réel du
    // courrier n'est donc jamais connu à ce stade", 2026-09-07), mais
    // preremplirDepuisBrouillon() pré-remplit l'objet AVANT que l'agent ait
    // choisi la confidentialité — updatedFormConfidentialite() ne l'écrasait
    // que si vide, donc un objet extrait par OCR (fréquent) restait affiché
    // et enregistré même après bascule en confidentiel. Cette valeur permet
    // de distinguer "l'agent a modifié l'objet depuis" (on ne l'écrase pas,
    // comportement déjà voulu) de "toujours la proposition automatique
    // brute" (on l'écrase, la fuite réelle).
    public ?string $objetProposeParOcr = null;

    // Module 5 — commentaire libre optionnel, attaché à l'entrée d'historique
    // de création (Règle n°5 : historique immuable, jamais un simple champ
    // texte sur Courrier). Maquette utilisateur du 2026-09-17 ("Informations
    // complémentaires" — Commentaires, 0/500).
    public string $commentaire = '';

    // Module "Organisation" v2 (2026-09-22, spec §16) — cascade Site →
    // Département → Service/Unité pour le sélecteur "sortant" (agent),
    // remplace le sélecteur plat unique. Le pont OrganizationUnit::service_id
    // résout un VRAI `services.id`, écrit sur `$this->form->service_id` dès
    // que la cascade est complète — CourrierForm/enregistrer() restent
    // INCHANGÉS (toujours un simple service_id validé/persisté).
    public ?int $siteSelectionneId = null;

    public ?int $departementSelectionneId = null;

    public ?int $uniteSelectionneeId = null;

    public function mount(): void
    {
        $this->authorize('create', Courrier::class);

        $this->agentId = (int) Auth::id();

        // brouillon déclenche déjà authorize('utiliser', ...) — 403 immédiat si
        // le brouillon appartient à quelqu'un d'autre (Règle n°6), pas un
        // paramètre ignoré silencieusement.
        if ($brouillon = $this->brouillon) {
            $this->appliquerBrouillon($brouillon);
        }

        $this->typeDocumentPersonnalise = $this->form->type_document !== ''
            && ! in_array($this->form->type_document, CourrierForm::typesDocument(), true);
    }

    // Bascule "Autre" du select vers le champ libre — voir $typeDocumentPersonnalise.
    public function updatedFormTypeDocument(string $valeur): void
    {
        if ($valeur === '__autre__') {
            $this->typeDocumentPersonnalise = true;
            $this->form->type_document = '';
        }
    }

    // Retour à la liste depuis le champ libre (ex. bascule accidentelle) —
    // vide la saisie personnalisée plutôt que de la laisser fantôme si
    // l'agent choisit ensuite une vraie option de la liste sans y repenser.
    public function choisirTypeDocumentDansLaListe(): void
    {
        $this->typeDocumentPersonnalise = false;
        $this->form->type_document = '';
    }

    // "Le système détecte les informations et les met dans un service
    // correspondant, en attente de validation humaine" (demande explicite) :
    // objet, destinataire, organisation, coordonnées (téléphone/email/
    // adresse séparés) et nom de l'expéditeur, mode de réception, extraits
    // directement du texte (voir ProcessDocumentOcr::extraireObjet()/
    // extraireDestinataire()/extraireExpediteurOrganisation()/
    // extraireExpediteurTelephone()/extraireExpediteurEmail()/
    // extraireExpediteurAdresse()/extraireExpediteurNom()/
    // extraireModeReception()), service et type
    // proposés en réutilisant le même moteur de règles que le classement
    // post-enregistrement (ClassificationService, Module 3). Ne remplace
    // jamais un choix déjà fait par l'agent, et reste entièrement modifiable
    // — l'indicateur "à vérifier" (champsProposesAutomatiquement) le rappelle
    // dans la vue plutôt que de faire semblant que c'est une saisie normale.
    // Le nom n'est tenté QUE via une convention explicite ("Je soussigné(e)",
    // "Signé :"/"Signature :") — une simple civilité en signature reste hors
    // périmètre, toujours trop ambiguë. Risque de faux positif accepté par
    // l'utilisateur pour destinataire et organisation expéditrice, décision
    // du 2026-09-04 (voir DECISIONS.md).
    private function preremplirDepuisBrouillon(CourrierBrouillon $brouillon): void
    {
        if ($brouillon->ocr_statut !== 'reussi' || blank($brouillon->texte_ocr)) {
            return;
        }

        if ($objet = ProcessDocumentOcr::extraireObjet($brouillon->texte_ocr)) {
            $this->form->objet = $objet;
            $this->objetProposeParOcr = $objet;
            $this->champsProposesAutomatiquement = true;
        }

        if ($destinataire = ProcessDocumentOcr::extraireDestinataire($brouillon->texte_ocr)) {
            $this->form->destinataire = $destinataire;
            $this->champsProposesAutomatiquement = true;
        }

        if ($organisation = ProcessDocumentOcr::extraireExpediteurOrganisation($brouillon->texte_ocr)) {
            $this->form->expediteur_organisation = $organisation;
            $this->champsProposesAutomatiquement = true;
        }

        if ($telephone = ProcessDocumentOcr::extraireExpediteurTelephone($brouillon->texte_ocr)) {
            $this->form->expediteur_telephone = $telephone;
            $this->champsProposesAutomatiquement = true;
        }

        if ($email = ProcessDocumentOcr::extraireExpediteurEmail($brouillon->texte_ocr)) {
            $this->form->expediteur_email = $email;
            $this->champsProposesAutomatiquement = true;
        }

        if ($adresse = ProcessDocumentOcr::extraireExpediteurAdresse($brouillon->texte_ocr)) {
            $this->form->expediteur_adresse = $adresse;
            $this->champsProposesAutomatiquement = true;
        }

        if ($rc = ProcessDocumentOcr::extraireExpediteurRc($brouillon->texte_ocr)) {
            $this->form->expediteur_rc = $rc;
            $this->champsProposesAutomatiquement = true;
        }

        if ($niu = ProcessDocumentOcr::extraireExpediteurNiu($brouillon->texte_ocr)) {
            $this->form->expediteur_niu = $niu;
            $this->champsProposesAutomatiquement = true;
        }

        if ($nom = ProcessDocumentOcr::extraireExpediteurNom($brouillon->texte_ocr)) {
            $this->form->expediteur_nom = $nom;
            $this->champsProposesAutomatiquement = true;
        }

        if ($modeReception = ProcessDocumentOcr::extraireModeReception($brouillon->texte_ocr)) {
            $this->form->mode_reception = $modeReception;
            $this->modeReceptionProposeAutomatiquement = true;
            $this->champsProposesAutomatiquement = true;
        }

        // Modèle non persisté : texte_ocr/ocr_statut et l'objet tout juste
        // extrait (s'il y en a un) sont renseignés, l'expéditeur reste vide —
        // ClassificationService juge donc sur le contenu du scan et l'objet.
        $proposition = app(ClassificationService::class)->classer(new Courrier([
            'texte_ocr' => $brouillon->texte_ocr,
            'ocr_statut' => $brouillon->ocr_statut,
            'objet' => $this->form->objet,
        ]));

        if ($proposition['service_id'] !== null) {
            $this->form->service_id = $proposition['service_id'];
            $this->preselectionnerCascadeDepuisService($proposition['service_id']);
            $this->champsProposesAutomatiquement = true;
        }

        if ($proposition['type_document'] !== null) {
            $this->form->type_document = $proposition['type_document'];
            $this->champsProposesAutomatiquement = true;
        }
    }

    // Module "Organisation" v2 (2026-09-22, spec §16) — mêmes 3 niveaux que
    // ShowCourrier::sitesDisponibles()/departementsDisponibles()/
    // unitesDisponibles() (dupliqués à l'identique, même convention que
    // services()/services() partout ailleurs dans ce projet — pas de
    // composant Livewire partagé, Règle n°2).
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

    // Changer un niveau vide les niveaux dépendants ET recalcule
    // $form->service_id via le nœud RÉELLEMENT retenu (Service/Unité choisi,
    // sinon le Département lui-même — spec §1) ; jamais un choix incohérent
    // laissé affiché, jamais un service_id périmé après un changement de
    // niveau supérieur.
    public function updatedSiteSelectionneId(): void
    {
        $this->reset('departementSelectionneId', 'uniteSelectionneeId');
        $this->form->service_id = null;
    }

    public function updatedDepartementSelectionneId(): void
    {
        $this->reset('uniteSelectionneeId');
        $this->resoudreServiceDepuisCascade();
    }

    public function updatedUniteSelectionneeId(): void
    {
        $this->resoudreServiceDepuisCascade();
    }

    private function resoudreServiceDepuisCascade(): void
    {
        $noeud = $this->uniteSelectionneeId
            ? OrganizationUnit::find($this->uniteSelectionneeId)
            : ($this->departementSelectionneId ? OrganizationUnit::find($this->departementSelectionneId) : null);

        $this->form->service_id = $noeud?->service_id;
    }

    // Même patron que ShowCourrier::preselectionnerCascadeDepuisService() —
    // retrouve, à partir d'un service_id proposé automatiquement (Module 3),
    // le nœud organization_units ponté et remonte ses ancêtres pour
    // pré-remplir la cascade. Aucun nœud ponté trouvé = cascade vide,
    // l'agent choisit manuellement (aucune donnée fabriquée).
    private function preselectionnerCascadeDepuisService(int $serviceId): void
    {
        $courant = OrganizationUnit::where('service_id', $serviceId)->first();

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

    // Rechargé + réautorisé à chaque requête à partir du seul ID (Règle n°2/n°6).
    #[Computed]
    public function brouillon(): ?CourrierBrouillon
    {
        if ($this->brouillonId === 0) {
            return null;
        }

        $brouillon = CourrierBrouillon::find($this->brouillonId);

        if ($brouillon === null) {
            return null;
        }

        $this->authorize('utiliser', $brouillon);

        return $brouillon;
    }

    // Date + pré-remplissage des champs pour UN brouillon — factorisé le
    // 2026-09-22 (repris par mount() ET brouillonOcrTermine() ci-dessous,
    // voir son commentaire : sans ce second appel, un agent qui ouvre un
    // brouillon AVANT la fin de l'OCR restait bloqué avec un formulaire
    // vide malgré la notification temps réel déjà câblée, devant rafraîchir
    // la page à la main pour relancer mount()).
    private function appliquerBrouillon(CourrierBrouillon $brouillon): void
    {
        // Le tampon n'est plus utilisé (voir DECISIONS.md) — sans lui, la
        // seule autre source de date restante était la date ÉCRITE par
        // l'expéditeur en tête du courrier (dateDepuisTexteCourrier(),
        // retirée le 2026-09-22 sur demande explicite de l'utilisateur) :
        // c'est la date de RÉDACTION/ENVOI par l'expéditeur, pas sa date de
        // RÉCEPTION chez Nsia — un courrier écrit une semaine avant
        // d'arriver proposait donc une date de réception fausse. La date du
        // jour (celui de l'enregistrement, qui coïncide avec la réception
        // dans l'immense majorité des cas) est un point de départ bien plus
        // fiable ; l'agent la corrige librement si ce courrier est traité
        // en retard sur un lot.
        $this->form->date_mouvement = self::dateDepuisTampon($brouillon->numero_tampon_detecte)
            ?? now()->format('Y-m-d');

        $this->preremplirDepuisBrouillon($brouillon);
    }

    // "Courriers perdus ou oubliés" (PRD.md, problème n°1) : un agent qui
    // scanne plusieurs documents avant d'avoir fini le premier formulaire ne
    // doit pas perdre la trace des suivants. Qui a `brouillons.utiliser_tout`
    // voit TOUS les brouillons en attente, pas seulement les siens
    // (2026-09-09, cohérence avec CourrierBrouillonPolicy::utiliser() qui
    // autorise déjà ce même privilège à UTILISER n'importe quel brouillon —
    // la liste ne le permettait simplement pas de le découvrir ; 2026-09-15,
    // aligné sur le système de privilèges, voir DECISIONS.md, au lieu d'un
    // nom de profil en dur). `creePar` chargé seulement dans ce cas
    // (Règle n°3 — pas de N+1, jamais nécessaire pour un agent qui ne voit
    // que les siens) pour distinguer les agents dans le tableau.
    //
    // Vraie pagination (demande explicite de l'utilisateur, 2026-09-14 :
    // "10 per table") — remplace le plafond fixe + compteur "non affiché"
    // du 2026-09-10 (même limite qu'un cap silencieux, juste rendue
    // visible ; la vraie pagination est la solution complète). Nom de page
    // dédié ('brouillonsPage') pour ne jamais entrer en collision avec un
    // autre paginateur sur la même page (Règle n°3 — pagination systématique).
    #[Computed]
    public function brouillonsEnAttente()
    {
        $peutTout = Auth::user()->hasPrivilege('brouillons.utiliser_tout');

        return CourrierBrouillon::query()
            ->when(
                $peutTout,
                fn ($q) => $q->with('creePar:id,name'),
                fn ($q) => $q->where('cree_par_id', Auth::id()),
            )
            // Un brouillon déjà finalisé (finalise_le non nul) a son fichier
            // déplacé/supprimé de brouillons/... par finaliserBrouillon() —
            // il ne doit plus jamais réapparaître ici comme "en attente"
            // (bug réel trouvé le 2026-09-09 : ce filtre manquait, un
            // brouillon fraîchement enregistré pouvait réapparaître dans la
            // liste juste après, puisque brouillonId repasse à 0).
            ->whereNull('finalise_le')
            ->when($this->brouillonId, fn ($q) => $q->whereKeyNot($this->brouillonId))
            ->latest()
            ->paginate(10, ['id', 'nom_original', 'ocr_statut', 'created_at', 'cree_par_id'], 'brouillonsPage');
    }

    // Point d'entrée du dossier surveillé depuis CETTE page (voir
    // DECISIONS.md "Watcher automatique sur le formulaire
    // d'enregistrement") — même méthode que ScanPremier::numeriserAutomatique(),
    // dupliquée plutôt que partagée entre composants Livewire (Règle n°2 —
    // pas d'imbrication de composants) : la logique commune passe par
    // BrouillonScanService, pas par une dépendance entre les deux
    // composants. Pas de toast/redirection (un scan en rafale ne doit pas
    // perturber l'agent en train de remplir le formulaire), mais PAS
    // #[Renderless] non plus contrairement à ScanPremier : ici le nouveau
    // brouillon doit apparaître dans la liste déroulante tout de suite,
    // c'est tout l'intérêt de la fonctionnalité sur cette page — un
    // re-render Livewire classique (morph) ne perd aucune saisie en cours
    // (les valeurs de $form déjà tapées voyagent avec chaque requête,
    // renderless ou non, et sont réappliquées au rendu).
    public function numeriserAutomatique(): array
    {
        $this->authorize('create', Courrier::class);
        $this->validate(['document' => BrouillonScanService::reglesValidation(ScanForm::resolutionMinimale())]);

        $brouillon = app(BrouillonScanService::class)->creer($this->document, CourrierBrouillon::SOURCE_DOSSIER_SURVEILLE);

        $this->reset('document');
        unset($this->brouillonsEnAttente);

        return [
            'brouillonId' => $brouillon->id,
            'nomOriginal' => $brouillon->nom_original,
        ];
    }

    // Import manuel visible (maquette utilisateur du 2026-09-17, étape 1
    // "Document" — bouton "Importer un fichier") : même mécanisme que
    // numeriserAutomatique() ci-dessus (dossier surveillé), mais déclenché
    // par un vrai clic sur CETTE page plutôt que par le watcher JS, et
    // met à jour brouillonId directement (au lieu de renvoyer un tableau à
    // JS) — #[Url] synchronise l'URL automatiquement, sans navigation
    // complète, pour que l'aperçu du document/les infos OCR apparaissent
    // immédiatement sur cette même page.
    public function importerFichier(): void
    {
        $this->authorize('create', Courrier::class);
        $this->validate(['document' => BrouillonScanService::reglesValidation(ScanForm::resolutionMinimale())]);

        $brouillon = app(BrouillonScanService::class)->creer($this->document, CourrierBrouillon::SOURCE_MANUEL);

        $this->reset('document');
        unset($this->brouillonsEnAttente);

        $this->brouillonId = $brouillon->id;
        $this->preremplirDepuisBrouillon($brouillon);
        $this->typeDocumentPersonnalise = $this->form->type_document !== ''
            && ! in_array($this->form->type_document, CourrierForm::typesDocument(), true);
    }

    // "Annuler" (étape 1 de la maquette) : abandonne le document actuellement
    // choisi sur CETTE page — le brouillon lui-même n'est ni supprimé ni
    // finalisé, il reste "en attente" et réapparaît dans la liste déroulante
    // (comportement déjà existant, voir brouillonsEnAttente()) pour être
    // repris plus tard, cohérent avec "Enregistrer et continuer plus tard".
    public function annulerDocument(): void
    {
        $this->reset('document');
        $this->brouillonId = 0;
        unset($this->brouillon, $this->brouillonsEnAttente);
    }

    // Règle n°1 (notification de fin de job) sans wire:poll (Règle n°2) : rafraîchit
    // le bandeau (numéro détecté), lève le blocage "numérisation en cours", ET
    // met à jour le statut affiché dans la liste déroulante (brouillonsEnAttente)
    // dès que ProcessBrouillonOcr termine — pour N'IMPORTE LEQUEL des
    // brouillons en attente de l'agent, pas seulement celui actuellement
    // sélectionné (voir DECISIONS.md "Watcher automatique sur le formulaire
    // d'enregistrement" — demande explicite : "it is only when i refresh the
    // page that it changes status"). D'où le canal par AGENT
    // (App.Models.User.{agentId}) plutôt que par brouillon.
    #[On('echo-private:App.Models.User.{agentId},.brouillon.ocr.termine')]
    public function brouillonOcrTermine(): void
    {
        unset($this->brouillon, $this->brouillonsEnAttente);
        $this->resetErrorBag('brouillonId');

        // Bug réel signalé par l'utilisateur (2026-09-22, "why do i have to
        // refresh before the ocr finished to extract the text after i
        // selected it") : ce listener rafraîchissait déjà le statut/la
        // liste, mais jamais les CHAMPS DU FORMULAIRE (objet, expéditeur...)
        // du brouillon actuellement ouvert — s'il avait été sélectionné
        // AVANT la fin de l'OCR, ses champs restaient figés vides jusqu'à un
        // rechargement manuel de la page (qui relance mount()). Ce canal
        // étant par AGENT (pas par brouillon), l'événement peut concerner un
        // AUTRE brouillon que celui actuellement ouvert : ré-appliquer est
        // sans effet dans ce cas (le brouillon rechargé n'a pas changé), et
        // corrige le cas visé quand c'est bien le même.
        if ($brouillon = $this->brouillon) {
            $this->appliquerBrouillon($brouillon);
        }
    }

    // Module 1 — demande explicite de l'utilisateur (2026-09-07), après
    // discussion avec la réception : un courrier confidentiel n'est jamais
    // ouvert par l'agent qui l'enregistre (juste le nom lu sur l'enveloppe,
    // orienté vers RH ou la DGA) — l'objet réel du courrier n'est donc
    // jamais connu à ce stade. Objet reste obligatoire (Règle métier
    // Module 1 : "un enregistrement incomplet ne peut pas être validé"),
    // mais se voit imposer un texte générique dès que l'agent passe le
    // courrier en confidentiel — jamais écrasé si l'agent a déjà saisi
    // quelque chose (ex. bascule accidentelle, ou un objet malgré tout
    // partiellement connu). Écrasé aussi si l'objet affiché est ENCORE
    // exactement la proposition automatique de l'OCR (objetProposeParOcr) :
    // sans ce second cas, un objet réel extrait du texte scanné restait
    // affiché/enregistré tel quel après bascule en confidentiel — bug réel
    // trouvé le 2026-09-10 (revue de code), contraire à la règle du
    // 2026-09-07 selon laquelle l'objet réel n'est jamais censé être connu
    // à ce stade pour un courrier confidentiel.
    public function updatedFormConfidentialite(int $valeur): void
    {
        $objetEncoreBrut = $this->form->objet === $this->objetProposeParOcr;

        // 2026-09-21 — "confidentialite" est un entier 1-5 (voir
        // User::NIVEAU_CONFIDENTIALITE_MAX) : > 1 signifie "au-dessus du
        // niveau le plus bas", même condition qu'avant
        // (in_array(['confidentiel', 'tres_confidentiel'])).
        if ($valeur > 1 && (blank($this->form->objet) || $objetEncoreBrut)) {
            $this->form->objet = __('Correspondance confidentielle (non ouverte)');
        }
    }

    public function enregistrer(NumeroReferenceGenerator $generateur, PieceJointeService $pieceJointeService): void
    {
        $this->authorize('create', Courrier::class);

        $brouillon = $this->brouillon;

        // 'non_traite' (job pas encore démarré — file d'attente de la queue,
        // worker arrêté...) ajouté le 2026-09-10 (revue de code) : ce garde-fou
        // ne bloquait que 'en_cours', pas l'état initial. Sans ce cas, un
        // brouillon pouvait être finalisé (sa ligne supprimée par
        // finaliserBrouillon()) AVANT même que ProcessBrouillonOcr démarre —
        // le job, une fois lancé, mettait alors à jour une ligne qui n'existe
        // plus (0 ligne affectée, silencieux), perdant le texte OCR/tampon
        // pour toujours sans aucune erreur visible.
        if ($brouillon && in_array($brouillon->ocr_statut, ['non_traite', 'en_cours'], true)) {
            $this->addError('brouillonId', __('La numérisation de ce document est encore en cours — réessayez dans quelques instants.'));

            return;
        }

        // Flux select "— Sans objet —" lie une chaîne vide, pas null : sans
        // cette normalisation, `nullable` (qui n'ignore que null, jamais '')
        // laisse passer '' jusqu'à Rule::in(['materiel','corporel']) et échoue.
        $this->form->sous_type_sinistre = $this->form->sous_type_sinistre ?: null;

        $data = $this->form->validate();
        $this->validate([
            'pieceJointe' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);

        // Module 1/4 — demande explicite de l'utilisateur (2026-09-08, précisée
        // le 2026-09-15 par SRS-GEC.pdf, voir DECISIONS.md) : le service n'est
        // plus saisi par l'agent pour un courrier ENTRANT — il est ignoré ici
        // (jamais persisté depuis le formulaire), et reste inconnu jusqu'à ce
        // que le DGA/ADJ DGA le choisisse (WorkflowService::validerService()).
        // Exception : un sinistre confirmé par la réceptionniste (type_document
        // = "Sinistre", voir CourrierForm::estUnSinistre()) est routé
        // directement au bon service via la même règle de classement que le
        // Module 3 (Module 3, "sinistre" → DSIN, donnée de configuration, pas
        // de code) — il saute la validation DGA, comme aujourd'hui. Si cette
        // règle ne résout aucun service (ex. non configurée), on retombe sur
        // la validation DGA plutôt que de laisser un courrier "enregistré"
        // sans service, orphelin (Module 6 : "un courrier a toujours un
        // responsable identifié"). Uniquement pour l'entrant : le sortant
        // garde le circuit actuel inchangé, service toujours requis à la
        // saisie (portée confirmée avec l'utilisateur, voir DECISIONS.md).
        //
        // 'en_attente_de_transfert', pas 'en_attente_validation_dga' : un
        // courrier entrant non-sinistre attend maintenant que la
        // réceptionniste clique explicitement "Transférer"
        // (WorkflowService::transferer()) avant de rejoindre la file du DGA
        // — le transfert n'est plus automatique à l'enregistrement (voir
        // DECISIONS.md, synchronisation SRS-GEC.pdf).
        if ($this->form->sens === 'entrant') {
            $data['service_id'] = null;

            if ($this->form->estUnSinistre()) {
                $proposition = app(ClassificationService::class)->classer(new Courrier(['objet' => $data['objet']]));
                $data['service_id'] = $proposition['service_id'];
            }

            if ($data['service_id'] === null) {
                $data['statut'] = 'en_attente_de_transfert';
            }
        }

        $courrier = DB::transaction(function () use ($data, $generateur) {
            $courrier = Courrier::create([
                ...$data,
                'numero_reference' => $generateur->generer(),
            ]);

            // Règle n°5 — toute création crée une entrée d'historique immuable
            CourrierHistorique::create([
                'courrier_id' => $courrier->id,
                'auteur_id' => Auth::id(),
                'action' => 'creation',
                'commentaire' => $this->commentaire ?: null,
            ]);

            return $courrier;
        });

        // Module 3 — "à l'enregistrement, le système propose un classement" : en
        // Job (Règle n°1), sur objet/expéditeur ; relancé après l'OCR sur le texte.
        IndexCourrierJob::dispatch($courrier)->onQueue('indexation');

        $avertissement = $brouillon ? $this->finaliserBrouillon($courrier, $brouillon) : null;

        if ($this->pieceJointe) {
            try {
                $pieceJointeService->attacher($courrier, $this->pieceJointe, Auth::id());
            } catch (Throwable $e) {
                report($e);
                $avertissement ??= __('Courrier enregistré, mais la pièce jointe n\'a pas pu être stockée (stockage indisponible).');
            }
        }

        $this->derniereReference = $courrier->numero_reference;
        $this->derniereCourrierId = $courrier->id;

        $this->form->reset();
        // champsProposesAutomatiquement inclus : le composant reste monté
        // après l'enregistrement (pas de redirection — l'agent peut
        // enchaîner un autre courrier via brouillonsEnAttente, voir son
        // commentaire), donc sans ce reset les bandeaux "à vérifier" d'un
        // précédent brouillon restaient affichés sous des valeurs ensuite
        // saisies à la main pour le courrier suivant — bug réel constaté
        // lors de la revue du 2026-09-04, voir DECISIONS.md.
        $this->reset('pieceJointe', 'commentaire', 'champsProposesAutomatiquement', 'modeReceptionProposeAutomatiquement', 'typeDocumentPersonnalise');
        $this->brouillonId = 0;
        unset($this->brouillon, $this->brouillonsEnAttente);

        Flux::toast(
            variant: $avertissement ? 'warning' : 'success',
            text: $avertissement ?? __('Courrier enregistré sous la référence :ref.', ['ref' => $courrier->numero_reference]),
        );

        // Maquette utilisateur du 2026-09-17 (vue en 5 étapes) : le
        // composant Alpine qui pilote l'étape affichée n'est jamais
        // réinitialisé par un re-render Livewire (morph, pas une vraie
        // navigation) — cet évènement lui signale de passer à l'étape 5
        // "Confirmation", plutôt qu'un x-effect fragile dépendant du texte
        // interpolé côté serveur.
        $this->dispatch('courrier-enregistre');
    }

    // "Enregistrer un autre courrier" (étape 5 de la maquette) : la
    // confirmation précédente ($derniereReference) n'était jusqu'ici jamais
    // effacée (utile pour garder "Voir le courrier" disponible après coup) —
    // ce bouton l'efface explicitement pour qu'un retour ultérieur à
    // l'étape 5 ne réaffiche pas une confirmation obsolète.
    public function nouveauCourrier(): void
    {
        $this->reset('derniereReference', 'derniereCourrierId');
    }

    // Déplace le document du brouillon vers son chemin final et lui copie les
    // champs OCR. Synchrone (pas un Job) mais résilient — même pattern que la
    // pièce jointe ci-dessus : une copie S3→S3 d'un seul fichier déjà traité,
    // déclenchée par une action utilisateur unique, n'est pas la catégorie de
    // traitement lourd visée par la Règle n°1 (voir DECISIONS.md "Flux scan-first").
    // Retourne un message d'avertissement si la finalisation échoue, sinon null.
    private function finaliserBrouillon(Courrier $courrier, CourrierBrouillon $brouillon): ?string
    {
        // Verrou anti-double-soumission (double clic, deux onglets) dans une
        // transaction courte et séparée — jamais l'opération réseau qui suit.
        $verrouille = DB::transaction(function () use ($brouillon) {
            $ligne = CourrierBrouillon::whereKey($brouillon->id)->lockForUpdate()->first();

            if (! $ligne || $ligne->finalise_le !== null) {
                return null;
            }

            $ligne->update(['finalise_le' => now()]);

            return $ligne;
        });

        if ($verrouille === null) {
            // Déjà finalisé par une requête concurrente : rien à refaire.
            return null;
        }

        $courrier->loadMissing('service');

        $extension = pathinfo($verrouille->fichier_path, PATHINFO_EXTENSION) ?: 'pdf';
        // segmentClassement() replie sur "_en_attente" tant qu'un courrier
        // entrant n'a pas encore de service (voir DECISIONS.md, synchronisation
        // SRS-GEC.pdf) — le fichier est déplacé vers le bon dossier service par
        // WorkflowService::validerService() une fois le DGA passé.
        $cheminFinal = sprintf(
            'courriers/%d/%s/%s.%s',
            now()->year,
            $courrier->segmentClassement(),
            $courrier->numero_reference,
            $extension,
        );

        try {
            Storage::disk('s3')->copy($verrouille->fichier_path, $cheminFinal);

            if (! Storage::disk('s3')->exists($cheminFinal)) {
                throw new RuntimeException('Copie du brouillon introuvable au chemin final après coup.');
            }

            Storage::disk('s3')->delete($verrouille->fichier_path);

            // Règle n°4 (complétée) — le brouillon avait été répliqué vers
            // le disque de secours dès sa création (BrouillonScanService::
            // creer(), ReplicateFichierJob). Sans ce nettoyage, une copie
            // orpheline restait indéfiniment à l'ancien chemin
            // brouillons/... même une fois le fichier définitif répliqué à
            // son tour vers courriers/... (bug réel trouvé en revue de
            // code, 2026-09-10). Même garde que ReplicateFichierJob : rien
            // à faire si aucun disque de secours n'est configuré (dev), et
            // `exists()` répond simplement `false` si la réplication du
            // brouillon n'avait pas encore eu lieu — jamais bloquant.
            if (filled(config('filesystems.disks.s3_backup.bucket')) && Storage::disk('s3_backup')->exists($verrouille->fichier_path)) {
                Storage::disk('s3_backup')->delete($verrouille->fichier_path);
            }

            $courrier->update([
                'fichier_path' => $cheminFinal,
                'texte_ocr' => $verrouille->texte_ocr,
                'ocr_statut' => $verrouille->ocr_statut,
                'ocr_confiance' => $verrouille->ocr_confiance,
                'ocr_traite_le' => now(),
                'numero_tampon_detecte' => $verrouille->numero_tampon_detecte,
            ]);

            // Règle n°5 — toute action crée une entrée d'historique immuable ;
            // même action que ScanForm::numeriser() (le document scanné est
            // attaché au courrier, peu importe le point d'entrée) — manquait
            // ici avant le 2026-09-10 (trouvé en revue de code), laissant le
            // flux scan-first sans aucune trace de la numérisation.
            CourrierHistorique::create([
                'courrier_id' => $courrier->id,
                'auteur_id' => Auth::id(),
                'action' => 'numerisation',
                'commentaire' => null,
            ]);

            // Règle n°4 (complétée) — même garantie de secours qu'un scan classique ;
            // l'ancienne copie au chemin brouillon n'est jamais répliquée elle-même.
            ReplicateFichierJob::dispatch($cheminFinal)->onQueue('replication');

            // Module 3 — même double déclenchement qu'un scan classique
            // (enregistrement, puis texte OCR une fois disponible).
            if ($verrouille->ocr_statut === 'reussi') {
                IndexCourrierJob::dispatch($courrier->fresh())->onQueue('indexation');
            }

            $verrouille->delete();

            return null;
        } catch (Throwable $e) {
            report($e);

            // Le brouillon n'est pas supprimé : récupérable manuellement, et
            // ScanForm (inchangé) reste le filet de secours pour re-numériser.
            return __('Courrier enregistré sous la référence :ref, mais le document scanné n\'a pas pu être rattaché — vous pouvez le re-numériser depuis la fiche.', ['ref' => $courrier->numero_reference]);
        }
    }

    // Mois abrégés (tampon, voir ProcessDocumentOcr::extraireNumeroTampon) et
    // en toutes lettres (date écrite du courrier, voir dateDepuisTexteCourrier
    // ci-dessous) — clés en ascii minuscule après normalisation, pour rester
    // insensible aux accents/à la casse. "juin"/"juil" gardés en entier (pas
    // de préfixe à 3 lettres) : les trois premières lettres seules sont
    // ambiguës entre les deux ; les noms en toutes lettres n'ont pas ce
    // problème (mots entièrement distincts), pas besoin de préfixe pour eux.
    private const MOIS_FRANCAIS = [
        'janv' => 1, 'jan' => 1, 'janvier' => 1,
        'fevr' => 2, 'fev' => 2, 'feb' => 2, 'fevrier' => 2,
        'mars' => 3, 'mar' => 3,
        'avr' => 4, 'apr' => 4, 'avril' => 4,
        'mai' => 5, 'may' => 5,
        'juin' => 6, 'jun' => 6,
        'juil' => 7, 'jul' => 7, 'juillet' => 7,
        'aout' => 8, 'aug' => 8,
        'sept' => 9, 'sep' => 9, 'septembre' => 9,
        'oct' => 10, 'octobre' => 10,
        'nov' => 11, 'novembre' => 11,
        'dec' => 12, 'decembre' => 12,
    ];

    // Reparse le groupe date du texte déjà détecté par
    // ProcessDocumentOcr::extraireNumeroTampon() pour pré-remplir le
    // formulaire ; best-effort, jamais bloquant.
    private static function dateDepuisTampon(?string $numeroTampon): ?string
    {
        if ($numeroTampon === null) {
            return null;
        }

        if (! preg_match('/(\d{1,2})\s+([A-Za-zÉÛéû]{3,5})\W{0,3}(\d{2,4})/u', $numeroTampon, $m)) {
            return null;
        }

        [, $jour, $moisTexte, $annee] = $m;

        $mois = self::MOIS_FRANCAIS[mb_strtolower(Str::ascii($moisTexte))] ?? null;

        if ($mois === null) {
            return null;
        }

        if (mb_strlen($annee) === 2) {
            $annee = '20'.$annee;
        }

        // checkdate() AVANT Carbon (bug réel trouvé en revue de code,
        // 2026-09-10) : Carbon::createFromDate() ne lève PAS d'exception
        // pour un jour hors plage (ex. 31 avril) — il fait silencieusement
        // déborder la date sur le mois suivant (1er mai). Cette date est la
        // seule proposition automatique traitée comme fiable, sans bandeau
        // "à vérifier" (voir mount()) : un OCR mal lu produirait sinon une
        // date de réception fausse, enregistrée sans aucun signal.
        if (! checkdate($mois, (int) $jour, (int) $annee)) {
            return null;
        }

        try {
            return Carbon::createFromDate((int) $annee, $mois, (int) $jour)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    public function render()
    {
        return view('frontend::registrationForm');
    }
}
