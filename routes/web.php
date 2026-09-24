<?php

use App\Http\Controllers\BrouillonDocumentApercuController;
use App\Http\Controllers\BrouillonDocumentDownloadController;
use App\Http\Controllers\CourrierAccuseReceptionController;
use App\Http\Controllers\CourrierBordereauController;
use App\Http\Controllers\CourrierDocumentApercuController;
use App\Http\Controllers\CourrierDocumentDownloadController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PieceJointeDownloadController;
use App\Livewire\Backend\CourrierList;
use App\Livewire\Backend\CourriersEnregistres;
use App\Livewire\Backend\Dashboard;
use App\Livewire\Backend\DossierClassementList;
use App\Livewire\Backend\EditForm;
use App\Livewire\Backend\MesCourriers;
use App\Livewire\Backend\OrganisationIndex;
use App\Livewire\Backend\ParametreSysteme;
use App\Livewire\Backend\ProfilList;
use App\Livewire\Backend\RegistrationForm;
use App\Livewire\Backend\RegistrationFormConfidentiel;
use App\Livewire\Backend\RegleList;
use App\Livewire\Backend\ScanForm;
use App\Livewire\Backend\ScanPremier;
use App\Livewire\Backend\ShowCourrier;
use App\Livewire\Backend\UserList;
use App\Livewire\Backend\WorkflowQueue;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Bascule FR/EN demandée par l'utilisateur (2026-09-07) — voir
// App\Http\Middleware\SetLocale et DECISIONS.md. Hors du groupe
// auth/verified : un visiteur non connecté doit pouvoir changer de langue
// aussi.
Route::get('langue/{locale}', LocaleController::class)->name('langue.changer');

Route::middleware(['auth', 'verified'])->group(function () {
    // Module 10 — tableau de bord (maquette GPT, 2026-09-16, voir
    // DECISIONS.md "Tableau de bord") : remplace le placeholder statique du
    // starter-kit par un vrai composant Livewire (statistiques/actions
    // rapides filtrées par privilège).
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');

    Route::livewire('courriers/nouveau', RegistrationForm::class)->name('courriers.nouveau');
    // Module 1/2 — aperçu du document scanné AVANT la création du Courrier
    // (panneau "Aperçu du document" du formulaire, voir DECISIONS.md
    // "Formulaire d'enregistrement en une page") — même principe que
    // courriers.document.apercu, pour un brouillon.
    Route::get('brouillons/{brouillon}/apercu', BrouillonDocumentApercuController::class)->name('brouillons.apercu');
    Route::get('brouillons/{brouillon}/telecharger', BrouillonDocumentDownloadController::class)->name('brouillons.telecharger');
    // Module 1 — "Cas particulier : courrier confidentiel" (specifications-modules-GEC.md) :
    // processus séparé, jamais de scan (voir DECISIONS.md "Courrier confidentiel").
    Route::livewire('courriers/confidentiel', RegistrationFormConfidentiel::class)->name('courriers.confidentiel');
    // Module 1/2 — flux "scan d'abord" (voir DECISIONS.md "Flux scan-first").
    Route::livewire('courriers/numeriser', ScanPremier::class)->name('courriers.numeriser-nouveau');
    // Module 4 — file d'attente du circuit de validation.
    Route::livewire('courriers/a-traiter', WorkflowQueue::class)->name('courriers.a-traiter');
    // Module 3/8 — recherche multi-critères ; DOIT rester avant
    // courriers/{courrierId} ci-dessous, sinon "rechercher" serait capturé
    // comme un ID de courrier par la route générique.
    Route::livewire('courriers/rechercher', CourrierList::class)->name('courriers.rechercher');
    // Module 1/4 — page dédiée réceptionniste (2026-09-15, "new page for
    // receptionist") : ses courriers entrant, par sous-statut de transfert.
    // Même raison que ci-dessus : DOIT rester avant la route générique.
    Route::livewire('courriers/mes-courriers', MesCourriers::class)->name('courriers.mes-courriers');
    // Module 1/4/8 — "Courriers enregistrés" (maquette GPT, 2026-09-16,
    // voir DECISIONS.md "Navigation (navbar + sidebar)") : mêmes 3 onglets
    // que "Mes courriers" ci-dessus, mais à l'échelle de ce que
    // l'utilisateur peut voir (périmètre CourrierList), pas limité à ses
    // propres courriers. Même raison que ci-dessus : DOIT rester avant la
    // route générique.
    Route::livewire('courriers/enregistres', CourriersEnregistres::class)->name('courriers.enregistres');
    Route::livewire('courriers/{courrierId}/modifier', EditForm::class)->name('courriers.modifier');
    Route::livewire('courriers/{courrierId}/numeriser', ScanForm::class)->name('courriers.numeriser');
    Route::get('courriers/{courrier}/bordereau', CourrierBordereauController::class)->name('courriers.bordereau');
    Route::get('courriers/{courrier}/accuse-reception', CourrierAccuseReceptionController::class)->name('courriers.accuse-reception');
    Route::get('courriers/{courrier}/document', CourrierDocumentDownloadController::class)->name('courriers.document');
    Route::get('courriers/{courrier}/document/apercu', CourrierDocumentApercuController::class)->name('courriers.document.apercu');
    Route::get('pieces-jointes/{pieceJointe}/telecharger', PieceJointeDownloadController::class)->name('pieces-jointes.telecharger');
    Route::livewire('courriers/{courrierId}', ShowCourrier::class)->name('courriers.show');

    // Module 3/9 — "Dossiers & Archives" (arborescence de classement +
    // archivage automatique, 2026-09-21). Garde via mount() ->
    // $this->authorize('viewAny', DossierClassement::class).
    Route::livewire('dossiers-classement', DossierClassementList::class)->name('dossiers-classement.index');

    // Module 3 — paramétrage des règles de classement (Administrateur, voir RegleClassementPolicy)
    Route::livewire('admin/regles-classement', RegleList::class)->name('admin.regles');
    // Module "Organisation" v2 (2026-09-22, spec technique complète fournie
    // par l'utilisateur) — remplace la page plate "Services" : hiérarchie
    // dynamique Company/Site/Department/Service/Sub-service, rattachement
    // utilisateur, cascade de transfert de courrier, périmètre de permission.
    Route::livewire('admin/organisation', OrganisationIndex::class)->name('admin.organisation');
    // Page séparée (2026-09-15) : assignation des privilèges PAR PROFIL.
    Route::livewire('admin/profils', ProfilList::class)->name('admin.profils');
    // Page "Utilisateurs & Accès" reconstruite le 2026-09-21 depuis la
    // maquette fournie par l'utilisateur — table + modales
    // ajouter/modifier/gérer les permissions PAR UTILISATEUR. Le catalogue
    // de privilèges (ex-/admin/privileges) est désormais fixe, plus de page
    // dédiée (voir CHANGELOG-AGENT.md, "Système de privilèges" en mémoire).
    Route::livewire('admin/utilisateurs', UserList::class)->name('admin.utilisateurs');
    // Module 1/5/7 — "Paramètres système" (2026-09-23, voir DECISIONS.md
    // "Paramètres système configurables") : remplace l'entrée sidebar
    // "SLA & Alertes" (jusque-là "Bientôt disponible") par une vraie page ;
    // numéro de référence + SLA, éditables sans redéploiement.
    Route::livewire('admin/parametres', ParametreSysteme::class)->name('admin.parametres');
});

require __DIR__.'/settings.php';
