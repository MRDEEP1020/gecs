<?php

namespace Database\Seeders;

use App\Models\Privilege;
use App\Models\Profil;
use Illuminate\Database\Seeder;

// Système de privilèges (2026-09-15, voir DECISIONS.md "Système de
// privilèges") : catalogue initial + assignations par défaut. Les profils
// listés ci-dessous reproduisent le comportement qui existait avant le
// retrofit des Policies (CourrierPolicy/CourrierBrouillonPolicy/
// RegleClassementPolicy) — SAUF Administrateur, ajouté automatiquement à
// TOUS les privilèges (demande explicite de l'utilisateur, 2026-09-15 :
// "admin should have all the privileges") : contrairement à la version
// initiale de cette entrée, Administrateur récupère désormais aussi
// `courriers.archiver`, changement de comportement assumé (l'archivage
// manuel n'est de toute façon pas encore implémenté, voir CourrierPolicy::archive()).
class PrivilegeSeeder extends Seeder
{
    public function run(): void
    {
        // cle => [nom, description, type (Lecture/Écriture/Administratif —
        // 2026-09-21, modale "Gestion des permissions" de la page
        // "Utilisateurs & Accès" reconstruite depuis la maquette fournie par
        // l'utilisateur ; voir Privilege::MODULES), [noms d'AUTRES profils
        // assignés par défaut — Administrateur est ajouté automatiquement à
        // tous, pas la peine de le répéter ici]]
        $catalogue = [
            'courriers.creer' => ['Créer un courrier', 'Enregistrer un nouveau courrier entrant ou sortant.', 'ecriture', ['Agent']],
            'courriers.voir_tout' => ['Voir tous les courriers', 'Consulter n\'importe quel courrier, sans restriction de service ou de créateur.', 'lecture', []],
            'courriers.voir_service' => ['Voir les courriers de son service', 'Consulter les courriers du service dont on est responsable.', 'lecture', ['Responsable de service']],
            'courriers.voir_propre' => ['Voir ses propres courriers', 'Consulter les courriers qu\'on a soi-même enregistrés.', 'lecture', ['Agent']],
            'courriers.voir_affecte' => ['Voir les courriers qui lui sont affectés', 'Consulter les courriers actuellement affectés à soi-même.', 'lecture', ['Collaborateur']],
            'courriers.voir_dga' => ['Voir les courriers en attente de validation DGA', 'Consulter les courriers entrants en attente de validation du service (DGA/ADJ).', 'lecture', ['DGA']],
            'courriers.modifier_tout' => ['Modifier tous les courriers', 'Corriger n\'importe quel enregistrement de courrier.', 'ecriture', []],
            'courriers.modifier_propre' => ['Modifier ses propres courriers', 'Corriger les courriers qu\'on a soi-même enregistrés.', 'ecriture', ['Agent']],
            'courriers.transferer_tout' => ['Transférer tous les courriers', 'Transférer n\'importe quel courrier entrant en attente vers le DGA/ADJ DGA.', 'ecriture', []],
            'courriers.transferer_propre' => ['Transférer ses propres courriers', 'Transférer vers le DGA/ADJ DGA les courriers entrants qu\'on a soi-même enregistrés.', 'ecriture', ['Agent']],
            'courriers.archiver' => ['Archiver un courrier', 'Archivage manuel (Module 9 — fonctionnalité pas encore implémentée, ce privilège ne fait donc rien pour l\'instant, même pour Administrateur).', 'ecriture', []],
            'courriers.affecter_tout' => ['Affecter tous les courriers', 'Affecter/réaffecter n\'importe quel courrier à un collaborateur.', 'ecriture', []],
            'courriers.affecter_service' => ['Affecter les courriers de son service', 'Affecter/réaffecter les courriers du service dont on est responsable.', 'ecriture', ['Responsable de service']],
            'courriers.traiter_tout' => ['Traiter tous les courriers', 'Démarrer le traitement de n\'importe quel courrier.', 'ecriture', []],
            'courriers.traiter_affecte' => ['Traiter les courriers qui lui sont affectés', 'Démarrer le traitement des courriers affectés à soi-même.', 'ecriture', ['Collaborateur']],
            'courriers.valider_tout' => ['Valider tous les courriers', 'Valider/renvoyer/rejeter n\'importe quel courrier.', 'ecriture', []],
            'courriers.valider_service' => ['Valider les courriers de son service', 'Valider/renvoyer/rejeter les courriers du service dont on est responsable.', 'ecriture', ['Responsable de service']],
            'courriers.voir_file_attente' => ['Voir la file d\'attente', 'Accéder à la liste des courriers à traiter.', 'lecture', ['Responsable de service', 'Collaborateur', 'DGA']],
            'courriers.dga_valider_service' => ['Valider le service proposé (DGA)', 'Confirmer/changer le service proposé pour un courrier entrant non-sinistre.', 'ecriture', ['DGA']],
            'courriers.rechercher' => ['Rechercher des courriers', 'Accéder à la recherche multi-critères (Module 8).', 'lecture', ['Responsable de service', 'Agent', 'Collaborateur', 'DGA']],
            'brouillons.utiliser_tout' => ['Utiliser tous les brouillons scannés', 'Reprendre/finaliser n\'importe quel document scanné en attente, pas seulement les siens.', 'ecriture', []],
            'regles_classement.gerer' => ['Gérer les règles de classement', 'Créer/modifier/supprimer les règles de classement automatique (Module 3).', 'administratif', []],
            // Configuration administrateur — listes de référence
            // (specifications-modules-GEC.md, "transversal") : la liste des
            // services/directions doit être gérable par l'Administrateur
            // sans intervention développeur, au même titre que les règles de
            // classement ci-dessus. Ajouté le 2026-09-22, demande explicite
            // de l'utilisateur ("where can i add services").
            'services.gerer' => ['Gérer les services', 'Créer/modifier/désactiver les services et directions (liste de référence, Module 1/3/6).', 'administratif', []],
            // Module 3 — "Dossiers de classement créés par service" (specifications-modules-GEC.md) :
            // le chef de service OU ses collaborateurs (pas seulement un
            // administrateur) peuvent créer leurs propres dossiers, en plus
            // de l'arborescence automatique service/type/période. L'accès à
            // UN dossier précis (qui peut le voir) est ensuite défini par
            // son créateur, dossier par dossier — ce n'est PAS un privilège
            // (une donnée assignée par dossier, pas par profil/utilisateur
            // global, voir DossierClassement/dossier_classement_user à
            // venir) : seule la capacité de CRÉER, et le garde-fou
            // administrateur pour gérer N'IMPORTE QUEL dossier (même esprit
            // que courriers.modifier_tout vs modifier_propre), sont des
            // privilèges.
            'dossiers_classement.creer' => ['Créer un dossier de classement', 'Créer un dossier de classement personnalisé au sein de son service, en plus de l\'arborescence automatique (Module 3).', 'ecriture', ['Responsable de service', 'Collaborateur']],
            'dossiers_classement.gerer_tout' => ['Gérer tous les dossiers de classement', 'Voir, renommer, gérer les droits d\'accès et supprimer n\'importe quel dossier de classement, pas seulement ceux qu\'on a soi-même créés.', 'administratif', []],
            'privileges.gerer' => ['Gérer les privilèges', 'Assigner les privilèges existants à un profil ou à un utilisateur (le catalogue lui-même est fixe depuis le 2026-09-21, voir CHANGELOG-AGENT.md).', 'administratif', []],
            // Module 10 — tableau de bord (demande explicite de l'utilisateur,
            // 2026-09-16, maquette GPT) : "Tâches du jour" (aperçu de la file
            // d'attente du jour) n'est pas affiché à tout le monde — "mainly
            // for collaborateur ... sometimes responsable service". Par
            // défaut Collaborateur (le cas principal) ; un Responsable de
            // service qui en a besoin le reçoit individuellement via
            // /admin/utilisateurs (le cas "sometimes"), pas un défaut de profil.
            'dashboard.taches_du_jour' => ['Voir les tâches du jour', 'Afficher l\'aperçu de la file d\'attente du jour ("Tâches du jour") sur le tableau de bord.', 'lecture', ['Collaborateur']],
            // Module "Organisation" v2 (2026-09-22, spec technique complète
            // fournie par l'utilisateur, §18) — hiérarchie dynamique Company/
            // Site/Department/Service/Sub-service + rattachement utilisateur.
            // 'manage_structure' court-circuite create/update/deactivate (voir
            // OrganizationUnitPolicy) — clés séparées gardées pour une
            // délégation plus fine si besoin plus tard (non exploitée
            // aujourd'hui : seul Administrateur reçoit tout par défaut, comme
            // toujours).
            'organisation.view' => ['Voir l\'organisation', 'Consulter la structure organisationnelle (sites, départements, services, sous-services).', 'lecture', []],
            'organisation.create' => ['Créer une entité organisationnelle', 'Ajouter un site, un département, un service ou un sous-service.', 'ecriture', []],
            'organisation.update' => ['Modifier une entité organisationnelle', 'Renommer, déplacer ou modifier les informations d\'une entité existante.', 'ecriture', []],
            'organisation.deactivate' => ['Activer/désactiver une entité organisationnelle', 'Basculer le statut actif/inactif — aucune suppression physique n\'est possible (spec Organisation).', 'ecriture', []],
            'organisation.manage_users' => ['Gérer les utilisateurs d\'une entité organisationnelle', 'Rattacher/retirer un utilisateur, changer son rôle au sein d\'une entité.', 'ecriture', []],
            'organisation.manage_structure' => ['Gérer toute la structure organisationnelle', 'Équivalent à create+update+deactivate combinés, pour une délégation globale sans devoir assigner les 3 clés séparément.', 'administratif', []],
            // Menus et sous-menus (2026-09-23, demande explicite de
            // l'utilisateur : "all in sidebar should be permission even the
            // submenu and menu") — chaque entrée de la sidebar est désormais
            // gouvernée par un privilège, voir DECISIONS.md "Menus pilotés
            // par privilège". Règle de défaut : chaque nouvelle clé est
            // donnée EXACTEMENT aux profils qui voyaient déjà l'entrée, pour
            // ne rien retirer ni ajouter à personne ; seules les pages "à
            // venir" reçoivent un défaut de gestion (administration/direction).
            'dashboard.voir' => ['Voir le tableau de bord', 'Accéder au tableau de bord (page d\'accueil après connexion).', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'courriers.numeriser' => ['Numériser un courrier', 'Accéder à "Numérisation & OCR" (scan d\'abord, dossier surveillé) et re-numériser un courrier existant.', 'ecriture', ['Agent']],
            'courriers.creer_confidentiel' => ['Enregistrer un courrier confidentiel', 'Enregistrer un pli confidentiel sans l\'ouvrir ni le scanner (nom sur l\'enveloppe uniquement).', 'ecriture', ['Agent']],
            'dossiers_classement.archives' => ['Consulter les archives', 'Afficher l\'entrée "Archives" (courriers archivés) dans Dossiers & Archives.', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'utilisateurs.gerer' => ['Gérer les comptes utilisateurs', 'Accéder à "Utilisateurs & Accès" : coordonnées, activation, réinitialisation du mot de passe, destinataires de transfert. Créer un compte ou changer profil, niveau, périmètre et permissions exige EN PLUS "Gérer les privilèges".', 'administratif', []],
            'statistiques.consulter' => ['Consulter les statistiques', 'Accéder à "Dashboard statistiques" (page à venir).', 'lecture', ['DGA', 'Responsable de service']],
            'statistiques.rapports' => ['Consulter les rapports', 'Accéder à "Rapports" (page à venir).', 'lecture', ['DGA', 'Responsable de service']],
            'administration.automatisation' => ['Gérer l\'automatisation', 'Accéder à "Automatisation" (page à venir).', 'administratif', []],
            'administration.workflows' => ['Gérer les workflows', 'Accéder à "Workflows" (page à venir).', 'administratif', []],
            'administration.sla' => ['Gérer les SLA et alertes', 'Accéder à "SLA & Alertes" (page à venir ; délais actuellement dans config/gec.php).', 'administratif', []],
            'administration.audit' => ['Consulter la sécurité et l\'audit', 'Accéder à "Sécurité & Audit" (page à venir).', 'administratif', []],
            'general.notifications' => ['Voir les notifications', 'Afficher le menu "Notifications" et la cloche de la barre supérieure.', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            // Actions sur un courrier séparées de la simple consultation
            // (2026-09-23, "all of them") — mêmes défauts qu'avant (tout
            // profil pouvant consulter), sauf la suppression, nouvelle.
            'courriers.telecharger' => ['Télécharger les documents', 'Télécharger le document principal, les pièces jointes et les documents scannés en attente. L\'aperçu à l\'écran reste soumis au seul droit de consultation.', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'courriers.imprimer_bordereau' => ['Imprimer le bordereau', 'Générer le bordereau PDF d\'un courrier qu\'on peut consulter.', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'courriers.supprimer' => ['Supprimer un courrier', 'Suppression logique (jamais physique, Règle n°5) avec motif obligatoire, tracée dans l\'historique ; impossible sur un courrier archivé.', 'administratif', []],
            // "Chaque action / lecture = un privilège" (2026-09-23, demande
            // explicite de l'utilisateur). Les privilèges de PORTÉE existants
            // (…_tout / …_service / …_propre / …_affecte) restent ; ceux-ci
            // s'y AJOUTENT, un par action ou lecture distincte. Défaut =
            // exactement les profils qui pouvaient déjà le faire.
            // — Circuit de traitement (fiche courrier)
            'courriers.reaffecter' => ['Réaffecter un courrier', 'Changer le collaborateur affecté (motif obligatoire), dans sa portée d\'affectation.', 'ecriture', ['Responsable de service']],
            'courriers.soumettre_validation' => ['Soumettre pour validation', 'Envoyer sa réponse/action au responsable pour validation.', 'ecriture', ['Collaborateur']],
            'courriers.renvoyer_correction' => ['Renvoyer pour correction', 'Refuser une soumission et la renvoyer au collaborateur (motif obligatoire).', 'ecriture', ['Responsable de service']],
            'courriers.rejeter' => ['Rejeter un courrier', 'Clore un courrier comme rejeté (motif obligatoire).', 'ecriture', ['Responsable de service']],
            'courriers.mettre_en_attente' => ['Mettre en attente d\'information', 'Suspendre le traitement en attendant une pièce ou une information (motif obligatoire).', 'ecriture', ['Collaborateur', 'Responsable de service']],
            'courriers.reprendre' => ['Reprendre le traitement', 'Relancer le traitement d\'un courrier mis en attente.', 'ecriture', ['Collaborateur', 'Responsable de service']],
            // — Classement et contenu
            'courriers.classer' => ['Classer un courrier dans un dossier', 'Ranger/retirer un courrier consultable dans un dossier de classement (unitaire ou en masse).', 'ecriture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'courriers.valider_classement' => ['Valider la proposition de classement', 'Accepter ou ignorer le type/service proposé automatiquement (Module 3).', 'ecriture', ['Agent']],
            'courriers.gerer_mots_cles' => ['Gérer les mots-clés', 'Ajouter ou retirer des mots-clés sur un courrier.', 'ecriture', ['Agent']],
            // — Lectures
            'courriers.voir_historique' => ['Voir l\'historique', 'Afficher l\'onglet "Historique" (journal immuable des actions) d\'un courrier consultable.', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'courriers.voir_texte_ocr' => ['Voir le texte extrait (OCR)', 'Afficher le texte extrait du document et rechercher dans ce contenu.', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'courriers.voir_pieces_jointes' => ['Voir les pièces jointes', 'Afficher la liste des pièces jointes d\'un courrier consultable (le téléchargement exige en plus "Télécharger les documents").', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            // 2026-09-23 — scindée en 4 clés, une par carte (même traitement
            // que dashboard.statistiques le même jour, "same thing for all
            // the pages, each card should be a permission") : impossible
            // auparavant de montrer "Total courriers" sans montrer aussi "En
            // erreur". Mêmes 4 profils par défaut que l'ancienne clé unique
            // (ci-dessous, supprimée du catalogue).
            'courriers.voir_carte_total' => ['Voir la carte "Total courriers"', 'Afficher la carte "Total courriers" de "Tous les courriers".', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'courriers.voir_carte_en_traitement' => ['Voir la carte "En traitement"', 'Afficher la carte "En traitement" de "Tous les courriers".', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'courriers.voir_carte_termines' => ['Voir la carte "Terminés"', 'Afficher la carte "Terminés" de "Tous les courriers".', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'courriers.voir_carte_en_erreur' => ['Voir la carte "En erreur"', 'Afficher la carte "En erreur" (échec OCR) de "Tous les courriers".', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'courriers.voir_enregistres' => ['Voir "Courriers enregistrés"', 'Accéder à la page "Courriers enregistrés" (courriers entrants par sous-statut de transfert).', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'courriers.voir_mes_courriers' => ['Voir "Mes courriers"', 'Accéder à la page "Mes enregistrements" (ses propres courriers entrants).', 'lecture', ['Agent']],
            'courriers.imprimer_accuse' => ['Imprimer l\'accusé de réception', 'Générer l\'accusé de réception d\'un courrier confidentiel.', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            // — Tableau de bord : CHAQUE carte/KPI sa propre clé (2026-09-23,
            // demande explicite de l'utilisateur — "on tableau de board all
            // kpi and card there should be permission"). Remplace l'ancienne
            // clé unique `dashboard.statistiques` qui gouvernait les 5 cartes
            // d'un coup (voir CHANGELOG-AGENT.md — privilège retiré du
            // catalogue, supprimé explicitement plus bas). Mêmes 4 profils
            // par défaut que l'ancienne clé partagée, pour ne rien changer
            // au comportement observé.
            'dashboard.courrier_entrant' => ['Voir "Courrier entrant"', 'Afficher la carte "Courrier entrant" (aujourd\'hui vs hier) du tableau de bord.', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'dashboard.courrier_sortant' => ['Voir "Courrier sortant"', 'Afficher la carte "Courrier sortant" (aujourd\'hui vs hier) du tableau de bord.', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'dashboard.en_attente' => ['Voir "En attente de traitement"', 'Afficher la carte "En attente de traitement" du tableau de bord.', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'dashboard.urgents' => ['Voir "Courriers urgents"', 'Afficher la carte "Courriers urgents" du tableau de bord.', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'dashboard.en_retard' => ['Voir "En retard"', 'Afficher la carte "En retard" (dépassement SLA) du tableau de bord.', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'dashboard.derniers_courriers' => ['Voir les derniers courriers', 'Afficher la liste "Derniers courriers enregistrés" du tableau de bord.', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'dashboard.delai_moyen' => ['Voir le délai moyen de traitement', 'Afficher la carte "Délai moyen de traitement" (toute l\'entreprise avec "Voir tous les courriers", sinon ses services).', 'lecture', ['Responsable de service']],
            // Panneau latéral : cartes "Notifications" et "Calendrier"
            // (contenu décoratif/placeholder, mais chaque carte a désormais
            // sa permission comme le reste — même défaut que general.notifications).
            'dashboard.notifications' => ['Voir la carte Notifications', 'Afficher la carte "Notifications" du tableau de bord (panneau latéral).', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'dashboard.calendrier' => ['Voir la carte Calendrier', 'Afficher la carte "Calendrier" du tableau de bord (panneau latéral).', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            // — Dossiers de classement
            'dossiers_classement.voir' => ['Voir les dossiers de classement', 'Accéder à "Dossiers & Archives" (seuls les dossiers créés, dirigés ou partagés restent visibles).', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
            'dossiers_classement.modifier' => ['Renommer / déplacer ses dossiers', 'Renommer ou déplacer un dossier qu\'on a créé.', 'ecriture', ['Responsable de service', 'Collaborateur']],
            'dossiers_classement.partager' => ['Partager ses dossiers', 'Définir qui a accès à un dossier qu\'on a créé.', 'ecriture', ['Responsable de service', 'Collaborateur']],
            'dossiers_classement.supprimer' => ['Supprimer ses dossiers', 'Supprimer un dossier vide qu\'on a créé.', 'ecriture', ['Responsable de service', 'Collaborateur']],
            // — Règles de classement et profils
            'regles_classement.voir' => ['Voir les règles de classement', 'Consulter la page "Référentiels (règles de classement)" sans pouvoir la modifier.', 'lecture', []],
            'regles_classement.reanalyser' => ['Réanalyser les courriers', 'Relancer la classification automatique sur les courriers existants.', 'administratif', []],
            'profils.creer' => ['Créer un profil', 'Créer un nouveau profil sur la page "Profils".', 'administratif', []],
            'general.aide' => ['Voir l\'aide', 'Afficher le menu "Aide / Documentation" et le bouton d\'aide de la barre supérieure.', 'lecture', ['Agent', 'DGA', 'Responsable de service', 'Collaborateur']],
        ];

        $profils = Profil::pluck('id', 'nom');

        foreach ($catalogue as $cle => [$nom, $description, $type, $nomsProfils]) {
            $privilege = Privilege::firstOrCreate(['cle' => $cle], ['nom' => $nom, 'description' => $description, 'type' => $type]);

            $nomsProfils = array_unique([...$nomsProfils, 'Administrateur']);
            $idsProfils = collect($nomsProfils)->map(fn ($nomProfil) => $profils[$nomProfil] ?? null)->filter()->values();

            $privilege->profils()->syncWithoutDetaching($idsProfils);
        }

        // dashboard.statistiques (2026-09-23) : remplacée ci-dessus par 5
        // clés, une par carte — supprimée explicitement (cascade sur les
        // pivots privilege_profil/privilege_user, voir leurs migrations)
        // plutôt que laissée comme entrée morte dans le catalogue.
        Privilege::where('cle', 'dashboard.statistiques')->delete();
        // courriers.voir_statistiques (2026-09-23, même jour) : remplacée
        // par 4 clés, une par carte de "Tous les courriers" — même raison.
        Privilege::where('cle', 'courriers.voir_statistiques')->delete();
    }
}
