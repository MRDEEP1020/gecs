<?php

namespace App\Policies;

use App\Models\Courrier;
use App\Models\User;

class CourrierPolicy
{
    // Module 9 — Droits d'accès. Toute action sensible passe par ici,
    // jamais un `if` direct dans une vue ou un composant Livewire.
    //
    // 2026-09-15 — Système de privilèges (voir DECISIONS.md "Système de
    // privilèges") : chaque branche ci-dessous vérifiait auparavant
    // `$user->profil?->nom` contre un nom de profil en dur ; désormais
    // `$user->hasPrivilege('...')`, assignable dynamiquement à un profil
    // ET/OU à un utilisateur précis depuis /admin/privileges, sans changer
    // de code. Le PÉRIMÈTRE (scope) de chaque privilège "_service"/
    // "_propre"/"_affecte"/"_dga" reste inchangé (même condition qu'avant),
    // seul le contrôle "qui a le droit" est désormais dynamique.

    // Module 1 — saisie réservée à l'agent courrier / secrétariat (voir
    // specifications-modules-GEC.md), l'administrateur gardant accès à tout.
    public function create(User $user): bool
    {
        return $user->hasPrivilege('courriers.creer');
    }

    // "Confidentialité numérique hiérarchique" (2026-09-21, voir
    // DECISIONS.md) : le niveau de confidentialité MAXIMUM de l'utilisateur
    // (User::niveau_confidentialite, page "Utilisateurs & Accès") est
    // comparé numériquement au niveau du courrier (Courrier::confidentialite,
    // entier 1-5 — voir User::NIVEAU_CONFIDENTIALITE_MAX) — SE CUMULE avec
    // le système de privilèges ci-dessous sur TOUTE ability qui reçoit un
    // $courrier précis, ne le remplace jamais : même un privilège
    // "modifier_tout"/"affecter_tout"/etc. ne suffit plus si le niveau est
    // insuffisant. Un utilisateur ne devrait pas pouvoir MODIFIER/AFFECTER/
    // TRAITER un courrier qu'il n'a de toute façon pas le droit de voir —
    // pas seulement bloqué sur view() pendant que les autres abilities
    // resteraient ouvertes (ex. EditForm autorise directement 'update',
    // jamais 'view' d'abord).
    private function niveauSuffisant(User $user, Courrier $courrier): bool
    {
        return $user->niveauConfidentialiteEffectif() >= $courrier->confidentialite;
    }

    // Module 3/9 — troisième mécanisme cumulatif (DECISIONS.md 2026-09-16 :
    // "les trois se cumulent"), en plus de niveauSuffisant() et du système
    // de privilèges ci-dessous. Un courrier NON classé (dossier_classement_id
    // null) n'est jamais affecté — seuls les courriers rangés dans un
    // dossier réel héritent de sa restriction d'accès (gerer_tout, ou
    // créateur/responsable du dossier, ou partage explicite
    // dossier_classement_user — jamais un privilège de périmètre générique,
    // voir PrivilegeSeeder.php).
    private function accesDossierSuffisant(User $user, Courrier $courrier): bool
    {
        if ($courrier->dossier_classement_id === null) {
            return true;
        }

        $dossier = $courrier->dossierClassement;

        if ($dossier === null) {
            // FK orpheline (nullOnDelete) — ne bloque jamais un accès sur
            // un état incohérent.
            return true;
        }

        if ($user->hasPrivilege('dossiers_classement.gerer_tout')) {
            return true;
        }

        if ($user->id === $dossier->cree_par_id || $user->id === $dossier->responsable_id) {
            return true;
        }

        return $dossier->utilisateursAutorises()->where('users.id', $user->id)->exists();
    }

    // Module "Organisation" v2 (2026-09-22, spec §18/§19) — QUATRIÈME
    // mécanisme cumulatif : "Le frontend ne doit jamais être la seule
    // protection... Les règles doivent également être appliquées côté
    // backend." Un utilisateur SANS périmètre assigné n'est pas restreint
    // par ce gate (opt-in, même principe que accesDossierSuffisant() pour
    // un courrier non classé) ; un utilisateur AVEC un périmètre ne voit/
    // n'agit que sur les courriers dont le service (via le pont
    // OrganizationUnit::service_id) est sous l'une des entités accordées.
    private function accesPerimetreSuffisant(User $user, Courrier $courrier): bool
    {
        return $courrier->estDansLePerimetreDe($user);
    }

    // Périmètre par profil (Module 8 : "un agent ne voit que les courriers de
    // son périmètre, sauf droits élargis"). Vérifié en PREMIER, avant tout
    // privilège de périmètre, pour bloquer même un accès par lien direct
    // (email/notification), pas seulement filtrer les listes (voir aussi
    // CourrierList::resultats()).
    public function view(User $user, Courrier $courrier): bool
    {
        if (! $this->niveauSuffisant($user, $courrier)) {
            return false;
        }

        if (! $this->accesDossierSuffisant($user, $courrier)) {
            return false;
        }

        if (! $this->accesPerimetreSuffisant($user, $courrier)) {
            return false;
        }

        if ($user->hasPrivilege('courriers.voir_tout')) {
            return true;
        }

        // service null-safe : un courrier entrant en attente de validation
        // DGA/ADJ n'a pas encore de service (voir DECISIONS.md,
        // synchronisation SRS-GEC.pdf) — un Responsable de service ne doit
        // simplement pas le voir tant que ce n'est pas le cas, pas planter.
        if ($user->hasPrivilege('courriers.voir_service') && $user->id === $courrier->service?->responsable_id) {
            return true;
        }

        if ($user->hasPrivilege('courriers.voir_propre') && $courrier->estCreeParUtilisateur($user)) {
            return true;
        }

        if ($user->hasPrivilege('courriers.voir_affecte') && $courrier->affectationCourante?->user_id === $user->id) {
            return true;
        }

        // 2026-09-08 — rôle global (pas de service_id), scopé au seul
        // statut où DGA a réellement quelque chose à faire, même logique
        // que les autres profils (périmètre = ce qui les concerne
        // actuellement, pas un historique). 'en_cours_de_transfert', pas
        // 'en_attente_de_transfert' (2026-09-15, voir DECISIONS.md,
        // synchronisation SRS-GEC.pdf) : le DGA n'a rien à faire tant que la
        // réceptionniste n'a pas explicitement cliqué "Transférer".
        // 2026-09-15 (mise à jour) — depuis que la réceptionniste choisit UN
        // destinataire précis (voir DECISIONS.md "Destinataires de
        // transfert"), un courrier adressé à quelqu'un d'autre ne doit plus
        // apparaître ici : `destinataire_transfert_id === null` reste
        // autorisé pour tout DGA-privilégié — comportement d'avant,
        // préservé pour les courriers transférés avant ce changement.
        if ($user->hasPrivilege('courriers.voir_dga')
            && $courrier->statut === 'en_cours_de_transfert'
            && ($courrier->destinataire_transfert_id === null || $courrier->destinataire_transfert_id === $user->id)) {
            return true;
        }

        return false;
    }

    // Module 1 — corriger un enregistrement : même périmètre que la consultation
    // (Agent limité à ce qu'il a lui-même enregistré), l'Administrateur pouvant
    // tout corriger.
    public function update(User $user, Courrier $courrier): bool
    {
        if (! $this->niveauSuffisant($user, $courrier)) {
            return false;
        }

        if (! $this->accesDossierSuffisant($user, $courrier)) {
            return false;
        }

        if (! $this->accesPerimetreSuffisant($user, $courrier)) {
            return false;
        }

        // Module 9 — "les documents archivés restent consultables mais ne
        // sont plus modifiables" : jamais sur view(), seulement sur les
        // abilities qui mutent le courrier.
        if ($courrier->statut === 'archive') {
            return false;
        }

        if ($user->hasPrivilege('courriers.modifier_tout')) {
            return true;
        }

        if ($user->hasPrivilege('courriers.modifier_propre') && $courrier->estCreeParUtilisateur($user)) {
            return true;
        }

        return false;
    }

    public function archive(User $user, $courrier): bool
    {
        // Pas encore implémenté (voir specifications-modules-GEC.md Module 9) —
        // câblé sur un privilège dédié pour être prêt. Assigné à
        // Administrateur (comme tous les privilèges, 2026-09-15) mais sans
        // effet réel tant que l'action d'archivage elle-même n'existe pas
        // ailleurs dans le code.
        return $user->hasPrivilege('courriers.archiver');
    }

    // Module 6 — affecter/réaffecter : le responsable du service concerné,
    // ou un administrateur. Un agent ou un collaborateur ne s'auto-affecte pas.
    public function affecter(User $user, Courrier $courrier): bool
    {
        if (! $this->niveauSuffisant($user, $courrier)) {
            return false;
        }

        if (! $this->accesDossierSuffisant($user, $courrier)) {
            return false;
        }

        if (! $this->accesPerimetreSuffisant($user, $courrier)) {
            return false;
        }

        if ($courrier->statut === 'archive') {
            return false;
        }

        if ($user->hasPrivilege('courriers.affecter_tout')) {
            return true;
        }

        if ($user->hasPrivilege('courriers.affecter_service') && $user->id === $courrier->service?->responsable_id) {
            return true;
        }

        return false;
    }

    // Module 4 — démarrer le traitement / soumettre pour validation : réservé
    // au collaborateur actuellement affecté (dernière ligne d'affectations),
    // ou à un administrateur.
    public function traiter(User $user, Courrier $courrier): bool
    {
        if (! $this->niveauSuffisant($user, $courrier)) {
            return false;
        }

        if (! $this->accesDossierSuffisant($user, $courrier)) {
            return false;
        }

        if (! $this->accesPerimetreSuffisant($user, $courrier)) {
            return false;
        }

        if ($courrier->statut === 'archive') {
            return false;
        }

        if ($user->hasPrivilege('courriers.traiter_tout')) {
            return true;
        }

        if ($user->hasPrivilege('courriers.traiter_affecte') && $courrier->affectationCourante?->user_id === $user->id) {
            return true;
        }

        return false;
    }

    // Module 4 — valider/renvoyer/rejeter/mettre en attente : le "supérieur
    // hiérarchique" du spec est ici le responsable du service concerné.
    public function valider(User $user, Courrier $courrier): bool
    {
        if (! $this->niveauSuffisant($user, $courrier)) {
            return false;
        }

        if (! $this->accesDossierSuffisant($user, $courrier)) {
            return false;
        }

        if (! $this->accesPerimetreSuffisant($user, $courrier)) {
            return false;
        }

        if ($courrier->statut === 'archive') {
            return false;
        }

        if ($user->hasPrivilege('courriers.valider_tout')) {
            return true;
        }

        if ($user->hasPrivilege('courriers.valider_service') && $user->id === $courrier->service?->responsable_id) {
            return true;
        }

        return false;
    }

    // Module 4 — file d'attente : réservée aux acteurs du circuit (un Agent
    // enregistre des courriers mais ne les traite pas).
    public function voirFileAttente(User $user): bool
    {
        return $user->hasPrivilege('courriers.voir_file_attente');
    }

    // Module 1/4 — la réceptionniste clique "Transférer" pour envoyer
    // explicitement au DGA/ADJ DGA un courrier entrant en attente (2026-09-15,
    // voir DECISIONS.md, synchronisation SRS-GEC.pdf). Même forme que
    // update() : "_tout" pour un administrateur, "_propre" limité à qui a
    // créé le courrier (la réceptionniste qui l'a enregistré).
    public function transferer(User $user, Courrier $courrier): bool
    {
        if (! $this->niveauSuffisant($user, $courrier)) {
            return false;
        }

        if (! $this->accesDossierSuffisant($user, $courrier)) {
            return false;
        }

        if (! $this->accesPerimetreSuffisant($user, $courrier)) {
            return false;
        }

        if ($courrier->statut === 'archive') {
            return false;
        }

        if ($user->hasPrivilege('courriers.transferer_tout')) {
            return true;
        }

        return $user->hasPrivilege('courriers.transferer_propre') && $courrier->estCreeParUtilisateur($user);
    }

    // Module 3/9 — filer un courrier dans un dossier de classement : délègue
    // entièrement à view() (si on peut voir un courrier, on peut le ranger),
    // le VRAI verrou est côté dossier (DossierClassementPolicy::view(), voir
    // DossierClassementList/ShowCourrier/CourrierList) — pas un nouveau
    // privilège dédié (confirmé avec l'utilisateur, "pas des privilèges").
    // Délibérément SANS blocage statut === 'archive' (contrairement à
    // update()/affecter()/etc.) : ranger un courrier archivé dans un dossier
    // (Module 9 — retrouvabilité) reste utile, ce n'est pas modifier son contenu.
    public function classer(User $user, Courrier $courrier): bool
    {
        // 2026-09-23 — privilège d'action dédié en plus de la consultation.
        return $user->hasPrivilege('courriers.classer') && $this->view($user, $courrier);
    }

    // ===== "Chaque action / lecture = un privilège" (2026-09-23) =====
    // Principe : le privilège d'ACTION (ex. courriers.rejeter) s'ajoute à la
    // vérification de PORTÉE déjà en place (valider() : valider_tout /
    // valider_service, niveau, dossier, périmètre) — jamais l'un sans l'autre.

    public function reaffecter(User $user, Courrier $courrier): bool
    {
        return $user->hasPrivilege('courriers.reaffecter') && $this->affecter($user, $courrier);
    }

    public function soumettreValidation(User $user, Courrier $courrier): bool
    {
        return $user->hasPrivilege('courriers.soumettre_validation') && $this->traiter($user, $courrier);
    }

    public function renvoyerCorrection(User $user, Courrier $courrier): bool
    {
        return $user->hasPrivilege('courriers.renvoyer_correction') && $this->valider($user, $courrier);
    }

    public function rejeter(User $user, Courrier $courrier): bool
    {
        return $user->hasPrivilege('courriers.rejeter') && $this->valider($user, $courrier);
    }

    // Ouvert au collaborateur affecté ET au responsable (voir
    // ShowCourrier::mettreEnAttente()) : portée traiter() OU valider().
    public function mettreEnAttente(User $user, Courrier $courrier): bool
    {
        return $user->hasPrivilege('courriers.mettre_en_attente')
            && ($this->traiter($user, $courrier) || $this->valider($user, $courrier));
    }

    public function reprendre(User $user, Courrier $courrier): bool
    {
        return $user->hasPrivilege('courriers.reprendre')
            && ($this->traiter($user, $courrier) || $this->valider($user, $courrier));
    }

    public function validerClassement(User $user, Courrier $courrier): bool
    {
        return $user->hasPrivilege('courriers.valider_classement') && $this->update($user, $courrier);
    }

    public function gererMotsCles(User $user, Courrier $courrier): bool
    {
        return $user->hasPrivilege('courriers.gerer_mots_cles') && $this->update($user, $courrier);
    }

    public function voirHistorique(User $user, Courrier $courrier): bool
    {
        return $user->hasPrivilege('courriers.voir_historique') && $this->view($user, $courrier);
    }

    public function voirTexteOcr(User $user, Courrier $courrier): bool
    {
        return $user->hasPrivilege('courriers.voir_texte_ocr') && $this->view($user, $courrier);
    }

    public function voirPiecesJointes(User $user, Courrier $courrier): bool
    {
        return $user->hasPrivilege('courriers.voir_pieces_jointes') && $this->view($user, $courrier);
    }

    public function voirEnregistres(User $user): bool
    {
        return $user->hasPrivilege('courriers.voir_enregistres');
    }

    public function voirMesCourriers(User $user): bool
    {
        return $user->hasPrivilege('courriers.voir_mes_courriers');
    }

    // Module 1/4 — demande explicite de l'utilisateur (2026-09-08) : la DGA/
    // ADJ confirme/change le service proposé par Module 3 pour un courrier
    // entrant non-sinistre, avant que le circuit habituel ne démarre.
    // $courrier non utilisé dans le corps, mais gardé dans la signature pour
    // rester appelable via ShowCourrier::courrierPour() comme
    // affecter/traiter/valider ci-dessus (même mécanisme d'appel,
    // `$this->authorize($abilite, $courrier)`). Voir DECISIONS.md "Circuit
    // courrier entrant : validation DGA/ADJ".
    public function validerService(User $user, Courrier $courrier): bool
    {
        if (! $this->niveauSuffisant($user, $courrier)) {
            return false;
        }

        if (! $this->accesDossierSuffisant($user, $courrier)) {
            return false;
        }

        if (! $this->accesPerimetreSuffisant($user, $courrier)) {
            return false;
        }

        if ($courrier->statut === 'archive') {
            return false;
        }

        if (! $user->hasPrivilege('courriers.dga_valider_service')) {
            return false;
        }

        // 2026-09-15 (mise à jour, voir DECISIONS.md "Destinataires de
        // transfert") : ne valide que ce qui lui a été explicitement
        // adressé — même exception `null` que view() ci-dessus pour les
        // courriers transférés avant ce changement.
        return $courrier->destinataire_transfert_id === null || $courrier->destinataire_transfert_id === $user->id;
    }

    // Module 8 — recherche/liste multi-critères : ouverte à tout profil
    // reconnu (contrairement à voirFileAttente, l'Agent y a accès aussi —
    // il doit pouvoir retrouver les courriers qu'il a lui-même enregistrés).
    // Le filtrage par périmètre lui-même est fait au niveau de la requête
    // dans CourrierList::resultats(), avec la même logique que view().
    // Télécharger (document principal, pièces jointes) et imprimer le
    // bordereau : séparés de la consultation le 2026-09-23 — il faut
    // pouvoir VOIR le courrier ET avoir le privilège de l'action.
    public function telecharger(User $user, Courrier $courrier): bool
    {
        return $user->hasPrivilege('courriers.telecharger') && $this->view($user, $courrier);
    }

    public function imprimerBordereau(User $user, Courrier $courrier): bool
    {
        return $user->hasPrivilege('courriers.imprimer_bordereau') && $this->view($user, $courrier);
    }

    // Suppression LOGIQUE uniquement (SoftDeletes, Règle n°5 : jamais
    // physique, jamais un courrier archivé), motif obligatoire tracé dans
    // l'historique par l'appelant (CourrierList::supprimerCourrier()).
    public function delete(User $user, Courrier $courrier): bool
    {
        return $user->hasPrivilege('courriers.supprimer')
            && $courrier->statut !== 'archive'
            && $this->view($user, $courrier);
    }

    // Module 1/2 — "Numérisation & OCR" (scan d'abord + dossier surveillé),
    // privilège distinct de courriers.creer depuis le 2026-09-23 (menus
    // pilotés par privilège, voir DECISIONS.md) : un opérateur de scan peut
    // numériser sans enregistrer, et inversement.
    public function numeriser(User $user): bool
    {
        return $user->hasPrivilege('courriers.numeriser');
    }

    // Re-numériser un courrier existant (ScanForm) : numériser ET pouvoir
    // corriger CE courrier.
    public function renumeriser(User $user, Courrier $courrier): bool
    {
        return $this->numeriser($user) && $this->update($user, $courrier);
    }

    // Module 1 — "Cas particulier : courrier confidentiel" : privilège
    // distinct de courriers.creer depuis le 2026-09-23.
    public function creerConfidentiel(User $user): bool
    {
        return $user->hasPrivilege('courriers.creer_confidentiel');
    }

    // Module 1 — accusé de réception d'un courrier confidentiel (2026-09-23) :
    // "remis au déposant" par la réceptionniste qui vient de l'enregistrer —
    // or son niveau de confidentialité est normalement inférieur à celui du
    // courrier, donc view() le lui refusait. L'accusé ne contient que ce
    // qu'elle a elle-même saisi (nom sur l'enveloppe, référence, date),
    // jamais l'objet ni le contenu (voir CourrierAccuseReceptionController),
    // d'où l'exception limitée au créateur.
    public function imprimerAccuseReception(User $user, Courrier $courrier): bool
    {
        // Privilège d'action courriers.imprimer_accuse (2026-09-23).
        if (! $user->hasPrivilege('courriers.imprimer_accuse')) {
            return false;
        }

        if ($this->view($user, $courrier)) {
            return true;
        }

        return $user->hasPrivilege('courriers.voir_propre') && $courrier->estCreeParUtilisateur($user);
    }

    public function rechercher(User $user): bool
    {
        return $user->hasPrivilege('courriers.rechercher');
    }
}
