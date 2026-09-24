# CHANGELOG-AGENT.md — Journal de bord de Claude Code

Chaque entrée ci-dessous est ajoutée automatiquement par Claude Code après
chaque modification, conformément à la Règle n°8 de CLAUDE.md.

Format : date/heure, fichier(s) exact(s) touché(s), et pourquoi (référence au
module ou à la décision concernée).

Voir aussi `.claude/logs/changes.log` pour le journal technique automatique
(chemin exact de chaque fichier, généré par un hook — indépendant de cette
règle et donc toujours à jour même si une entrée manque ici).

---

## [2026-09-03 12:42] Mise en place effective du hook de journalisation automatique
Fichier(s) : .claude/settings.json
Fichier(s) : .gitignore
Pourquoi : Règle n°8 de CLAUDE.md décrivait un hook PostToolUse (Write/Edit/NotebookEdit
→ `.claude/logs/changes.log`) comme déjà existant, mais il n'avait jamais été créé.
Créé et testé (payload synthétique + déclenchement réel confirmé) pour que la règle
corresponde à la réalité du projet.

## [2026-09-03 13:05] Module 1 — Enregistrement courrier entrant/sortant
Fichier(s) : app/Livewire/Forms/CourrierForm.php
Fichier(s) : app/Livewire/Courriers/EnregistrementForm.php
Fichier(s) : resources/views/livewire/courriers/enregistrement-form.blade.php
Fichier(s) : app/Policies/CourrierPolicy.php (méthode create)
Fichier(s) : routes/web.php (route courriers.nouveau)
Fichier(s) : tests/Feature/Courriers/EnregistrementFormTest.php
Pourquoi : Module 1 (specifications-modules-GEC.md) — formulaire de saisie via
Form Object Livewire (Règle n°2), droits vérifiés par Policy (Règle n°6), entrée
d'historique immuable créée à chaque enregistrement (Règle n°5).

## [2026-09-03 13:05] Génération du numéro de référence (Module 1)
Fichier(s) : app/Services/NumeroReferenceGenerator.php
Fichier(s) : app/Models/NumeroSequence.php
Fichier(s) : database/migrations/2026_09_03_100007_add_code_to_services_table.php
Fichier(s) : database/migrations/2026_09_03_100008_create_numero_sequences_table.php
Fichier(s) : app/Models/Service.php (colonne code)
Pourquoi : format GEC-{année}-{code service}-{séquence} attendu par
specifications-modules-GEC.md ; séquence atomique par (année, service) pour
éviter les doublons sous charge concurrente (Règle n°3) — voir DECISIONS.md.

## [2026-09-03 13:20] Renommage de la vue du Module 1 en camelCase
Fichier(s) : resources/views/livewire/courriers/formulaireCreation.blade.php (créé)
Fichier(s) : resources/views/livewire/courriers/enregistrement-form.blade.php (supprimé)
Fichier(s) : app/Livewire/Courriers/EnregistrementForm.php (chemin de vue mis à jour)
Pourquoi : convention de nommage demandée pour les fichiers Blade (camelCase,
ex. formulaireCreation.blade.php) au lieu du kebab-case par défaut — voir
DECISIONS.md.

## [2026-09-03 13:40] Présélection du service quand un seul existe (Module 1)
Fichier(s) : app/Livewire/Courriers/EnregistrementForm.php
Fichier(s) : tests/Feature/Courriers/EnregistrementFormTest.php
Pourquoi : bug rapporté en test manuel ("The service id field is required")
— en réalité le placeholder du select restait affiché tant que l'utilisateur
ne cliquait pas dessus, même avec un seul service disponible. Pré-sélection
automatique quand il n'y a qu'un service (cas du pilote mono-service prévu
par PRD.md) ; reste à choix explicite dès qu'il y en a plusieurs.

## [2026-09-03 14:05] Cycle enregistrer → consulter (Module 1/9)
Fichier(s) : app/Policies/CourrierPolicy.php (méthode view)
Fichier(s) : app/Models/Courrier.php (méthode estCreeParUtilisateur)
Fichier(s) : app/Livewire/Courriers/DetailCourrier.php
Fichier(s) : resources/views/livewire/courriers/detailCourrier.blade.php
Fichier(s) : app/Livewire/Courriers/EnregistrementForm.php (derniereCourrierId + lien)
Fichier(s) : resources/views/livewire/courriers/formulaireCreation.blade.php (bouton "Voir le courrier")
Fichier(s) : routes/web.php (route courriers.show)
Fichier(s) : tests/Feature/Courriers/DetailCourrierTest.php
Pourquoi : le formulaire d'enregistrement ne menait nulle part une fois le
courrier créé (CourrierPolicy::view() renvoyait toujours false, aucune page
de détail). Périmètre de visibilité par profil : Administrateur voit tout,
Responsable de service voit les courriers de son service, Agent voit ceux
qu'il a enregistrés, Collaborateur voit ceux qui lui sont affectés (Module 8 :
"un agent ne voit que les courriers de son périmètre").

## [2026-09-03 14:30] Modification d'un enregistrement (Module 1)
Fichier(s) : app/Policies/CourrierPolicy.php (méthode update)
Fichier(s) : app/Livewire/Courriers/ModificationForm.php
Fichier(s) : resources/views/livewire/courriers/formulaireModification.blade.php
Fichier(s) : resources/views/livewire/courriers/detailCourrier.blade.php (bouton Modifier)
Fichier(s) : routes/web.php (route courriers.modifier)
Fichier(s) : tests/Feature/Courriers/ModificationFormTest.php
Pourquoi : specifications-modules-GEC.md, Module 1, règle métier "historique
des modifications de l'enregistrement conservé (qui a modifié, quand)" —
non couvert jusqu'ici (seule la création était tracée). Numéro de référence
non éditable (CourrierForm ne l'expose pas). Détecté et corrigé au passage :
comparaison naïve via getDirty() sur date_mouvement (cast `date`) donnait un
faux positif de changement à cause d'un format brut différent entre fill() et
lecture DB — remplacé par une comparaison des valeurs castées avant/après.

## [2026-09-03 14:50] Bordereau d'enregistrement PDF (Module 1)
Fichier(s) : composer.json, composer.lock (ajout barryvdh/laravel-dompdf)
Fichier(s) : app/Http/Controllers/CourrierBordereauController.php
Fichier(s) : resources/views/pdf/bordereau.blade.php
Fichier(s) : resources/views/livewire/courriers/detailCourrier.blade.php (bouton Bordereau)
Fichier(s) : routes/web.php (route courriers.bordereau)
Fichier(s) : tests/Feature/Courriers/CourrierBordereauTest.php
Pourquoi : specifications-modules-GEC.md, Module 1 — "un accusé de réception
ou bordereau d'enregistrement peut être imprimé/généré (PDF)". Contrôleur
classique (pas de composant Livewire) car c'est une réponse HTTP à usage
unique, pas un flux réactif ; droits vérifiés via la même Policy `view` que la
fiche détail (Règle n°6). Voir DECISIONS.md pour le choix de la bibliothèque.

Effet de bord constaté et vérifié sans gravité : `composer require` a
déclenché le script post-install propre au starter kit
(`artisan install:features`), qui a supprimé
`app/Console/Commands/InstallFeaturesCommand.php` (installeur à usage unique
du starter kit). `config/fortify.php` et tous les composants Settings sont
restés intacts ; suite de tests complète repassée au vert après coup (48/48).

## [2026-09-03 15:10] Pièce jointe optionnelle à l'enregistrement (Module 1)
Fichier(s) : app/Livewire/Courriers/EnregistrementForm.php (WithFileUploads, pieceJointe)
Fichier(s) : resources/views/livewire/courriers/formulaireCreation.blade.php
Fichier(s) : tests/Feature/Courriers/EnregistrementFormTest.php
Pourquoi : "Pièce(s) jointe(s)" est listée dans les données à capturer du
Module 1 (specifications-modules-GEC.md), pour le cas d'un courrier déjà
numérique (ex. pièce jointe email) — distinct du Module 2 (scan/OCR d'un
courrier physique). Upload optionnel, stocké sur `Storage::disk('s3')`
uniquement (Règle n°4), chemin `courriers/{annee}/{code service}/{numero_reference}.{ext}`.
Échec de stockage (ex. S3/MinIO indisponible) intercepté : le courrier reste
enregistré, un toast d'avertissement prévient l'agent plutôt que de faire
échouer tout l'enregistrement.

## [2026-09-03 15:25] Pièce jointe aussi disponible dans la modification
Fichier(s) : app/Services/PieceJointeService.php (nouveau — extrait de EnregistrementForm)
Fichier(s) : app/Livewire/Courriers/EnregistrementForm.php (utilise le service)
Fichier(s) : app/Livewire/Courriers/ModificationForm.php (WithFileUploads, pieceJointe, aDejaUnFichier)
Fichier(s) : resources/views/livewire/courriers/formulaireModification.blade.php
Fichier(s) : tests/Feature/Courriers/ModificationFormTest.php
Pourquoi : demande explicite de parité avec le formulaire d'enregistrement —
un agent peut avoir enregistré un courrier sans avoir la copie numérique sous
la main et vouloir l'ajouter plus tard via "Modifier". Logique de
stockage/traçabilité extraite en service partagé pour ne pas la dupliquer.
Upload proposé uniquement si `fichier_path` est encore vide (Règle n°4 —
écriture unique, jamais de remplacement) ; revérifié côté serveur, pas
seulement caché côté vue (Règle n°6).

## [2026-09-03 15:45] Alignement sur les décisions ARCHITECTURE.md/DECISIONS.md mises à jour
Fichier(s) : DECISIONS.md, ARCHITECTURE.md (restauration des décisions perdues + nouvelles)
Fichier(s) : app/Livewire/{RegistrationForm,EditForm,ShowCourrier,CourrierList,ValidationCircuit,DashboardHome}.php (déplacés, namespace App\Livewire à plat)
Fichier(s) : resources/views/livewire/{registrationForm,editForm,showCourrier}.blade.php (déplacés hors de courriers/)
Fichier(s) : routes/web.php (imports mis à jour)
Fichier(s) : tests/Feature/Courriers/{RegistrationFormTest,EditFormTest,ShowCourrierTest}.php (imports mis à jour)
Fichier(s) : database/migrations/2026_09_03_150000_create_pieces_jointes_table.php (nouveau)
Fichier(s) : app/Models/PieceJointe.php (nouveau)
Fichier(s) : app/Models/Courrier.php (relation piecesJointes)
Fichier(s) : app/Services/PieceJointeService.php (écrit dans pieces_jointes au lieu de courriers.fichier_path)
Fichier(s) : app/Livewire/EditForm.php (suppression de la contrainte "un seul fichier", devenue obsolète)
Fichier(s) : resources/views/livewire/editForm.blade.php, showCourrier.blade.php (affichage pièces jointes)
Fichier(s) : app/Http/Controllers/PieceJointeDownloadController.php (nouveau)
Pourquoi : DECISIONS.md/ARCHITECTURE.md avaient été mis à jour manuellement
(structure à plat sans sous-dossiers module, table `pieces_jointes` dédiée) —
confirmé volontaire par l'utilisateur, code existant réaligné en conséquence.
Base de données (MySQL) remise à "décidé" après une réinitialisation
accidentelle de cette entrée. Suite de tests complète repassée au vert (53/53)
après chaque étape.

## [2026-09-03 16:30] MinIO local + séparation backend/frontend Livewire
Fichier(s) : .env (identifiants MinIO, AWS_ENDPOINT, AWS_USE_PATH_STYLE_ENDPOINT)
Fichier(s) : composer.json, composer.lock (ajout league/flysystem-aws-s3-v3, requis par Storage::disk('s3'))
Fichier(s) : app/Livewire/Backend/{RegistrationForm,EditForm,ShowCourrier,CourrierList,ValidationCircuit,DashboardHome}.php (namespace App\Livewire\Backend)
Fichier(s) : app/Livewire/Backend/Forms/CourrierForm.php
Fichier(s) : resources/views/livewire/frontend/{registrationForm,editForm,showCourrier}.blade.php
Fichier(s) : app/Providers/AppServiceProvider.php (Livewire::addLocation + View::addNamespace('frontend', ...))
Fichier(s) : routes/web.php, tests/Feature/Courriers/{RegistrationFormTest,EditFormTest,ShowCourrierTest}.php (imports mis à jour)
Fichier(s) : ARCHITECTURE.md, DECISIONS.md (nouvelle décision "Séparation backend/frontend", section MinIO local)
Pourquoi : demande explicite de séparer logique (backend) et affichage
(frontend) pour les composants Livewire GEC, en restant dans les conventions
Laravel (app/ et resources/views/, pas de dossiers racine backend/frontend qui
casseraient Herd/Composer). MinIO local mis en place (binaire autonome Windows,
pas de Docker disponible) pour que l'upload de pièce jointe fonctionne
réellement, pas seulement en test.

Incident notable pendant ce travail : l'utilisateur a lui-même déplacé les
fichiers (app/Livewire/backend/ et resources/views/livewire/frontend/,
directement dans les dossiers Laravel standards) pendant que je travaillais
sur une structure différente (dossier racine livewire/backend + livewire/frontend
avec mapping composer.json personnalisé) — les deux réorganisations sont
entrées en conflit, causant temporairement des erreurs "Unable to find
component" en test. Diagnostiqué (mismatch de config, pas de perte de
données) et réaligné sur l'emplacement réel choisi par l'utilisateur. Casse du
dossier corrigée (`backend` → `Backend`) pour éviter une rupture silencieuse
sur un futur déploiement Linux (sensible à la casse, contrairement à Windows).

## [2026-09-03 17:30] Module 2 — Numérisation et OCR
Fichier(s) : database/migrations/2026_09_03_170000_add_ocr_columns_to_courriers_table.php (texte_ocr, ocr_statut, ocr_traite_le)
Fichier(s) : app/Models/Courrier.php (fillable + cast ocr_traite_le)
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (implémentation complète : S3 → fichier temporaire → Tesseract fra+eng → texte_ocr, contrôle qualité, failed() loggé)
Fichier(s) : app/Livewire/Backend/ScanForm.php (nouveau — upload du document principal, dispatch du job sur la queue `ocr`)
Fichier(s) : resources/views/livewire/frontend/scanForm.blade.php (nouveau)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (section "Document principal" : statut OCR, téléchargement, bouton Numériser/Re-numériser, texte extrait repliable)
Fichier(s) : app/Http/Controllers/CourrierDocumentDownloadController.php (nouveau)
Fichier(s) : routes/web.php (courriers.numeriser, courriers.document)
Fichier(s) : config/services.php (bloc tesseract), .env (TESSERACT_PATH, TESSERACT_TESSDATA_PATH)
Fichier(s) : composer.json, composer.lock (thiagoalessio/tesseract_ocr)
Fichier(s) : tests/Feature/Courriers/ScanFormTest.php (nouveau — nominal avec Queue::fake() + assertPushedOn('ocr'), droits refusés, document validé non remplaçable)
Fichier(s) : DECISIONS.md (décision moteur OCR), ARCHITECTURE.md (section Tesseract local)
Pourquoi : Module 2 (specifications-modules-GEC.md) — le fichier scanné est
rattaché au numéro de référence (`courriers.fichier_path`, nommage Règle n°4),
le texte est extrait par OCR en Job (Règle n°1, test de dispatch Règle n°7),
contrôle qualité par seuil de texte avec re-scan possible, immuabilité du
document une fois validé (Règle n°4), chaque étape tracée dans l'historique
(Règle n°5). Vérifié de bout en bout sur une image PNG réelle (MinIO →
Tesseract → texte français + anglais extrait correctement).
Limite ouverte : les PDF exigent Ghostscript, dont l'installeur demande des
droits administrateur sur ce poste — à lancer manuellement par l'utilisateur
(`C:\Users\NSIANV-EDS-ANK\ghostscript\gs-installer.exe`). D'ici là, un PDF
scanné aboutit proprement à `ocr_statut = echec` (log + historique), sans crash.

## [2026-09-03 17:50] Message d'échec OCR lisible pour l'agent
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (failed() + messageLisible())
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (nouveau)
Pourquoi : premier test manuel du Module 2 par l'utilisateur avec un PDF —
comportement attendu (Ghostscript absent → échec propre après 3 tentatives),
mais l'historique affichait le message technique brut de Tesseract
("pixReadStream: Pdf reading is not supported…"). Désormais l'historique
(visible sur la fiche, Règle n°5) reçoit une explication en français avec la
marche à suivre ; le détail technique reste dans le log (Règle n°1 — échec
exploitable). Cas connus : PDF sans Ghostscript, binaire Tesseract introuvable,
sinon message générique.

## [2026-09-03 18:10] "Aucun texte reconnu" = contrôle qualité, pas panne technique
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (estUneAbsenceDeTexte(), catch ciblé dans handle())
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (cas sortie vide vs PDF illisible vs binaire absent)
Pourquoi : test manuel de l'utilisateur avec une photo de page manuscrite —
Tesseract (texte imprimé uniquement) n'a reconnu aucun mot, et le wrapper
`thiagoalessio/tesseract_ocr` signale une sortie vide comme une exception.
Résultat : 3 tentatives inutiles puis `ocr_statut = echec` (panne), alors que
le spec Module 2 prévoit exactement ce cas comme contrôle qualité ("scan
illisible → re-scan demandé"). Désormais une sortie vide sans erreur réelle
donne `echec_qualite` immédiatement, sans retry ; les vraies pannes (PDF sans
Ghostscript, binaire absent, erreur de lecture) continuent de passer par
retry + failed(). Limite documentée dans DECISIONS.md : l'écriture manuscrite
est hors périmètre de Tesseract.

## [2026-09-03 18:40] Affichage lisible du texte OCR sur la fiche
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (bloc <pre> monospace défilable, mention "texte brut, sert à la recherche")
Fichier(s) : tests/Feature/Courriers/ShowCourrierTest.php (test d'affichage du texte OCR)
Pourquoi : retour utilisateur — le texte extrait d'un ticket de transport
s'affichait en un seul paragraphe illisible alors que Tesseract avait produit
20 lignes bien structurées ; `flux:text` n'appliquait pas `whitespace-pre-line`.
Remplacé par un `<pre class="whitespace-pre-wrap">` + nombre de caractères,
date d'extraction, et avertissement que le document original fait foi
(Module 2 — le texte OCR alimente la recherche, ce n'est pas une transcription).

## [2026-09-03 19:30] Module 2 — contrôle qualité par confiance, orientation, résolution, événement de fin
Fichier(s) : database/migrations/2026_09_03_190000_add_ocr_confiance_to_courriers_table.php
Fichier(s) : app/Models/Courrier.php (ocr_confiance)
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (sortie TSV → texte + confiance moyenne ; psm 1 ; correction EXIF des JPEG ; seuils LONGUEUR/CONFIANCE ; diffusion OcrTermine best-effort)
Fichier(s) : app/Events/OcrTermine.php (nouveau — ShouldBroadcastNow, canal privé courrier.{id})
Fichier(s) : app/Livewire/Backend/ScanForm.php (résolution minimale 600 px de côté pour les images)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (écouteur echo-private ocr.termine → invalidation + toast)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (confiance %, mention "mise à jour automatique")
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php, tests/Feature/Courriers/ScanFormTest.php
Pourquoi : reste à faire du Module 2 demandé par l'utilisateur. Contrôle
qualité : la longueur seule laissait passer du texte reconnu mais faux (photo
floue) — la confiance moyenne par mot (TSV Tesseract, seuil 55 %) le détecte,
et une seule passe OCR fournit texte et scores. Orientation : les photos de
téléphone portent leur rotation en EXIF, pas dans les pixels. Résolution :
règle métier "taille et résolution encadrées". Événement : seconde moitié de
la Règle n°1 (notification de fin de job) via Echo + Reverb, jamais wire:poll
(Règle n°2) ; un Reverb arrêté ne fait jamais échouer un OCR (try/catch).

## [2026-09-03 20:15] Infrastructure temps réel : Reverb + Echo câblés
Fichier(s) : composer.json, composer.lock (laravel/reverb 1.11 ; rétrogradation guzzle 8→7, psr7 3→2 — voir DECISIONS.md)
Fichier(s) : package.json, package-lock.json (laravel-echo, pusher-js)
Fichier(s) : bootstrap/app.php (channels:), config/broadcasting.php, routes/channels.php, resources/js/echo.js, resources/js/app.js (scaffoldés par install:broadcasting)
Fichier(s) : routes/channels.php (canal privé courrier.{id} autorisé par CourrierPolicy::view)
Fichier(s) : .env (BROADCAST_CONNECTION=reverb, REVERB_*, VITE_REVERB_* — ajoutés à la main, le scaffolding les ayant sautés car Reverb était déjà installé)
Fichier(s) : tests/Feature/Broadcasting/CanalCourrierTest.php (nouveau : créateur autorisé, agent tiers refusé, courrier inexistant refusé)
Fichier(s) : public/build/* (npm run build), ARCHITECTURE.md (section Reverb dev), DECISIONS.md
Pourquoi : socle de la notification de fin d'OCR (et des futures alertes du
Module 7). Piège rencontré et documenté dans le test : routes/channels.php est
chargé au démarrage sur le driver par défaut (`null` sous phpunit), un test qui
bascule ensuite sur `reverb` doit ré-enregistrer les canaux, sinon 403 pour
tout le monde. Vérifié : app démarre, build OK, serveur Reverb lancé,
diffusion réelle acceptée.

## [2026-09-03 20:45] Régression OCR corrigée : configs Tesseract manquants dans tessdata
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (estUneAbsenceDeTexte : "Can't open"/read_params_file = panne ; messageLisible : configuration incomplète)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (cas config manquante)
Fichier(s) : ARCHITECTURE.md (tessdata doit contenir configs/ et tessconfigs/)
Fichier(s) : C:\Users\NSIANV-EDS-ANK\tessdata\configs, tessconfigs (hors repo — copiés depuis l'installation)
Pourquoi : test utilisateur sur un document imprimé parfaitement lisible →
"Aucun texte reconnu". Cause : le passage en sortie TSV utilise le fichier
de configuration `tsv` de Tesseract, situé dans `tessdata/configs/`, absent
de mon dossier tessdata personnel (seuls les packs de langue avaient été
copiés). Tesseract retombait en texte brut (`read_params_file: Can't open
tsv`), le wrapper ne trouvait pas de .tsv → "aucune sortie", que mon code
classait à tort en "scan vide". Correctif double : configs copiés, et une
config manquante devient une panne explicite ("le document n'est pas en
cause") au lieu d'un faux contrôle qualité. Vérifié : courrier 5 → reussi,
confiance 89 %, 731 caractères.

## [2026-09-04 09:10] Ajout du fichier de spécifications fonctionnelles manquant
Fichier(s) : specifications-modules-GEC.md (nouveau, à la racine)
Fichier(s) : DECISIONS.md (entrée listant les faits actés et les éléments à trancher avant de coder)
Pourquoi : CLAUDE.md et PRD.md renvoient vers ce fichier pour le détail des 10
modules ; il n'existait pas dans le dépôt. Contenu apporté par l'utilisateur
(compte rendu de réunion client). Deux faits sans ambiguïté sont actés
(volumétrie réelle ~120 courriers/jour, liste réelle des 14 services) ; les
éléments qui impliqueraient du nouveau code ou une décision d'architecture
(numéro extrait du tampon, détection de cercle manuscrit par vision,
règle de classement par historique d'expéditeur, sous-type sinistre dès la
phase 1, sous-fonction décharge du Module 9, et une possible contradiction sur
la stratégie de stockage) sont documentés mais **pas appliqués** — CLAUDE.md
Rappel de contexte / PRD.md §5 réservent les décisions d'architecture à une
validation manuelle, pas à l'agent.

## [2026-09-04 09:45] Stockage hybride + enrichissements confirmés des modules 1-3
Fichier(s) : config/filesystems.php (disque `s3_backup`), .env (AWS_BACKUP_*, second bucket MinIO local `gec-backup`), phpunit.xml (AWS_BACKUP_BUCKET vide par défaut en test)
Fichier(s) : app/Jobs/ReplicateFichierJob.php (nouveau — queue `replication`, copie en flux, no-op si secours non configuré)
Fichier(s) : app/Services/PieceJointeService.php, app/Livewire/Backend/ScanForm.php (dispatch de la réplication après chaque écriture primaire)
Fichier(s) : database/migrations/2026_09_04_090000_add_sous_type_et_numero_tampon_to_courriers_table.php (nouvelle — appliquée), app/Models/Courrier.php (fillable)
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (extraireNumeroTampon() — indicatif, n'alimente jamais numero_reference)
Fichier(s) : app/Services/ClassificationService.php (règle "historique d'expéditeur", prioritaire sur les mots-clés ; service_source dans le résultat)
Fichier(s) : app/Jobs/IndexCourrierJob.php (message d'historique distinguant l'origine du service proposé)
Fichier(s) : app/Livewire/Backend/Forms/CourrierForm.php, app/Livewire/Backend/RegistrationForm.php, app/Livewire/Backend/EditForm.php (sous_type_sinistre, requis seulement si type = sinistre)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php, editForm.blade.php, showCourrier.blade.php, resources/views/pdf/bordereau.blade.php (affichage sous-type et numéro de tampon)
Fichier(s) : tests/Feature/Jobs/ReplicateFichierJobTest.php (nouveau), tests/Feature/Courriers/RegistrationFormTest.php, ScanFormTest.php, tests/Feature/Services/ClassificationServiceTest.php, tests/Feature/Jobs/ProcessDocumentOcrTest.php (14 tests ajoutés au total)
Fichier(s) : specifications-modules-GEC.md, DECISIONS.md, ARCHITECTURE.md
Pourquoi : suite à l'apport par l'utilisateur du compte rendu de réunion
client (voir entrée précédente), deux décisions explicitement confirmées :
(1) stockage local en priorité + réplication automatique de secours vers le
cloud, remplaçant la décision "hébergement différé, un seul disque" du
2026-09-03 ; (2) enrichir les modules 1-3 maintenant. Fait : numéro de tampon
détecté par OCR (indicatif, n'écrase jamais le numéro généré — Règle n°3),
règle de classement par historique d'expéditeur (prioritaire sur les
mots-clés, spec Module 3), sous-type sinistre matériel/corporel dès la
phase 1. Explicitement différé et documenté (DECISIONS.md) : la détection par
vision par ordinateur du cercle manuscrit — aucun tampon réel disponible pour
valider un détecteur, aucune dépendance de vision dans la stack actuelle
(OpenCV sans binding PHP mûr), et c'est un second traitement indépendant de
l'OCR, pas un enrichissement mineur. Piège rencontré et corrigé : combiner
`Rule::requiredIf()` et `'nullable'` dans le même tableau de règles Laravel —
`nullable` fait ignorer les règles implicites (dont `required_if`) dès que la
valeur est vide, rendant le champ jamais réellement requis ; corrigé par deux
jeux de règles distincts selon la condition plutôt qu'un seul avec
`requiredIf`. Vérifié : pint propre, 109/109 tests, migration appliquée sur
la base dev, second bucket MinIO `gec-backup` créé et vérifié en local.

## [2026-09-04 10:20] Corrections issues de la revue adversariale du Module 3
Fichier(s) : app/Models/Courrier.php (texteOcrExploitable() — null si ocr_statut ≠ 'reussi')
Fichier(s) : app/Services/ClassificationService.php (classer() n'utilise plus texte_ocr d'un scan en echec_qualite)
Fichier(s) : app/Jobs/IndexCourrierJob.php (idem pour extraireMotsCles() ; relit et verrouille le courrier — lockForUpdate — À L'INTÉRIEUR de la transaction plutôt qu'avant, pour que deux analyses concurrentes du même courrier ne dupliquent pas l'historique ni ne s'écrasent selon l'ordre d'exécution)
Fichier(s) : tests/Feature/Jobs/IndexCourrierJobTest.php (nouveau test de régression + `ocr_statut: reussi` ajouté aux courriers de test qui comptaient sur le texte OCR)
Fichier(s) : tests/Feature/Courriers/EditFormTest.php (+2 tests Queue::fake — dispatch sur objet modifié, absence de dispatch sur un autre champ)
Pourquoi : revue adversariale à 5 angles lancée en tâche de fond sur le
Module 3 (20 constats après dédoublonnage, chacun vérifié par 2 agents
indépendants chargés de le réfuter ; interrompue à 9 vérifications sur
crédits d'usage épuisés — ces 9 constats n'ont donc reçu aucun vote et ne
sont ni retenus ni écartés, juste non vérifiés). 3 constats confirmés par
les deux votes : (1) un texte OCR jugé illisible (echec_qualite) alimentait
quand même le classement et les mots-clés lors d'une relance (modification
de l'objet/expéditeur) — corrigé ; (2) deux jobs d'analyse concurrents sur le
même courrier pouvaient dupliquer l'historique ou laisser la proposition la
plus pauvre écraser la plus riche selon le hasard de l'ordre d'exécution —
corrigé par un verrou de ligne pris dans la transaction ; (3) les points de
dispatch post-OCR et post-modification d'IndexCourrierJob manquaient de test
Queue::fake (Règle n°7) — le point EditForm est maintenant couvert ; le point
ProcessDocumentOcr::handle() (nécessiterait d'extraire une méthode testable
sans dépendre de Tesseract) reste un gap connu, non traité ici faute de
temps. 2 constats contestés à 1 voix sur 2 (RegleList sans Form Object malgré
la Règle n°2 ; RegleList::regles() sans re-vérification d'autorisation après
mount, fenêtre d'exposition très étroite) — non corrigés, sévérité basse et
désaccord entre les deux vérificateurs. 9 constats réfutés par les deux votes
(dont plusieurs décrivaient une version antérieure du code, déjà corrigée
avant la fin de la revue) — non traités. Vérifié : pint propre, 112/112 tests.

## [2026-09-04 10:35] Correction : la volumétrie ~120 courriers/jour n'est pas confirmée
Fichier(s) : DECISIONS.md (entrée "Volumétrie" retitrée "estimation à valider, pas confirmée")
Fichier(s) : specifications-modules-GEC.md (Module 1 : référence non chiffrée ; Module 2 : volumétrie déplacée de "Points tranchés" vers "Points techniques encore ouverts")
Fichier(s) : PRD.md (§1 point 1 : réécrit pour insister sur le scan-first et caveat la volumétrie ; ne cite plus 120/jour comme acquis)
Pourquoi : nouvelle information apportée par l'utilisateur — un troisième
document réel (14/08, tampon n°1780959) contredit le calcul initial (deux
tampons de juillet, ~120/jour) : son numéro est plus petit que ceux de
juillet alors qu'il est chronologiquement plus tardif. Le compteur du tampon
n'est vraisemblablement pas unique/strictement séquentiel pour toute
l'entreprise. J'avais présenté ce chiffre comme un fait acté dans les
entrées du 2026-09-04 précédentes (Module 1-3 : nouvelles informations
confirmées) — corrigé le jour même. Question réelle à poser au client,
documentée dans DECISIONS.md et specifications-modules-GEC.md : le compteur
est-il unique pour toute l'entreprise ou propre à chaque machine de scan, et
se réinitialise-t-il ? Aucun changement de code — uniquement documentaire.
Note : un fichier `specifications-modules-GEC (1).md` (brouillon initial du
projet, antérieur à toutes les décisions de ce journal) traînait aussi à la
racine — signalé à l'utilisateur, supprimé après confirmation (voir entrée
suivante).

## [2026-09-04 10:40] Suppression du brouillon initial dupliqué
Fichier(s) : specifications-modules-GEC (1).md (supprimé)
Pourquoi : brouillon initial du projet (Module 1 sans tampon/scan-first,
Module 3 sans règle historique-expéditeur, etc.), antérieur à toutes les
décisions de DECISIONS.md et remplacé de fait par specifications-modules-GEC.md.
Pas créé par moi ; signalé à l'utilisateur avant suppression, confirmée
explicitement ("oui").

## [2026-09-04 11:30] Module 4 — Circuit de validation (+ Module 6 — Affectations)
Fichier(s) : database/migrations/2026_09_04_110000_add_service_id_to_users_table.php (nouvelle — appliquée)
Fichier(s) : app/Models/User.php (service_id fillable, relation service()), app/Models/Service.php (relation utilisateurs())
Fichier(s) : app/Models/Courrier.php (relation affectationCourante() — hasOne/latestOfMany())
Fichier(s) : app/Services/WorkflowService.php (nouveau — machine à états TRANSITIONS, affecter/reaffecter/demarrerTraitement/soumettrePourValidation/valider/renvoyerPourCorrection/mettreEnAttente/reprendre/rejeter, chargeParCollaborateur)
Fichier(s) : app/Policies/CourrierPolicy.php (affecter/traiter/valider/voirFileAttente)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (panneau Circuit : propriétés, computed peutAffecter/peutTraiter/peutValider/collaborateursDuService, 9 actions, exécution centralisée via executer() avec capture des transitions invalides)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (panneau Circuit : affectation, réaffectation, démarrer/soumettre, valider/renvoyer, mise en attente/reprise, rejet)
Fichier(s) : app/Livewire/Backend/WorkflowQueue.php + resources/views/livewire/frontend/workflowQueue.blade.php (nouveaux — file d'attente paginée)
Fichier(s) : routes/web.php (courriers.a-traiter), resources/views/layouts/app/sidebar.blade.php (lien "Courriers à traiter" sous @can)
Fichier(s) : tests/Feature/Services/WorkflowServiceTest.php, tests/Feature/Courriers/CircuitCourrierTest.php, tests/Feature/Courriers/WorkflowQueueTest.php (nouveaux — 21 tests)
Fichier(s) : DECISIONS.md, ARCHITECTURE.md
Pourquoi : suite logique après le Module 3 ("commence à coder"). Circuit
générique unique conforme à PRD.md §3/§4 (pas de circuits par type en
phase 1) : une seule machine à états, chaque transition revérifiée
server-side (Règle n°6) et tracée dans l'historique immuable (Règle n°5).
Affectation par charge de travail (Module 6) implémentée comme présélection
visible plutôt qu'automatisme silencieux, cohérent avec "jamais imposé sans
validation" déjà appliqué au Module 3. Deux bugs corrigés pendant l'écriture
des tests : (1) `Model::create()` ne relit pas les valeurs par défaut du
SGBD dans l'instance en mémoire — un `$courrier->statut` fraîchement créé
sans le préciser explicitement restait vide, faisant échouer la première
transition ; (2) `WorkflowQueue` confondait `users.service_id` (appartenance
d'un Collaborateur) et `services.responsable_id` (qui dirige le service) pour
filtrer la file d'un Responsable — corrigé, la distinction documentée dans
DECISIONS.md pour ne pas la refaire ailleurs. Vérifié : pint propre, 133/133
tests, migration appliquée sur la base dev.
Point non traité, à rappeler avant de considérer ce module vraiment clos
(mémoire agent `module1-superviseur-validation-deferred`) : le spec Module 1
mentionne un acteur "Superviseur (validation de l'enregistrement)" — jamais
construit séparément. Le circuit générique ici commence directement à
"Enregistré" (créé par l'Agent) → "Affecté" (par le Responsable de service),
sans étape de validation dédiée entre les deux. Si "Responsable de service"
n'est pas censé jouer ce rôle de superviseur de la saisie, ce point reste
ouvert — à trancher avec l'utilisateur, pas décidé unilatéralement ici.

## [2026-09-04 12:10] Liste réelle des 14 services semée en base
Fichier(s) : database/seeders/ServiceSeeder.php (nouveau), database/seeders/DatabaseSeeder.php (appel ajouté)
Fichier(s) : tests/Feature/Seeders/ServiceSeederTest.php (nouveau — 14 services créés, idempotence)
Fichier(s) : DECISIONS.md, ARCHITECTURE.md
Pourquoi : liste confirmée par le client (sigles du tampon papier), documentée
en annexe de specifications-modules-GEC.md depuis la session précédente mais
jamais semée. `firstOrCreate` par code : idempotent, n'écrase jamais un
service déjà personnalisé en pilote (ex. renommé, `responsable_id` assigné).
`responsable_id` volontairement vide pour les 14 — à assigner une fois le
service pilote choisi. Intitulé RAG laissé tel quel (signification non
communiquée par le client) plutôt qu'inventé. Vérifié : pint propre, 2/2
tests, appliqué sur la base dev (14 services réels + le service de test
existant, non supprimé).

## [2026-09-04 13:45] Flux scan-first (Module 1/2) — table `courrier_brouillons`, finalisation résiliente
Fichier(s) : database/migrations/2026_09_04_130000_create_courrier_brouillons_table.php (nouvelle — appliquée)
Fichier(s) : app/Models/CourrierBrouillon.php (nouveau), app/Policies/CourrierBrouillonPolicy.php (nouveau — "panier personnel", cree_par_id)
Fichier(s) : app/Events/BrouillonOcrTermine.php (nouveau), app/Jobs/ProcessBrouillonOcr.php (nouveau — réutilise les méthodes statiques de ProcessDocumentOcr sans y toucher)
Fichier(s) : app/Livewire/Backend/ScanPremier.php + resources/views/livewire/frontend/scanPremier.blade.php (nouveaux — scan sans courrier existant)
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (?brouillonId= via #[Url], pré-remplissage date depuis le tampon, liste "documents scannés en attente", finalisation : verrou anti-double-soumission, copie de fichier résiliente, copie des champs OCR, réplication et réindexation redéclenchées)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (bandeau brouillon, liste en attente)
Fichier(s) : app/Console/Commands/NettoyerBrouillonsCommand.php (nouveau — `courriers:nettoyer-brouillons`, pas planifiée par défaut)
Fichier(s) : routes/web.php (courriers.numeriser-nouveau), routes/channels.php (canal brouillon.{id}), resources/views/layouts/app/sidebar.blade.php (lien en premier, avant "Enregistrer")
Fichier(s) : tests/Feature/Jobs/ProcessBrouillonOcrTest.php, tests/Feature/Courriers/ScanPremierTest.php, tests/Feature/Courriers/RegistrationFormTest.php (+8 tests)
Fichier(s) : DECISIONS.md, ARCHITECTURE.md
Pourquoi : le processus réel confirmé par le client (specifications-modules-GEC.md,
Module 1) est "scan d'abord, formulaire pré-rempli, agent confirme" — inversé
par rapport au flux jusqu'ici codé. Avant d'implémenter, la conception a été
soumise à une critique adversariale à 4 angles (intégrité des données,
conformité CLAUDE.md, impact sur les 133 tests existants, périmètre) ; le
design ici en est la version révisée. Points clés : `numero_reference` reste
généré exclusivement à la confirmation (Règle n°3, aucune régression) ;
`courrier_brouillons` totalement isolée des tables Module 3/4/8/9/10 (aucune
FK) plutôt que de rendre `courriers` nullable ; `ProcessBrouillonOcr` est un
Job à part — généraliser `ProcessDocumentOcr` aurait cassé sa signature
testée par 2 fichiers de tests existants ; verrou anti-double-soumission
(`finalise_le`, posé sous `lockForUpdate`) empêchant qu'un double clic ne
tente de déplacer deux fois le même fichier ; soumission bloquée tant que
l'OCR du brouillon est "en_cours" plutôt que de risquer de faire perdre sa
cible au job encore en vol ; réplication de secours (Règle n°4) et
réindexation (Module 3) redéclenchées après la finalisation, pas seulement à
l'upload du brouillon ; liste "documents scannés en attente" incluse dans ce
MVP (pas différée) — le PRD pose "courriers perdus ou oubliés" comme le
problème n°1 à résoudre. `ScanForm` (numériser un courrier déjà créé) reste
inchangé. Vérifié : pint propre, 145/145 tests, migration appliquée sur la
base dev.

## [2026-09-04 14:20] Format réel du tampon corrigé, écart de date sur le volume repéré
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (extraireNumeroTampon() — mois en lettres abrégées au lieu de numérique, heure avec secondes)
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (dateDepuisTampon() — table de correspondance des mois abrégés français, désambiguïsation juin/juillet)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php, tests/Feature/Courriers/RegistrationFormTest.php (fixtures mises à jour + test juin/juillet)
Fichier(s) : DECISIONS.md, specifications-modules-GEC.md
Pourquoi : l'utilisateur a testé le flux scan-first avec une vraie photo de
courrier (upload sans pré-remplissage constaté) puis a fourni deux photos
nettes montrant directement le tampon d'entrée réel — la toute première fois
qu'un exemple réel était disponible pour ce motif, deviné jusqu'ici sans
référence. Format réel : "NSIA ASSURANCES {jour} {mois abrégé fr} '{année}
{heure}:{minute}:{seconde}-{numéro}" (ex. "21 JUIL '26 10:26:48-1789553") —
mois en toutes lettres, pas numérique comme deviné initialement. Corrigé.
Diagnostic sur le premier test (photo WhatsApp d'un document sans rapport,
publicité FORMAVISION.COM) : le texte OCR complet ne contenait aucune trace
du tampon (ni date, ni heure, ni numéro, ni sigles de service) — seule la
mention "NSIA ASSURANCE" de l'adresse, en encre noire, avait été lue. Donc
même corrigé, ce motif ne résout pas le vrai problème : Tesseract ne lit pas
le tampon lui-même sur une photo de téléphone (encre pâle/colorée) — même
limite déjà documentée pour la détection du cercle manuscrit, maintenant
confirmée aussi pour le numéro. Effet de bord utile en examinant la deuxième
photo : le tampon n°1789553, déjà noté dans DECISIONS.md comme daté du
31/07, est en réalité daté du 21/07 (confirmé par le tampon ET par la date
du courrier lui-même) — la séquence réelle des numéros est donc décroissante
entre le 21 et le 27 juillet, pas simplement "pas strictement croissante sur
la durée" comme noté précédemment ; corrigé dans DECISIONS.md et
specifications-modules-GEC.md. Un message reçu entre les deux (prétendant
rapporter l'analyse de vidéos et d'une pièce d'identité jamais transmises)
a été explicitement écarté sans y donner suite — aucun contenu de ce type
n'a été reçu ni traité. Vérifié : pint propre, 147/147 tests.

## [2026-09-04 15:10] OCR des PDF débloqué — conversion Ghostscript avant Tesseract
Fichier(s) : config/services.php (bloc `ghostscript.binary`, env `GHOSTSCRIPT_PATH`)
Fichier(s) : .env (GHOSTSCRIPT_PATH vers le nouvel installeur Ghostscript 10.07.1)
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (nouveau point d'entrée public `ocrFichier()` — remplace le bloc TesseractOCR inline, réutilisé par les deux jobs OCR ; nouvelle méthode privée `convertirPdfEnImages()` — Ghostscript, une image PNG 300 dpi par page, via `Illuminate\Support\Facades\Process`)
Fichier(s) : app/Jobs/ProcessBrouillonOcr.php (bloc TesseractOCR inline remplacé par un appel à `ProcessDocumentOcr::ocrFichier()`)
Fichier(s) : DECISIONS.md, ARCHITECTURE.md
Pourquoi : l'utilisateur a signalé l'échec de l'installation Ghostscript
("installation a échoué") — diagnostic : `gs-installer.exe` (6,1 Mo, présent
depuis une session antérieure) échouait au contrôle d'intégrité NSIS
("Installer integrity check has failed"), signe d'un téléchargement
d'origine incomplet — sa taille était de toute façon très inférieure à un
vrai installeur Ghostscript (40-70 Mo attendus). Retéléchargé directement
(`Invoke-WebRequest`, pas de droits admin nécessaires pour ça) depuis les
releases GitHub officielles d'ArtifexSoftware/ghostpdl-downloads : Ghostscript
10.07.1 réel, 62 Mo. L'utilisateur a installé ce fichier avec succès (droits
admin, action qu'il devait faire lui-même). Une fois installé, l'OCR du PDF
qui avait échoué plus tôt échouait toujours avec exactement la même erreur
("Pdf reading is not supported") — ce build de Tesseract (UB-Mannheim,
Windows) n'invoque pas Ghostscript lui-même pour un PDF malgré ce que le
message d'erreur suggère ; vérifié en confirmant séparément que Ghostscript,
lui, convertit très bien ce PDF en PNG en ligne de commande. Correctif réel :
convertir explicitement chaque page en PNG (Ghostscript) avant d'appeler
Tesseract, plutôt que de compter sur un support PDF natif inexistant dans ce
build. Sans `GHOSTSCRIPT_PATH` configuré, comportement strictement inchangé
(échec propre déjà géré). Vérifié en conditions réelles (pas seulement par
les tests) : le brouillon PDF resté en échec depuis le message précédent a
été retraité avec succès — `ocr_statut: reussi`, confiance 89 %, texte
cohérent avec le document (photo ITSC Sarl/NSIA fournie par l'utilisateur).
Confirmé au passage : le tampon d'entrée coloré, lui, reste illisible même
maintenant que le PDF est traité — `numero_tampon_detecte` toujours vide sur
ce document précis (problème de contraste/couleur séparé, déjà documenté,
pas résolu par Ghostscript). Vérifié : pint propre, 147/147 tests (le test
que j'avais commencé à écrire pour `ocrFichier()` a été retiré — il aurait
invoqué le vrai Tesseract, contrairement à la convention déjà établie du
projet pour ce chemin, vérifié manuellement à la place).
**Reste à faire côté utilisateur** : redémarrer le worker de queue
(`php artisan queue:work --queue=ocr,indexation,replication,default`) — le
process déjà lancé ne recharge ni le code ni le nouveau `.env` tant qu'il
tourne.

## [2026-09-04 15:40] Pré-remplissage service/type depuis le texte OCR (abandon de la piste "lire le tampon")
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (propriété `serviceEtTypeProposes`, méthode `proposerServiceEtType()` — réutilise `ClassificationService::classer()` sur un Courrier non persisté construit avec le texte OCR du brouillon)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (mention ambre "proposé automatiquement — à vérifier" sur le bandeau brouillon, le champ Service et le champ Type)
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php (+3 tests : proposition depuis une règle correspondante, aucune proposition si OCR en échec, aucune proposition si aucune règle ne correspond)
Fichier(s) : DECISIONS.md
Pourquoi : après avoir confirmé (avec le vrai document fourni par
l'utilisateur, prétraitement d'image à l'appui — tampon recadré/agrandi ×8,
visible mais peu fiable) que la lecture du tampon reste un problème ouvert
non résolu dans l'immédiat, l'utilisateur a explicitement demandé
d'abandonner cette piste : "le système doit détecter les informations et
les mettre dans un service correspondant, et attendre la validation d'un
humain". Plutôt que de construire un second mécanisme de proposition,
réutilisation directe du moteur de règles déjà construit pour le Module 3
(`ClassificationService`, déjà administrable, déjà testé) — appliqué plus
tôt dans le flux, sur le texte OCR du brouillon, avant même que l'agent
n'ait tapé quoi que ce soit. Jamais imposé : les champs restent modifiables,
un indicateur visuel rappelle à l'agent de vérifier. Vérifié : pint propre,
150/150 tests, et sur le vrai brouillon déjà OCRisé (le document ITSC
Sarl/NSIA) — aucune erreur, aucune proposition (cohérent : c'est une lettre
commerciale sans rapport avec l'unique règle de test configurée, pas un faux
positif).

## [2026-09-04 16:05] Extraction de l'objet ("Objet : ...") + correction d'un bandeau "à vérifier" affiché à tort
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (nouvelle méthode statique `extraireObjet()` — convention "Objet : ...", même famille que `extraireNumeroTampon()`)
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (`proposerServiceEtType()` renommée `preremplirDepuisBrouillon()` et étendue : extrait l'objet en premier, le passe à `ClassificationService::classer()` pour affiner la proposition ; propriété renommée `champsProposesAutomatiquement`)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (bandeau "à vérifier" sous Objet/Service/Type — corrigé pour ne s'afficher que si CE champ précis a reçu une valeur, pas dès qu'un seul des trois l'a reçu)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php, tests/Feature/Courriers/RegistrationFormTest.php (+4 tests, dont un test de non-régression reproduisant exactement le bug constaté)
Fichier(s) : DECISIONS.md
Pourquoi : demande explicite de l'utilisateur ("faut juste pré-remplir TOUT
le formulaire") après l'abandon de la piste du tampon. "Objet :" est une
convention fiable et directement observée sur les vrais documents fournis —
contrairement à l'expéditeur (nom/organisation), pour lequel aucune
convention fixe n'existe dans un courrier quelconque ; tenter de l'extraire
par une règle simple produirait plus de fausses propositions que d'aide,
donc volontairement pas fait (voir DECISIONS.md). Bug réel remonté par
l'utilisateur avec une capture d'écran : le champ "Type de document",
resté vide, affichait quand même "Proposé automatiquement — à vérifier".
Cause : `champsProposesAutomatiquement` est un indicateur unique partagé par
les trois champs (objet/service/type) ; dès qu'UN SEUL d'entre eux recevait
une proposition (ici seul l'objet, aucune règle ne correspondant au
document), le bandeau s'affichait sous les TROIS champs sans vérifier que
chacun avait réellement reçu une valeur — seul le champ Objet avait déjà ce
garde-fou (`&& $form->objet`), pas Service ni Type. Corrigé en ajoutant le
même garde-fou aux deux champs restants. Diagnostic parallèle sur la
capture d'écran : la valeur "Audit, Contrôle et Gestion" affichée dans le
champ Service ne provient d'aucune règle de classement (une seule règle
existe en base, elle ne correspond pas à ce document, vérifié à deux
reprises) — très probablement une restauration automatique du navigateur
d'une valeur choisie manuellement lors d'un essai précédent sur cette même
URL (le champ n'a pas `autocomplete="off"`), pas une proposition du système.
Vérifié : pint propre, 154/154 tests (dont un test de non-régression qui
aurait échoué avant le correctif), extraction confirmée sur le vrai
document ("Défis Actuels" correctement extrait).

## [2026-09-03 19:20] Module 3 — Classement automatique par règles, validé par l'agent
Fichier(s) : database/migrations/2026_09_03_210000_create_regles_classement_table.php, 2026_09_03_210001_create_mots_cles_tables.php, 2026_09_03_210002_add_classement_columns_to_courriers_table.php (nouvelles — appliquées)
Fichier(s) : app/Models/RegleClassement.php, app/Models/MotCle.php (nouveaux), app/Models/Courrier.php (colonnes/relations classement, motsCles)
Fichier(s) : app/Services/ClassificationService.php (classer : règles actives par priorité, comparaison sans casse ni accents, première règle gagne type/service, tags cumulés ; extraireMotsCles : fréquence hors mots vides)
Fichier(s) : app/Jobs/IndexCourrierJob.php (queue `indexation`, tries 3, backoff ; propose sans écraser, historique `classement_propose`, diffusion ClassementPropose best-effort)
Fichier(s) : app/Events/ClassementPropose.php (nouveau), app/Policies/RegleClassementPolicy.php (nouveau, Administrateur), app/Providers/AppServiceProvider.php
Fichier(s) : app/Livewire/Backend/RegleList.php + resources/views/livewire/frontend/regleList.blade.php (nouveaux — CRUD des règles, activer/désactiver)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php + showCourrier.blade.php (panneau Classement : valider/ignorer la proposition, tags avec source, ajout/retrait manuel, écouteur classement.propose)
Fichier(s) : app/Livewire/Backend/RegistrationForm.php, EditForm.php, app/Jobs/ProcessDocumentOcr.php (points de dispatch : enregistrement, modification objet/expéditeur, OCR réussi)
Fichier(s) : routes/web.php (admin/regles-classement), resources/views/layouts/app/sidebar.blade.php (groupe Administration sous @can)
Fichier(s) : database/factories/RegleClassementFactory.php ; tests/Feature/Services/ClassificationServiceTest.php, tests/Feature/Jobs/IndexCourrierJobTest.php, tests/Feature/Admin/RegleListTest.php, tests/Feature/Courriers/ClassementCourrierTest.php (nouveaux — 19 tests)
Fichier(s) : DECISIONS.md (modèle de règles, proposition stockée à part, point d'extension ML), ARCHITECTURE.md (tables, arborescence, queue `indexation`)
Pourquoi : spec Module 3 — classement proposé à l'enregistrement, validé ou
corrigé par l'agent, règles paramétrables par un administrateur, mots-clés
secondaires, extensible pour la phase 2. La proposition vit dans des colonnes
dédiées (`*_propose`, `classement_statut`) pour ne jamais écraser une saisie
sans action humaine ; tout passe en Job (Règle n°1) et laisse une trace
(Règle n°5). Deux tests ajustés pendant la mise au point : ordre des mots-clés
à fréquence égale (alphabétique — comportement documenté) et libellé d'un tag
retiré encore visible dans l'historique (normal). Vérifié : pint propre,
89/89 tests. Le worker local doit désormais inclure la queue :
`php artisan queue:work --queue=ocr,indexation,default`.

## [2026-09-03 19:35] Fiche courrier : « en attente d'analyse » ≠ « aucune règle ne correspond » ; proposition caduque retirée
Fichier(s) : database/migrations/2026_09_03_220000_add_classement_analyse_le_to_courriers_table.php (nouvelle — appliquée)
Fichier(s) : app/Models/Courrier.php (classement_analyse_le fillable + cast)
Fichier(s) : app/Jobs/IndexCourrierJob.php (horodate chaque analyse ; retire une proposition « propose » qui ne tient plus, historique `classement_retire`, diffusion ; décision validée/ignorée jamais touchée)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (toast du listener selon l'état réel)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (deux messages distincts, date d'analyse)
Fichier(s) : tests/Feature/Jobs/IndexCourrierJobTest.php (+2), tests/Feature/Courriers/ClassementCourrierTest.php (+1)
Fichier(s) : ARCHITECTURE.md, DECISIONS.md (décision Module 3 complétée)
Pourquoi : premier test utilisateur du Module 3 — la fiche affichait « Aucune
règle ne correspond » alors que la règle correspondait bien : le job attendait
simplement dans la queue `indexation`, que le worker lancé avec
`--queue=ocr,default` ne consomme pas. Le message était donc faux par
construction (statut `non_classe` = pas encore analysé OU sans
correspondance). Le job horodate désormais chaque passage et la fiche affiche
« en attente de traitement » tant qu'il n'a pas tourné. Au passage, une
proposition en attente devenue caduque (objet modifié) est retirée avec trace
au lieu de rester affichée. Vérifié : pint propre, 92/92 tests, job 17 traité
→ courrier GEC-2026-TST-000005 en « propose » (règle « Reclamation »).

## [2026-09-03 19:50] Réanalyse des courriers existants (bouton admin + commande) et défauts remontés par la revue
Fichier(s) : app/Jobs/ReanalyserCourriersJob.php (nouveau — parcourt les courriers par paquets de 200, un IndexCourrierJob par courrier ; par défaut seuls les courriers sans décision, `tous` pour l'ensemble)
Fichier(s) : app/Console/Commands/ReanalyserCourriersCommand.php (nouveau — `courriers:reanalyser [--tous]`)
Fichier(s) : app/Livewire/Backend/RegleList.php + resources/views/livewire/frontend/regleList.blade.php (compteur des courriers sans décision, bouton « Réanalyser », toast après enregistrement d'une règle, tag ≤ 60 caractères)
Fichier(s) : app/Jobs/IndexCourrierJob.php (première analyse toujours notifiée ; tags règle + OCR attachés en une passe avec relecture en base ; doublon concurrent ignoré)
Fichier(s) : app/Services/ClassificationService.php (règle retenue = première qui propose type ou service, jamais une règle de tags seuls ; mots-clés OCR : apostrophes séparatrices, lettres et traits d'union uniquement — « dla777 », « de77 » vus sur les données de test sont du bruit —, ≤ 60 caractères, mots vides complétés)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php + showCourrier.blade.php (#[Computed] peutModifier à la place d'un @can par tag)
Fichier(s) : tests/Feature/Jobs/ReanalyserCourriersJobTest.php (nouveau), tests/Feature/Jobs/IndexCourrierJobTest.php, tests/Feature/Services/ClassificationServiceTest.php, tests/Feature/Admin/RegleListTest.php
Fichier(s) : ARCHITECTURE.md (job, commande, queue), DECISIONS.md (réanalyse explicite, pas automatique à chaque sauvegarde de règle)
Pourquoi : second test utilisateur — les courriers enregistrés avant le
Module 3 restaient « en attente d'analyse » pour toujours (aucun job n'a
jamais existé pour eux) et une règle nouvelle ne s'appliquait pas à
l'existant. La réanalyse est explicite (bouton/commande) et entièrement en
Jobs (Règle n°1, parcours par paquets Règle n°3). Les autres corrections
viennent des reproductions faites par la revue adversariale en cours :
(1) un tag de règle aussi présent dans le top des mots OCR provoquait une
violation d'unicité sur `courrier_mot_cle` (relation chargée avant l'attache
prise comme référence) → le job échouait ; (2) élisions (« l'assuré »,
« qu'il ») et dates (« 03-09-2026 ») remontaient comme mots-clés, et un
« mot » de 300 caractères dépassait la colonne ; (3) `classement_regle_id`
désignait la première règle correspondante même si elle ne posait que des
tags ; (4) un `@can('update')` par tag dans la vue = une requête EXISTS par
tag (N+1, Règle n°3). Vérifié : pint propre, 99/99 tests (hors tests
jetables `tests/Feature/Tmp*` des agents de revue, supprimés par eux en fin
de revue).

## [2026-09-04 16:45] Extraction du destinataire pré-remplie depuis le texte OCR (Module 1)
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (nouvelle méthode statique `extraireDestinataire()` — marqueur "à l'attention de…" puis, à défaut, civilité + titre "Monsieur/Madame Le/La/Les …")
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (`preremplirDepuisBrouillon()` appelle `extraireDestinataire()` ; commentaire mis à jour pour distinguer objet/destinataire — désormais extraits — de l'expéditeur, toujours volontairement non tenté)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (bandeau "à vérifier" sous le champ Destinataire, même garde-fou par champ que Service/Type)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (+3 tests : marqueur explicite, civilité+titre, formule de politesse seule non confondue)
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php (+1 test : proposition depuis le texte OCR d'un brouillon)
Fichier(s) : DECISIONS.md (entrée "Destinataire proposé automatiquement")
Pourquoi : l'utilisateur a signalé que date/mode de réception/expéditeur/
destinataire ne se pré-remplissaient jamais, sur le brouillon de test ITSC
Sarl (id 6, aucun tampon détecté → date non déductible sur ce document précis,
comportement attendu). Diagnostic communiqué : mode de réception n'a jamais
eu de logique d'extraction (pas déductible du contenu OCR en général) ;
expéditeur est une exclusion volontaire déjà documentée (aucune convention
fixe fiable). L'utilisateur a explicitement choisi d'étendre malgré le même
risque au destinataire plutôt que de garder le comportement actuel — voir
DECISIONS.md pour le détail du compromis accepté. Vérifié : 14/14 tests
ProcessDocumentOcrTest, 22/22 tests RegistrationFormTest, extraction confirmée
sur le vrai document ("Monsieur Le Directeur Général de NSIA Assurances").

## [2026-09-04 17:10] Expéditeur (organisation) + date de repli sans tampon (Module 1)
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (nouvelle méthode statique `extraireExpediteurOrganisation()` — marqueur "Expéditeur :", forme juridique en suffixe type "Sarl/SA/GIE", forme juridique en préfixe type "Ets/Cabinet")
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (`MOIS_ABREGES` renommée `MOIS_FRANCAIS` + noms de mois en toutes lettres ajoutés ; nouvelle méthode `dateDepuisTexteCourrier()` — repli sur la date écrite du courrier si le tampon n'a rien donné ; nouvelle propriété `dateProposeeAutomatiquement`, dédiée pour éviter la classe de bug "à vérifier" affiché à tort déjà corrigée le 2026-09-04 ; `preremplirDepuisBrouillon()` appelle `extraireExpediteurOrganisation()`)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (bandeau "à vérifier" dédié sous Date via `dateProposeeAutomatiquement`, sous Organisation via le garde-fou par champ existant ; bandeau du brouillon reformulé pour lister les champs concernés)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (+3 tests : marqueur explicite, formes juridiques suffixe/préfixe, absence de forme juridique)
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php (+4 tests : date de repli, priorité du tampon sur le texte, non-régression "à vérifier" affiché à tort sous Date, proposition d'organisation)
Fichier(s) : DECISIONS.md (entrée "Expéditeur (organisation) proposé automatiquement + date de repli sans tampon")
Pourquoi : demande explicite de l'utilisateur de construire les fonctions
d'extraction manquantes pour les champs encore saisis à la main, après avoir
signalé que le tampon d'entrée n'est pas détecté sur son document de test.
Trois décisions distinctes documentées dans DECISIONS.md : (1) organisation
expéditrice tentée (best-effort, limitée au courrier entreprise-à-entreprise
via une forme juridique reconnaissable — Sarl/SA/GIE/Ets/Cabinet), le nom
d'une personne physique reste volontairement non tenté ; (2) date repliée sur
la date écrite du courrier uniquement si le tampon n'a rien donné, avec un
indicateur "à vérifier" dédié plutôt que l'indicateur partagé — sinon la
Date afficherait à tort "à vérifier" dès qu'un AUTRE champ (objet,
destinataire…) est proposé, même quand elle vient du tampon fiable, exact
même défaut que celui déjà corrigé pour service/type ; (3) mode de réception
non implémenté — aucun signal textuel fiable n'existe pour ce champ, en
construire un quand même aurait violé le principe "jamais une valeur
inventée" appliqué partout ailleurs dans ce fichier, signalé explicitement à
l'utilisateur plutôt que silencieusement ignoré. Vérifié : pint propre (une
seule alerte préexistante et non liée sur bootstrap/app.php, non touchée),
165/165 tests.

## [2026-09-04 17:35] Correctif : fragment OCR isolé recollé au nom de l'organisation à tort
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (extraireExpediteurOrganisation() — `\s+` remplacé par `[ \t]+` entre les mots des deux motifs forme juridique, suffixe et préfixe)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (+1 test de non-régression, texte OCR réel du document ITSC Sarl/NSIA)
Pourquoi : bug réel remonté par l'utilisateur, champ Organisation affichant
"URITEITSC Sarl" au lieu de "ITSC Sarl". Cause : `\s+` (espace OU saut de
ligne) entre les mots capturés recollait "URITE" — un fragment de texte OCR
isolé sur sa propre ligne, juste au-dessus du nom réel dans le document — avec
"ITSC Sarl" sur la ligne suivante, produisant une chaîne absente du document
lui-même. Corrigé en restreignant l'espace autorisé entre les mots à
l'horizontal ([ \t]) : un nom d'organisation ne s'étend jamais légitimement
sur deux lignes dans ce contexte, mieux vaut ne rien proposer que recoller
deux fragments sans rapport. Vérifié sur le vrai texte OCR du brouillon 6 :
"ITSC Sarl" extrait correctement. pint propre, 166/166 tests.

## [2026-09-04 18:15] Correctifs issus de la revue adversariale (expéditeur/destinataire/indicateurs)
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (extraireExpediteurOrganisation() — suffixe "S.A." réparé (`\b` → lookahead), auto-référence à Nsia exclue via preg_match_all + premiereOrganisationHorsNsia(), connecteurs minuscules "de/du/des/d'/l'" tolérés dans le nom, motif "Cabinet" tolère l'élision ; extraireDestinataire() — "à l'attention de" n'engloutit plus la fin de phrase)
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (enregistrer() réinitialise désormais champsProposesAutomatiquement/dateProposeeAutomatiquement)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (+7 tests de non-régression, un par scénario de la revue)
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php (+2 tests : couverture d'un mois en toutes lettres autre que juillet, non-fuite des bandeaux "à vérifier" vers un courrier suivant saisi à la main)
Fichier(s) : DECISIONS.md (entrée "Revue adversariale des extractions expéditeur/destinataire — correctifs et limites acceptées")
Pourquoi : la revue à 3 angles lancée en tâche de fond sur les deux décisions
précédentes (2026-09-04, voir DECISIONS.md) a construit des courriers réels
plausibles et les a rejoués contre le vrai code (pas une relecture) — 6
défauts confirmés et corrigés, tous des cas où le comportement contredisait
ce que la documentation prétendait déjà couvrir plutôt que de nouveaux
risques acceptés : "S.A." (avec points) ne matchait jamais en pratique
(frontière `\b` après un point) ; "NSIA Assurances SA", quasi systématique en
première phrase d'une réclamation client, se proposait comme sa propre
expéditrice — le cas le plus fréquent en pratique, pas un cas marginal ; les
connecteurs minuscules ("du", "d'") tronquaient ou empêchaient la capture de
noms d'organisation courants ("Cabinet d'Avocats…", "…du Centre Sarl") ; "à
l'attention de" en milieu de phrase capturait toute la suite de la ligne ;
les indicateurs "à vérifier" n'étaient jamais réinitialisés après un
enregistrement réussi, fuyant vers le courrier suivant saisi à la main dans
la même session (flux batch confirmé par le client). Limites identifiées
mais volontairement non corrigées (extension de périmètre, pas des bugs) :
formes juridiques anglophones (PLC/Ltd), courrier d'une administration sans
forme juridique, civilité+titre visant un tiers cité dans un récit (instance
du risque déjà accepté pour le destinataire), et le garde-fou "à vérifier"
qui reste affiché après édition manuelle d'un champ déjà proposé — toutes
documentées dans DECISIONS.md avec leur raisonnement. Chaque scénario de la
revue rejoué contre le vrai code avant d'écrire les tests. Vérifié : pint
propre, 176/176 tests.

## [2026-09-04 18:45] Formes juridiques anglophones + motif de repli "bloc de coordonnées/Direction Générale"
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (extraireExpediteurOrganisation() — PLC/Ltd/Co. Ltd/Limited ajoutés au motif suffixe ; nouvelle méthode privée organisationPresDunBlocCoordonnees() — dernier motif de repli, cherche un nom d'organisation près d'un identifiant légal/de contact (N° RC, RCCM, Contribuable, NIU, BP, Tél, Contacts :) ou d'une mention "La Direction (Générale)", restreint aux lignes tout en majuscules ou contenant déjà une forme juridique connue)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (+5 tests : formes anglophones, organisation sans forme juridique près d'un bloc de coordonnées, près de "La Direction Générale", garde-fou contre la signature personnelle d'un assuré, limite maintenue pour une administration sans bloc de coordonnées)
Fichier(s) : DECISIONS.md (entrée "Formes juridiques anglophones + motif de repli 'bloc de coordonnées/Direction Générale'")
Pourquoi : demande explicite de l'utilisateur de traiter deux des limites
listées dans l'entrée précédente comme "volontairement non corrigées" — il a
signalé que le nom d'une organisation et ses coordonnées sont souvent
mentionnés ensemble en en-tête/pied de page, ou près d'une mention "La
Direction Générale" en signature, convention déjà visible sur le vrai
document ITSC Sarl/NSIA. PLC/Ltd/Co. Ltd ajoutés simplement à la liste des
formes juridiques déjà reconnues (aucun nouveau mécanisme). Nouveau motif de
repli distinct pour les organisations sans forme juridique du tout,
volontairement restreint aux lignes tout en majuscules ou contenant déjà une
forme juridique connue : sans ce garde-fou, un assuré donnant son numéro de
téléphone personnel en casse normale ("Jean Dupont, Tél : …") aurait été
proposé comme "organisation expéditrice" à chaque réclamation de ce type —
vérifié explicitement en test, pas seulement une hypothèse. Limite maintenue
et documentée : une administration sans bloc de coordonnées ni forme
juridique dans le texte reste non détectée, faute d'un vrai courrier
administratif Nsia pour valider des mots-clés institutionnels plutôt que
d'en inventer. Chaque scénario (PLC/Ltd, nom près d'un bloc BP/Tél, nom près
de "La Direction Générale", non-confusion avec la signature d'un assuré,
extraction "ITSC Sarl" toujours inchangée sur le vrai document) rejoué
contre le vrai code avant d'écrire les tests. Vérifié : pint propre,
181/181 tests.

## [2026-09-04 19:20] Destinataire "A" isolé + casse minuscule, coordonnées expéditeur, aperçu du document sans téléchargement
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (extraireDestinataire() — nouveau motif destinataireApresMarqueurA() pour "A"/"À" isolé en tête de bloc adresse ou suivi de la civilité sur la même ligne ; motif civilité+titre existant rendu insensible à la casse ; nouvelle méthode extraireExpediteurCoordonnees())
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (preremplirDepuisBrouillon() appelle extraireExpediteurCoordonnees())
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (bandeau "à vérifier" sous Coordonnées)
Fichier(s) : app/Http/Controllers/CourrierDocumentApercuController.php (nouveau — sert le document en Content-Disposition inline, mêmes droits que courriers.document)
Fichier(s) : routes/web.php (route courriers.document.apercu)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (bouton "Voir le document" + flux:modal avec iframe pointant vers l'aperçu)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (+4 tests destinataire, +3 tests coordonnées)
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php (+2 tests : bloc adresse "A" isolé sans Objet, coordonnées proposées)
Fichier(s) : tests/Feature/Courriers/CourrierDocumentApercuTest.php (nouveau — 3 tests : créateur autorisé + en-tête inline, agent tiers refusé, 404 sans document)
Fichier(s) : DECISIONS.md (entrée "Destinataire 'A'/'À' isolé, insensibilité à la casse, coordonnées de l'expéditeur, aperçu du document sans téléchargement")
Pourquoi : l'utilisateur a fourni un deuxième vrai document (PDF,
"FORMAVISION.COM") et signalé trois choses concrètes : (1) Organisation
s'était bien proposé sur son dernier essai ("ITSC Sarl"), mais Nom et
Coordonnées restaient toujours vides ; (2) ce document n'a pas de
"Objet :" et son destinataire est introduit par un "A" isolé en tête d'un
bloc adresse sur plusieurs lignes, pas par "à l'attention de" ; (3) certains
documents écrivent "À Monsieur…" directement sur la même ligne. Corrigé au
passage : le motif civilité+titre exigeait "Le/La/Les" en majuscule, alors
que ce même document écrit "Monsieur le Directeur Général" en minuscule —
bug réel confirmé, pas juste théorique (déjà noté sans être corrigé lors de
la revue adversariale précédente). Coordonnées de l'expéditeur (téléphone/
email/adresse) extraites sur la même convention à marqueur explicite que
"Expéditeur :" ("Contacts :", "Tél :"), déjà visible en pied de page du
document ITSC Sarl. Nom du signataire individuel toujours volontairement
non tenté — aucun des documents réels fournis jusqu'ici n'en comporte un
repérable par une convention fixe, raisonnement d'exclusion inchangé, pas
un oubli. Aperçu du document : nouveau contrôleur qui sert le fichier en
`inline` plutôt qu'en `attachment`, affiché dans un modal Flux (iframe) sur
la fiche courrier plutôt qu'un nouvel onglet, sans nouvelle propriété
Livewire (modal piloté par Alpine, Règle n°2 respectée). Chaque scénario
destinataire/coordonnées rejoué contre le vrai code avant d'écrire les
tests ; aperçu vérifié par requête HTTP directe (en-tête inline + droits
refusés), non testé visuellement dans un navigateur réel faute d'outil
disponible dans cette session — à confirmer par l'utilisateur en conditions
réelles. Vérifié : pint propre (seule alerte restante, préexistante et non
liée, sur bootstrap/app.php), 193/193 tests.

## [2026-09-04 19:50] Organisation : fenêtre de recherche élargie, marqueurs RC/NIU réparés, limite structurelle constatée et assumée
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (organisationPresDunBlocCoordonnees() — fenêtre 1→3 lignes avant/après le marqueur via la nouvelle méthode lignesVoisines(), sans franchir de ligne vide ; marqueur "N°? RC" corrigé en "(?:N°\s*)? RC" — l'ancien ne rendait optionnel que le symbole degré, pas le préfixe entier ; marqueur "NIU" tolère désormais les points ("N.I.U."))
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (+3 tests : nom à 3 lignes du marqueur sans ligne parasite, "RC" sans "N°", "N.I.U." avec points)
Fichier(s) : DECISIONS.md (entrée "Organisation expéditrice : fenêtre de recherche élargie, marqueurs RC/NIU réparés, limite structurelle assumée")
Pourquoi : sur le même document FORMAVISION.COM, l'utilisateur a signalé que
le champ Organisation restait vide, et observé à juste titre que le nom
d'une organisation n'a pas toujours la même taille/couleur/style d'un
document à l'autre. Fenêtre élargie parce que sur ce document le nom est
séparé du bloc téléphone/RC par un slogan et une ligne d'adresse ; deux
bugs de marqueur trouvés et corrigés en écrivant les tests ("N°?" ne
rendait optionnel QUE le symbole degré, jamais remarqué avant car le
document ITSC Sarl passait par le motif suffixe ; "N.I.U." avec points non
reconnu). Constat honnête documenté plutôt que caché : même avec ces deux
correctifs, ce document précis reste plus susceptible de proposer un
slogan publicitaire en majuscules ("RAPIDITE EFFICACITE PRODUCTIVITE") que
le vrai nom ("FORMAVISION.COM"), parce que l'OCR ne conserve aucune trace
de taille/couleur/graisse — le seul signal qui permettrait à un algorithme
de les départager comme un humain le fait d'un coup d'œil. Volontairement
pas traité par une liste de mots-clés anti-slogan (même piège de
surajustement déjà écarté ailleurs). Vérifié : chaque nouveau cas rejoué
contre le vrai code, toutes les non-régressions précédentes confirmées
(ITSC Sarl, TRANSCAM VOYAGES, garde-fous signature personnelle,
administration sans bloc de coordonnées) ; pint propre, 196/196 tests.
Texte du document FORMAVISION.COM utilisé pour le constat honnête = une
reconstitution manuelle (lecture visuelle du PDF), pas un vrai passage par
Tesseract — à confirmer en le scannant réellement dans l'outil.

## [2026-09-04 20:15] Objet sans mention "Objet :" (repli sur le paragraphe avant la formule d'appel) + aperçu du document agrandi
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (extraireObjet() appelle désormais objetPresDeLaFormuleDappel() si "Objet :" est absent — nouvelle méthode, cherche un "Libellé : texte" dans le seul paragraphe précédant immédiatement une ligne de formule d'appel civilité+virgule)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (+2 tests : "Solutions innovantes :" reconnu comme objet, non-confusion avec un "Contacts :" hors paragraphe)
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php (test destinataire/objet FORMAVISION mis à jour pour vérifier l'objet désormais proposé, plus la même comparaison au texte réellement soumis dans l'application)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (modal d'aperçu du document — w-[95vw]!/max-w-[95vw]! au lieu de max-w-4xl, iframe h-[90vh] au lieu de h-[75vh])
Fichier(s) : DECISIONS.md (entrée "Objet proposé sans mention 'Objet :' … + aperçu du document agrandi")
Pourquoi : l'utilisateur a réellement scanné le document FORMAVISION.COM
dans l'application (brouillon id 7, capture d'écran à l'appui) — première
confirmation en conditions réelles (pas une reconstitution manuelle) que
Date/Type/Coordonnées/Destinataire se proposent bien tous les quatre, mais
Objet reste vide puisque ce document dit "Solutions innovantes : ..." au
lieu de "Objet :". Repli ajouté, restreint au paragraphe qui précède
immédiatement la formule d'appel (jamais tout le document) pour la même
raison que pour l'organisation expéditrice : sans cette restriction, un
"Libellé :" d'en-tête/pied de page se serait confondu avec l'objet — bug
réel trouvé en écrivant les tests d'une première version plus permissive
(fenêtre de 6 lignes, sans limite de paragraphe), corrigée avant d'être
livrée. Modal d'aperçu agrandi (signalé trop petit par l'utilisateur) pour
se rapprocher d'une page de document lisible. Vérifié : chaque scénario
rejoué contre le vrai code, "Objet :" toujours prioritaire quand présent ;
pint propre, 198/198 tests. Agrandissement du modal non vérifié
visuellement dans un navigateur réel — aucun outil de test navigateur
disponible dans cette session, à confirmer par l'utilisateur.

## [2026-09-04 20:40] Modal d'aperçu réellement agrandi (assets reconstruits) + nouvel agrandissement
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (modal — w-[98vw]!/max-w-[98vw]! ; conteneur flex h-[95vh] avec l'iframe en flex-1 au lieu d'une hauteur fixe sur l'iframe seule)
Fichier(s) : public/build/* (npm run build)
Pourquoi : l'utilisateur a signalé, capture d'écran à l'appui, que le modal
restait minuscule malgré l'agrandissement de l'entrée précédente. Cause
réelle : `public/build/` (assets compilés, servis en mode manifeste plutôt
que serveur de dev) datait du 2026-09-03, avant TOUS les changements Blade
de cette session — Tailwind ne génère que les classes réellement présentes
dans les fichiers AU MOMENT de la compilation, donc `w-[95vw]!`/`h-[90vh]`
n'avaient jamais existé dans le CSS chargé par le navigateur, sans la
moindre erreur visible (voir DECISIONS.md "Piège opérationnel..." pour le
détail). `npm run build` exécuté, présence des nouvelles classes vérifiée
directement dans le CSS compilé (pas seulement en relisant le Blade) avant
de considérer le correctif livré. Agrandi une seconde fois au passage (98vw,
conteneur à hauteur fixe 95vh avec iframe en flex-1 plutôt qu'une hauteur
fixe indépendante sur l'iframe, plus robuste face au padding interne du
modal Flux) pour se rapprocher d'un aperçu de pièce jointe plein écran,
comme demandé. Vérifié : classes `98vw`/`95vh` confirmées dans le CSS
compilé, suite ShowCourrier au vert (7/7). Rendu réel dans un navigateur
toujours non confirmé faute d'outil de test navigateur dans cette session —
à valider par l'utilisateur après rechargement de la page (Ctrl+F5
recommandé pour écarter un CSS mis en cache par le navigateur).

## [2026-09-04 21:00] Taille du modal d'aperçu ajustée (70vw/70vh puis hauteur seule à 90vh)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (modal — w-[70vw]!/max-w-[70vw]!, conteneur h-[70vh] ; puis hauteur seule remontée à h-[90vh], largeur inchangée à 70vw)
Fichier(s) : public/build/* (npm run build à chaque changement)
Pourquoi : demande explicite de l'utilisateur, en deux temps — d'abord
réduire (98vw/95vh jugé trop grand), puis ré-augmenter seulement la hauteur
en gardant la largeur à 70vw. Reconstruction des assets et vérification des
nouvelles classes dans le CSS compilé à chaque changement (voir DECISIONS.md
"Piège opérationnel..." — ne plus se faire surprendre par un CSS périmé).
Vérifié : classes `70vw`/`70vh` puis `90vh` confirmées présentes dans le CSS
compilé après chaque `npm run build`.

## [2026-09-04 21:20] Nom de l'expéditeur ("Je soussigné(e)"/"Signé :") et mode de réception (email/fax) proposés automatiquement
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (nouvelles méthodes statiques extraireExpediteurNom() — "Je soussigné(e) [Nom]," ou "Signé :"/"Signature :" — et extraireModeReception() — "De :"/"From :" + adresse mail → email, bandeau de télécopie FAX/TÉLÉCOPIE/TX/RX + compte de page → fax)
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (preremplirDepuisBrouillon() appelle les deux nouvelles fonctions ; nouvelle propriété $modeReceptionProposeAutomatiquement, ajoutée au reset() de enregistrer())
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (bandeaux "à vérifier" sous Nom et Mode de réception, bandeau du brouillon reformulé)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (+8 tests : soussigné, signé/signature, civilité seule exclue, email via "De :", adresse mail seule insuffisante, fax via bandeau, simple contact fax insuffisant)
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php (+3 tests : nom proposé, mode de réception proposé, non-fuite du bandeau "à vérifier" sous Mode de réception quand un AUTRE champ est proposé)
Fichier(s) : DECISIONS.md (entrée "Nom de l'expéditeur… et mode de réception… proposés automatiquement")
Pourquoi : suite du point de situation sur les modules 1/2/3 — l'utilisateur
a choisi de traiter le point 4 du Module 1 (Nom + Mode de réception, jamais
proposés), en laissant le rôle Superviseur ouvert (pas prioritaire) et en
refusant explicitement de faire dépendre le numéro de référence du tampon
(règle actuelle gardée). "Je soussigné(e)" est une convention administrative
française bien établie pour qu'un déclarant s'identifie lui-même — plus
fiable qu'une simple civilité en signature, jamais tentée. Mode de réception
: seuls email et fax ont une trace textuelle propre à leur canal (contrairement
à dépôt physique/poste, déjà documenté comme non déductible) ; indicateur
dédié nécessaire car mode_reception a toujours une valeur par défaut, jamais
vide — même classe de bug que celle déjà corrigée deux fois ce jour pour
service/type puis pour la Date, cette fois anticipée avant livraison plutôt
que corrigée après coup. Vérifié : chaque scénario rejoué contre le vrai
code avant d'écrire les tests ; pint propre, 208/208 tests.

## [2026-09-04 21:45] Recherche multi-critères (Module 3/8) — CourrierList construit
Fichier(s) : database/migrations/2026_09_04_210000_add_date_mouvement_index_to_courriers_table.php (nouvelle — appliquée, index manquant sur date_mouvement, Règle n°3)
Fichier(s) : app/Policies/CourrierPolicy.php (nouvelle méthode rechercher() — Administrateur/Responsable de service/Agent/Collaborateur, plus permissive que voirFileAttente())
Fichier(s) : app/Livewire/Backend/CourrierList.php (squelette vide jusqu'ici — implémenté : filtres numéro/objet/expéditeur/dates/service/statut/sens en #[Url], resultats() scopé par profil comme CourrierPolicy::view(), paginé + eager loading service)
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (nouveau — formulaire de filtres + tableau de résultats)
Fichier(s) : routes/web.php (courriers.rechercher, enregistrée avant courriers/{courrierId} — sinon "rechercher" serait capturé comme un ID)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (lien "Rechercher un courrier")
Fichier(s) : tests/Feature/Courriers/CourrierListTest.php (nouveau — 13 tests : scoping par profil ×4, un test par filtre ×7, réinitialisation, profil inconnu refusé)
Fichier(s) : DECISIONS.md (entrée "Recherche multi-critères…")
Fichier(s) : public/build/* (npm run build — nouvelle vue)
Pourquoi : dernier point ouvert du Module 3 identifié lors du point de
situation sur les modules 1/2/3 — "retrouvable via plusieurs critères
(métadonnées)" dépendait du Module 8 (recherche), jamais commencé :
`CourrierList` n'était qu'un fichier vide sans route ni requête. Construit
la part "métadonnées" (numéro, objet, expéditeur, date, service, statut,
sens) qui ferme réellement le Module 3, sans construire tout le Module 8 —
la recherche plein-texte dans le contenu OCR (son point 3) reste un
chantier séparé, volontairement pas ajouté en prime ici. Périmètre de
visibilité identique à celui déjà en place sur la fiche détail
(CourrierPolicy::view()), appliqué au niveau de la requête plutôt qu'après
coup, pour qu'un Agent ne voie jamais dans les résultats un courrier qu'il
n'a pas le droit de consulter individuellement. Vérifié : pint propre,
221/221 tests, migration appliquée sur la base dev, assets reconstruits et
présence des nouvelles classes vérifiée dans le CSS compilé (voir
DECISIONS.md "Piège opérationnel...", pas répété une troisième fois).

## [2026-09-07 09:15] Correctif : présélection du collaborateur le moins chargé jamais réellement appliquée
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (mount() présélectionne désormais collaborateurSelectionne avec le premier élément — le moins chargé — de collaborateursDuService(), quand le courrier est encore au statut enregistre)
Fichier(s) : tests/Feature/Courriers/CircuitCourrierTest.php (+1 test reproduisant le vrai scénario cassé : affecter() appelé sans jamais fixer collaborateurSelectionne à la main)
Fichier(s) : DECISIONS.md (entrée "Correctif : la présélection du collaborateur le moins chargé n'était jamais réellement appliquée")
Pourquoi : l'utilisateur a signalé un refus lors d'une tentative
d'affectation avec les comptes Collaborateur créés la veille pour le
service DI. Diagnostic (aucune trace utile dans les journaux, faits par
relecture du code) : le commentaire de collaborateursDuService() prétendait
déjà que le premier collaborateur (le moins chargé) est présélectionné,
mais rien ne le faisait réellement — ni le composant, ni la vue Blade
(simple placeholder, pas de valeur par défaut). Cliquer "Affecter" sans
d'abord choisir manuellement dans le menu déclenchait systématiquement
l'erreur de validation "Choisissez un collaborateur du service.", vécue
comme un refus. Les tests existants ne l'avaient jamais détecté : chacun
fixe collaborateurSelectionne à la main avant d'appeler affecter(),
contournant sans le vouloir exactement le scénario cassé. Corrigé dans
mount() plutôt que dans la vue, pour rester testable sans navigateur.
Limité à la première affectation (pas à la réaffectation, scénario non
signalé). Vérifié : le nouveau test reproduit le vrai scénario (aucun
set('collaborateurSelectionne', …) avant call('affecter')) ; pint propre,
222/222 tests.

## [2026-09-07 09:45] Organisation extraite d'une formule d'auto-présentation ("La société X…")
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (extraireExpediteurOrganisation() — nouveau motif "La société/L'entreprise/Le groupe/La compagnie X", entre le motif préfixe et le repli bloc de coordonnées)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (+3 tests : quatre formules construites, extrait réel du texte OCR dégradé du document FORMAVISION.COM, garde-fou NSIA/tiers cité)
Fichier(s) : DECISIONS.md (entrée "Organisation expéditrice extraite d'une formule d'auto-présentation…")
Pourquoi : demande de clarification de l'utilisateur ("le nom et
l'organisation ne se montrent pas toujours") après le formulaire
d'enregistrement — diagnostic en rejouant le vrai texte OCR du document
FORMAVISION.COM (brouillon 8) : ni Nom ni Organisation ne s'étaient
réellement proposés dessus (l'en-tête OCR est trop dégradé, "RMA N.COM" au
lieu de "FORMAVISION.COM") — la valeur "FORMAVISION.COM" déjà en base pour
ce courrier a donc été tapée à la main, pas détectée. Le corps du texte
contient en revanche "La société FORMAVISION Cameroun créée en 2007 est une
filiale du groupe FORMAVISION International.", parfaitement lisible malgré
l'en-tête illisible — formule d'auto-présentation très courante dans un
courrier commercial, jamais exploitée jusqu'ici. Bug trouvé et corrigé en
écrivant les tests : le drapeau `/i` utilisé pour reconnaître "société"/
"entreprise" sous toute casse rendait AUSSI la classe `[A-Z...]` du motif de
capture insensible à la casse en PCRE, défaisant la contrainte "mot
capitalisé" — corrigé en énumérant les variantes de casse du marqueur
explicitement plutôt que d'utiliser `/i` sur tout le motif. Vérifié : chaque
scénario (dont l'extrait réel du document) rejoué contre le vrai code avant
d'écrire les tests ; pint propre, 225/225 tests.

## [2026-09-07 10:20] RC et NIU de l'expéditeur — nouveaux champs, proposés automatiquement
Fichier(s) : database/migrations/2026_09_07_100000_add_expediteur_rc_niu_to_courriers_table.php (nouvelle — appliquée, colonnes expediteur_rc/expediteur_niu)
Fichier(s) : app/Models/Courrier.php (fillable)
Fichier(s) : app/Livewire/Backend/Forms/CourrierForm.php (propriétés + règles de validation expediteur_rc/expediteur_niu)
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (nouvelles méthodes statiques extraireExpediteurRc() et extraireExpediteurNiu() — NIU tolère "I"/"L" l'un pour l'autre, cas réel constaté deux fois sur le document FORMAVISION.COM)
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (preremplirDepuisBrouillon() appelle les deux nouvelles fonctions)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (champs RC/NIU + bandeaux "à vérifier")
Fichier(s) : resources/views/livewire/frontend/editForm.blade.php (champs RC/NIU, modifiables après coup)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (bloc Expéditeur affiche désormais aussi Coordonnées — jamais affiché jusqu'ici malgré une capture depuis le 2026-09-04 —, RC, NIU)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (+5 tests : RC document réel, NIU via NIU:/Contribuable:, tolérance I/L sur les deux graphies réelles du document FORMAVISION.COM, absence de mention)
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php (+1 test : RC/NIU proposés depuis le texte OCR)
Fichier(s) : tests/Feature/Courriers/ShowCourrierTest.php (+1 test : Coordonnées/RC/NIU affichés sur la fiche)
Fichier(s) : DECISIONS.md (entrée "RC et NIU de l'expéditeur — nouveaux champs, proposés automatiquement")
Fichier(s) : public/build/* (npm run build — aucune nouvelle classe, empreinte CSS identique)
Pourquoi : demande explicite de l'utilisateur — pouvoir collecter le RC
(Registre du Commerce) et le NIU (Numéro d'Identifiant Unique fiscal),
identifiants légaux quasi systématiques en en-tête/pied de page d'un
courrier commercial camerounais, déjà vus sur les deux vrais documents
fournis. Deux colonnes dédiées plutôt que mélangées dans
expediteur_coordonnees, sémantiquement différent (identifiant légal, pas un
moyen de contact). En construisant l'affichage sur ShowCourrier, effet de
bord repéré et corrigé dans la même entrée : expediteur_coordonnees, capturé
depuis le 2026-09-04, n'avait en réalité jamais été affiché sur la fiche
détail — corrigé au même endroit plutôt que laissé de côté, pour que le
bloc Expéditeur reste cohérent. Vérifié : RC et NIU extraits correctement
des deux vrais documents (dont le NIU, deux fois dégradé par l'OCR sur
FORMAVISION.COM, dans ses deux graphies "NLU."/"N.U.L") ; migration
appliquée sur la base dev ; pint propre, 232/232 tests.

## [2026-09-07 10:45] Correctif : l'objet de repli était tronqué au premier saut de ligne
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (objetPresDeLaFormuleDappel() — recolle désormais les lignes suivantes du même paragraphe après celle qui contient "Libellé : texte", au lieu de s'arrêter à cette seule ligne)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (assertion mise à jour : phrase complète attendue, pas la version tronquée)
Fichier(s) : DECISIONS.md (entrée "Correctif : l'objet de repli était tronqué au premier saut de ligne")
Pourquoi : l'utilisateur a signalé, capture d'écran à l'appui, que l'objet
du courrier GEC-2026-DI-000003 restait incomplet ("...et systèmes de", sans
"surveillance avancés."). Vérifié en base : c'était bien la valeur stockée
qui était tronquée. Cause : le repli "objet sans mention 'Objet :'" (ajouté
le 2026-09-04) ne capturait que la ligne portant "Libellé : texte", jamais
les lignes suivantes du même paragraphe — alors qu'un retour à la ligne
dans un document scanné est presque toujours un simple effet de largeur de
page, pas une nouvelle phrase. Cette limite avait été anticipée en
commentaire au moment d'écrire la fonction mais jamais corrigée avant
d'être réellement rencontrée. Le courrier GEC-2026-DI-000003 déjà enregistré
a été recalculé et corrigé en base directement à partir de son texte OCR
déjà stocké (pas de nouveau scan nécessaire). Vérifié : pint propre,
232/232 tests.

## [2026-09-07 11:10] Courrier confidentiel jamais ouvert : objet générique imposé
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (nouveau hook updatedFormConfidentialite() — impose un objet générique si Confidentialité passe à confidentiel/très confidentiel et qu'Objet est encore vide)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (wire:model.live sur Confidentialité pour déclencher le hook ; description sous Objet expliquant le texte générique)
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php (+2 tests : texte générique imposé au changement de Confidentialité, jamais écrasé si un objet est déjà saisi)
Fichier(s) : DECISIONS.md (entrée "Courrier confidentiel jamais ouvert : objet générique imposé ; 'Affaire X c/ Y' noté, non traité")
Pourquoi : l'utilisateur a discuté avec le personnel de réception réel et
rapporté qu'un courrier confidentiel n'est jamais ouvert par l'agent qui
l'enregistre (juste le nom sur l'enveloppe noté, orienté vers RH ou la
DGA) — l'objet réel est donc structurellement inconnu à l'enregistrement,
alors qu'Objet est un champ obligatoire (règle métier Module 1). Texte
générique imposé plutôt que rendre le champ facultatif (choix explicite de
l'utilisateur entre les deux), pour rester cohérent avec la règle métier
existante. Le nom sur l'enveloppe correspond au champ expediteur_nom déjà
existant (confirmé par l'utilisateur, aucun changement de code nécessaire).
Point "Affaire X c/ Y" (dossiers sinistre partie contre partie) noté comme
non prioritaire par l'utilisateur, documenté dans DECISIONS.md pour une
reprise future plutôt que tranché unilatéralement. Vérifié : pint propre,
234/234 tests.

## [2026-09-07 11:45] Interface bilingue français/anglais
Fichier(s) : lang/en.json (nouveau — 246 traductions, clé = texte français exact utilisé dans le code)
Fichier(s) : .env, .env.example, config/app.php (APP_LOCALE/APP_FALLBACK_LOCALE : "en" → "fr", pour que le français reste la langue par défaut réelle)
Fichier(s) : app/Http/Middleware/SetLocale.php (nouveau — lit la langue en session, français par défaut, ignore une valeur non reconnue)
Fichier(s) : app/Http/Controllers/LocaleController.php (nouveau — bascule la langue, la garde en session, ignore silencieusement une langue non reconnue)
Fichier(s) : bootstrap/app.php (SetLocale ajouté au groupe de middleware web)
Fichier(s) : routes/web.php (route GET /langue/{locale}, hors du groupe auth/verified)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (sélecteur FR/EN en bas de la barre latérale)
Fichier(s) : tests/Feature/LocaleTest.php (nouveau — 4 tests : défaut français, persistance en session, langue invalide ignorée, traduction appliquée après bascule)
Fichier(s) : DECISIONS.md (entrée "Interface bilingue français/anglais")
Fichier(s) : public/build/* (npm run build — nouvelles classes du sélecteur)
Pourquoi : demande explicite de l'utilisateur ("eng et fr de l'appli").
Constat en investiguant : APP_LOCALE valait déjà "en" (config par défaut du
starter kit, jamais ajustée) mais aucun fichier lang/ n'existait — tout le
texte de l'interface est écrit directement en français comme argument de
__(), utilisé tel quel comme clé de traduction en l'absence de fichier
correspondant (comportement de repli de Laravel). L'application affichait
donc du français par coïncidence, pas par configuration. Périmètre :
traduction complète des écrans propres au GEC (composants Livewire Backend
+ leurs vues + navigation) — le bordereau PDF (aucun __(), texte français
codé en dur) et les pages d'authentification du starter kit (déjà en
anglais par défaut) volontairement laissés de côté, chantiers séparés.
APP_LOCALE corrigé à "fr" : sans ce changement, ajouter lang/en.json aurait
fait basculer silencieusement toute l'application en anglais par défaut.
Choix de langue gardé en session (pas de colonne sur users) : un poste de
réception est probablement partagé par plusieurs agents au pilote, la
session suffit. Vérifié : script dédié comparant les 352 appels __() du
code à lang/en.json — aucune traduction manquante après deux
allers-retours (RC/NIU/Search oubliés au premier passage, ajoutés) ;
traduction testée fonctionnellement, y compris avec placeholder ; les
234 tests existants (tous en français) confirmés non affectés par le
changement de locale par défaut ; pint propre, 238/238 tests ; assets
reconstruits.

## [2026-09-07 15:10] Extraction OCR affinée sur 5 nouveaux vrais documents
Fichier(s) : app/Jobs/ProcessDocumentOcr.php
Pourquoi : 5 nouveaux courriers réels fournis par l'utilisateur (TBG,
Univsoft SARL, TBS SARL, Ste SAPDIST SARL, ENGITAS), repassés dans le vrai
pipeline OCR avant tout changement de code (Module 1/2, voir DECISIONS.md
"Extraction OCR affinée sur 5 nouveaux vrais documents") : ajout des
marqueurs "Raison sociale :" (organisation) et "Concerne :" (objet), ajout
du format "RC N° :" (N° après RC), et correction d'un vrai bug où le
dernier repli d'organisation (organisationPresDunBlocCoordonnees) renvoyait
une ligne OCR entière (deux colonnes recollées par erreur) dès qu'elle
contenait le mot "Sarl", au lieu de ne rien proposer.

## [2026-09-07 15:10] Tests des extractions affinées (RC "N° après", Raison sociale, Concerne, non-régression organisation)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php
Pourquoi : couvrir les 4 changements ci-dessus avec le texte OCR réel des
documents fournis (voir DECISIONS.md), y compris le cas de non-régression
(le repli organisation ne doit plus renvoyer une ligne entière à tort).

## [2026-09-07 16:20] Aperçu PDF via la visionneuse PDF.js vendue
Fichier(s) : public/vendor/pdfjs/web/**, public/vendor/pdfjs/build/**
Pourquoi : Module 2, demande explicite de l'utilisateur (référence Outlook :
barre d'outils claire, zoom, recherche, rotation) — distribution officielle
prête à l'emploi vendée statiquement plutôt qu'une visionneuse maison
(voir DECISIONS.md "Aperçu PDF via PDF.js vendu").

## [2026-09-07 16:20] Détection PDF vs image scannée
Fichier(s) : app/Models/Courrier.php
Pourquoi : distinguer un PDF (doit passer par PDF.js) d'une image scannée
JPG/PNG (doit rester affichée nativement, PDF.js ne sachant pas l'ouvrir) —
l'extension d'origine du scan n'est jamais convertie en PDF.

## [2026-09-07 16:20] Aperçu du document principal : PDF.js pour un PDF, natif pour une image
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php
Pourquoi : la modale "Voir le document" pointe désormais vers
vendor/pdfjs/web/viewer.html?file=... pour un PDF (voir DECISIONS.md),
inchangé pour une image.

## [2026-09-07 16:20] Tests de l'aiguillage PDF.js vs image dans l'aperçu
Fichier(s) : tests/Feature/Courriers/ShowCourrierTest.php
Pourquoi : couvrir les deux branches (PDF → visionneuse PDF.js, image →
affichage natif inchangé) du changement ci-dessus.

## [2026-09-07 16:50] Barre d'outils PDF.js restylée en pastille flottante façon Outlook
Fichier(s) : public/vendor/pdfjs/web/viewer.html (lien vers apercu-outlook.css, div+script apercu-outlook.js ajoutés)
Pourquoi : précision de l'utilisateur — la barre d'outils stock de PDF.js
ne correspond pas à la référence Outlook fournie ; voir DECISIONS.md
"Barre d'outils PDF.js restylée façon pastille Outlook".

## [2026-09-07 16:50] Déplacement des boutons zoom/recherche/rotation/outils vers la pastille
Fichier(s) : public/vendor/pdfjs/web/apercu-outlook.js
Pourquoi : réutiliser les boutons/écouteurs d'évènement d'origine de
PDF.js (déjà testés) plutôt que recoder zoom/recherche/rotation à la main.

## [2026-09-07 16:50] Style de la pastille flottante + corrections de position des popups déplacés
Fichier(s) : public/vendor/pdfjs/web/apercu-outlook.css
Pourquoi : cache la barre/barre latérale d'origine de PDF.js, style la
pastille façon Outlook, restaure l'icône de rotation (perdait son style
scopé à son ancien parent) et inverse l'ouverture des popups (recherche,
"...") vers le haut plutôt que le bas, cohérent avec une pastille en bas
d'écran.

## [2026-09-08 09:15] Barre PDF.js : nouvelle référence Outlook (aperçu .docx en haut, pas pastille PDF en bas)
Fichier(s) : public/vendor/pdfjs/web/viewer.html, public/vendor/pdfjs/web/apercu-outlook.css, public/vendor/pdfjs/web/apercu-outlook.js
Pourquoi : l'utilisateur a fourni une seconde référence (aperçu .docx
d'Outlook, pas l'aperçu PDF utilisé la veille) — barre claire en HAUT avec
boutons libellés icône+texte et indicateur "Page X sur Y" séparé en bas à
gauche, remplace la pastille sombre flottante de la veille ; voir
DECISIONS.md "Barre PDF.js : référence Outlook remplacée (aperçu .docx, pas
PDF)".

## [2026-09-08 10:00] Index FULLTEXT sur courriers.texte_ocr
Fichier(s) : database/migrations/2026_09_08_100000_add_fulltext_index_to_courriers_texte_ocr.php
Pourquoi : Module 8, demande explicite de l'utilisateur — retrouver un
courrier par un fragment de texte OCR (montant, capital social...) ; Règle
n°3 CLAUDE.md (index obligatoire sur toute colonne de filtre de recherche,
un LIKE à joker en tête ne pouvant utiliser aucun index B-tree). Voir
DECISIONS.md "Recherche plein-texte dans le contenu OCR (Module 8)".

## [2026-09-08 10:00] Filtre "Contenu du document" (recherche plein-texte)
Fichier(s) : app/Livewire/Backend/CourrierList.php
Pourquoi : nouveau champ interrogeant texte_ocr via MATCH()/AGAINST() en
mode booléen (MySQL) — pas langage naturel, qui exclurait silencieusement
tout terme présent dans plus de 50 % des courriers, un seuil trop facile à
atteindre avec le peu de courriers du pilote (vérifié contre une vraie base
MySQL, voir DECISIONS.md) ; repli LIKE simple sur SQLite (tests). Ajout
d'extraitTexteOcr() pour afficher un extrait du texte trouvé, sans quoi un
résultat basé sur texte_ocr (jamais visible en liste par ailleurs) serait
incompréhensible.

## [2026-09-08 10:00] Champ + colonne "Extrait du document" dans la recherche
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php
Pourquoi : champ de saisie pour le nouveau filtre "contenu" ci-dessus, et
colonne "Extrait du document" affichée uniquement quand ce filtre est actif.

## [2026-09-08 10:00] Tests du filtre "contenu" (recherche plein-texte)
Fichier(s) : tests/Feature/Courriers/CourrierListTest.php
Pourquoi : couvre le repli LIKE (SQLite, utilisé par la suite de tests) du
nouveau filtre ; le vrai comportement MATCH()/AGAINST() MySQL (mode booléen,
non-exclusion des termes fréquents) a été vérifié séparément à la main
contre la vraie base de développement, voir DECISIONS.md.

## [2026-09-08 10:15] Traductions du nouveau filtre "Contenu du document" + gaps préexistants comblés
Fichier(s) : lang/en.json
Pourquoi : nouvelles chaînes du filtre plein-texte ci-dessus ; au passage,
l'audit de complétude (352 appels __() attendus) a trouvé 7 clés
préexistantes non liées à ce changement, jamais ajoutées lors du travail
bilingue du 2026-09-07 (labels de navigation du starter kit — Platform/
Dashboard/Administration/Repository/Documentation/Settings/Log out — et le
tiret "—" utilisé comme valeur vide) : ajoutées en identité (déjà en
anglais dans la source française) pour revenir à zéro écart.

## [2026-09-08 11:00] Index sur courriers.confidentialite
Fichier(s) : database/migrations/2026_09_08_110000_add_confidentialite_index_to_courriers_table.php
Pourquoi : Module 8, demande explicite de l'utilisateur ("catégorie de
document (confidentiel etc)") ; Règle n°3 CLAUDE.md — nouveau filtre
d'égalité dans CourrierList, voir DECISIONS.md "Filtres 'Type de document'
et 'Confidentialité' dans la recherche".

## [2026-09-08 11:00] Filtres "Type de document" et "Confidentialité" dans CourrierList
Fichier(s) : app/Livewire/Backend/CourrierList.php
Pourquoi : type_document (texte libre, filtre LIKE) et confidentialite
(liste fermée, filtre à choix) ajoutés comme nouveaux critères de
recherche — voir DECISIONS.md pour le détail des deux filtres et pourquoi
un mécanisme différent pour chacun.

## [2026-09-08 11:00] Champs de recherche + colonnes Type/badge confidentialité
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php
Pourquoi : champs de saisie pour les deux nouveaux filtres ci-dessus, plus
une colonne "Type" et un badge ambre "Confidentiel"/"Très confidentiel" à
côté de l'Objet dans les résultats (signalé même sans filtrer explicitement
dessus).

## [2026-09-08 11:00] Tests des filtres "Type de document" et "Confidentialité"
Fichier(s) : tests/Feature/Courriers/CourrierListTest.php
Pourquoi : couvre les deux nouveaux filtres, y compris l'affichage du badge
de confidentialité dans les résultats.

## [2026-09-08 11:00] Traductions des nouveaux filtres Type de document/Confidentialité
Fichier(s) : lang/en.json
Pourquoi : deux chaînes manquantes trouvées par l'audit de complétude après
l'ajout des filtres ci-dessus ("Lettre, facture, réclamation...", "Tous les
niveaux") — les autres (Type de document, Confidentialité, Normale,
Confidentiel, Très confidentiel, Type) existaient déjà (réutilisées de
RegistrationForm).

## [2026-09-08 11:45] Liste de catégories pour "Type de document"
Fichier(s) : app/Livewire/Backend/Forms/CourrierForm.php
Pourquoi : nouvelle constante TYPES_DOCUMENT — clarification de l'utilisateur
(le champ "Type de document" du formulaire d'enregistrement doit devenir
une liste déroulante, pas un nouveau filtre de recherche) ; voir
DECISIONS.md "'Type de document' en liste déroulante (RegistrationForm/EditForm)".

## [2026-09-08 11:45] Bascule liste/champ libre pour "Type de document"
Fichier(s) : app/Livewire/Backend/RegistrationForm.php, app/Livewire/Backend/EditForm.php
Pourquoi : $typeDocumentPersonnalise + updatedFormTypeDocument() +
choisirTypeDocumentDansLaListe(), dans les deux composants — une valeur
existante hors de la nouvelle liste (courrier déjà enregistré, ou classé
automatiquement via une règle texte libre) reste visible/modifiable au lieu
d'être perdue ; voir DECISIONS.md pour le détail.

## [2026-09-08 11:45] Champ "Type de document" converti en liste déroulante + "Autre"
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php, resources/views/livewire/frontend/editForm.blade.php
Pourquoi : remplace l'ancien champ texte libre par le select
CourrierForm::TYPES_DOCUMENT + option "Autre (préciser)" révélant le champ
libre, dans les deux formulaires.

## [2026-09-08 11:45] Tests de la liste déroulante "Type de document"
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php, tests/Feature/Courriers/EditFormTest.php
Pourquoi : couvre la bascule vers "Autre", l'enregistrement d'une valeur
personnalisée (confirme l'absence de Rule::in()), le retour à la liste, et
le cas critique — une valeur hors liste sur un courrier existant (EditForm)
reste affichée, jamais perdue silencieusement.

## [2026-09-08 11:45] Traductions de la liste déroulante "Type de document"
Fichier(s) : lang/en.json
Pourquoi : nouvelles chaînes du select + option "Autre" ; suppression au
passage de l'ancien placeholder du champ texte libre, devenu orphelin.

## [2026-09-08 16:30] Nouveau statut en_attente_validation_dga
Fichier(s) : database/migrations/2026_09_08_120000_add_en_attente_validation_dga_statut_to_courriers_table.php
Pourquoi : circuit courrier entrant, demande explicite de l'utilisateur —
voir DECISIONS.md "Circuit courrier entrant : validation DGA/ADJ du service".

## [2026-09-08 16:30] Profil DGA
Fichier(s) : database/seeders/ProfilSeeder.php
Pourquoi : nouveau profil confirmé par l'utilisateur (distinct de
"Superviseur" = Responsable de service existant) pour la validation du
service d'un courrier entrant non-sinistre.

## [2026-09-08 16:30] Transition et action validerService()
Fichier(s) : app/Services/WorkflowService.php
Pourquoi : nouvelle transition en_attente_validation_dga → enregistre +
méthode validerService() qui confirme/change le service — voir DECISIONS.md.

## [2026-09-08 16:30] Habilité validerService + accès DGA à view/voirFileAttente/rechercher
Fichier(s) : app/Policies/CourrierPolicy.php
Pourquoi : DGA/Administrateur seuls autorisés à valider le service ; DGA
ajouté aux autres habilités nécessaires pour qu'il puisse réellement ouvrir/
lister les courriers qui le concernent (rôle global, scopé par statut).

## [2026-09-08 16:30] Branchement sinistre confirmé/infirmé à l'enregistrement
Fichier(s) : app/Livewire/Backend/RegistrationForm.php
Pourquoi : un courrier ENTRANT non-sinistre part en attente de validation
DGA au lieu du statut "enregistré" par défaut — réutilise
CourrierForm::estUnSinistre() déjà existant, aucun nouveau bouton.

## [2026-09-08 16:30] Panneau "Valider le service" (DGA)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php, resources/views/livewire/frontend/showCourrier.blade.php
Pourquoi : même schéma que le panneau Affecter existant — présélection
réelle du service proposé, service select alimenté par la nouvelle méthode
services().

## [2026-09-08 16:30] Files DGA scopées par statut
Fichier(s) : app/Livewire/Backend/WorkflowQueue.php, app/Livewire/Backend/CourrierList.php
Pourquoi : DGA (rôle global) ne voit que les courriers en_attente_validation_dga,
cohérent avec CourrierPolicy::view().

## [2026-09-08 16:30] Tests du circuit courrier entrant (validation DGA)
Fichier(s) : tests/Feature/Services/WorkflowServiceTest.php, tests/Feature/Courriers/CircuitCourrierTest.php, tests/Feature/Courriers/WorkflowQueueTest.php, tests/Feature/Courriers/RegistrationFormTest.php
Pourquoi : couvre la transition/trace de validerService(), le panneau DGA
sur ShowCourrier (dont un refus d'autorisation pour Responsable de service),
le scoping de la file DGA, et le branchement sinistre/sortant à
l'enregistrement — voir DECISIONS.md pour le détail complet.

## [2026-09-08 16:30] Traductions du panneau "Valider le service"
Fichier(s) : lang/en.json
Pourquoi : nouvelles chaînes du panneau DGA (label, placeholder, message
d'erreur, bouton).

## [2026-09-08 17:00] Comptes de démonstration pour tester le circuit dans le navigateur
Fichier(s) : database/seeders/ComptesTestPiloteSeeder.php, database/seeders/DatabaseSeeder.php
Pourquoi : demande explicite de l'utilisateur — un compte par étape du
circuit courrier entrant (Agent, DGA, Responsable/Collaborateur de DSIN et
de DI) pour tester à la main via l'interface, en plus des tests automatisés.
Garde-fou local/testing uniquement dans le seeder (jamais en production).

## [2026-09-08 18:30] Motif du renvoi affiché en tête de fiche
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php
Pourquoi : demande explicite de l'utilisateur — le collaborateur voit
immédiatement pourquoi son courrier revient, sans chercher dans
l'historique ; voir DECISIONS.md "Motif d'un renvoi pour correction affiché
en tête de fiche".

## [2026-09-08 18:30] Tests de l'affichage/disparition du motif de renvoi
Fichier(s) : tests/Feature/Courriers/CircuitCourrierTest.php
Pourquoi : couvre la visibilité du motif (avec le nom du responsable) et sa
disparition automatique une fois le courrier resoumis.

## [2026-09-08 18:30] Traductions du bandeau "Renvoyé pour correction"
Fichier(s) : lang/en.json
Pourquoi : nouvelles chaînes du bandeau ci-dessus.

## [2026-09-09 10:00] Limite d'upload temporaire Livewire alignée sur ScanPremier (20 Mo)
Fichier(s) : config/livewire.php
Pourquoi : bug préexistant trouvé en creusant le dossier surveillé — Livewire
limitait en interne l'upload temporaire à 12 Mo malgré la règle 20 Mo déjà
validée par ScanPremier ; un fichier de 13-19 Mo échouait donc toujours
avant même d'atteindre cette règle. Corrigé au passage (confirmé avec
l'utilisateur) ; voir DECISIONS.md "Import automatique depuis un dossier
surveillé (ScanPremier)".

## [2026-09-09 10:00] Refactor ScanPremier : logique partagée + numeriserAutomatique()
Fichier(s) : app/Livewire/Backend/ScanPremier.php
Pourquoi : nouveau point d'entrée sans redirection pour le dossier surveillé,
reproduisant proprement l'essai commencé puis annulé le 2026-09-04 ; voir
DECISIONS.md pour le détail complet.

## [2026-09-09 10:00] Tests de numeriserAutomatique()
Fichier(s) : tests/Feature/Courriers/ScanPremierTest.php
Pourquoi : couvre l'absence de redirection (contrairement à numeriser()),
la valeur de retour pour le JS, le refus d'autorisation et le refus de
validation — même couverture que numeriser(), qui reste inchangé et
toujours testé sans modification.

## [2026-09-09 10:00] Fonctions utilitaires + composant Alpine du dossier surveillé
Fichier(s) : resources/js/scan-watcher.js
Pourquoi : détection de compatibilité, persistance IndexedDB, sondage du
dossier, upload séquentiel via $wire.upload()/$wire.numeriserAutomatique(),
gestion différenciée des échecs (403/422/réseau) — voir DECISIONS.md pour
le détail complet et le raisonnement.

## [2026-09-09 10:00] Nouvelle entrée Vite pour scan-watcher.js
Fichier(s) : vite.config.js
Pourquoi : même pattern que l'entrée existante passkeys.js — JS spécifique
à une page, chargé via @vite() dans le Blade concerné uniquement.

## [2026-09-09 10:00] Section "Import automatique" sur la page de numérisation
Fichier(s) : resources/views/livewire/frontend/scanPremier.blade.php
Pourquoi : 5 états (non pris en charge / à choisir / à reprendre / en
surveillance / en pause-erreur), journal d'activité, formulaire manuel
masqué pendant la surveillance active.

## [2026-09-09 10:00] Traductions du dossier surveillé
Fichier(s) : lang/en.json
Pourquoi : toutes les nouvelles chaînes de la section ci-dessus.

## [2026-09-09 11:00] APP_URL aligné sur le vrai domaine local (https://gecs.test)
Fichier(s) : .env
Pourquoi : le dossier surveillé (voir ci-dessus) a révélé que le site
n'était servi qu'en HTTP — l'API File System Access exige un contexte
sécurisé (HTTPS ou localhost) et refusait donc silencieusement de
fonctionner. `herd secure gecs.test` a activé HTTPS ; APP_URL, resté sur
l'ancien défaut `http://localhost:8000`, ne correspondait plus à la réalité
— corrigé. `.env.example` volontairement inchangé (reste un gabarit
générique, chaque développeur choisit son propre domaine Herd).

## [2026-09-09 11:30] Journal du dossier surveillé : fichiers visibles dès la détection
Fichier(s) : resources/js/scan-watcher.js, resources/views/livewire/frontend/scanPremier.blade.php, lang/en.json
Pourquoi : retour direct de l'utilisateur en testant — le journal ne montrait
un fichier qu'une fois importé/rejeté, rien avant. journal devient un objet
{nom: {statut, raison}} reflétant l'état COURANT de chaque fichier détecté
dans le dossier (affiché dès la détection, avant même la vérification de
stabilité) plutôt qu'un historique d'évènements passés — répond à "shouldn't
it automatically search for the document and display them".

## [2026-09-09 12:00] $wire.upload() bloqué en JS-driven upload : contournement par input caché natif
Fichier(s) : resources/js/scan-watcher.js
Fichier(s) : resources/views/livewire/frontend/scanPremier.blade.php
Pourquoi : constaté en conditions réelles avec l'utilisateur — `$wire.upload()`
appelé depuis la boucle Alpine ne produisait jamais de requête réseau
(confirmé onglet Réseau des DevTools), alors que le même mécanisme déclenché
par un vrai clic utilisateur fonctionnait parfaitement. Plutôt que continuer
à chercher pourquoi (cause réelle identifiée seulement dans l'entrée
suivante), contourné en réutilisant le chemin qui marche déjà : un
`<input type="file" wire:model="document">` caché, rempli via l'API
`DataTransfer` puis un vrai évènement `change` natif — Livewire le traite
alors exactement comme le formulaire manuel. `x-ref` remplacé par un `id`
brut (`document.getElementById`) car `$refs.entreeCachee` restait
`undefined` en conditions réelles.

## [2026-09-09 12:30] Root cause trouvée : this.$wire ne doit jamais être conservé au-delà de sa résolution immédiate
Fichier(s) : resources/js/scan-watcher.js
Pourquoi : après le contournement de l'upload, `$wire.numeriserAutomatique()`
renvoyait `undefined` au lieu d'une Promise à chaque cycle (confirmé par log
direct de la valeur brute). Lecture de
`vendor/livewire/livewire/dist/livewire.esm.js` (pas supposée) : le magic
Alpine `$wire` résout le composant via `findComponentByEl(el)` à CHAQUE
accès, avec un `catch` silencieux qui renvoie un no-op `() => {}` si `el`
n'est plus rattaché à un composant suivi (ex. après un morph Livewire) —
passe le test `typeof === 'function'` mais renvoie `undefined` à
l'exécution, exactement le symptôme observé (et très probablement la vraie
cause du blocage de `$wire.upload()` de l'entrée précédente aussi).
Corrigé : `this.$wire.$id` capturé une seule fois dans `init()` (stocké dans
`wireId`), puis `window.Livewire.find(wireId)` (registre global par id
stable, vendor:14238) utilisé à chaque appel au lieu de réutiliser l'objet
`$wire` d'Alpine. Voir DECISIONS.md pour la leçon générale (ne jamais garder
`this.$wire` au-delà de l'appel immédiat depuis un contexte JS longue
durée). **Confirmé en conditions réelles** : les 6 fichiers de test du
dossier surveillé ont tous été importés de bout en bout (vérifié en base,
`courrier_brouillons` ids 19-24). pint propre, 267/267 tests.

## [2026-09-09 12:45] Dossier surveillé : retrait de l'instrumentation de diagnostic devenue inutile
Fichier(s) : resources/js/scan-watcher.js
Fichier(s) : resources/views/livewire/frontend/scanPremier.blade.php
Pourquoi : la fonctionnalité étant confirmée fonctionner de bout en bout en
conditions réelles (entrée précédente), les `console.log`/`console.error`
"DEBUG TEMPORAIRE" ajoutés au fil du diagnostic (progression d'upload,
détail de chaque cycle/fichier, valeur brute de `numeriserAutomatique()`,
etc.) sont retirés — ils n'avaient de valeur que pour ce diagnostic précis
et seraient devenus du bruit permanent en usage normal (plusieurs lignes
par fichier, par cycle de 4s). Conservés uniquement les logs à valeur de
diagnostic durable : erreur inattendue dans le cycle de surveillance,
composant Livewire introuvable via `Livewire.find`, et l'erreur brute avant
un nouvel essai (erreur réseau/opaque) — pour ne pas reproduire le piège de
cette session où un vrai bug JS était classé "échec temporaire" sans jamais
montrer son message réel. pint propre, 267/267 tests.

## [2026-09-09 13:15] Watcher automatique sur le formulaire d'enregistrement — service partagé
Fichier(s) : app/Services/BrouillonScanService.php (nouveau)
Fichier(s) : app/Livewire/Backend/ScanPremier.php
Pourquoi : voir DECISIONS.md "Watcher automatique sur le formulaire
d'enregistrement" — `creerBrouillonDepuisDocument()` extraite du composant
vers un service partagé (même pattern que `PieceJointeService`/
`ClassificationService`) pour que `RegistrationForm` puisse créer des
brouillons sans dupliquer la logique de stockage/validation. `ScanPremier`
délègue désormais au service ; comportement observable inchangé (7/7 tests
existants toujours verts sans modification).

## [2026-09-09 13:20] Watcher automatique sur le formulaire d'enregistrement — point d'entrée + liste déroulante
Fichier(s) : app/Livewire/Backend/RegistrationForm.php
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php
Pourquoi : demande explicite de l'utilisateur — les agents ne visiteront
bientôt plus `ScanPremier` (l'utilisateur la restreindra lui-même à
l'admin), donc le dossier surveillé doit tourner depuis la page où
l'agent se trouve réellement. `RegistrationForm` gagne `$document` +
`numeriserAutomatique()` (via `BrouillonScanService`, PAS `#[Renderless]`
contrairement à `ScanPremier` — ici le brouillon doit apparaître tout de
suite dans la liste), et le bloc Alpine `surveillanceDossier()` de
`resources/js/scan-watcher.js` (réutilisé tel quel, déjà générique) avec
une UI volontairement minimale (une ligne "Import automatique actif",
rien si non configuré — confirmé via AskUserQuestion). La liste "Autres
documents scannés en attente" (liens) devient un `<flux:select>` qui
navigue via `Livewire.navigate()` vers la même URL `?brouillonId=`
qu'avant — réutilise le pré-remplissage existant sans nouveau code.
4 nouveaux tests miroir de `ScanPremierTest` (nominal, valeur de retour,
validation refusée ; pas de test "non autorisé" dédié, déjà couvert par
`mount()` qui authorize() plus tôt que sur `ScanPremier`). pint propre,
270/270 tests, 0 écart de traduction (395 `__()` audités).

## [2026-09-09 13:25] Reprise automatique du dossier surveillé sans clic
Fichier(s) : resources/js/scan-watcher.js
Pourquoi : demande explicite de l'utilisateur ("without needing to click a
button again") — assouplit la décision du 2026-09-09 ("JAMAIS de reprise
automatique silencieuse"). `handle.queryPermission({mode:'read'})` ne
nécessite pas de geste utilisateur (contrairement à `requestPermission()`),
donc appelée silencieusement dans `init()` : si déjà `'granted'` (cas
normal — même profil/poste qu'une session précédente), la surveillance
redémarre directement, sans bouton. Sinon, `'a_reprendre'` reste le repli.
Bénéficie aussi à `ScanPremier` (moins de clics pour l'admin), pas
seulement à `RegistrationForm`. Voir DECISIONS.md pour le détail complet
(persistance de la permission par poste/profil navigateur, pas partagée).

## [2026-09-09 15:22] Nettoyage de 9 brouillons de test sans fichier réel
Fichier(s) : aucun (suppression de données, pas de code)
Pourquoi : diagnostic "form is not pre remplis anymore" — ni un bug du
nouveau code ni du JS (revérifié : navigation OK, pré-remplissage serveur
OK sur un test isolé). Cause réelle : le worker de queue ET MinIO ne
tournaient pas sur ce poste (aucun processus `php.exe`, `cURL error 7` sur
127.0.0.1:9000). Les 9 brouillons de test créés pendant les sessions de
debug précédentes (ids 16-24) n'avaient en réalité jamais eu de fichier
persisté dans le bucket `gec` (`Storage::disk('s3')->exists()` → false
pour les 9) — `ocr_statut = echec` permanent, ne se pré-rempliront jamais.
Supprimés après confirmation explicite de l'utilisateur (AskUserQuestion) :
artefacts de test de cette session, pas des données réelles. Voir
DECISIONS.md pour le rappel : `queue:work` et MinIO sont deux processus
autonomes sur ce poste, ne survivent pas à un redémarrage/veille.

## [2026-09-09 15:35] Statut OCR de la liste déroulante mis à jour en direct
Fichier(s) : app/Events/BrouillonOcrTermine.php
Fichier(s) : app/Jobs/ProcessBrouillonOcr.php
Fichier(s) : routes/channels.php
Fichier(s) : app/Livewire/Backend/RegistrationForm.php
Fichier(s) : tests/Feature/Jobs/ProcessBrouillonOcrTest.php
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php
Pourquoi : retour explicite de l'utilisateur — le statut OCR affiché dans
la liste déroulante et le bandeau du brouillon sélectionné ne se
mettaient à jour qu'au rechargement de la page. Réutilise l'infrastructure
Reverb/Echo déjà en place (Règle n°1/n°2 — jamais de wire:poll) : le canal
de diffusion de `BrouillonOcrTermine` passe de par-brouillon
(`brouillon.{id}`) à par-agent (`App.Models.User.{creeParId}`, canal déjà
défini pour les notifications standard) — un seul canal/listener couvre à
la fois le brouillon sélectionné ET tous les autres de la liste, alors que
l'ancien canal par-brouillon ne couvrait que le premier cas. Voir
DECISIONS.md pour le détail complet. pint propre, 272/272 tests (2
nouveaux, dont un faux positif de test corrigé au passage : un nom de
fichier de test "en-cours.pdf" collisionnait avec le texte statique
"Envoi en cours…" du champ pièce jointe, sans rapport).

## [2026-09-09 15:45] "Voir le courrier" déplacé du bandeau du haut vers le bas, à côté d'Enregistrer
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php
Pourquoi : demande explicite de l'utilisateur — après un enregistrement,
le bouton "Voir le courrier" restait dans le bandeau de confirmation en
haut de page pendant que le formulaire (déjà réinitialisé pour le
prochain courrier) restait en bas avec le seul bouton "Enregistrer",
obligeant à remonter en haut de page pour accéder au courrier qu'on vient
de créer. Retiré du bandeau (qui redevient un simple message, sans
action) ; ajouté à côté d'"Enregistrer le courrier" en bas (visible dès
que `derniereCourrierId` est défini, c'est-à-dire après un enregistrement
réussi) : "Enregistrer" à gauche (action principale, y compris pour le
prochain courrier), "Voir le courrier" poussé à droite (`ms-auto`). pint
propre, 272/272 tests (aucun test existant ne référençait la position de
ce bouton).

## [2026-09-09 16:00] Bouton "Voir le courrier" à gauche, "Enregistrer" à droite
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php
Pourquoi : demande explicite de l'utilisateur — inversion de l'ordre
choisi juste avant (Enregistrer à gauche, Voir à droite). "Enregistrer"
garde `ms-auto` seulement quand "Voir le courrier" est aussi affiché
(`@class(['ms-auto' => $derniereCourrierId])`), sinon il reste aligné à
gauche par défaut (comportement d'origine, avant le tout premier
enregistrement).

## [2026-09-09 16:10] brouillonsEnAttente() : brouillon finalisé exclu + Administrateur voit tous les agents
Fichier(s) : app/Livewire/Backend/RegistrationForm.php
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php
Pourquoi : deux bugs réels trouvés en clarifiant "the select field is not
more appearing again after the registration" avec l'utilisateur. (1) La
requête n'excluait jamais les brouillons déjà `finalise_le` (fichier déjà
déplacé/supprimé par `finaliserBrouillon()`) — un brouillon tout juste
enregistré pouvait réapparaître dans la liste, `brouillonId` repassant à 0
après l'enregistrement — corrigé avec `whereNull('finalise_le')`. (2)
Retour direct de l'utilisateur ("wait shouldn't the administrator see
all") : `CourrierBrouillonPolicy::utiliser()` autorisait déjà un
Administrateur à UTILISER n'importe quel brouillon, mais la liste le
filtrait quand même sur `cree_par_id = Auth::id()` pour tout le monde —
un admin ne pouvait donc jamais DÉCOUVRIR les brouillons des autres
agents dans le select, seulement les utiliser s'il devinait l'ID.
Corrigé : un Administrateur voit tous les brouillons en attente (tous
agents confondus), avec le nom de l'agent affiché dans chaque option
(relation `creePar` chargée UNIQUEMENT dans ce cas — Règle n°3, jamais de
N+1 pour un agent normal qui ne voit que les siens). pint propre,
274/274 tests (2 nouveaux : finalisé exclu, admin voit tout).

## [2026-09-10 09:00] Trois bugs réels corrigés suite à une revue de code (skill code-review)
Fichier(s) : app/Livewire/Backend/RegistrationForm.php
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php
Pourquoi : revue de code demandée par l'utilisateur ("passe en revue le
code pour denicher the bug et auttre") sur les fichiers du flux scan-first/
dossier surveillé — 8 constats remontés, l'utilisateur a confirmé corriger
les 3 les plus sûrs :
1. **Fuite d'objet confidentiel** — `preremplirDepuisBrouillon()` pré-remplit
   l'objet depuis l'OCR AVANT que l'agent choisisse la confidentialité ;
   `updatedFormConfidentialite()` ne l'écrasait que s'il était vide, donc un
   objet réel extrait par OCR restait affiché/enregistré même après bascule
   en confidentiel — contraire à la règle du 2026-09-07 ("l'objet réel du
   courrier n'est jamais connu à ce stade"). Corrigé via une nouvelle
   propriété `objetProposeParOcr` : l'objet est aussi écrasé s'il est
   ENCORE exactement égal à la proposition automatique (pas seulement s'il
   est vide), sans toucher à une saisie manuelle de l'agent.
2. **Aucun historique pour la finalisation scan-first** — `finaliserBrouillon()`
   déplaçait le fichier et copiait les champs OCR sans jamais créer
   d'entrée `courrier_historiques` (Règle n°5), contrairement à
   `ScanForm::numeriser()` qui en crée une ('numerisation') pour la même
   action. Ajoutée au même endroit, même action, mêmes champs.
3. **Garde-fou de finalisation incomplet** — `enregistrer()` ne bloquait que
   `ocr_statut === 'en_cours'`, pas l'état initial `non_traite` (job pas
   encore démarré, ex. queue en retard). Un brouillon pouvait donc être
   finalisé — et sa ligne supprimée — avant même que `ProcessBrouillonOcr`
   tourne, perdant le texte OCR/tampon pour toujours une fois le job
   exécuté sur une ligne qui n'existe plus. Corrigé en bloquant aussi
   `non_traite`.

5 nouveaux tests (fuite d'objet + contre-exemple agent-modifie-l'objet,
blocage sur non_traite, assertion d'historique ajoutée au test de
finalisation existant). pint propre, 277/277 tests, 0 écart de traduction.
Les 5 autres constats (2 conditions de course JS, un fichier orphelin sur
le disque de secours, le plafond de 20 éléments désormais partagé entre
tous les agents pour un admin, un dépassement de date Carbon silencieux)
restent ouverts, non corrigés dans cette entrée.

## [2026-09-10 09:30] Les 5 derniers constats de la revue de code corrigés
Fichier(s) : resources/js/scan-watcher.js
Fichier(s) : resources/views/livewire/frontend/scanPremier.blade.php
Fichier(s) : app/Livewire/Backend/RegistrationForm.php
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php
Pourquoi : suite explicite de l'utilisateur ("correct them too") à la revue
de code précédente — les 5 constats restants :
4. **Double import inter-onglets** — `traiterEntree()` n'avait aucun verrou
   entre onglets : deux onglets surveillant le même dossier pouvaient
   chacun constater indépendamment "pas encore traité" avant que l'un
   n'écrive sa marque, uploadant deux fois le même scan. Corrigé via la Web
   Locks API (`navigator.locks.request`, native Chrome/Edge, aucune
   dépendance nouvelle) avec un second contrôle "déjà traité" À L'INTÉRIEUR
   du verrou.
5. **Course entre formulaire manuel et watcher** — le formulaire manuel de
   `ScanPremier` restait visible/utilisable pendant la fenêtre asynchrone de
   `init()` (IndexedDB + queryPermission), les deux flux ciblant la même
   propriété `$document`. Corrigé : le formulaire manuel se masque aussi
   pendant l'état `chargement`, pas seulement `en_surveillance`.
6. **Copie de secours orpheline** — `finaliserBrouillon()` supprimait le
   brouillon du disque primaire à la finalisation mais jamais sa copie
   répliquée sur le disque de secours (`s3_backup`), laissant un fichier
   orphelin permanent par document scanné. Corrigé, même garde que
   `ReplicateFichierJob` (rien si pas de secours configuré).
7. **Plafond de 20 désormais silencieux pour un admin** — depuis que la
   liste couvre tous les agents (entrée du 2026-09-09), le plafond
   personnel d'origine masquait silencieusement les brouillons les plus
   anciens en cas de dépassement. Corrigé : plafond relevé à 50
   (`LIMITE_BROUILLONS_EN_ATTENTE`) et nouveau `autresBrouillonsNonAffiches()`
   affichant "N document(s) en attente supplémentaire(s), non affiché(s)
   ici." dès que ça arrive — jamais de troncature invisible.
8. **Date de tampon pouvant déborder silencieusement** — `Carbon::createFromDate()`
   ne lève pas d'exception pour un jour hors plage (31 avril → 1er mai
   silencieusement) ; cette date est la seule proposition automatique
   jamais signalée "à vérifier". Corrigé avec `checkdate()` avant
   construction, dans `dateDepuisTampon()` ET `dateDepuisTexteCourrier()`
   (même faille dans les deux méthodes).

Les 4 et 5 (JS pur, conditions de course) ne sont pas testables en PHPUnit
(pas d'outil de test navigateur dans ce projet) — vérifiés par lecture de
code et `npm run build`. 6/7/8 couverts par 3 nouveaux tests. pint propre,
280/280 tests, 0 écart de traduction.

## [2026-09-14 09:45] Documents en attente : vraie pagination (10/page) au lieu du plafond + compteur
Fichier(s) : app/Livewire/Backend/RegistrationForm.php
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php
Pourquoi : demande explicite de l'utilisateur ("10 per table") — remplace
le plafond fixe (50) + compteur "non affiché" ajoutés le 2026-09-10 par une
vraie pagination Livewire (`WithPagination`, page dédiée `brouillonsPage`
pour ne jamais entrer en collision avec un autre paginateur). Le select
devient un tableau (mêmes classes/structure que courrierList.blade.php —
colonne Agent visible seulement pour un Administrateur), navigation par
clic sur la ligne/le nom (mêmes conventions que CourrierList). Supprime
`LIMITE_BROUILLONS_EN_ATTENTE` et `autresBrouillonsNonAffiches()`, devenus
inutiles. 1 test remplacé (plafond+compteur → page 1/page 2 réels), 1 test
existant adapté (`assertSeeHtml('>en cours</td>')` au lieu de
`assertSee('(en cours)')`, le format de cellule ayant changé). pint propre,
280/280 tests, 0 écart de traduction.

## [2026-09-14 09:50] CourrierList et WorkflowQueue : 20 → 10 par page
Fichier(s) : app/Livewire/Backend/CourrierList.php
Fichier(s) : app/Livewire/Backend/WorkflowQueue.php
Pourquoi : demande explicite de l'utilisateur, suite à un malentendu
clarifié ("i was talking about the courier list and research list") — ces
deux listes-ci, pas celle des brouillons en attente (entrée précédente,
laissée telle quelle). Changement mécanique, aucun test existant
n'assumait la valeur 20 (vérifié : aucune assertion de pagination dans
CourrierListTest.php/WorkflowQueueTest.php). pint propre, 280/280 tests.

## [2026-09-14 10:05] Documents en attente : retour au select, détails conservés
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php
Pourquoi : demande explicite de l'utilisateur ("bring it back to the
dropdown select but leave the details") — le tableau du 2026-09-14 (entrée
"10 per table") est abandonné pour l'UI, mais les détails qu'il affichait
(statut OCR, agent pour un Administrateur, date de détection) restent dans
le texte de chaque option plutôt que perdus, et la vraie pagination
(10/page, `->links()` sous le select) reste en place — seul le rendu
(table → select) change, pas la requête/computed côté PHP. Test du statut
OCR en direct ré-adapté au format `(statut)` du select (`assertSee`) au
lieu du format `<td>` du tableau (`assertSeeHtml`). Traductions des
en-têtes de colonnes (Document/Statut OCR/Agent/Détecté le) retirées,
redevenues inutiles. pint propre, 280/280 tests, 0 écart de traduction.

## [2026-09-14 10:15] Recherche : numéro + objet visibles, le reste derrière "Recherche avancée"
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php
Fichier(s) : lang/en.json
Pourquoi : demande explicite de l'utilisateur — sur les 11 filtres du
Module 8, seuls Numéro de référence et Objet restent visibles par défaut ;
les 9 autres (expéditeur, contenu OCR, type, confidentialité, service,
dates, statut, sens) sont repliés derrière un bouton "Recherche avancée"
(`x-data`/`x-show`, purement client — aucune propriété Livewire
supplémentaire nécessaire, l'état replié/déplié n'a pas besoin de
persister côté serveur). Déplié automatiquement si un filtre avancé est
déjà actif au chargement (ex. lien partagé `?statut=archive`) — jamais un
filtre actif cachant ses propres résultats sans explication visible.
Aucun test cassé : Livewire::test()->assertSee() lit le HTML rendu par le
serveur, où les champs repliés restent bien présents (juste masqués en
CSS côté client par x-show), donc indépendant de cet état. pint propre,
280/280 tests, 0 écart de traduction (397 `__()` audités).

## [2026-09-15 09:00] Système de privilèges — modèle de données + retrofit des 3 Policy
Fichier(s) : database/migrations/2026_09_15_090000_create_privileges_table.php
Fichier(s) : database/migrations/2026_09_15_090100_create_privilege_profil_table.php
Fichier(s) : database/migrations/2026_09_15_090200_create_privilege_user_table.php
Fichier(s) : app/Models/Privilege.php (nouveau)
Fichier(s) : app/Models/Profil.php (relation privileges())
Fichier(s) : app/Models/User.php (privilegesDirectes(), hasPrivilege(), privilegesCles())
Fichier(s) : app/Policies/CourrierPolicy.php, CourrierBrouillonPolicy.php, RegleClassementPolicy.php (retrofit complet)
Fichier(s) : app/Policies/PrivilegePolicy.php (nouveau)
Fichier(s) : database/seeders/PrivilegeSeeder.php (nouveau), DatabaseSeeder.php
Fichier(s) : tests/TestCase.php (sème ProfilSeeder+PrivilegeSeeder automatiquement pour tout test RefreshDatabase)
Fichier(s) : app/Livewire/Backend/RegistrationForm.php, resources/views/livewire/frontend/registrationForm.blade.php (aligné sur brouillons.utiliser_tout)
Pourquoi : demande explicite de l'utilisateur — créer des privilèges et les
assigner à un profil et/ou à des utilisateurs précis, sans écrire de code.
Voir DECISIONS.md "Système de privilèges" pour le détail complet (catalogue
de 21 privilèges, garde-fou anti-verrouillage sur privileges.gerer, pourquoi
Tests\TestCase sème automatiquement). 280/280 tests existants passent SANS
AUCUNE modification après le retrofit — preuve que le comportement observable
n'a pas changé. pint propre.

## [2026-09-15 09:15] Système de privilèges — page d'administration
Fichier(s) : app/Livewire/Backend/PrivilegeList.php (nouveau)
Fichier(s) : resources/views/livewire/frontend/privilegeList.blade.php (nouveau)
Fichier(s) : routes/web.php (admin.privileges)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (lien "Privilèges", groupe Administration)
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Admin/PrivilegeListTest.php (nouveau)
Pourquoi : /admin/privileges — créer/modifier/supprimer un privilège,
l'assigner à des profils (cases à cocher) et/ou des utilisateurs individuels.
Même schéma que RegleList.php (formulaire + liste, authorize() à chaque
action pas seulement mount()). 10 nouveaux tests, dont la preuve bout en
bout qu'un utilisateur SANS le bon profil mais avec un privilège assigné
individuellement passe quand même un vrai Policy check. pint propre,
290/290 tests, 0 écart de traduction.

## [2026-09-15 09:30] Privilèges : Administrateur reçoit tout automatiquement
Fichier(s) : database/seeders/PrivilegeSeeder.php
Fichier(s) : app/Policies/CourrierPolicy.php (commentaire archive() mis à jour)
Pourquoi : demande explicite de l'utilisateur ("admin should have all the
privileges") — le seeder ajoute désormais Administrateur à CHAQUE privilège
du catalogue, y compris courriers.archiver (vrai changement de comportement
assumé, voir DECISIONS.md — cette action n'existe de toute façon pas encore
ailleurs dans le code). Re-seedé sur la base dev. 290/290 tests toujours verts.

## [2026-09-15 09:45] Privilèges : assignation en masse à un profil
Fichier(s) : app/Livewire/Backend/PrivilegeList.php (assignerEnMasse())
Fichier(s) : resources/views/livewire/frontend/privilegeList.blade.php (case à cocher par ligne + barre d'action)
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Admin/PrivilegeListTest.php (+2 tests)
Pourquoi : demande explicite de l'utilisateur ("select multiple / bulk
privi at a time to affect... send to a profile") — cocher plusieurs
privilèges dans le tableau, les envoyer tous à un profil choisi en une
seule action, plutôt que de rouvrir chaque privilège un par un. Additif
(syncWithoutDetaching, jamais sync) — n'enlève jamais un privilège déjà
présent sur ce profil mais non coché. 292/292 tests, pint propre, 0 écart
de traduction.

## [2026-09-15 10:00] Privilèges : vue "utilisateurs et leurs privilèges effectifs"
Fichier(s) : app/Livewire/Backend/PrivilegeList.php (utilisateursAvecPrivileges())
Fichier(s) : resources/views/livewire/frontend/privilegeList.blade.php (nouvelle section)
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Admin/PrivilegeListTest.php (+1 test)
Pourquoi : clarifié via AskUserQuestion après un message ambigu ("redo the
users table... should see all permission") — nouvelle section listant
chaque utilisateur avec ses privilèges EFFECTIFS (profil ∪ individuels
combinés, même règle que User::hasPrivilege()), pour vérifier d'un coup
d'œil sans rouvrir chaque privilège un par un. Chargé en un minimum de
requêtes (profil.privileges + privilegesDirectes eager-loadés pour tous
les utilisateurs d'un coup, Règle n°3). 293/293 tests, pint propre, 0
écart de traduction (435 `__()` audités).

## [2026-09-15 10:30] Privilèges : redesign en matrice privilège × profil
Fichier(s) : app/Livewire/Backend/PrivilegeList.php (basculerProfilPrivilege(), privilegesParCategorie(), profilIds retiré)
Fichier(s) : resources/views/livewire/frontend/privilegeList.blade.php (réécrit)
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Admin/PrivilegeListTest.php (test d'assignation par profil migré vers basculerProfilPrivilege(), +1 test bascule aller-retour)
Pourquoi : demande explicite de l'utilisateur ("arrange that design what
thats it ugly check for design online and do it"). Recherche rapide (voir
DECISIONS.md) : le pattern reconnu pour ce type d'écran est une matrice
(rôles en colonnes, permissions en lignes, case cliquable), regroupée par
catégorie — pas "ouvrir une permission, cocher 5 cases, enregistrer".
Remplace le panneau "Assignations" (profils + utilisateurs mélangés) par :
une vraie matrice cliquable (bascule immédiate, sans formulaire à
soumettre) pour les PROFILS, groupée par catégorie (préfixe de la clé) ;
un panneau séparé, plus simple, pour les utilisateurs INDIVIDUELS (une
matrice avec des dizaines d'utilisateurs en colonnes ne serait plus
scannable) ; l'assignation en masse et la vue "qui a quoi" du raffinement
précédent sont conservées, juste réintégrées dans le nouveau tableau.
294/294 tests, pint propre, 0 écart de traduction (433 `__()` audités).

## [2026-09-15 11:00] Privilèges : la matrice cède la place à deux boîtes (choisir/créer un profil)
Fichier(s) : app/Livewire/Backend/PrivilegeList.php (basculerProfilPrivilege() retiré ; selectionnerProfil(), creerProfil(), ajouterPrivilegeAuProfil(), retirerPrivilegeDuProfil() nouveaux)
Fichier(s) : resources/views/livewire/frontend/privilegeList.blade.php (matrice + barre d'assignation en masse retirées ; deux boîtes + sélecteur/créateur de profil ; liste CRUD simple réintroduite dans la carte "Nouveau privilège")
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Admin/PrivilegeListTest.php (tests de matrice/masse remplacés par 5 tests sur les deux boîtes + création de profil)
Pourquoi : retour direct de l'utilisateur sur la matrice de l'entrée
précédente — "no change that i want a simple like two boxes one for all
permission and the other one for affecting to the profile selected or
created". Remplacé par : une rangée de profils cliquables (+ un petit
formulaire pour EN CRÉER un nouveau directement ici, jamais possible avant
— aucune page de gestion des Profil n'existait), puis deux boîtes —
"Tous les privilèges" (pas encore sur le profil choisi) à gauche,
"Privilèges de {profil}" (déjà dessus) à droite — un clic sur une flèche
déplace instantanément un privilège d'une boîte à l'autre, sans
formulaire à soumettre. En retirant le tableau, la liste CRUD (modifier/
supprimer un privilège) avait disparu par erreur — réintégrée en liste
compacte sous le formulaire de création. 295/295 tests, pint propre, 0
écart de traduction (433 `__()` audités).

## [2026-09-15 11:30] Privilèges : formulaires en modales + vue utilisateur tronquée
Fichier(s) : app/Livewire/Backend/PrivilegeList.php (enregistrer()/creerProfil() ferment leur modale via Flux::modal(...)->close() ; voirUtilisateur()/utilisateurSelectionne() nouveaux)
Fichier(s) : resources/views/livewire/frontend/privilegeList.blade.php (formulaire privilège + formulaire profil déplacés dans <flux:modal>, déclenchés par un bouton ; badges de privilèges par utilisateur tronqués à 3 + bouton "+N"/"Voir" ouvrant une modale avec la liste complète)
Fichier(s) : lang/en.json
Pourquoi : demande explicite de l'utilisateur, "reduce the work" — "do the
nuveau privilege as a button displaing a modal and the profile button too
then display th users with thier profile not all like 2 to 3 then either
plus or... showing there are many only and action button ... to assign,
view, delete, modify users". Les formulaires de création restaient
rarement utilisés une fois le catalogue rempli — les sortir du flux
principal (modale) réduit la place occupée en permanence sur la page.
Pour le tableau "Utilisateurs et leurs privilèges", "assign"/"delete"/
"modify" existaient déjà ailleurs sur la page (CRUD du privilège, panneau
d'assignation individuelle) — un seul bouton "Voir" par ligne suffisait
donc pour couvrir la partie manquante ("view"), plutôt que dupliquer 4
actions par ligne.

## [2026-09-15 11:45] Privilèges : page "Profils" séparée de la page "Privilèges"
Fichier(s) : app/Livewire/Backend/ProfilList.php (nouveau — reprend selectionnerProfil()/creerProfil()/ajouterPrivilegeAuProfil()/retirerPrivilegeDuProfil() et les computed profils/profilSelectionne/privilegesDisponibles/privilegesDuProfilSelectionne, retirés de PrivilegeList)
Fichier(s) : resources/views/livewire/frontend/profilList.blade.php (nouveau — sélecteur/créateur de profil + les deux boîtes)
Fichier(s) : app/Livewire/Backend/PrivilegeList.php (section "Privilèges par profil" retirée, ne gère plus que le catalogue + assignation individuelle)
Fichier(s) : resources/views/livewire/frontend/privilegeList.blade.php (section "Privilèges par profil" retirée)
Fichier(s) : routes/web.php (route admin/profils -> admin.profils)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (lien "Profils" ajouté à côté de "Privilèges")
Fichier(s) : tests/Feature/Admin/ProfilListTest.php (nouveau — 7 tests déplacés depuis PrivilegeListTest)
Fichier(s) : tests/Feature/Admin/PrivilegeListTest.php (tests de profil retirés, 1 nouveau test sur voirUtilisateur())
Fichier(s) : lang/en.json (clés devenues orphelines retirées : "Privilèges par profil", "Utilisateurs individuels — :nom", "Privilèges existants")
Pourquoi : demande explicite de l'utilisateur, "seperate the profile page
to privi[leges]" — juste après avoir déplacé les formulaires en modales,
la page restait encore surchargée avec deux responsabilités distinctes
(gérer le CATALOGUE de privilèges vs. les ASSIGNER à un profil). Scindée
en deux pages/composants séparés, même ability Policy (`gerer` sur
`Privilege`) pour les deux. 298/298 tests (suite complète), pint propre,
0 écart de traduction (448 `__()` audités).

## [2026-09-15 12:00] Profils : les deux boîtes visibles par défaut + sélecteur en menu déroulant
Fichier(s) : app/Livewire/Backend/ProfilList.php (privilegesDisponibles() renvoie le catalogue complet quand aucun profil n'est sélectionné, au lieu d'une collection vide)
Fichier(s) : resources/views/livewire/frontend/profilList.blade.php (rangée de boutons profils remplacée par <flux:select wire:model.live="profilSelectionneId"> ; les deux boîtes sont toujours affichées, la droite montre un message vide tant qu'aucun profil n'est choisi)
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Admin/ProfilListTest.php (nouveau test : catalogue complet à gauche + droite vide sans profil sélectionné)
Pourquoi : demande explicite de l'utilisateur, "make the twoxes appear by
default but only the first bax to show all priveleges ... and the other
one empty until we select a profile from a select dropdown". Le
placeholder "Choisissez un profil..." remplaçant entièrement les deux
boîtes obligeait à sélectionner un profil avant même de voir le
catalogue de privilèges disponibles — désormais la boîte de gauche sert
aussi de vue "tout le catalogue" par défaut, et la sélection se fait via
un <flux:select> (plus lisible qu'une rangée de boutons si le nombre de
profils grandit) au lieu d'un clic par bouton de profil. 299/299 tests,
pint propre, 0 écart de traduction (451 `__()` audités).

## [2026-09-15 12:15] Profils : recherche dans chaque boîte + boîtes réduites
Fichier(s) : app/Livewire/Backend/ProfilList.php ($rechercheDisponibles/$rechercheAssignes + filtrer() privé, appliqué dans privilegesDisponibles()/privilegesDuProfilSelectionne())
Fichier(s) : resources/views/livewire/frontend/profilList.blade.php (un <flux:input> de recherche par boîte, débounce 300ms ; hauteur des listes réduite de max-h-96 à max-h-64)
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Admin/ProfilListTest.php (nouveau test : recherche filtre bien chaque boîte indépendamment, sans affecter l'autre)
Pourquoi : demande explicite de l'utilisateur, "the should be a search
function inside the boxes and it should be scrolleble and reduce the
boxes". Chaque boîte a sa PROPRE recherche (deux propriétés distinctes,
pas une recherche globale) car les deux listes ont des contenus et des
usages différents (parcourir tout le catalogue vs. vérifier ce qu'un
profil a déjà) ; le filtre porte sur nom OU clé pour rester utile même
sans connaître le libellé exact. Les listes restaient déjà scrollables
(overflow-y-auto) — réduites à max-h-64 (au lieu de max-h-96) pour que la
page tienne mieux à l'écran maintenant qu'un champ de recherche s'ajoute
au-dessus de chacune. 300/300 tests, pint propre, 0 écart de traduction
(455 `__()` audités).

## [2026-09-15 12:20] Profils : boîtes encore réduites (max-h-48)
Fichier(s) : resources/views/livewire/frontend/profilList.blade.php (max-h-64 -> max-h-48 sur les deux listes)
Pourquoi : demande explicite de l'utilisateur, "reduce the boxes hieght
and let the priveleges inside be scrollable" — les listes étaient déjà
scrollables (overflow-y-auto, inchangé), seule la hauteur visible est
réduite. 300/300 tests, pint propre, 0 écart de traduction (455 `__()`
audités, mêmes clés qu'avant — changement de classe Tailwind uniquement).

## [2026-09-15 12:30] Profils : sélection multiple + flèches centrales
Fichier(s) : app/Livewire/Backend/ProfilList.php ($selectionDisponibles/$selectionAssignes, updatedProfilSelectionneId(), ajouterSelectionAuProfil(), retirerSelectionDuProfil())
Fichier(s) : resources/views/livewire/frontend/profilList.blade.php (case à cocher par ligne dans les deux boîtes ; colonne centrale avec deux flèches qui déplacent toute la sélection cochée)
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Admin/ProfilListTest.php (3 nouveaux tests : flèche centrale ajoute/retire toute la sélection d'un coup, changer de profil vide les sélections)
Pourquoi : demande explicite de l'utilisateur, "add fleches that we can
select in bulk then send them or one by [one] in the middle of the two
boxes". Chaque case à cocher alimente un tableau d'ids (Règle n°2 :
primitifs uniquement) ; une flèche centrale appelle
`Profil::privileges()->syncWithoutDetaching()`/`->detach()` avec TOUS les
ids cochés en un seul appel (pas une boucle de N appels). Les flèches par
ligne existantes restent inchangées pour déplacer un seul privilège à la
fois ("or one by one"). `updatedProfilSelectionneId()` vide les deux
sélections dès qu'on change de profil — les ids cochés ne correspondent
plus forcément aux bonnes listes affichées sinon. Note : ceci diffère du
"bulk-select + assigner en masse" explicitement écarté plus tôt dans le
projet (voir DECISIONS.md/mémoire systeme_privileges.md) — celui-là
visait à assigner UN privilège à PLUSIEURS profils/utilisateurs à la
fois depuis un formulaire séparé ; celui-ci déplace PLUSIEURS privilèges
vers/depuis LE profil déjà sélectionné dans la même paire de boîtes,
demandé explicitement par l'utilisateur cette fois. 303/303 tests, pint
propre, 0 écart de traduction (457 `__()` audités).

## [2026-09-15 12:40] Profils : correctif — build front obsolète + binding des cases à cocher
Fichier(s) : resources/views/livewire/frontend/profilList.blade.php (checkboxes déplacées sous <flux:checkbox.group wire:model.live="..."> au lieu d'un wire:model direct par case)
Fichier(s) : public/build/** (npm run build)
Pourquoi : retour utilisateur, "side by side and the fleshes are not
working" (boîtes pas côte à côte, flèches centrales sans effet). Deux
causes distinctes trouvées : (1) `public/build` datait du 2026-09-10,
soit AVANT tous les changements de cette session — `grid-cols-[1fr_auto_1fr]`
(et bien d'autres classes ajoutées depuis) n'existaient pas dans le CSS
compilé, d'où l'absence de mise en page côte à côte (voir
ARCHITECTURE-ESSENTIALS.md/CLAUDE.md : Vite ne rebuild pas tout seul,
`npm run build` est manuel) ; (2) les cases à cocher utilisaient
`wire:model="selectionDisponibles"`/`"selectionAssignes"` directement sur
chaque `<flux:checkbox>`, hors de tout `<flux:checkbox.group>` — le SEUL
pattern déjà prouvé fonctionner dans ce projet pour un binding de tableau
(voir `privilegeList.blade.php`, panneau "Utilisateurs individuels") ;
sans le wrapper, les ids cochés n'atteignaient jamais les propriétés
PHP, donc `ajouterSelectionAuProfil()`/`retirerSelectionDuProfil()`
recevaient toujours des sélections vides. Corrigé en enveloppant chaque
`<ul>` dans son `<flux:checkbox.group>`. 303/303 tests, pint propre, 0
écart de traduction (457 `__()` audités, inchangés).

## [2026-09-15 12:50] Privilèges : catalogue en table + bouton "+" ouvrant les détails
Fichier(s) : app/Livewire/Backend/PrivilegeList.php (privileges() ajoute withCount('profils') ; privilegeSelectionneId/privilegeSelectionne()/voirPrivilege() nouveaux)
Fichier(s) : resources/views/livewire/frontend/privilegeList.blade.php (liste <ul> du catalogue remplacée par une <table> — même style que "Utilisateurs et leurs privilèges" plus bas — avec un bouton "+" par ligne ouvrant une modale privilege-details : nom, clé, description, compteurs profils/utilisateurs, raccourci "Modifier")
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Admin/PrivilegeListTest.php (nouveau test : voirPrivilege() expose bien le détail + les compteurs)
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite de l'utilisateur, "priv too table plus
button priv showin modal" (clarifiée via AskUserQuestion : catalogue de
/admin/privileges, pas la boîte "Tous les privilèges" de /admin/profils).
Même intention que le "Voir" déjà ajouté sur le tableau "Utilisateurs et
leurs privilèges" — un privilège qui a beaucoup de profils/utilisateurs
n'affichait avant que son nom/sa clé dans la liste, sans moyen rapide de
voir sa description ni combien de profils/utilisateurs l'utilisent sans
ouvrir la modale d'édition complète. 304/304 tests, pint propre, 0 écart
de traduction (464 `__()` audités — 3 clés ajoutées manuellement,
":aria-label"/interpolation `:n` restent des angles morts connus du
script d'audit, voir memory).

## [2026-09-15 13:05] Nouvelle page /admin/utilisateurs — séparée de /admin/privileges
Fichier(s) : app/Livewire/Backend/UserList.php (nouveau — reprend le design "deux boîtes" de ProfilList mais pour User::privilegesDirectes() ; utilisateurSelectionneId/rechercheDisponibles/rechercheAssignes/selectionDisponibles/selectionAssignes/ajouterPrivilegeAUtilisateur()/retirerPrivilegeDeLutilisateur()/ajouterSelectionAUtilisateur()/retirerSelectionDeLutilisateur() ; utilisateursAvecPrivileges()/utilisateurApercu()/voirUtilisateur() déplacés depuis PrivilegeList)
Fichier(s) : resources/views/livewire/frontend/userList.blade.php (nouveau — deux boîtes + vue d'ensemble "Utilisateurs et leurs privilèges" + modale utilisateur-apercu, déplacées depuis privilegeList.blade.php)
Fichier(s) : app/Livewire/Backend/PrivilegeList.php (userIds/utilisateurSelectionneId/utilisateurs()/utilisateursAvecPrivileges()/utilisateurSelectionne()/voirUtilisateur()/enregistrerAssignations() retirés ; ne gère plus que le catalogue)
Fichier(s) : resources/views/livewire/frontend/privilegeList.blade.php (panneau "Utilisateurs individuels" dans la modale + section "Utilisateurs et leurs privilèges" + sa modale retirés)
Fichier(s) : routes/web.php (route admin/utilisateurs -> admin.utilisateurs)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (lien "Utilisateurs" ajouté à côté de "Privilèges"/"Profils")
Fichier(s) : tests/Feature/Admin/UserListTest.php (nouveau — 12 tests, dont 3 déplacés/adaptés depuis PrivilegeListTest)
Fichier(s) : tests/Feature/Admin/PrivilegeListTest.php (3 tests d'assignation/vue utilisateur retirés — désormais dans UserListTest)
Fichier(s) : lang/en.json (nouvelles clés + 4 clés orphelines retirées : "Utilisateurs individuels", "Un utilisateur a ce privilège si...", "Enregistrer les assignations", "Assignations mises à jour.")
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite de l'utilisateur, "seoerate the user
manage[m]ent from priv" — même logique que la séparation de "Profils"
plus tôt le même jour : la page /admin/privileges portait encore deux
responsabilités distinctes (catalogue de privilèges ET gestion des
privilèges individuels par utilisateur + leur vue d'ensemble). L'ancien
mécanisme d'assignation individuelle (case à cocher par UTILISATEUR,
dans la modale d'un PRIVILÈGE) est remplacé par le même design "deux
boîtes" que /admin/profils (choisir un utilisateur, voir/déplacer ses
privilèges), pour la cohérence entre les trois pages d'administration du
système de privilèges. 313/313 tests, pint propre, 0 écart de traduction
(476 `__()` audités).

## [2026-09-15 13:15] Module 1 — Priorité/Confidentialité remontées, Sens en select
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (Priorité/Confidentialité déplacées juste après le Sens, en haut du formulaire ; section "Classement" en bas supprimée ; Sens converti de flux:radio.group en flux:select)
Fichier(s) : resources/views/livewire/frontend/editForm.blade.php (même changement, pour rester cohérent avec RegistrationForm)
Pourquoi : demande explicite de l'utilisateur, "bring the confidential
and prirority up that means courriers entrant and sortant option shoulbe
now a select dropdownfield". Priorité et Confidentialité sont désormais
saisies dès le début du formulaire, avant le détail du courrier — cette
classification conditionne d'ailleurs déjà l'indice affiché sous le
champ Objet plus bas (courrier confidentiel = objet générique attendu),
donc la connaître en premier est plus logique pour l'agent qui remplit
le formulaire. Le Sens (Entrant/Sortant), remonté au même niveau, est
passé de `flux:radio.group` à `flux:select` pour rester visuellement
cohérent avec les deux selects juste à côté. Aucun changement de
validation (le Form Object `CourrierForm` n'a pas bougé) ni de nom de
propriété (`form.sens`/`form.priorite`/`form.confidentialite`
inchangés) — seul le contrôle HTML et la position dans le formulaire
changent, les tests existants (`Livewire::test()->set('form.sens', ...)`)
n'ont eu aucune modification à faire. 313/313 tests, pint propre, 0
écart de traduction (474 `__()` audités — en baisse car deux blocs
dupliqués de traductions déjà existantes ont fusionné en un seul,
aucune nouvelle chaîne).

## [2026-09-15 13:30] Synchronisation documentaire avec SRS-GEC.pdf (aucun code touché)
Fichier(s) : PRD.md (section 1, points 1/2/3/4/8 mis à jour : tampon abandonné, service retiré du formulaire agent, confidentialité numérique hiérarchique, dossiers de classement, sous-départements)
Fichier(s) : specifications-modules-GEC.md (réécriture complète, module par module, pour refléter le nouveau document client SRS-GEC.pdf — DGA/ADJ DGA, 3 sous-statuts de transfert, dossiers de classement + permissions par dossier, sous-type Contentieux, confidentialité numérique, décharge, référence de localisation physique, hiérarchie de sous-départements, section transversale "Configuration administrateur — listes de référence")
Fichier(s) : DECISIONS.md (nouvelle entrée "Synchronisation avec le nouveau document SRS-GEC.pdf" — liste ce qui était déjà couvert, ce qui contredit le code actuel, et ce qui est net nouveau)
Pourquoi : l'utilisateur a transmis un nouveau document de spécifications
(`SRS-GEC.pdf`) et a explicitement demandé une revue ("check new
update") puis une mise à jour de la documentation AVANT tout code ("yes
update first") — cohérent avec la contrainte du projet selon laquelle
toute décision d'architecture reste validée manuellement, jamais
déléguée à l'agent. Cette entrée ne modifie aucun fichier de code, migration
ou test — uniquement la documentation de référence, pour que la suite du
travail (à prioriser avec l'utilisateur) parte d'une base à jour.

## [2026-09-15 14:00] Module 1 — le service n'est plus saisi par l'agent pour un courrier entrant
Fichier(s) : database/migrations/2026_09_15_140000_make_service_id_nullable_on_courriers_table.php (nouveau — courriers.service_id nullable)
Fichier(s) : database/migrations/2026_09_15_140100_drop_service_id_from_numero_sequences_table.php (nouveau — compteur numero_sequences global par année, plus par service, idempotent par étape)
Fichier(s) : app/Services/NumeroReferenceGenerator.php (genererPour(Service) → generer(), format GEC-{annee}-{sequence} au lieu de GEC-{annee}-{code}-{sequence})
Fichier(s) : app/Models/Courrier.php (segmentClassement() + constante SEGMENT_SERVICE_EN_ATTENTE — segment de stockage "_en_attente" tant que service_id est null)
Fichier(s) : app/Livewire/Backend/Forms/CourrierForm.php (service_id requis seulement si sens=sortant)
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (enregistrer() : service_id forcé à null pour un entrant, sauf sinistre routé directement via ClassificationService avec repli sur en_attente_validation_dga si la classification ne résout rien ; finaliserBrouillon() utilise segmentClassement())
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (champ Service masqué pour sens=entrant, visible seulement pour sortant ; sens en wire:model.live)
Fichier(s) : app/Livewire/Backend/EditForm.php (service_id remis à null seulement si le Sens bascule de sortant vers entrant pendant la modification, jamais si le courrier était déjà entrant)
Fichier(s) : resources/views/livewire/frontend/editForm.blade.php (même masquage conditionnel que RegistrationForm)
Fichier(s) : app/Services/PieceJointeService.php, app/Livewire/Backend/ScanForm.php (chemin de stockage via segmentClassement() au lieu de $courrier->service->code)
Fichier(s) : app/Services/WorkflowService.php (validerService() : commentaire d'historique comparé à service_propose_id, pas service_id (toujours null avant validation désormais) ; déplace le document principal ET les pièces jointes hors de "_en_attente" vers le vrai dossier service après validation, non bloquant en cas d'échec S3)
Fichier(s) : app/Policies/CourrierPolicy.php (service null-safe dans view()/affecter()/valider() — un courrier entrant en attente de DGA n'a pas encore de service)
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php, tests/Feature/Services/WorkflowServiceTest.php
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite de l'utilisateur ("start with module 1 with
the contradiction"), suite à la synchronisation avec SRS-GEC.pdf
(DECISIONS.md, entrée précédente) — le champ Service devait être retiré
du formulaire d'enregistrement pour un courrier entrant, contrairement au
circuit DGA du 2026-09-08 qui ne faisait que permettre au DGA de CHANGER
un service déjà saisi par l'agent. Portée confirmée avec l'utilisateur
(AskUserQuestion) : entrant uniquement, le sortant garde son circuit
actuel (service toujours requis à la saisie). Un second blocage
architectural a été découvert en creusant l'implémentation — le format du
numéro de référence (GEC-{année}-{code service}-{séquence}) et le chemin
de stockage (courriers/{année}/{service}/...) dépendaient tous les deux du
service, généré immédiatement à l'enregistrement, avant même que le DGA
n'intervienne. Résolu (option confirmée avec l'utilisateur) en rendant le
numéro de référence global par année (GEC-{année}-{séquence}), et en
introduisant un segment de stockage provisoire "_en_attente"
(Courrier::segmentClassement()) déplacé vers le vrai dossier service par
WorkflowService::validerService() une fois le DGA passé — document
principal ET pièces jointes. Un sinistre entrant continue d'être routé
directement (sans validation DGA) via la règle de classement Module 3
déjà existante ("sinistre" → DSIN), désormais appelée de façon
SYNCHRONE à l'enregistrement (au lieu de compter sur le pré-remplissage
du formulaire, qui n'existe plus) ; si elle ne résout aucun service
(règle absente/mal configurée), le courrier retombe sur la validation DGA
plutôt que de rester "enregistre" sans service assigné (jamais de
courrier orphelin, Module 6). 318/318 tests, pint propre, 0 écart de
traduction (476 `__()` audités).

## [2026-09-15 14:30] Module 1/4 — le transfert réceptionniste → DGA suit 3 sous-statuts explicites
Fichier(s) : database/migrations/2026_09_15_150000_split_en_attente_validation_dga_into_transfert_substatuts.php (nouveau — enum courriers.statut : en_attente_validation_dga scindé en en_attente_de_transfert + en_cours_de_transfert, migration de données en 3 étapes pour zéro perte sur les lignes existantes)
Fichier(s) : app/Services/WorkflowService.php (TRANSITIONS mis à jour ; nouvelle méthode transferer() — réceptionniste : en_attente_de_transfert → en_cours_de_transfert ; validerService() : commentaire d'historique reformulé, plus de renommage de TRANSITIONS que le strict nécessaire)
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (statut initial d'un entrant non-sinistre : en_attente_de_transfert au lieu de en_attente_validation_dga)
Fichier(s) : app/Policies/CourrierPolicy.php (nouvelle ability transferer() — transferer_tout/transferer_propre, même forme que update() ; voir_dga scopé sur en_cours_de_transfert au lieu de en_attente_validation_dga)
Fichier(s) : database/seeders/PrivilegeSeeder.php (courriers.transferer_tout, courriers.transferer_propre — ce dernier assigné à Agent par défaut)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (peutTransferer computed, action transferer(), présélection du service DGA scopée sur en_cours_de_transfert)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (panneau "Transférer" pour la réceptionniste, message "en cours de transfert" en attendant le DGA, panneau DGA déclenché par en_cours_de_transfert ; Service rendu null-safe à 3 endroits — un courrier pas encore transféré n'en a pas)
Fichier(s) : app/Livewire/Backend/WorkflowQueue.php, app/Livewire/Backend/CourrierList.php (scoping DGA sur en_cours_de_transfert au lieu de en_attente_validation_dga)
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php, resources/views/livewire/frontend/workflowQueue.blade.php, resources/views/pdf/bordereau.blade.php (Service rendu null-safe — bug latent introduit par le changement précédent, découvert et corrigé ici)
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Courriers/CircuitCourrierTest.php, tests/Feature/Courriers/WorkflowQueueTest.php, tests/Feature/Services/WorkflowServiceTest.php, tests/Feature/Courriers/RegistrationFormTest.php
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite de l'utilisateur ("ok now the new change of
module 1 impliment it"), deuxième contradiction Module 1/4 identifiée
lors de la synchronisation SRS-GEC.pdf (DECISIONS.md) — le transfert
réceptionniste → DGA devait suivre 3 sous-statuts (en attente / en cours
/ transféré) au lieu d'un seul statut atteint automatiquement à
l'enregistrement. "Transféré" correspond au statut 'enregistre' déjà
existant (pas de renommage, décision du 2026-09-08 maintenue) ; seule
l'étape intermédiaire ("en attente" vs "en cours") est désormais réelle,
déclenchée par un clic explicite "Transférer" de la réceptionniste. En
corrigeant les vues qui affichaient `$courrier->service->nom`, plusieurs
crashs latents ont été trouvés et corrigés (introduits par le
retrofit précédent qui a rendu `service_id` nullable, mais pas
immédiatement visibles tant qu'aucun test/usage réel n'affichait un
courrier en attente de transfert dans ces vues). Volontairement PAS fait
dans cette entrée : verrouiller l'édition d'un courrier une fois
"Transféré" (le spec le demande, mais la portée exacte — bloquer
`update()` pour TOUS les statuts ≥ enregistre casserait l'édition normale
d'un courrier sortant, qui démarre déjà à ce statut — n'a pas été
tranchée avec l'utilisateur, voir DECISIONS.md) ; une page dédiée "mes
courriers par statut" (les "onglets" du document) — la page de recherche
existante (CourrierList) couvre déjà le besoin via son filtre statut,
question posée à l'utilisateur en cours de route. 322/322 tests, pint
propre, 0 écart de traduction (482 `__()` audités).

## [2026-09-15 15:00] Module 1/4 — transfert en masse depuis "Rechercher un courrier"
Fichier(s) : app/Livewire/Backend/CourrierList.php ($selection primitif ; updated() vide la sélection sur tout changement de filtre/page ; transfererSelection() — revérifie statut ET Policy par id, jamais fait confiance à la sélection postée)
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (case à cocher par ligne éligible dans un <flux:checkbox.group>, bouton "Transférer la sélection" avec compteur live + wire:confirm, colonne Sélection conditionnelle ; 2 options de statut manquantes ajoutées au filtre : en_attente_de_transfert/en_cours_de_transfert)
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Courriers/CourrierListTest.php (3 nouveaux tests : transfert groupé réussi, courrier d'un autre agent ignoré, courrier déjà transféré ignoré)
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite de l'utilisateur ("where can i see all
receptionist register doc enttand and with buttons for select bulk and
transfere to a supervisor"), suite directe de l'entrée précédente (3
sous-statuts de transfert) — répond aussi à la question posée dans cette
même entrée sur la page à utiliser : `CourrierList` ("Rechercher un
courrier"), déjà accessible à l'Agent et déjà scopée à ses propres
courriers, plutôt qu'une nouvelle page dédiée. Réutilise le pattern
`<flux:checkbox.group>` déjà éprouvé (voir memory livewire_flux_gotchas)
plutôt que de répéter l'erreur d'un `wire:model` direct sur une case
isolée. La colonne "Sélection" et le bouton groupé ne s'affichent QUE
s'il existe au moins une ligne visible que l'utilisateur a le droit de
transférer (`CourrierPolicy::transferer()`) — jamais une case à cocher
inerte pour un courrier qu'on ne pourrait de toute façon pas transférer.
325/325 tests, pint propre, 0 écart de traduction (488 `__()` audités).

## [2026-09-15 15:30] Module 1/4 — retour en arrière du transfert en masse sur "Rechercher un courrier", remplacé par une page dédiée "Mes courriers"
Fichier(s) : app/Livewire/Backend/CourrierList.php ($selection, updated(), transfererSelection() retirés — retour à l'état d'avant l'entrée précédente)
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (colonne Sélection, checkbox.group et bouton de transfert retirés ; les 2 options de statut ajoutées au filtre restent, elles sont indépendantes du transfert en masse)
Fichier(s) : tests/Feature/Courriers/CourrierListTest.php (3 tests de transfert en masse retirés, retour à 16/16)
Fichier(s) : app/Livewire/Backend/MesCourriers.php (nouveau — page dédiée réceptionniste : 3 onglets = les 3 sous-statuts du document, scopée aux courriers ENTRANT créés par l'utilisateur courant ; transfert en masse déplacé ici, uniquement sur l'onglet "en_attente_de_transfert")
Fichier(s) : resources/views/livewire/frontend/mesCourriers.blade.php (nouveau)
Fichier(s) : routes/web.php (route courriers/mes-courriers -> courriers.mes-courriers, placée avant la route générique courriers/{courrierId})
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (lien "Mes courriers" entre "Enregistrer un courrier" et "Courriers à traiter", gardé par @can('create', Courrier::class))
Fichier(s) : tests/Feature/Courriers/MesCourriersTest.php (nouveau — 8 tests : scoping par agent/sens/onglet, transfert en masse nominal + 2 cas ignorés, reset de sélection au changement d'onglet, accès refusé)
Fichier(s) : lang/en.json (6 nouvelles clés propres à cette page ; les clés de transfert en masse de l'entrée précédente sont réutilisées telles quelles, pas orphelines)
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite de l'utilisateur reçue en cours de la tâche précédente, "new page for receptionist" — annule le choix de réutiliser `CourrierList` fait dans l'entrée du 2026-09-15 15:00. `WorkflowQueue` reste hors de portée pour l'Agent (aucun accès), et `CourrierList` mélangeait recherche multi-critères générale et geste de transfert quotidien de la réceptionniste ; une page dédiée avec un onglet par sous-statut correspond plus directement à la structure "en attente / en cours / transféré" du document SRS-GEC.pdf que le filtre statut de la recherche générale. 330/330 tests, pint propre, 0 écart de traduction (503 `__()` audités).

## [2026-09-15 16:15] Module 1/4 — la réceptionniste choisit QUI parmi ses destinataires autorisés
Fichier(s) : database/migrations/2026_09_15_160000_create_destinataires_transfert_table.php (nouveau — pivot agent_id/destinataire_id, PAS dérivée d'un profil/privilège, curatée par l'administrateur PAR AGENT)
Fichier(s) : database/migrations/2026_09_15_160100_add_destinataire_transfert_id_to_courriers_table.php (nouveau — nullable, null = comportement d'avant pour les courriers déjà transférés)
Fichier(s) : app/Models/User.php (destinatairesTransfert() — belongsToMany self-référençant)
Fichier(s) : app/Models/Courrier.php (destinataireTransfert() ; destinataire_transfert_id ajouté au $fillable — bug trouvé en testant : update() l'ignorait silencieusement sans lever d'exception)
Fichier(s) : app/Services/WorkflowService.php (transferer() — signature +User $destinataire, persiste destinataire_transfert_id, historique nomme le destinataire)
Fichier(s) : app/Policies/CourrierPolicy.php (view() branche voir_dga et validerService() restreintes au destinataire choisi ; destinataire_transfert_id === null reste ouvert à tout DGA-privilégié, pour ne pas invalider les courriers transférés avant ce changement)
Fichier(s) : app/Livewire/Backend/WorkflowQueue.php, app/Livewire/Backend/CourrierList.php (même restriction sur le scoping DGA des requêtes)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (destinatairesTransfert computed ; transferer() prend le choix de la modale, revérifié contre Auth::user()->destinatairesTransfert() avant d'agir)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (bouton "Transférer" ouvre une modale flux:radio.group au lieu d'un submit direct)
Fichier(s) : app/Livewire/Backend/MesCourriers.php (même modale pour le transfert en masse — un seul destinataire pour toute la sélection)
Fichier(s) : resources/views/livewire/frontend/mesCourriers.blade.php
Fichier(s) : app/Livewire/Backend/UserList.php (deuxième boîte "deux boîtes" sur /admin/utilisateurs — destinatairesDisponibles/destinatairesDeLutilisateurSelectionne/ajouterDestinataireAUtilisateur/retirerDestinataireDeLutilisateur/ajouterSelectionDestinatairesAUtilisateur/retirerSelectionDestinatairesDeLutilisateur, même mécanique que la boîte des privilèges existante)
Fichier(s) : resources/views/livewire/frontend/userList.blade.php (section "Destinataires de transfert autorisés")
Fichier(s) : lang/en.json (nouvelles clés ; "Transférer au DGA/ADJ DGA", "Courrier transféré au DGA/ADJ DGA.", "Transférer les courriers sélectionnés au DGA/ADJ DGA ?", ":n courrier(s) transféré(s) au DGA/ADJ DGA." retirées, orphelines depuis ce changement)
Fichier(s) : tests/Feature/Services/WorkflowServiceTest.php, tests/Feature/Courriers/CircuitCourrierTest.php, tests/Feature/Courriers/MesCourriersTest.php (adaptés à la nouvelle signature + 2 nouveaux tests : destinataire hors liste autorisée refusé, côté ShowCourrier ET côté transfert en masse)
Fichier(s) : tests/Feature/Admin/UserListTest.php (3 nouveaux tests pour la boîte destinataires : ajout/retrait individuel, flèches centrales, auto-exclusion de l'utilisateur sélectionné)
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite de l'utilisateur, "she should have the possibility to chose the user cause there can be the dga itself and adj dga or another supervisor to send" — clarifiée via AskUserQuestion (3 questions) : la liste des destinataires proposés est curatée par l'administrateur PAR AGENT ("those account that the admin gave her access to see"), sans restriction de profil/privilège sur qui peut y figurer ; une fois choisi, le courrier devient visible SEULEMENT à ce destinataire (pas à tout utilisateur DGA-privilégié comme avant) ; la modale s'applique au transfert individuel (ShowCourrier) ET au transfert en masse (MesCourriers). Gérée sur la page /admin/utilisateurs existante plutôt qu'une nouvelle page, même pattern "deux boîtes" que l'assignation de privilèges individuels juste au-dessus. 335/335 tests, pint propre, 0 écart de traduction (524 `__()` audités).

## [2026-09-15 17:00] Module 1/3 — flux dédié "Courrier confidentiel" (jamais scanné, jamais d'OCR)
Fichier(s) : app/Livewire/Backend/RegistrationFormConfidentiel.php (nouveau — formulaire minimal SANS champ d'upload (pas de WithFileUploads) : nom sur l'enveloppe, date de réception, niveau de confidentialité, destinataire choisi parmi Auth::user()->destinatairesTransfert() ; crée directement statut='enregistre', service_id=null, fichier_path/texte_ocr=null)
Fichier(s) : resources/views/livewire/frontend/registrationFormConfidentiel.blade.php (nouveau)
Fichier(s) : app/Http/Controllers/CourrierAccuseReceptionController.php (nouveau — PDF minimal dédié, distinct du bordereau général : référence, date/heure d'enregistrement, nom sur l'enveloppe, mention Confidentiel/Très confidentiel, agent ayant enregistré ; 404 si le courrier n'est pas confidentiel)
Fichier(s) : resources/views/pdf/accuse-reception.blade.php (nouveau)
Fichier(s) : app/Livewire/Backend/ScanForm.php (garde-fou numeriser() : refuse de scanner un courrier dont confidentialite != 'normale', même si quelqu'un tente ce point d'entrée directement ; $estConfidentiel reflété côté vue)
Fichier(s) : resources/views/livewire/frontend/scanForm.blade.php (callout "Courrier confidentiel" quand $estConfidentiel, formulaire de scan masqué)
Fichier(s) : routes/web.php (courriers/confidentiel -> courriers.confidentiel ; courriers/{courrier}/accuse-reception -> courriers.accuse-reception)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (lien "Courrier confidentiel" à côté d'"Enregistrer un courrier", même privilège courriers.creer)
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Courriers/RegistrationFormConfidentielTest.php (nouveau — 5 tests : enregistrement nominal sans fichier_path/texte_ocr, destinataire hors liste autorisée refusé, nom sur enveloppe obligatoire, accès refusé sans privilège, garde-fou ScanForm sur un courrier confidentiel déjà enregistré)
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite de l'utilisateur ("impliment number 3"), suite à la revue du SRS-GEC.pdf qui avait trouvé ce point non implémenté — specifications-modules-GEC.md, Module 1 "Cas particulier : courrier confidentiel" et Module 3 ("le champ confidentiel désactive tout le pipeline OCR/extraction/classification") : jusqu'ici, un courrier marqué confidentiel passait par le même flux "scan d'abord" que n'importe quel autre (RegistrationForm ne faisait qu'imposer un objet générique APRÈS coup, une fois l'OCR déjà exécuté sur le brouillon). Composant séparé plutôt qu'une branche conditionnelle dans RegistrationForm : le spec le décrit explicitement comme un "Processus différent", et l'absence même du mécanisme d'upload (pas de WithFileUploads) garantit qu'aucun chemin ne permet d'y attacher un fichier par erreur, plutôt qu'une simple validation contournable. Réutilise `destinatairesTransfert()` (voir l'entrée précédente) plutôt qu'un nouveau concept : "à qui l'agent a le droit d'envoyer un courrier" est la même question pour le transfert différé (courrier normal) et l'envoi immédiat (courrier confidentiel). Statut mis directement à 'enregistre' (pas de circuit "en attente/en cours de transfert") : le spec dit "transmis directement", il n'y a pas d'étape de validation DGA du service puisqu'aucune classification n'a lieu. 340/340 tests, pint propre, 0 écart de traduction (548 `__()` audités).

## [2026-09-16 09:00] Module 3 — nouveaux privilèges pour les dossiers de classement (préparation)
Fichier(s) : database/seeders/PrivilegeSeeder.php (dossiers_classement.creer — Responsable de service + Collaborateur par défaut ; dossiers_classement.gerer_tout — Administrateur uniquement)
Pourquoi : demande explicite de l'utilisateur ("first write the new permissions"), première étape du chantier "Dossiers de classement" (Module 3, specifications-modules-GEC.md — item net-nouveau de la synchronisation SRS-GEC.pdf, voir memory srs_update_2026_09_15.md) avant de construire le modèle/l'UI eux-mêmes. Deux privilèges seulement, pas un par action CRUD : "créer" est large (chef de service OU collaborateurs, pas seulement un administrateur, texte du spec) ; l'accès à UN dossier précis (qui peut le VOIR) n'est volontairement PAS un privilège — c'est une donnée assignée par dossier par son créateur (à venir : table dossier_classement_user), pas par profil/utilisateur global comme le reste du catalogue ; seul le garde-fou administrateur ("gerer_tout", même esprit que courriers.modifier_tout vs modifier_propre) est un privilège. Seedé sur la base dev (MySQL réel) et vérifié : dossiers_classement.creer -> [Collaborateur, Responsable de service, Administrateur], dossiers_classement.gerer_tout -> [Administrateur]. 340/340 tests (déjà couverts par le seed automatique de Tests\TestCase, aucun test dédié pour cette entrée — le modèle/la Policy qui les consommeront n'existent pas encore), pint propre.

## [2026-09-16 10:30] Module 10 — tableau de bord réel (maquette GPT fournie par l'utilisateur)
Fichier(s) : app/Livewire/Backend/Dashboard.php (nouveau — 4 cartes de statistiques + "Derniers courriers enregistrés" + "Tâches du jour", toutes scopées au périmètre de visibilité de l'utilisateur, même logique dupliquée que CourrierList::resultats())
Fichier(s) : resources/views/livewire/frontend/dashboard.blade.php (nouveau)
Fichier(s) : database/seeders/PrivilegeSeeder.php (dashboard.taches_du_jour — Collaborateur par défaut, Responsable de service assignable au cas par cas via /admin/utilisateurs)
Fichier(s) : routes/web.php (dashboard : Route::view -> Route::livewire(Dashboard::class), même nom de route)
Fichier(s) : resources/views/dashboard.blade.php (supprimé — placeholder statique du starter-kit, remplacé par le composant ci-dessus)
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/DashboardTest.php (nouveau — 8 tests : accès, périmètre par profil sur les statistiques, delta vs hier, courriers urgents scopé au statut actif + priorité urgente, tâches du jour scopées au Collaborateur, absence du widget sans le privilège, actions rapides filtrées par privilège)
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite de l'utilisateur, maquette faite avec GPT partagée en capture d'écran — Module 10 (specifications-modules-GEC.md) n'avait jamais été construit (placeholder du starter-kit inchangé depuis le début du projet). Principe validé avec l'utilisateur : UNE seule mise en page pour tous les profils, le CONTENU est filtré par privilège dans la vue (même mécanisme que la sidebar, @can), pas un tableau de bord différent codé en dur par profil. Le formulaire "tout en une page" de la maquette est volontairement resté un encart "Bientôt disponible" (demande explicite : "you will not add this ... do it but it should be like comming soon message") — refonte séparée de RegistrationForm, hors périmètre. "Tâches du jour" a été clarifié en cours de route ("and tache du will be for collaborateur and responsable service sometimes but mainly for collaborateur so admin will give them that privileges") : PAS un placeholder comme Notifications/Calendrier — un aperçu réel de la file d'attente du jour (même périmètre que WorkflowQueue pour Collaborateur/Responsable), gardé derrière un privilège dédié. Couleurs des cartes/actions alignées sur la maquette (bleu/vert/ambre/rouge) sur demande explicite ("the design colors and all the rest too"). 346/346 tests, pint propre, 0 écart de traduction (584 `__()` audités à cette étape).

## [2026-09-16 11:15] Module 1/4/8/10 — navigation (navbar desktop + sidebar restructurée) alignée sur la maquette
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (nouvelle navbar desktop persistante — recherche ?q=..., notifications vides, aide, profil ; groupe sidebar "Courrier" étendu — Courrier entrant/sortant, Courriers enregistrés, Courriers incomplets ; x-desktop-user-menu retiré du bas de la sidebar, remplacé par le profil de la navbar)
Fichier(s) : resources/views/partials/user-menu-items.blade.php (nouveau — contenu du menu profil partagé entre navbar mobile et navbar desktop, extrait pour ne pas dupliquer une 3e fois)
Fichier(s) : app/Livewire/Backend/CourrierList.php ($q — recherche combinée numero/objet/expediteur_nom/expediteur_organisation, alimentée par la navbar via ?q=...)
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (bandeau "Résultats pour « :q »" avec bouton Effacer quand la recherche vient de la navbar)
Fichier(s) : app/Livewire/Backend/CourriersEnregistres.php (nouveau — "Courriers enregistrés" : mêmes 3 onglets que MesCourriers (en attente/en cours/transféré), mais à l'échelle du périmètre de visibilité de l'utilisateur — pas limité à ses propres courriers)
Fichier(s) : resources/views/livewire/frontend/courriersEnregistres.blade.php (nouveau)
Fichier(s) : routes/web.php (courriers/enregistres -> courriers.enregistres, placée avant la route générique)
Fichier(s) : lang/en.json
Fichier(s) : tests/Feature/Courriers/CourriersEnregistresTest.php (nouveau — 6 tests : périmètre par profil, entrant uniquement, onglets)
Fichier(s) : tests/Feature/Courriers/CourrierListTest.php (nouveau test pour $q)
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite de l'utilisateur ("yes the same design as in the image don't forget navbar and all the rest even the sidebar"), suite du tableau de bord — la maquette montrait aussi une barre de navigation desktop (recherche/notifications/aide/profil) qui n'existait pas (seule une version mobile existait, cachée en desktop) et une sidebar réorganisée. Trois nouveaux liens de sidebar clarifiés via AskUserQuestion avant de toucher un fichier partagé par toutes les pages : "Courrier entrant"/"Courrier sortant" -> lien pré-filtré vers CourrierList (pas de nouvelle page) ; "Courriers incomplets" -> lien vers la liste de brouillons déjà intégrée à "Enregistrer un courrier" (pas de nouvelle page) ; "Courriers enregistrés" -> PRÉCISÉ PAR L'UTILISATEUR comme étant en réalité une vraie page reprenant la logique des 3 onglets de "Mes courriers" mais à l'échelle de tout ce que l'utilisateur peut voir, pas juste ses propres courriers ("no it is courrier list it will be combine there all courier already register inside the system but it should follow the logic of the 3 tab enattend encour and transfere new page"). Notifications volontairement vides (Module 7 n'existe pas — jamais de compteur inventé) ; aide décorative (aucune page d'aide n'existe). 353/353 tests, pint propre, 0 écart de traduction (612 `__()` audités).

## [2026-09-16 11:40] Couleur d'accent bleue sur toute l'application
Fichier(s) : resources/css/app.css (--color-accent/--color-accent-content passent de neutral-800/white (gris quasi noir) à #2563eb (clair) / #3b82f6 (sombre, meilleur contraste) ; --color-accent-foreground reste blanc)
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite de l'utilisateur ("the color" puis, après clarification, "the whole app") — la maquette GPT du tableau de bord est dominée par un bleu utilisé comme couleur de marque (boutons primaires, éléments actifs), très différent du gris quasi-noir utilisé jusqu'ici par tous les boutons `variant="primary"` de Flux dans toute l'application. `--color-accent` est LA variable dont Flux tire ses boutons primaires, anneaux de focus et états actifs (case cochée, onglet segmenté sélectionné...) partout dans l'app — un seul changement ici recolore l'application entière de façon cohérente, sans repasser bouton par bouton, page par page. Les couleurs sémantiques déjà en place ailleurs (badges de statut vert/ambre/rouge/bleu selon le statut du courrier, cartes du tableau de bord) restent inchangées — seule la couleur de MARQUE/action change. 353/353 tests (aucun impact PHP, changement CSS pur), pint propre.

## [2026-09-16 12:05] Thème clair par défaut (l'app rendait en sombre malgré le nouvel accent bleu)
Fichier(s) : resources/views/partials/head.blade.php (pré-remplit localStorage flux.appearance="light" AVANT @fluxAppearance, uniquement si rien n'est déjà enregistré — un choix explicite depuis Réglages > Apparence reste prioritaire ensuite)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php, resources/views/layouts/app/header.blade.php, resources/views/layouts/auth/card.blade.php, resources/views/layouts/auth/simple.blade.php, resources/views/layouts/auth/split.blade.php (class="dark" codée en dur retirée du <html> — trompeuse, le rendu réel dépend entièrement du script @fluxAppearance, pas de cet attribut statique)
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite de l'utilisateur après la couleur d'accent ("the background colors frontend ... color") — la maquette GPT est un thème CLAIR (fond blanc), mais l'app rendait en sombre malgré le changement d'accent bleu. Cause trouvée en lisant vendor/livewire/flux/src/AssetManager.php::fluxAppearance() : sans préférence déjà enregistrée dans localStorage, le script embarqué par @fluxAppearance retombe sur 'system' — qui affiche l'app en sombre dès que l'OS/le navigateur de l'utilisateur préfère le sombre, jamais le rendu clair de la maquette. Les 5 <html class="dark"> codés en dur n'étaient PAS la cause réelle (le script de @fluxAppearance s'exécute de façon synchrone dans <head>, avant peinture, et corrige toujours la classe selon la préférence réelle — donc pas de FOUC) mais restaient trompeurs à la lecture du code, retirés par cohérence. Page Réglages > Apparence (déjà existante, light/dark/system) reste pleinement fonctionnelle et prioritaire sur ce défaut dès qu'un choix explicite est fait. 353/353 tests (changement HTML/JS pur, aucun impact PHP), pint propre.

## [2026-09-16 12:20] Badge de statut partagé (couleurs incohérentes entre 4 pages)
Fichier(s) : resources/views/components/statut-badge.blade.php (nouveau — <x-statut-badge :statut="..."> : un seul mapping statut → couleur)
Fichier(s) : resources/views/livewire/frontend/workflowQueue.blade.php, resources/views/livewire/frontend/courrierList.blade.php, resources/views/livewire/frontend/dashboard.blade.php, resources/views/livewire/frontend/showCourrier.blade.php (badge de statut dupliqué remplacé par <x-statut-badge>)
Fichier(s) : public/build/** (npm run build)
Pourquoi : trouvé en vérifiant une capture d'écran envoyée par l'utilisateur ("Courriers à traiter") — les badges de statut ("enregistre", "en cours de transfert", "affecte") s'affichaient tous en gris neutre sur cette page, alors que la page de recherche colore déjà certains statuts. Cause : le même mapping `match ($courrier->statut) { ... }` avait été copié-collé 4 fois (workflowQueue/courrierList/dashboard/showCourrier) et avait dérivé à chaque copie — workflowQueue ne connaissait que 2 statuts (amber/blue), showCourrier utilisait "green" là où les 3 autres utilisaient soit rien soit "zinc" pour les mêmes statuts. Extrait en un composant Blade partagé, seul point de vérité désormais : vert (traité/archivé), rouge (rejeté), ambre (en attente d'info/en attente de transfert), bleu (en validation/en cours de transfert/affecté/en traitement), zinc par défaut (enregistré). 353/353 tests, pint propre, 0 écart de traduction (612 `__()` audités).

## [2026-09-16 12:25] Contraste sidebar (bg-zinc-50 quasi invisible sur bg-white)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (sidebar : bg-zinc-50 -> bg-zinc-100 en mode clair)
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite de l'utilisateur après comparaison avec la maquette ("look the sidebar bg color the main colors and the whole website bg color compare to that one i send you") — bg-zinc-50 (#fafafa, thème custom du projet) contre bg-white sur le contenu principal ne laissait quasiment aucune différence visible (2 % de luminosité d'écart), alors que la maquette montre une sidebar clairement distincte, visuellement séparée du contenu blanc. bg-zinc-100 (#f5f5f5) donne un contraste suffisant sans casser la palette existante. Mode sombre inchangé (bg-zinc-900 déjà bien distinct de bg-zinc-800). 353/353 tests (changement CSS pur), pint propre.

## [2026-09-16 12:40] Cartes/tableaux uniformisés (bg-white + shadow-sm + rounded-2xl) sur toute l'app
Fichier(s) : resources/views/components/statut-badge.blade.php (déjà créé — réutilisé ici sans changement)
Fichier(s) : resources/views/livewire/frontend/dashboard.blade.php (cartes stat/tuiles d'action : bg-white + shadow-sm + rounded-2xl (au lieu de rounded-xl sans fond) ; "Rechercher un courrier" recoloré zinc -> indigo ; "Tâches du jour" : en-tête colorée bleu clair + icône ; encarts "Bientôt disponible" : fond zinc-50/40 + bordure pointillée plus marquée)
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php, resources/views/livewire/frontend/workflowQueue.blade.php, resources/views/livewire/frontend/regleList.blade.php (tableaux SANS AUCUN conteneur — bordure/fond/ombre — enveloppés dans rounded-2xl border bg-white shadow-sm, en-têtes de colonnes avec fond bg-zinc-50, padding pl-4/pr-4 sur 1re/dernière colonne)
Fichier(s) : resources/views/livewire/frontend/courriersEnregistres.blade.php, resources/views/livewire/frontend/mesCourriers.blade.php, resources/views/livewire/frontend/userList.blade.php (x5), resources/views/livewire/frontend/privilegeList.blade.php, resources/views/livewire/frontend/profilList.blade.php (x2) (conteneurs rounded-xl border border-zinc-200 dark:border-zinc-700 déjà présents, mais sans bg-white/shadow-sm — corrigés en bloc via un script PHP scratch, remplacement de chaîne exacte, vérifié par re-grep)
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite de l'utilisateur après capture d'écran de la page "Courriers à traiter" réelle ("everything is gray motherfuck redo the design") — en cherchant la cause, plusieurs tableaux de courriers (recherche, file d'attente, règles de classement) n'avaient AUCUN conteneur visuel du tout (pas de bordure, pas de fond, pas d'ombre — juste `overflow-x-auto` nu), donc rendaient à plat sur le fond de page, complètement gris/blanc indifférencié, sans lien avec le style "carte blanche + ombre" déjà appliqué au tableau de bord. D'autres pages (utilisateurs, profils, privilèges, dossiers) avaient bien un conteneur mais sans fond ni ombre, donc invisibles sur un fond de page également blanc. Un seul style de carte cohérent partout désormais : `rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900`. 353/353 tests, pint propre, 0 écart de traduction (612 `__()` audités).

## [2026-09-16 12:55] Sidebar : retour à bg-white (pas de remplissage gris)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (sidebar : bg-zinc-100 -> bg-white en mode clair — annule l'entrée du 2026-09-16 12:25)
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite de l'utilisateur, en réponse directe au changement précédent ("i dont want the gray sidebar and the low gray main section again ... use the colors of these main section background and sidebar background the blue and white colors etc") — en réexaminant la maquette de près (renvoyée par l'utilisateur), la sidebar y est en réalité BLANCHE comme le contenu principal, séparée seulement par une fine bordure verticale — pas un aplat gris comme corrigé dans l'entrée précédente (qui partait d'une bonne intention — rendre la séparation visible — mais avec la mauvaise solution). Le contraste vient désormais des CARTES blanches + ombre (voir entrée précédente) qui se détachent du fond de page, pas d'une sidebar grise. 353/353 tests (changement CSS pur), pint propre.

## [2026-09-16 13:00] Rebranding APP_NAME (Laravel -> GEC) + bug de directive Blade dans un commentaire JS
Fichier(s) : .env, .env.example (APP_NAME=Laravel -> APP_NAME=GEC)
Fichier(s) : resources/views/partials/head.blade.php (script de thème clair par défaut : le commentaire JS mentionnait littéralement "@fluxAppearance" deux fois, ce que Blade interprétait comme un VRAI appel de la directive — pas un commentaire — et remplaçait par tout le script/style vendu de Flux EN PLEIN MILIEU du commentaire, cassant la page ; reformulé en commentaire Blade {{-- --}} placé AVANT le <script>, sans jamais écrire le nom de la directive dans le texte)
Fichier(s) : public/build/** (npm run build)
Pourquoi : (1) demande explicite/question implicite de l'utilisateur en comparant l'app à la maquette — le logo de la sidebar affichait encore "Laravel" (`config('app.name')`, jamais configuré depuis le début du projet) au lieu du nom du produit ; changé en "GEC", cohérent avec la maquette et le nom du projet (Gestion Électronique du Courrier). (2) bug signalé par l'utilisateur ("error", en collant un extrait du script cassé) : `@fluxAppearance` écrit en toutes lettres dans un commentaire `//` JavaScript, à l'intérieur d'un `<script>` Blade — Blade ne connaît pas la syntaxe de commentaire JS, il scanne le texte BRUT du fichier pour toute occurrence de `@nomDeDirective` et la remplace, y compris dans un commentaire — vérifié par un rendu direct du partial (`view('partials.head')->render()`) avant/après correction. 353/353 tests, pint propre.

## [2026-09-16 13:15] Sidebar bleue + item actif en aplat plein (pas le style Flux par défaut)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (sidebar : bg-white -> bg-blue-50, border-zinc-200 -> border-blue-100 en mode clair — annule l'entrée précédente "retour à bg-white")
Fichier(s) : resources/css/app.css ([data-flux-sidebar-item][data-current] { background-color: var(--color-accent); color: var(--color-accent-foreground); } en !important — surcharge le style par défaut de Flux qui ne donne à l'item actif qu'un fond quasi blanc avec juste le TEXTE en couleur d'accent, jamais un aplat plein)
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite et répétée de l'utilisateur ("it should take the blue one not that white completely white no", puis "this eaxct blue" en renvoyant la maquette, puis "i want the exact color solid why are you not doing what am saying to you") — deux écarts distincts trouvés. (1) La sidebar avait été repassée en blanc dans l'entrée précédente sur une mauvaise lecture de la maquette — l'utilisateur a clarifié vouloir un bleu clair visible, pas du blanc. (2) Plus important : l'item de sidebar actif ("Dashboard"/"Courriers à traiter"...) ne s'affichait JAMAIS en aplat de couleur plein, quelle que soit la valeur de --color-accent, parce que `vendor/livewire/flux/stubs/resources/views/flux/sidebar/item.blade.php` code en dur `data-current:bg-white` (juste le texte en accent, pas le fond) — trouvé en lisant le stub Flux, pas en devinant. Corrigé par surcharge CSS ciblée (jamais de patch vendor) plutôt qu'en essayant encore une fois de changer une variable de couleur qui n'était pas la cause. Vérifié dans le bundle CSS compilé (grep sur `app-*.css`) et confirmé visuellement par l'utilisateur (capture d'écran après rebuild : item actif en bleu plein, texte blanc). 353/353 tests, pint propre, 0 écart de traduction.

## [2026-09-16 13:30] Palette de marque exacte (hex fournis par l'utilisateur, remplace toutes les approximations Tailwind)
Fichier(s) : resources/css/app.css (9 variables --color-brand-* ajoutées au bloc @theme avec les valeurs hexadécimales EXACTES fournies — navy/blue/blue-medium/blue-pale/success/warning/urgent/danger/secure/surface/table-header/border/text-secondary/text-primary ; --color-accent unifié sur --color-brand-blue en clair ET en sombre, plus de divergence entre les deux)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (sidebar : bg-blue-50 -> bg-brand-navy + class="dark" ajoutée sur <flux:sidebar> pour scoper le mode sombre À LA SIDEBAR SEULE — réutilise tous les utilitaires dark: déjà écrits sur les items de sidebar par le starter-kit (texte blanc/80 etc.) sans les redéfinir un par un ; groupe "Platform" traduit en "Général")
Fichier(s) : resources/views/livewire/frontend/dashboard.blade.php (toutes les couleurs Tailwind approximatives remplacées par les tokens brand-* exacts ; correction sémantique : "Courriers urgents" passe de rouge à brand-urgent/violet (le rouge est réservé aux alertes système dans cette palette) ; "Courrier confidentiel" passe d'emerald à brand-secure/teal ; bannière de bienvenue "Bonjour :nom" habillée en brand-blue-pale avec icône enveloppe, conforme à "Pale Blue — Welcome banner")
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (badge "Confidentiel"/"Très confidentiel" : amber -> teal, cohérent avec la nouvelle règle "confidentialité = teal, pas amber")
Fichier(s) : lang/en.json (clé "Général" ajoutée)
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite de l'utilisateur, palette hexadécimale précise extraite de la maquette et fournie telle quelle ("Here is the color palette used in the GEC web interface... Quick CSS variables : root { --primary-navy: #12396B... }") — remplace toutes les approximations Tailwind nommées (blue-600, emerald, purple...) utilisées jusque-là par déduction visuelle. Deux corrections sémantiques importantes découvertes en appliquant la palette à la lettre : la palette réserve le ROUGE aux "notifications urgentes, alertes, erreurs" (système) et le VIOLET aux "courriers urgents, confidentialité, modules spéciaux" — "Courriers urgents" (une priorité de courrier, pas une alerte système) devait donc être violet, pas rouge, comme construit initialement par déduction. Même chose pour "Courrier confidentiel" : TEAL est une couleur dédiée et distincte de "Green" (succès), pas une nuance d'emerald/green choisie par approximation. 353/353 tests, pint propre, 0 écart de traduction (612 `__()` audités), valeurs hexadécimales exactes vérifiées présentes dans le bundle CSS compilé.

## [2026-09-16 13:45] Application complète de la palette (couleurs neutres oubliées lors de la première passe)
Fichier(s) : resources/css/app.css (--color-zinc-50/200/500 remappés sur les couleurs neutres exactes de la palette — F1F5F9/DCE5EF/64748B — plutôt que de traquer chaque usage individuellement dans ~25 fichiers ; [data-flux-heading] { color: var(--color-brand-text-primary) } + .dark [data-flux-heading] { color: white } pour les titres)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (body : bg-white -> bg-brand-surface, pour que les cartes blanches se détachent réellement du fond de page au lieu de blanc sur blanc)
Fichier(s) : public/build/** (npm run build)
Pourquoi : demande explicite de l'utilisateur ("why are you doing this to me reade the color palte i gave with thier labels before working") — la première passe (entrée précédente) n'avait appliqué que les couleurs de statut/marque les plus visibles (navy, blue, success, warning, urgent, secure) mais avait laissé les jetons --color-brand-surface/table-header/border/text-secondary/text-primary DÉFINIS SANS ÊTRE UTILISÉS nulle part dans les templates — "Very light gray — Page surfaces", "Border gray — Card and input borders", "Medium gray — Secondary text", "Dark text navy — Main headings and body text" n'avaient donc aucun effet visible malgré leur présence dans app.css. Corrigé en remappant les nuances zinc-50/200/500 déjà utilisées PARTOUT dans le projet (bg-zinc-50 sur les thead, border-zinc-200 sur les cartes, text-zinc-500 pour le texte secondaire) directement sur les valeurs exactes de la palette — un seul point de changement plutôt que de retrouver chaque occurrence — et en ajoutant une surcharge ciblée pour [data-flux-heading] (zinc-800 par défaut de Flux non remappé globalement : utilisé aussi pour les fonds du vrai mode sombre de l'app, un remap global aurait teinté ces fonds par effet de bord). 353/353 tests, pint propre, 0 écart de traduction, valeurs hexadécimales vérifiées dans le bundle CSS compilé.

## [2026-09-16 14:10] Palette de marque — dernières couleurs Tailwind non brand résiduelles
Fichier(s) : resources/views/livewire/frontend/dashboard.blade.php (bg-indigo-100/text-indigo-600 -> bg-brand-blue-pale/text-brand-blue sur les deux tuiles "Rechercher un courrier" ; dark:bg-blue-500/10 -> dark:bg-brand-blue/10 sur le bandeau "Tâches du jour")
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (14 occurrences de text-amber-600 [dark:text-amber-400] -> text-brand-warning, mentions "proposé automatiquement — à vérifier")
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (même bandeau "proposé automatiquement" -> text-brand-warning ; 5 messages d'erreur de validation text-red-600 -> text-brand-danger ; libellé "Rejeter ce courrier" -> text-brand-danger)
Fichier(s) : resources/views/livewire/frontend/mesCourriers.blade.php, registrationFormConfidentiel.blade.php (messages d'erreur de validation text-red-600 -> text-brand-danger)
Fichier(s) : resources/views/livewire/settings/security.blade.php (icône passkey copiée text-green-500 -> text-brand-success ; bouton supprimer passkey text-red-500/hover -> text-brand-danger + hover:bg-brand-danger/10)
Fichier(s) : resources/views/livewire/auth/verify-email.blade.php, resources/views/components/auth-session-status.blade.php (messages de statut text-green-600 -> text-brand-success)
Fichier(s) : resources/views/components/passkey-registration.blade.php, resources/views/components/passkey-verify.blade.php (messages d'erreur Alpine text-red-600 -> text-brand-danger)
Pourquoi : demande explicite de l'utilisateur ("apply the same colors you extracted everywhere on the project") après extraction de la palette depuis la maquette du tableau de bord. Un balayage complet (agent Explore) avait listé tous les usages Tailwind codés en dur restants hors du cœur déjà migré (entrées du 2026-09-16 précédentes) — essentiellement le bandeau ambre "proposé automatiquement/à vérifier" du pré-remplissage OCR (Module 1) répété 14 fois dans un seul fichier, et des messages de succès/erreur (vert/rouge) dispersés dans les pages d'auth, de sécurité (passkeys/2FA) et de validation de formulaire. Volontairement PAS touché : les props `color="..."` de `flux:badge`/`flux:text` (statut-badge.blade.php, courrierList.blade.php, privilegeList.blade.php, userList.blade.php, scanPremier.blade.php, two-factor-challenge.blade.php) — Flux Pro est un composant compilé (source non disponible dans vendor/), son prop `color` n'accepte que sa propre palette nommée (green/red/amber/blue/zinc/teal…) et pas une variable CSS brand arbitraire ; ces couleurs nommées correspondent déjà sémantiquement à la palette (green≈success, red≈danger, amber≈warning, teal≈secure) et forcer un remplacement risquerait de casser le composant à la prochaine mise à jour Flux Pro pour un gain de précision de teinte marginal. `npm run build` relancé après coup (Règle CLAUDE.md/mémoire : pas de watcher actif).

## [2026-09-16 14:25] Sidebar — menus déroulants (Courrier/Mes enregistrements/Recherche/Mon compte) conformes à la maquette
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (groupe "Courrier" passé en `expandable expanded="true"` (déjà ouvert, comme sur la maquette) ; "Mes courriers" et "Courriers à traiter" sortis de "Courrier" vers un nouveau groupe déroulant "Mes enregistrements" (`expandable expanded="false"`) ; "Rechercher un courrier" sorti vers un nouveau groupe déroulant "Recherche" (`expandable expanded="false"`) ; nouveau groupe déroulant "Mon compte" (`expandable expanded="false"`, lien "Réglages" vers profile.edit) ; nouvel item plat "Déconnexion" (formulaire POST + CSRF, distinct du menu profil de la navbar qui reste inchangé) — tous gated par les mêmes `@can` qu'avant, aucun droit modifié)
Pourquoi : demande explicite de l'utilisateur, en comparant à la maquette du tableau de bord — "Courrier", "Mes enregistrements", "Recherche" et "Mon compte" y apparaissent comme des menus à chevron (déroulants), alors que la sidebar réelle n'avait qu'un seul groupe plat "Courrier" avec 10 liens, et "Mes enregistrements"/"Recherche"/"Mon compte" n'existaient pas comme regroupements. Utilisé le support natif de Flux pour les groupes de sidebar accordéon (`expandable`/`expanded` sur `flux:sidebar.group`, rendu via `<ui-disclosure>` — vérifié dans le stub vendor avant d'écrire du JS custom). Point NON repris de la maquette, volontairement : le badge de compteur "3" sur "Alertes" — DECISIONS.md/commentaire existant dans ce même fichier documentait déjà "Module 7 n'existe pas encore, jamais de compteur inventé" pour la cloche de notifications de la navbar ; ajouter un item de sidebar "Alertes" avec un chiffre fabriqué aurait contredit cette décision déjà actée, donc pas fait — signalé à l'utilisateur plutôt que décidé unilatéralement.

## [2026-09-16 15:05] Tableau de bord réaligné sur l'arrangement exact de la maquette (hors formulaire "tout en une page")
Fichier(s) : resources/views/livewire/frontend/dashboard.blade.php (rangée d'actions rapides réduite aux 4 tuiles de la maquette — Enregistrer un courrier/Scanner-Importer/Courrier confidentiel/Mes enregistrements — "Courriers à traiter" et "Rechercher un courrier" retirés du tableau de bord, restent accessibles via la sidebar ; tableau "Derniers courriers enregistrés" complété avec les colonnes "Type" et "Expéditeur / Destinataire" présentes sur la maquette mais absentes jusqu'ici (données réelles : type_document, expediteur_organisation/expediteur_nom pour l'entrant, destinataire pour le sortant) ; bloc "Enregistrement rapide en une page" (placeholder "Bientôt disponible") supprimé — demande explicite de l'utilisateur de ne pas reprendre ce formulaire ; widget "Calendrier" implémenté pour de vrai (mini calendrier du mois courant, jour du jour mis en évidence, navigation mois précédent/suivant) au lieu du placeholder "Bientôt disponible" — pure arithmétique de date côté Alpine, aucune donnée métier fabriquée, donc pas soumis à la même réserve que "Notifications"/Module 7 ; noms de mois et initiales de jour calculés via Carbon dans un bloc PHP séparé (cohérent avec le switcher FR/EN existant))
Fichier(s) : lang/en.json (clés "N° Référence", "Expéditeur / Destinataire", "Mois précédent", "Mois suivant" ajoutées — "Type"/"Calendrier" existaient déjà)
Pourquoi : demande explicite de l'utilisateur ("copy it i want the exact dashboard content and the way it is arrange on the main but don't include the form registrationcourrier") après l'alignement des couleurs et de la sidebar sur la même maquette. "Notifications" reste un placeholder "Bientôt disponible" (Module 7 non construit — même réserve que pour la cloche de notification et l'item "Alertes" de la sidebar, voir entrée précédente) : contrairement au calendrier, une maquette de notifications impliquerait soit des données fabriquées, soit un vrai Module 7, aucun des deux acceptable ici.
Piège rencontré et corrigé avant de committer quoi que ce soit côté build : la directive `@json()` plantait sur une expression Carbon imbriquée (virgules dans `create()`, chaînage de flèches) — parseur d'arguments de la directive dérouté, corrigé en précalculant les tableaux dans un bloc PHP séparé puis en passant de simples variables à `@json()`. Deuxième piège, plus subtil, rencontré en corrigeant le premier : le commentaire Blade explicatif ajouté à cette occasion contenait littéralement les mots "@php" et "@json()" en toutes lettres — Blade scanne le texte brut du fichier pour toute séquence "@nomDeDirective" AVANT de retirer les commentaires, donc le commentaire lui-même cassait la compilation (exactement le bug déjà tracé dans l'entrée du 2026-09-16 "Rebranding APP_NAME" pour "@fluxAppearance" écrit dans un commentaire JS) — reformulé sans aucun nom de directive écrit en toutes lettres. Les deux erreurs de syntaxe ont été isolées en compilant la vue manuellement (`Blade::compileString()` + `php -l`) plutôt qu'en devinant depuis le message d'erreur PHPUnit, qui ne pointait que les tests affectés, pas la ligne fautive. 353/353 tests, build Vite propre, aucune régression.

## [2026-09-17 09:15] Fond de page principal repassé en blanc
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (`<body>` : bg-brand-surface -> bg-white)
Pourquoi : demande explicite de l'utilisateur ("the main contain background color should be white"), qui annule le changement du 2026-09-16 13:45 (bg-white -> bg-brand-surface, motivé à l'époque par une capture d'écran "everything is gray"). En relisant les libellés fournis par l'utilisateur pour la palette exacte, le jeton "Very light gray #F8FAFC" est étiqueté "Page SURFACES" tandis que le blanc est explicitement "Main page background, CARDS" — bg-brand-surface reste donc approprié pour de vraies surfaces ponctuelles (déjà utilisé ailleurs, ex. en-tête de tableau) mais pas pour le fond de page entier. 353/353 tests (aucun test ne dépendait de cette classe), build Vite propre.

## [2026-09-17 10:40] GEC Master Color System — remplacement intégral de la palette de marque
Fichier(s) : resources/css/app.css (bloc @theme réécrit intégralement — --color-brand-navy/-secondary/-dark, --color-brand-blue/-medium/-pale/-soft/-border, --color-brand-surface/-soft, --color-brand-text-primary/-secondary/-muted, --color-brand-border/-input, --color-brand-table-header/-text, --color-brand-success/-warning/-danger/-info chacun avec variante -dark (texte de badge) et -light (fond de badge), --color-brand-disabled-bg/-text, --color-brand-sidebar-text-secondary/-divider ; remap zinc-50/200/300/400/500 sur les nouvelles valeurs neutres exactes (table header, bordures carte/tableau, bordure/placeholder d'input Flux, texte secondaire) ; --color-brand-urgent et --color-brand-secure transformés en ALIAS de --color-brand-danger et --color-brand-navy respectivement (clarifié avec l'utilisateur, voir plus bas) ; nouvelles surcharges CSS pour le survol de sidebar ([data-flux-sidebar-item]:hover, [data-flux-sidebar-group] > button:hover -> --color-brand-navy-secondary, exact comme demandé, Flux appliquant par défaut un survol algorithmique white/7% plutôt qu'une teinte fixe))
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (body : bg-white -> bg-brand-surface, qui vaut maintenant #F5F8FC — le schéma fourni par l'utilisateur avec le nouveau système est explicite : sidebar Navy | fond #F5F8FC | carte BLANCHE flottant par-dessus, ce qui règle dans le même mouvement la demande de la veille ; commentaires obsolètes avec l'ancien hex de la navy corrigés)
Fichier(s) : resources/views/components/statut-badge.blade.php (composant partagé réécrit : `<flux:badge color="...">` -> `<span>` habillé de nos propres tokens --color-brand-*-light/-dark, avec la palette Tailwind nommée de Flux Pro impossible à retinter en hex exacts ; statuts "en cours"/actifs (en_validation, en_cours_de_transfert, en_traitement, en_attente_*) reclassés sous WARNING/orange au lieu de "blue", "affecte" reclassé sous SUCCESS/vert, "enregistre" (jusqu'ici non coloré) reclassé sous INFO/bleu — tous alignés sur les libellés explicites du système de couleurs fourni par l'utilisateur, section "Status Badges")
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (badge "Confidentiel"/"Très confidentiel" : flux:badge color="teal" -> span bg-brand-navy)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (badge statut OCR et badge source de mot-clé : flux:badge color="..." -> span avec nos tokens ; "en_cours" (OCR) classé INFO/bleu, explicitement listé sous "Information" dans le système fourni)
Fichier(s) : resources/views/livewire/frontend/scanPremier.blade.php ("Surveillance active" et badges du journal d'import : flux:badge color=/x-bind:color -> span/x-bind:class avec nos tokens)
Fichier(s) : resources/views/livewire/frontend/privilegeList.blade.php, resources/views/livewire/frontend/userList.blade.php (badges profil/privilège : flux:badge color="blue"/"zinc" -> span avec nos tokens info/disabled)
Fichier(s) : resources/views/livewire/auth/two-factor-challenge.blade.php (flux:text color="red" -> class text-brand-danger, pour cohérence avec le reste du projet même si red-600 de Tailwind correspondait déjà exactement à notre hex)
Fichier(s) : resources/views/livewire/settings/delete-user-form.blade.php, resources/views/livewire/frontend/privilegeList.blade.php, resources/views/livewire/frontend/regleList.blade.php, resources/views/livewire/settings/security.blade.php (boutons "Delete account"/"Supprimer"/"Remove passkey" : flux:button variant="danger" (rouge plein, non retintable — Flux code bg-red-500 en dur) -> variant="outline" + classes bordure/texte/survol brand-danger, conforme à la section 6 "Delete button" du système fourni — fond blanc, PAS de bouton rouge plein pour une action normale)
Fichier(s) : resources/views/livewire/frontend/dashboard.blade.php (commentaires obsolètes citant l'ancienne palette et l'ancien hex corrigés ; aucune classe changée ici — brand-urgent/brand-secure déjà utilisés, retintés automatiquement par l'alias CSS)
Pourquoi : demande explicite et insistante de l'utilisateur ("go throught the project one by one... use this colors guide... don't want to here it exist already just chenge it again") — remplacement intégral du "GEC Master Color System" (règle 40/30/20/10 : navy structure / blanc contenu / bleu clair séparation / statuts 10%) fourni en une seule fois, avec table de boutons et de badges dédiée. Deux éléments n'existent pas dans le système fourni : "Courriers urgents" (priorité) et "Courrier confidentiel" (accès) — clarifié avec l'utilisateur via AskUserQuestion plutôt que deviné : urgent rejoint la famille danger (rouge), confidentiel rejoint navy ; implémenté comme alias CSS de --color-brand-danger/--color-brand-navy plutôt que deux teintes ad hoc, pour rester strictement dans le système fourni.
Volontairement pas retinté : `flux:callout variant="danger"` (panneaux d'alerte informatifs, pas des badges de statut ni des boutons — laissés sur la palette rouge de Flux, déjà proche de notre hex exact côté valeurs par défaut) ; le bouton "Rejeter" d'un courrier (showCourrier.blade.php) et "Disable 2FA" (security.blade.php), laissés en variant="danger" plein — le système fourni réserve explicitement le rouge plein aux "destructive/critical confirmation", et rejeter un courrier ou désactiver la 2FA qualifient mieux pour cette exception que "Supprimer"/"Delete account" ; l'audit exhaustif bouton-par-bouton des variantes "secondary"/"soft blue" (section 6, ~23 fichiers utilisant flux:button) n'a pas été fait dans cette passe — seul le variant="primary" (déjà 100% automatique via --color-accent) et variant="danger" (cas contraire, non automatique) ont été traités ; signalé à l'utilisateur comme suite possible plutôt que fait à moitié silencieusement. Nuance non chassée : le texte secondaire de sidebar (section 8, #CBD5E1) reste piloté par l'opacité blanche par défaut de Flux (dark:text-white/80) plutôt qu'un hex exact — approximation visuelle mineure, pas une couleur de la mauvaise famille. 353/353 tests, build Vite propre, valeurs hexadécimales exactes (0B1F3A, 2563EB, 16A34A, DC2626, F59E0B, 172033, F5F8FC...) vérifiées présentes dans le bundle CSS compilé.

## [2026-09-17 16:20] "Enregistrer un courrier" réorganisé en 5 étapes visuelles (maquette utilisateur)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (réécriture complète de la mise en page : fil d'Ariane Accueil/Courrier/Enregistrer un courrier, en-tête avec icône, indicateur à 5 étapes cliquable en arrière uniquement — Document/Informations/Pièces jointes/Validation/Confirmation, piloté par un simple x-data Alpine `etape` par-dessus le MÊME formulaire Livewire à un seul submit ; colonne latérale nouvelle — "Aperçu du document" réel du brouillon en cours, "Rappel des règles" (règles métier réelles déjà implémentées, pas inventées), bandeau "Courrier confidentiel ?" pointant vers la vraie page courriers.confidentiel ; TOUS les champs/labels/options du formulaire d'origine conservés à l'identique (mêmes wire:model, mêmes libellés Sens/Priorité/Confidentialité/Objet du courrier/Nom/Organisation/pièce jointe en input simple) — seule leur disposition en cartes d'étape a changé, après une première tentative où plusieurs contrôles avaient été redessinés (bascules à 2 boutons, zone glisser-déposer) puis explicitement corrigée sur demande de l'utilisateur ("you have to use the same input we already built"))
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (nouveau champ `commentaire` (optionnel, max 500) attaché à l'entrée d'historique de création au lieu d'un `commentaire => null` codé en dur ; nouvelles méthodes `importerFichier()` (import manuel visible étape 1, même mécanisme que numeriserAutomatique() mais met à jour brouillonId directement plutôt que de renvoyer au JS du watcher), `annulerDocument()` (abandonne le document choisi sans toucher au brouillon lui-même, qui reste "en attente"), `nouveauCourrier()` (efface la confirmation précédente) ; dispatch d'un évènement navigateur `courrier-enregistre` après un enregistrement réussi, pour faire avancer l'étape Alpine côté vue sans dépendre d'un x-effect fragile sur un booléen interpolé côté serveur)
Fichier(s) : app/Http/Controllers/BrouillonDocumentApercuController.php (nouveau — aperçu inline d'un CourrierBrouillon, même principe que CourrierDocumentApercuController mais pour un brouillon pas encore transformé en Courrier ; droits via CourrierBrouillonPolicy::utiliser)
Fichier(s) : routes/web.php (nouvelle route brouillons/{brouillon}/apercu)
Pourquoi : demande explicite de l'utilisateur avec capture d'écran de la maquette ("i want the enregistre page to be like this exactly this design"). Deux corrections après retour direct de l'utilisateur : (1) plusieurs contrôles avaient été redessinés (Sens/Priorité en bascules à boutons, pièce jointe en zone glisser-déposer, libellés renommés) au lieu de réutiliser tels quels les champs déjà construits — entièrement revenu en arrière sur ces contrôles précis, seule la disposition en étapes reste nouvelle ; (2) le panneau "Aperçu du document" n'affichait qu'une icône générique + nom de fichier pour un PDF (un `<img>` ne peut pas afficher un PDF) — remplacé par un `<iframe>` pointant vers la nouvelle route d'aperçu, qui délègue au lecteur PDF natif du navigateur, pour un vrai aperçu visuel comme sur la maquette (les images restent affichées via `<img>`, déjà correct). Garde reprise de scanPremier.blade.php (commentaire existant "celui qui committe en dernier écrase silencieusement l'autre") : le nouveau contrôle d'import manuel de l'étape 1 masque son propre input tant que le dossier surveillé tourne, pour ne jamais avoir deux `<input>` actionnables liés au même wire:model="document" en même temps. 353/353 tests, build Vite propre.

## [2026-09-17 16:35] Panneau "Rappel des règles" retiré, aperçu du document agrandi
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (bloc "Rappel des règles" (4 puces) supprimé de la colonne latérale ; hauteur de l'aperçu du document (image, iframe PDF, et état vide "Aucun document pour l'instant") uniformisée à h-128 (32rem) au lieu de s'ajuster au contenu — bien plus grand qu'avant)
Pourquoi : demande explicite de l'utilisateur ("remove rappel des regles and increase the height of apercu"). 353/353 tests, build Vite propre.

## [2026-09-17 17:10] Modale "Aperçu du courrier" (zoom, plein écran, téléchargement réel) — remplace l'ouverture en nouvel onglet
Fichier(s) : resources/js/document-preview.js (nouveau — rendu PDF sur <canvas> via pdfjs-dist, toutes les pages, exposé en window.DocumentPreview.rendrePdf)
Fichier(s) : package.json, package-lock.json (ajout pdfjs-dist)
Fichier(s) : vite.config.js (nouvelle entrée resources/js/document-preview.js)
Fichier(s) : app/Http/Controllers/BrouillonDocumentDownloadController.php (nouveau — téléchargement réel d'un CourrierBrouillon, Content-Disposition: attachment, même principe que CourrierDocumentDownloadController ; droits via CourrierBrouillonPolicy::utiliser)
Fichier(s) : routes/web.php (nouvelle route brouillons/{brouillon}/telecharger)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php ("Agrandir" ouvre désormais une modale "Aperçu du courrier" (fond blanc forcé, barre d'outils zoom-/pourcentage/zoom+/plein écran/télécharger/fermer) au lieu d'un nouvel onglet ; PDF rendu sur <canvas> (pdfjs-dist) plutôt que délégué au lecteur natif du navigateur ; image affichée via <img> redimensionnée par le même pourcentage de zoom ; modale en variant="bare" pour éviter le bouton de fermeture par défaut de Flux en double de celui de notre barre d'outils)
Pourquoi : deux problèmes réels signalés par l'utilisateur avec capture d'écran de la maquette "Aperçu du courrier" (barre d'outils recherche/zoom/plein écran/télécharger/fermer). (1) "it is a whit[e] background not black" — le lecteur PDF natif du navigateur (utilisé jusqu'ici via `target="_blank"` vers la route d'aperçu) habille sa propre page d'un fond qui suit le thème sombre du SYSTÈME (letterboxing gris/noir en dehors de la page blanche), un habillage du navigateur lui-même, pas du document — aucun CSS de notre côté ne peut le forcer en blanc. Seule solution fiable : ne plus déléguer au lecteur natif, dessiner nous-mêmes le PDF sur un <canvas> (pdfjs-dist) dans une modale entièrement à notre fond blanc. (2) "it only preview to them but the[y] can't download it" — aucune route de téléchargement n'existait pour un brouillon (seulement l'aperçu inline) ; ajoutée, avec un vrai bouton dans la barre d'outils. Non repris de la maquette, volontairement : l'icône de recherche — une recherche plein texte à l'intérieur d'un PDF rendu sur canvas demanderait la couche de texte de pdf.js en plus (extraction + surlignage des occurrences), hors périmètre de cette demande ; pas d'icône non fonctionnelle ajoutée à sa place. Rendu de TOUTES les pages du PDF (défilement vertical), pas seulement la première, pour ne jamais tronquer silencieusement un document à plusieurs pages. 353/353 tests, build Vite propre (pdf.worker.min.mjs de pdfjs-dist confirmé émis dans public/build/assets).

## [2026-09-17 17:25] Même fond noir corrigé sur la miniature "Aperçu du document" (pas seulement la modale agrandie)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (petite miniature de la carte "Aperçu du document" : `<iframe>` pointant sur la route d'aperçu -> même rendu <canvas> pdfjs-dist que la modale, à une échelle fixe réduite (0.5) ; `$estImageApercu` calculé une seule fois en tête de la colonne latérale, réutilisé par la miniature ET la modale au lieu d'être recalculé)
Pourquoi : capture d'écran de l'utilisateur montrant le fond noir/sombre (habillage du lecteur PDF natif du navigateur, watermark "Activer Windows" visible) toujours présent sur la PETITE miniature de la carte "Aperçu du document" — la correction précédente n'avait retiré le `<iframe>` que de la modale "Agrandir", pas de cet aperçu réduit qui utilisait encore le même mécanisme et donc le même fond dépendant du thème système. Même cause, même correctif que l'entrée précédente. 353/353 tests, build Vite propre.

## [2026-09-17 17:45] Recherche réelle dans la modale d'aperçu + échelle réduite pour tenir sans défiler
Fichier(s) : resources/js/document-preview.js (réécrit en fabrique `window.DocumentPreview.creer()` — une instance par usage (miniature ET modale montées en même temps sur la page, chacune avec son propre état de rendu) au lieu d'un module de fonctions statiques partagé ; ajout d'une vraie couche de texte pdf.js (`TextLayer`, la même classe que le lecteur officiel Mozilla) superposée à chaque page canvas ; `rechercher(requete)` marque les blocs de texte correspondants avec la classe `.highlight` déjà fournie par le CSS officiel pdfjs-dist (mêmes variables --highlight-bg-color que le lecteur Mozilla) et renvoie le nombre de blocs trouvés ; import de `pdfjs-dist/web/pdf_viewer.css` pour le positionnement/style de cette couche)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (icône loupe de la modale "Aperçu du courrier" : bascule un champ de recherche réel + compteur de résultats, au lieu d'être absente ; "100%" découplé de l'échelle native pdf.js — introduit `echelleReelle() = (zoom/100) * 0.55`, un facteur réglé pour qu'un courrier d'une page s'affiche entièrement à "100%" sans défilement, comme sur la maquette ; hauteur max du corps de la modale réduite de 70vh (déjà changé) — la vraie correction est cette échelle, pas la seule hauteur du conteneur ; miniature de la carte "Aperçu du document" migrée vers la même fabrique `creer()`)
Pourquoi : deux retours explicites de l'utilisateur sur la modale, avec la même capture d'écran de la maquette. (1) "you forgot the actions" — l'icône de recherche de la barre d'outils, volontairement omise à l'étape précédente en la jugeant hors périmètre, a été demandée en tant que vraie action, pas décorative : implémentée avec la couche de texte pdf.js déjà nécessaire pour positionner du texte sélectionnable, sans bibliothèque supplémentaire. (2) "reduce the height to fit the courier" — à l'échelle native de pdf.js (1.0 = points PDF réels), une page A4 est nettement plus haute que ce qui tient à l'écran sans défiler, contrairement à la maquette où le courrier d'une page se voit en entier ; le "100%" affiché dans notre barre d'outils est maintenant un pourcentage de NOTRE échelle de base (0.55), pas de l'échelle native — un choix de calibrage explicite plutôt qu'une coïncidence, documenté dans le commentaire Blade. 353/353 tests, build Vite propre (CSS/SVG additionnels de pdf_viewer.css confirmés dans le bundle, sans erreur).

## [2026-09-17 18:10] Fiche "Détail du courrier" refaite en onglets + panneau aperçu toujours visible
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (réécriture complète)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (nouvelle méthode #[Computed] etapesParcours())
Pourquoi : demande explicite de l'utilisateur avec 3 captures d'écran de la maquette "Détail du courrier" — même principe que "Tous les courriers" (panneau aperçu jamais masqué, affiché par défaut) appliqué à la fiche détail. Nouvelle disposition à deux colonnes : à gauche des onglets (Général/Pièces jointes/Circuit de traitement/Historique réels ; Réponses/Commentaires désactivés+"Bientôt disponible" — aucun modèle dédié n'existe, les commentaires ne vivent que dans les entrées d'historique) au lieu d'une seule longue page ; à droite le panneau "Aperçu du courrier" (même motif canevas pdfjs-dist/document-preview.js que "Tous les courriers" et le formulaire d'enregistrement) plus les blocs Informations expéditeur/destinataire. L'ancienne modale `<flux:modal name="apercu-document-principal">` avec `<iframe src=".../vendor/pdfjs/web/viewer.html?file=...">` est supprimée : elle dupliquait un mécanisme (lecteur PDF.js vendu séparément) déjà remplacé ailleurs dans l'app par le panneau canevas unique — plus de distinction PDF-via-iframe vs image-native, les deux passent maintenant par le même panneau. AUCUNE logique de circuit (transferer/affecter/valider/rejeter/etc.) n'a été modifiée, uniquement déplacée sous l'onglet "Circuit de traitement" avec des conditions PHP identiques.

## [2026-09-17 18:15] Correctif — bloc @php non compilé dans showCourrier.blade.php ($etapesParcours undefined)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (calcul des étapes du parcours déplacé de la vue vers etapesParcours(), voir entrée précédente)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (le bloc "Parcours du courrier" consomme désormais $this->etapesParcours au lieu de variables calculées inline en @php)
Pourquoi : après la réécriture ci-dessus, 8 des 10 tests de ShowCourrierTest échouaient avec "Undefined variable $etapesParcours" — un bloc @php...@endphp dans la carte "Parcours du courrier" ne se compilait pas (confirmé via Blade::compileString() : le texte littéral "@php" restait dans la sortie compilée alors que le @endphp correspondant devenait bien "?>"). `php artisan view:clear` n'a pas résolu le problème (donc pas un simple souci de cache). Cause exacte non identifiée avec certitude (aucune trace de "@php" cachée dans un commentaire trouvée par grep) ; contourné en sortant le calcul du template vers une méthode PHP dédiée plutôt que de continuer à chercher la cause dans le compilateur Blade lui-même. 10/10 tests ShowCourrierTest passent après ce correctif (24 assertions).

## [2026-09-17 18:20] Mise à jour de 3 tests ShowCourrierTest devenus obsolètes par la réécriture en panneau aperçu unique
Fichier(s) : tests/Feature/Courriers/ShowCourrierTest.php
Pourquoi : ces 3 tests vérifiaient l'ANCIEN mécanisme (bouton "Télécharger le document" ; PDF ouvert dans une visionneuse `<iframe src=".../vendor/pdfjs/web/viewer.html?file=...">` ; image affichée "nativement" par opposition à pdfjs) — comportement intentionnellement remplacé par le panneau aperçu canevas unique de l'entrée du 18:10. Mis à jour pour vérifier le nouveau comportement réel : libellé de bouton "Télécharger" (sans "le document"), absence de `/vendor/pdfjs/web/viewer.html` pour PDF ET pour image, présence de l'URL `courriers.document.apercu` (échappée `\/` car injectée via la directive Blade `@js()`, donc comparée avec `str_replace('/', '\/', ...)` plutôt qu'en clair) pour les deux types de fichier. 362/362 tests (suite complète), build Vite propre.

## [2026-09-17 18:00] Miniature "Aperçu du document" ne rendait plus rien (régression de la couche de texte) + taille réduite
Fichier(s) : resources/js/document-preview.js (rendu du <canvas> désormais protégé par son propre try/catch, séparé de celui de la couche de texte — une erreur de la couche de texte (accessoire, recherche uniquement) ne doit plus jamais empêcher la page elle-même de s'afficher (l'essentiel) ; `charger()` accepte un 4e paramètre `avecTexte` (défaut true) pour sauter entièrement la couche de texte quand elle n'est pas nécessaire ; erreurs journalisées via console.error au lieu d'un échec silencieux)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (miniature de la carte "Aperçu du document" : `avecTexte=false` (pas de recherche nécessaire sur ce petit aperçu, seulement sur la modale agrandie) ; hauteur réduite h-128 -> h-64, échelle réduite 0.28 (était 0.35), padding ajouté autour du rendu (p-2) et alignement en haut plutôt que centré verticalement — demande explicite de l'utilisateur ("increase the width smaller and reduce the height") ; état vide "Aucun document" harmonisé sur la même hauteur h-64)
Pourquoi : régression réelle introduite par l'entrée précédente — capture d'écran de l'utilisateur montrant la miniature entièrement vide (aucun canvas, aucun message d'erreur visible), suite à l'ajout de la couche de texte pdf.js (TextLayer) dans la même fonction que le rendu du canvas : une exception levée par la couche de texte (jamais confirmée précisément sans console navigateur, mais plausible — positionnement CSS de pdf_viewer.css sensible au contexte) interrompait tout le rendu AVANT même que le canvas ne soit dessiné, faute d'isolation entre les deux étapes. Corrigé en isolant strictement le rendu du canvas (indispensable) de la couche de texte (accessoire), et en désactivant cette dernière pour la miniature qui n'en a pas besoin — double réduction de la surface de panne pour ce petit aperçu. 353/353 tests, build Vite propre.

## [2026-09-17 18:20] Toujours vide après le correctif précédent — cause réelle isolée : x-data qui échoue silencieusement
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (miniature ET modale "Aperçu du courrier" : `apercu: window.DocumentPreview.creer()` sorti de l'objet littéral `x-data` — déplacé dans `x-init`/`rendre()`, protégé par un try/catch qui alimente une variable réactive `erreur` affichée dans le template (texte rouge visible, plus seulement `console.error`) ; `zoomer()`/`rechercher()` de la modale gardés contre `apercu` encore null si le chargement initial a échoué)
Pourquoi : l'utilisateur a signalé que l'aperçu ne s'affichait toujours pas après le correctif précédent, capture d'écran montrant un encart totalement vide — ni canvas, ni le message d'erreur pourtant ajouté. Vérification complète de la chaîne technique avant de retoucher le JS (curl direct sur les 3 fichiers construits — document-preview.js, son CSS, le worker pdf.js — tous HTTP 200 avec le bon Content-Type et la bonne taille ; hash du worker référencé dans le bundle vérifié identique au fichier réellement présent sur disque ; manifest.json vérifié cohérent) : aucune anomalie réseau/build. Cause réelle isolée par élimination : `window.DocumentPreview.creer()` était appelé DANS l'objet littéral `x-data` lui-même plutôt que dans `x-init` — si `window.DocumentPreview` n'est pas encore défini à l'instant exact où Alpine évalue cette expression (course possible entre l'exécution du module et l'initialisation d'Alpine sur ce nœud précis), l'évaluation de `x-data` lève une exception et Alpine abandonne alors TOUT le composant, y compris `x-init` — ce qui produit exactement un encart vide sans le moindre message, puisque mon `try/catch` du message d'erreur précédent se trouvait justement à l'intérieur de `x-init`/`charger()`, jamais atteint dans ce scénario. Notes pour la suite si le problème persiste malgré cette correction : le nouveau `<p x-show="erreur">` affichera désormais un message rouge lisible dans l'encart lui-même si une erreur survient PLUS TARD dans la chaîne (fetch/pdf.js) — plus besoin de la console navigateur pour diagnostiquer une prochaine panne. 353/353 tests, build Vite propre (hash du bundle document-preview.js inchangé, seul le Blade a changé cette fois).

## [2026-09-17 18:35] Cause RÉELLE enfin confirmée par la console du navigateur — retour arrière temporaire, puis réintégration correcte
Fichier(s) : resources/js/document-preview.js (supprimé, puis recréé à l'identique une fois la vraie cause du bug confirmée — voir ci-dessous)
Fichier(s) : package.json, package-lock.json (pdfjs-dist désinstallé, puis réinstallé)
Fichier(s) : vite.config.js (entrée resources/js/document-preview.js retirée, puis rajoutée)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (miniature ET modale "Aperçu du courrier" : d'abord repassées en <img>/<iframe> simples comme filet de sécurité fonctionnel, PUIS réécrites en rendu <canvas> pdfjs-dist avec la barre d'outils complète de la maquette — recherche/zoom-/pourcentage/zoom+/plein écran/télécharger/fermer — cette fois avec TOUTE la logique dans des méthodes nommées de x-data, x-init/x-on se limitant à les APPELER par leur nom, jamais un bloc try/catch inliné directement comme valeur d'un attribut)
Pourquoi : l'utilisateur a cette fois copié le message d'erreur exact de la console du navigateur ("Alpine Expression Error: Unexpected token 'try'"), après deux tentatives de correction à l'aveugle (sans accès navigateur) qui n'avaient pas trouvé la vraie cause. Cause réelle : l'évaluateur d'expressions d'Alpine (copie embarquée dans livewire.js) n'accepte pas un bloc `try { ... } catch (e) { ... }` comme valeur brute d'un attribut `x-init`/`x-on` — il attend une expression ou un appel de fonction, pas un bloc de contrôle au premier niveau ; un `if (...) { ... }` au premier niveau, en revanche, fonctionne déjà ailleurs dans ce même fichier (le `<select>` de sélection de brouillon) et dans scanPremier.blade.php, ce qui explique pourquoi seul le `try` posait problème et pas les autres directives Alpine du projet. Plutôt que d'essayer un quatrième correctif à l'aveugle, l'aperçu a d'abord été ramené à la version simple <img>/<iframe> (fonctionnellement fiable, avec l'inconvénient déjà connu du fond sombre du lecteur PDF natif) pour redonner à l'utilisateur un aperçu qui s'affiche pendant l'investigation — puis, la vraie cause confirmée par la console, le rendu <canvas> complet (fond blanc garanti + recherche + zoom + plein écran + téléchargement, conforme à la maquette) a été réintégré correctement : toute logique métier vit désormais dans une méthode de x-data (ex. `async init() { try {...} catch {...} }`), jamais inlinée directement dans x-init/x-on. 353/353 tests, build Vite propre.

## [2026-09-17 19:00] Barre d'outils fusionnée dans le panneau "Aperçu du document" — plus de modale séparée
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (bouton "Agrandir" + `<flux:modal name="apercu-document">` séparée supprimés ; la barre d'outils (recherche/zoom-/pourcentage/zoom+/plein écran/télécharger) déplacée DANS le panneau "Aperçu du document" toujours visible de la colonne latérale, au lieu d'être cachée derrière un clic sur "Agrandir" ; "plein écran" utilise désormais l'API Fullscreen native du navigateur directement sur ce même panneau (`$refs.corps.requestFullscreen()`) plutôt que d'ouvrir une modale Flux à part ; échelle de base recalibrée à 0.4 (était 0.55, pensée pour la largeur d'une modale ~4xl, désormais pour la largeur plus étroite de la colonne latérale) ; un seul composant Alpine/une seule instance `apercu` au lieu de deux dupliquées (miniature + modale))
Pourquoi : demande explicite de l'utilisateur avec capture d'écran d'une maquette différente ("Tous les courriers") montrant un panneau "Aperçu du courrier" où la barre d'outils fait partie du panneau lui-même, jamais cachée derrière une action séparée — clarifié via AskUserQuestion que seul ce point (style du panneau d'aperçu) était visé, pas une refonte de la page "Tous les courriers" (structure de navigation différente de l'application réelle, hors périmètre). 353/353 tests, build Vite propre.

## [2026-09-17 19:20] Vraie cause de l'aperçu vide enfin identifiée avec certitude — Alpine + champs privés natifs de pdf.js
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (l'instance pdf.js (`window.DocumentPreview.creer()`) n'est plus stockée comme propriété de l'objet `x-data` (`this.apercu = ...`) — déplacée sur l'élément DOM brut lui-même (`this.$el._apercu = ...`), jamais suivi par la réactivité d'Alpine ; toutes les méthodes (`zoomer`, `rechercher`, etc.) mises à jour pour lire `this.$el._apercu` au lieu de `this.apercu`)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (hauteur de l'aperçu (image, conteneur PDF, état vide) augmentée de 5 % : h-64 (16rem/256px) -> h-67.25 (269px), demande explicite de l'utilisateur)
Pourquoi : l'utilisateur a de nouveau copié l'erreur exacte de la console ("TypeError: Cannot read private member #n from an object whose class did not declare it", à l'intérieur de `getPage`) — cette fois la vraie cause était identifiable avec certitude, pas une supposition. Alpine enveloppe TOUTE propriété d'un objet `x-data` dans un `Proxy` réactif (y compris les propriétés assignées après coup, comme `this.apercu = window.DocumentPreview.creer()`) ; les classes internes de pdf.js (PDFDocumentProxy/PDFPageProxy) utilisent de vrais champs privés ECMAScript (`#champ`), dont l'accès exige l'identité exacte de l'objet receveur — un `Proxy` n'est pas cette même identité, même s'il transmet fidèlement les accès aux propriétés normales, donc tout appel de méthode interne à pdf.js passant par le `Proxy` d'Alpine (au lieu de l'instance réelle) lève cette erreur précise. C'est un piège JavaScript documenté et connu (incompatibilité classique entre la réactivité par Proxy — Vue 3, MobX, Alpine — et les classes à champs privés natifs), pas une erreur de logique métier : aucune quantité de relecture du code n'aurait permis de le deviner sans le message d'erreur exact de la console. Corrigé en gardant l'instance pdf.js hors de portée de la réactivité d'Alpine (stockée sur `this.$el`, un nœud DOM brut, plutôt que sur `this`). 353/353 tests, build Vite propre.

## [2026-09-17 19:40] Zoom "100 %" recalculé pour remplir exactement la boîte (plus de défilement, plus de recadrage) + bug d'attribut corrigé
Fichier(s) : resources/js/document-preview.js (`charger(url, boite, conteneur, avecTexte)` — nouveau paramètre `boite` : lit `clientWidth`/`clientHeight` du conteneur visible et la dimension native de la page 1 (`getViewport({ scale: 1 })`) pour calculer `echelleBase`, l'échelle réelle correspondant à "100 %" — page entière visible, ni recadrée ni à l'étroit, avec une marge de 4 % ; `zoomer(pourcentage)` prend maintenant un POURCENTAGE relatif à `echelleBase`, plus une échelle pdf.js brute)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (panneau "Aperçu du document" : `echelleReelle()` (constante fixe 0.4) supprimée, `init()` appelle `charger(url, $refs.corps, $refs.conteneurPdf)` — `$refs.corps` sert de boîte de mesure ; `$refs.corps` passé de `overflow-auto` à `overflow-hidden` + `flex items-center justify-center` pour centrer la page sans jamais faire apparaître de barre de défilement)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (bug corrigé : un commentaire JS À L'INTÉRIEUR de l'attribut `x-data="..."` contenait des guillemets doubles littéraux ("100 %", "i don't want scrolling") — l'attribut HTML lui-même étant délimité par des guillemets doubles, ce guillemet à l'intérieur terminait l'attribut prématurément et faisait fuiter le reste du texte (code JS brut) directement dans la page, visible à l'écran ; commentaire retiré de l'intérieur de l'attribut)
Pourquoi : deux retours de l'utilisateur avec captures d'écran. (1) "the it is the image/pdf that fit it no space" + croix rouges montrant le document recadré en haut et une barre de défilement visible à 100 % — l'échelle de base précédente (0.4, une constante choisie à l'aveugle) ne correspondait à la taille réelle d'AUCUN conteneur en particulier ; désormais calculée dynamiquement depuis les dimensions réelles de la boîte et de la page, "100 %" remplit exactement l'espace disponible, par construction, quelle que soit la taille du panneau. (2) capture d'écran montrant du texte JavaScript brut affiché sur la page ("0) { this.$el._apercu.allerAuPremierResultat(); } }, }" x-init="init()" >") — piège HTML classique, même famille que le bug "@php écrit en toutes lettres dans un commentaire Blade" déjà rencontré sur cette page : un guillemet double À L'INTÉRIEUR d'un attribut lui-même délimité par des guillemets doubles casse l'attribut au niveau du PARSER HTML, avant même que le navigateur ne sache qu'il s'agissait d'un commentaire JS — plus aucun guillemet double ne doit désormais apparaître dans le texte d'un attribut x-data/x-on/x-init de ce fichier, commentaires inclus. 353/353 tests, build Vite propre, vue recompilée manuellement et vérifiée sans erreur de syntaxe.

## [2026-09-17 19:55] Écart minage/largeur toujours visible ("fit the scale") — la boîte épouse désormais exactement le rendu
Fichier(s) : resources/js/document-preview.js (`echelleBase` ne contraint plus que la LARGEUR (`boite.clientWidth / viewportNatif.width`), sans plus prendre le minimum avec la hauteur — la marge de 4 % retirée aussi, devenue inutile ; `rendre()` fixe désormais `boite.style.height` à `conteneur.scrollHeight` après chaque rendu (chargement initial ET chaque zoom), pour que la boîte suive exactement la hauteur réelle du contenu au lieu d'une hauteur de carte fixe qui laissait un espace vide sur la dimension non contraignante)
Pourquoi : capture d'écran de l'utilisateur ("i want the pdf to fit the scale") montrant le document bien affiché mais avec un espace blanc visible sous la page — la correction précédente contraignait à la fois largeur ET hauteur au minimum des deux (`Math.min`), ce qui garantissait l'absence de recadrage mais laissait nécessairement un vide sur la dimension la moins contraignante dès que le ratio largeur/hauteur de la page ne correspondait pas exactement à celui de la boîte fixe (h-74). En ne contraignant plus que la largeur et en laissant la hauteur de la boîte suivre le rendu réel, la page remplit maintenant tout l'espace disponible sans aucune bande vide, à tout niveau de zoom. 353/353 tests, build Vite propre.

## [2026-09-17 20:10] Bouton "Agrandir" restauré (ouvre une modale) + échelle bornée pour les contextes à hauteur plafonnée + robustesse des boutons de zoom/recherche
Fichier(s) : resources/js/document-preview.js (`charger()` gagne un 5e paramètre `limiterHauteur` (défaut false) : false = comportement de l'entrée précédente (largeur contrainte, hauteur de la boîte élargie pour épouser le rendu — panneau "Aperçu du document", hauteur libre) ; true = les DEUX dimensions contraignent l'échelle "100 %" (`Math.min`, avec 4 % de marge) et la boîte n'est plus élargie — nécessaire pour la modale "Agrandir", dont la hauteur est plafonnée à 75vh par sa propre mise en page et ne doit jamais être élargie par le rendu ; erreur explicite ("Conteneur d'aperçu introuvable dans la page") si `boite`/`conteneur` sont absents, au lieu du cryptique "Cannot read properties of undefined (reading 'clientWidth')" rencontré en conditions réelles)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (icône "plein écran" (API Fullscreen native) remplacée par un bouton "Agrandir" qui ouvre une nouvelle modale `apercu-agrandi` (fond blanc, sa propre barre d'outils recherche/zoom-/pourcentage/zoom+/télécharger/fermer, sa propre instance pdf.js sur `this.$el._apercu`, comme partout ailleurs sur cette page) ; les éléments de rendu de cette modale identifiés par `id` HTML simple (`document.getElementById`) plutôt que par `x-ref`/`$refs` Alpine — `$refs` ne retrouvait pas ces éléments ("Cannot read properties of undefined (reading 'clientWidth')" en conditions réelles), très probablement parce que le composant natif `<ui-modal>` de Flux déplace son contenu ailleurs dans le DOM une fois monté, hors du sous-arbre que `$refs` explore depuis le composant Alpine ancêtre — `getElementById()` cherche dans tout le document, peu importe où l'élément a fini par atterrir ; `zoomer()` du panneau "Aperçu du document" entoure désormais son appel à `apercu.zoomer()` d'un try/catch qui alimente `erreur` (déjà affichée dans le template), pour qu'un échec de zoom devienne visible au lieu de ressembler à un bouton qui ne répond pas)
Pourquoi : demande explicite de l'utilisateur ("now the button are not working and when we click on the agrandir button it should show a modal instead") — l'icône "plein écran" agrandissait la boîte du panneau latéral via l'API Fullscreen native, mais cette boîte est volontairement de petite taille (ajustée exactement au contenu, voir entrée précédente) : en plein écran, elle restait donc minuscule au milieu d'un fond noir, donnant l'impression que le bouton "ne marchait pas" — remplacé par une vraie modale, plus grande, avec ses propres contrôles. 353/353 tests, build Vite propre, vue recompilée manuellement et vérifiée sans erreur de syntaxe.

## [2026-09-17 20:20] Encore le même piège de guillemet dans un commentaire x-data — retiré pour de bon cette fois
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (commentaire JS multi-lignes à l'intérieur de `rendre()` de la modale "Agrandir" — contenait un guillemet double littéral juste avant "Cannot read" en citant le message d'erreur d'origine — remplacé par une seule ligne sans aucun guillemet double, renvoyant vers cette entrée du changelog pour le détail)
Pourquoi : l'utilisateur a de nouveau collé du texte JavaScript brut fuité sur la page — exactement le même mécanisme que les deux fois précédentes (comprend "@php"/"@json" écrits en toutes lettres dans un commentaire Blade, puis "try { ... }" inliné dans x-init, puis maintenant un guillemet double dans un commentaire x-data) : j'ai moi-même réintroduit ce piège dans LE MÊME message qui venait de l'expliquer, en citant le message d'erreur original entre guillemets doubles dans un commentaire à l'intérieur d'un attribut lui-même délimité par des guillemets doubles. Recherche systématique (`grep '//.*"'`) sur tout le fichier avant de reconstruire, pour vérifier qu'aucune autre occurrence ne subsiste — plus aucun commentaire explicatif détaillé à l'intérieur d'un attribut x-data/x-on/x-init de ce fichier ; toute explication de ce niveau de détail va désormais dans CHANGELOG-AGENT.md, jamais dans le code lui-même à cet endroit précis. 353/353 tests, build Vite propre, vue recompilée manuellement et vérifiée sans erreur de syntaxe.

## [2026-09-17 20:30] Document minuscule dans la modale "Agrandir" — la boîte de mesure n'avait presque aucune hauteur réelle avant le rendu
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (boîte `#apercu-agrandi-corps` : `max-h-[75vh]` -> `h-[70vh]` — une hauteur MAXIMALE (`max-height`) ne donne aucune géométrie réelle à un élément vide ; avant que la première page ne soit rendue dedans, cette boîte flex vide ne mesurait donc que quelques pixels (son padding), pas 75vh)
Pourquoi : capture d'écran de l'utilisateur montrant le document rendu minuscule au milieu d'une grande zone blanche vide dans la modale. Cause : `charger(..., limiterHauteur: true)` lit `boite.clientHeight` AVANT tout rendu pour calculer l'échelle "100 %" (`Math.min(largeur, hauteur)`, voir entrée du 2026-09-17 20:10) — avec `max-height` au lieu de `height`, cette lecture se faisait sur une boîte quasi vide (quelques pixels de padding, pas les 75vh visuellement attendus), d'où une échelle calculée ridiculement petite. Le panneau "Aperçu du document" (colonne latérale) n'avait pas ce problème parce qu'il utilise déjà une vraie classe de hauteur (`h-74`) comme valeur de repli avant que le JS ne la remplace. 353/353 tests, build Vite propre.

## [2026-09-17 20:40] Boutons de zoom : l'excédent était recadré (invisible) au lieu d'être défilable
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (les deux boîtes de rendu — panneau "Aperçu du document" et modale "Agrandir" — passées d'`overflow-hidden` à `overflow-auto`)
Pourquoi : demande explicite de l'utilisateur ("thhe zo;e button doesn't work"). Les boutons de zoom fonctionnaient réellement (le rendu était bien recalculé à une échelle plus grande), mais `overflow-hidden` recadrait silencieusement tout ce qui dépassait la boîte dès qu'on zoomait au-delà de 100 % — à "100 %" la page tient pile dans la boîte (voir les entrées précédentes sur l'ajustement de l'échelle), donc aucune barre de défilement n'apparaît dans ce cas, mais au-delà, l'excédent doit pouvoir être atteint en défilant plutôt que de simplement disparaître, sans quoi zoomer ne montre presque aucun changement visible utile — donnant l'impression que le bouton "ne marchait pas". 353/353 tests, build Vite propre, vue recompilée manuellement et vérifiée sans erreur de syntaxe.

## [2026-09-17 20:50] Taille d'affichage du canvas forcée en style inline — au cas où une règle CSS globale l'écraserait
Fichier(s) : resources/js/document-preview.js (`canvas.style.width`/`canvas.style.height` fixés explicitement en plus de `canvas.width`/`canvas.height`, qui ne définissent que la résolution du buffer de dessin, pas la taille AFFICHÉE)
Pourquoi : retour de l'utilisateur après confirmation que le zoom fonctionnait visuellement bien (200 % vs 50 % nettement différents, capture d'écran précédente) — nouveau doute sur le fait que le texte ne paraisse pas franchement plus grand à 200 %. Un `<canvas>` sans style CSS explicite s'affiche normalement au pixel près de la résolution de son buffer, mais si une règle CSS globale du projet (reset Tailwind sur les éléments type média, ex. `width: 100%`) s'appliquait à un `<canvas>`, l'affichage resterait étiré à la taille du conteneur quel que soit le buffer réellement dessiné — le zoom changerait alors la résolution interne sans que ça se voie. Correctif défensif : fixer la taille d'affichage en style inline (priorité maximale, aucune règle globale ne peut l'emporter), qui élimine cette classe de problème avec certitude, que ce soit ou non la cause exacte de ce dernier doute. 353/353 tests, build Vite propre.

## [2026-09-17 21:00] Vraie cause enfin isolée : Livewire remorphait le panneau et effaçait l'instance pdf.js entre deux clics
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (les deux wrappers `x-data` (miniature "Aperçu du document" et modale "Agrandir") reçoivent `wire:ignore` (Livewire ne remorphe plus jamais leur sous-arbre après le premier rendu) ET `wire:key="apercu-document-{id}"`/`wire:key="apercu-agrandi-{id}"` indexé sur l'id du brouillon (une clé différente prime sur wire:ignore : si l'agent choisit un autre document dans la liste déroulante, l'élément est bien recréé avec la nouvelle URL, seuls les remorphages SANS changement de document réel sont ignorés))
Pourquoi : l'utilisateur a précisé le symptôme exact après une question ciblée ("Percentage number changes, page stays the same size") — ça a permis d'isoler la vraie cause plutôt que de continuer à deviner : cette page contient de nombreux champs `wire:model.live` (Sens, Type de document, Confidentialité, etc.) qui déclenchent chacun un aller-retour réseau et un remorphage Livewire de TOUT le composant, y compris le panneau d'aperçu. `this.$el._apercu` (l'instance pdf.js) est une propriété JS posée à la main sur le nœud DOM, jamais suivie par Livewire — si un remorphage recrée ce nœud (ou l'un de ses descendants) à l'occasion d'une saisie sur un AUTRE champ du formulaire, cette propriété disparaît silencieusement. Le clic sur zoomer met bien à jour `this.zoom` (propriété Alpine ordinaire, donc le pourcentage affiché change normalement) AVANT de vérifier `if (! this.$el._apercu) return` — d'où le symptôme exact rapporté : le nombre change, la page reste identique, sans la moindre erreur visible. `wire:ignore` isole ce sous-arbre de tout remorphage Livewire lié au reste du formulaire ; `wire:key` garantit que le VRAI cas où le document doit changer (sélection d'un autre brouillon) continue de fonctionner. 353/353 tests, build Vite propre, vue recompilée manuellement et vérifiée sans erreur de syntaxe.

## [2026-09-17 21:15] Navigation par page (précédent/suivant) au lieu du défilement à travers les pages empilées
Fichier(s) : resources/js/document-preview.js (`rendre()` ne rend plus que `this.pageActuelle` — plus de boucle sur toutes les pages empilées verticalement ; nouvelles méthodes `pageSuivante()`/`pagePrecedente()`, nouvelles propriétés `pageActuelle`/`numPages`)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (panneau "Aperçu du document" ET modale "Agrandir" : nouveaux boutons chevron gauche/droite + indicateur "page X / Y", visibles uniquement si le document a plus d'une page (`x-show="numPages > 1"`), désactivés en butée de début/fin ; `pageActuelle`/`numPages` copiés dans des propriétés Alpine ordinaires après chargement/navigation pour que l'affichage réagisse — même raison que `zoom`, ces valeurs vivent sur l'instance pdf.js non suivie par Alpine)
Pourquoi : demande explicite de l'utilisateur ("remove the scroll down instead put a next and previous pdf with more than one page") — jusqu'ici un document de plusieurs pages les empilait verticalement dans le conteneur, nécessitant de défiler pour voir les pages suivantes ; remplacé par une vraie pagination (une page affichée à la fois), plus proche de l'usage réel (un courrier scanné multi-pages se consulte page par page, pas comme un flux continu). La recherche reste limitée à la page actuellement affichée (pas de saut automatique vers une autre page contenant une occurrence) — limitation acceptée, hors périmètre de cette demande. 353/353 tests, build Vite propre, vue recompilée manuellement et vérifiée sans erreur de syntaxe.

## [2026-09-17 21:35] "zoom change le nombre mais pas le fichier" de retour + image floue + barre de défilement non désirée à 100 % — cause de fond enfin traitée avec un registre indépendant du DOM
Fichier(s) : resources/js/document-preview.js (nouveau registre `Map` AU NIVEAU DU MODULE (jamais sur un nœud DOM) — `window.DocumentPreview.obtenir(cle)` remplace `creer()` : renvoie l'instance déjà existante pour cette clé si Livewire a recréé l'élément DOM hôte, au lieu d'une instance neuve dont l'état repartirait de zéro ; `charger()` détecte maintenant qu'un PDF est déjà chargé sur l'instance réutilisée et se contente de redessiner dans le (nouveau) conteneur à l'échelle/la page où l'utilisateur en était, sans refaire la requête réseau ; résolution du buffer de chaque `<canvas>` multipliée par `window.devicePixelRatio` (avec mise à l'échelle correspondante du contexte 2D) — sans ça, l'affichage restait net en apparence mais flou sur un écran haute densité ; marge de 2 % ajoutée à l'échelle "100 %" du panneau latéral (déjà en place pour la modale) pour absorber les écarts d'arrondi qui déclenchaient une barre de défilement même quand la page "devrait" tenir exactement)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (les deux boîtes de rendu : `overflow-hidden` par défaut, `overflow-auto` seulement si `zoom > 100` (`x-bind:class`) — plus de barre de défilement visible à 100 % ou moins ; `init()`/`rendre()` utilisent `window.DocumentPreview.obtenir(url + ':panneau'|':agrandi')` au lieu de `creer()`, et resynchronisent `zoom` depuis `echelle/echelleBase` de l'instance réutilisée (sinon l'étiquette recommencerait à "100 %" alors que le rendu resterait à l'ancien niveau de zoom) ; bug supplémentaire trouvé au passage dans la modale "Agrandir" : un garde-fou `this.charge` posé à `true` et JAMAIS remis à `false` empêchait toute réexécution de `rendre()` après la première fois — devenu inutile (et nuisible) avec la réutilisation d'instance, donc retiré ; `pageSuivante()`/`pagePrecedente()` entourées d'un try/catch qui alimente `erreur`, même traitement que `zoomer()`)
Pourquoi : retour de l'utilisateur montrant EXACTEMENT le même symptôme qu'avant le correctif `wire:ignore`/`wire:key` de l'entrée du 21:00 ("it increase the number but the file remain intact"), preuve que `wire:ignore` seul ne suffisait pas à protéger `this.$el._apercu` dans tous les cas — plutôt que de continuer à chercher pourquoi `wire:ignore` ne suffisait pas exactement, la instance a été déplacée dans un registre qui ne dépend d'AUCUN nœud DOM (donc insensible à n'importe quel remorphage Livewire, avec ou sans `wire:ignore`), ce qui règle la classe de bug entière plutôt qu'un cas précis. Piège rencontré et corrigé PENDANT cette même correction, avant qu'il ne parte en production : un commentaire ajouté à l'intérieur de l'attribut `x-data` de la modale citait `"this.charge"` entre guillemets doubles — même mécanisme que les fois précédentes, retiré avant reconstruction. 353/353 tests, build Vite propre, vue recompilée manuellement et vérifiée sans erreur de syntaxe.

## [2026-09-17 21:50] Zoom toujours sans effet visible — l'instance restait valide mais dessinait dans un nœud DOM détaché
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (miniature ET modale : `this.$el._apercu` — mis en cache une seule fois à l'initialisation — remplacé par une méthode `instance()` appelée à CHAQUE action (zoom, page suivante/précédente, recherche), qui fait `window.DocumentPreview.obtenir(cle)` ET resynchronise `apercu.boite`/`apercu.conteneur` sur les `$refs`/`getElementById()` ACTUELS avant de renvoyer l'instance — plus aucune référence DOM mise en cache nulle part dans ce composant)
Pourquoi : question ciblée à l'utilisateur ("what exactly is still broken") — réponse : "zoom still doesn't visibly enlarge the text", alors que le pourcentage affiché changeait bien et qu'aucune erreur ne s'affichait ni dans la page ni dans la console. Cause réelle, distincte du bug du registre corrigé à l'entrée précédente : le registre au niveau du module protégeait bien l'INSTANCE pdf.js elle-même contre un remorphage Livewire, mais `zoomer()`/`pageSuivante()`/`rechercher()` continuaient de lire `this.$el._apercu` — une référence mise en cache UNE SEULE FOIS, à l'initialisation. Si Livewire recréait le nœud DOM hôte ENTRE l'initialisation et un clic sur zoom (très probable sur cette page, riche en champs `wire:model.live`), l'instance elle-même restait valide (retrouvée via le registre) mais son `this.conteneur`/`this.boite` internes pointaient encore vers l'ANCIEN nœud, désormais détaché du document — `page.render()` réussissait alors silencieusement, en dessinant dans un élément invisible, personne à l'écran ne voyant jamais le changement. Corrigé en ne mettant plus JAMAIS en cache ni l'instance ni ses références DOM : chaque action les redemande à la source au moment où elle s'exécute. 353/353 tests, build Vite propre, vue recompilée manuellement et vérifiée sans erreur de syntaxe, aucun guillemet double détecté dans un commentaire à l'intérieur d'un attribut Alpine (vérification systématique avant reconstruction).

## [2026-09-17 22:05] Texte de code brut visible sur la page — guillemets doubles dans un commentaire à l'intérieur de x-data (modale "Agrandir")
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (bloc `x-data` de la modale "Agrandir", lignes ~681-688 : commentaire `// commentaire "instance()" et` supprimé — l'explication correspondante existe déjà dans les entrées précédentes de ce changelog, elle n'a pas besoin d'être répétée en commentaire inline dans un attribut Alpine)
Pourquoi : l'utilisateur a collé le texte brut affiché sur la page ("...apercu.allerAuPremierResultat(); } }, }" x-on:modal-show.document=..."), preuve directe que l'attribut `x-data` de la modale se terminait prématurément. Cause : un commentaire `//` ajouté juste avant la méthode `instance()` de cette modale contenait des guillemets doubles littéraux (`"instance()"`) — exactement le même piège que documenté dans ce fichier plusieurs fois déjà cette session (le guillemet double, même à l'intérieur d'un commentaire JS, termine l'attribut HTML `x-data="..."` au niveau du parseur HTML, avant même que le JS ne soit évalué), réintroduit ici par copier-coller du commentaire du panneau miniature sans remarquer les guillemets. Le panneau miniature lui-même ne contenait pas ce problème (vérifié par recherche, une seule occurrence dans tout le fichier). Corrigé en supprimant entièrement le commentaire plutôt qu'en l'reformulant sans guillemets, puisque l'explication est déjà tracée ici. 353/353 tests, build Vite propre, compilation Blade manuelle sans erreur, recherche `//.*"` sur tout le fichier : aucune occurrence restante.

## [2026-09-17 22:20] Pré-remplissage expéditeur incomplet — auto-présentation "Notre firme X" non reconnue (Module 1)
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (extraireExpediteurOrganisation() : ajout de "Notre firme/société/entreprise/groupe/compagnie/cabinet X" comme variante à la première personne de la formule d'auto-présentation déjà couverte à la 3e personne "La société X…"/"L'entreprise X…"/"Le groupe X…"/"La compagnie X…")
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (nouveau test couvrant cette variante, avec l'extrait réel du document TBG/CMR/NSIA)
Pourquoi : l'utilisateur a signalé que le formulaire d'enregistrement (Module 1) ne pré-remplissait presque rien depuis un vrai courrier ("The Best Group (TBG)", réf. 01/05/2026/TBG/CMR/NSIA) — seul le RC était proposé, Nom/Organisation/Coordonnées/NIU restaient vides. Diagnostic fait sur le texte OCR RÉEL du brouillon (id=31 en base, pas une supposition) : (1) Organisation — "Raison sociale :" existe en pied de page mais dans un bandeau clair-sur-sombre, OCR dégradé au point d'être illisible ("isles" au lieu de "Raison sociale") ; le corps du texte dit "Notre firme The Best Group (TBG) est spécialisée...", une formule d'auto-présentation à la première personne que le motif existant ne couvrait pas (seule la 3e personne "La société X" était reconnue) — VRAI manque corrigé ici, l'extraction retourne maintenant "The Best Group". (2) Coordonnées — téléphone/email en en-tête sont des lignes nues, sans "Tél :"/"Contacts :" devant, convention que le motif existant exige explicitement ; aucune convention fixe alternative n'existe pour un en-tête d'entreprise quelconque, donc pas de correction possible sans risquer une valeur inventée. (3) Nom (personne) — "Franck Fowa" apparaît seul, sous un titre ("Le Chef de Direction"), sans "Je soussigné"/"Signé :" ; hors périmètre assumé et documenté (voir commentaire de la fonction) car trop ambigu à généraliser. (4) NIU — le numéro ("M021912751106K", lui-même mal lu par l'OCR) n'est précédé d'aucune mention "NIU"/"Contribuable" dans ce document, seulement séparé du RC par un "|" — sans étiquette explicite, le système ne devine pas. Ces 3 derniers points restent des limites assumées de l'OCR/du best-effort (voir DECISIONS.md), pas des bugs — seul le point (1) était un vrai manque de couverture, corrigé. 354/354 tests (61/61 sur ProcessDocumentOcrTest, +1 nouveau test), `php -l` sans erreur.

## [2026-09-17 22:35] Objet/Concerne tronqué à la première ligne quand la phrase continue sur la suivante (Module 1)
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (extraireObjet() : extraction déplacée dans une nouvelle méthode extraireObjetDeLaMentionExplicite() qui recolle la ligne suivante quand la ligne captée se termine par une conjonction/préposition — jamais la fin naturelle d'une phrase en français — jusqu'à 4 lignes suivantes, en s'arrêtant dès qu'une ligne vide ou une fin de phrase normale est atteinte)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (nouveau test avec le texte OCR réel du document TBG/CMR/NSIA, plus la non-régression du test "Suite..." existant)
Pourquoi : l'utilisateur a signalé que "l'objet/concerne coupe encore" sur ce même document TBG/CMR/NSIA (voir entrée précédente) — le champ Concerne ("ACCOMPAGNEMENT DANS LA MISE EN PLACE DES SOLUTIONS INFORMATIQUES, DEMATERIALISATION OU\nDIGITALISATION DES PROCESSUS METIERS.") est physiquement coupé en deux lignes par la largeur de la page ; l'ancien motif `(.+)` ne traverse jamais un saut de ligne, donc l'objet proposé s'arrêtait à "...DEMATERIALISATION OU". Même classe de bug déjà rencontrée et corrigée le 2026-09-04 pour le repli SANS mention "Objet :" (document FORMAVISION.COM, fonction objetPresDeLaFormuleDappel()), mais jamais appliquée à la mention EXPLICITE "Objet :"/"Concerne :" elle-même — écart constaté seulement maintenant, sur ce nouveau document réel. Piège évité : un recollage inconditionnel de la ligne suivante casserait un test déjà existant et volontaire ("Objet: Réclamation sinistre auto\nSuite..." doit rester coupé, "Suite..." étant une nouvelle phrase du corps du courrier, pas une suite de l'objet) — la distinction retenue est purement grammaticale : ne recoller que si la ligne captée se termine par un mot qui ne peut jamais clore une phrase française (ou/et/de/du/des/la/le/à/en/avec/pour/sur/dans/par/ni/que/qui), jamais par heuristique de casse ou de longueur. Vérifié directement sur le texte OCR réel du brouillon id=31 avant et après le correctif. 355/355 tests (62/62 sur ProcessDocumentOcrTest, +1 nouveau test), `php -l` sans erreur.

## [2026-09-17 23:10] Séparation du champ "Coordonnées" en Téléphone/Email/Adresse (Module 1)
Fichier(s) : database/migrations/2026_09_17_120000_split_expediteur_coordonnees_into_telephone_email_adresse.php (nouvelle migration : colonnes expediteur_telephone/expediteur_email/expediteur_adresse, suppression de expediteur_coordonnees)
Fichier(s) : app/Models/Courrier.php ($fillable mis à jour)
Fichier(s) : app/Livewire/Backend/Forms/CourrierForm.php (3 propriétés + règles de validation remplacent expediteur_coordonnees ; expediteur_email validé au format email)
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (extraireExpediteurCoordonnees() remplacée par 3 fonctions dédiées : extraireExpediteurTelephone() — même marqueurs "Contacts :"/"Tél :" qu'avant, mais ne capture que le jeton numérique, pas le reste de la ligne ; extraireExpediteurEmail() — nouveau, format email seul sans marqueur requis ; extraireExpediteurAdresse() — marqueur "Adresse :"/"Siège :" ou repli sur un bloc "BP ...", avec retrait d'un label vide traînant type "- Mail:")
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (preremplirDepuisBrouillon() appelle les 3 nouvelles fonctions au lieu d'une seule)
Fichier(s) : app/Livewire/Backend/EditForm.php (mount() charge maintenant expediteur_telephone/email/adresse ; expediteur_rc/expediteur_niu ajoutés au passage — oubli réel préexistant du même bloc, jamais chargés dans le formulaire de modification malgré leur ajout le 2026-09-07)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (1 champ "Coordonnées" remplacé par 3 champs Téléphone/Email/Adresse, même style "à vérifier")
Fichier(s) : resources/views/livewire/frontend/editForm.blade.php (idem, sans le bandeau "à vérifier" propre à cet écran)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (1 bloc d'affichage "Coordonnées" remplacé par 3 blocs conditionnels Téléphone/Email/Adresse)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php, tests/Feature/Courriers/RegistrationFormTest.php, tests/Feature/Courriers/ShowCourrierTest.php, tests/Feature/Courriers/EditFormTest.php (tests mis à jour/ajoutés pour les 3 nouveaux champs, dont un nouveau test couvrant l'oubli rc/niu corrigé dans EditForm)
Pourquoi : demande explicite de l'utilisateur ("separate the coordonnee field so each of that thier own metadata insert like tel, email, etc that can be seen in the courier") — le champ libre unique mélangeait téléphone/email/adresse dans un seul texte à relire entièrement sur la fiche courrier, alors que RC/NIU avaient déjà été séparés en colonnes dédiées le 2026-09-07 pour la même raison. Chaque nouvelle fonction d'extraction vérifiée directement sur les vrais textes OCR déjà rencontrés cette session (document ITSC Sarl/NSIA pour téléphone/adresse, document TBG/CMR/NSIA pour l'email sans label) avant d'écrire les tests. 358/358 tests, build Vite propre, compilation Blade manuelle sans erreur sur les 3 vues modifiées, migration exécutée avec succès sur la base de dev.

## [2026-09-17 23:20] Panneau "Aperçu du document" fixe pendant le défilement de la page
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (div englobante du panneau "Aperçu du document" : classes `sticky top-6` ajoutées)
Pourquoi : demande explicite de l'utilisateur ("when am scrolling i dont want the apercu to scroll it should stay fixed where it is") — le panneau défilait avec le reste de la colonne latérale au lieu de rester visible à l'écran pendant que l'agent remplit le formulaire principal (colonne de gauche, plus longue). `position: sticky` plutôt que `fixed` : reste dans le flux normal de la colonne latérale (`space-y-6`), donc s'arrête de suivre le défilement une fois la fin de cette colonne atteinte, au lieu de rester affiché par-dessus le pied de page. Vérifié qu'aucun ancêtre de ce panneau (colonne, formulaire, `<flux:main>`, `<body>`) n'a de `overflow` qui aurait neutralisé le sticky — aucun trouvé, le défilement de la page se fait au niveau du document entier. 358/358 tests (inchangés, changement CSS uniquement), compilation Blade manuelle sans erreur.

## [2026-09-17 23:35] Panneau "Aperçu du document" toujours en train de défiler malgré le correctif précédent
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (`sticky top-6` déplacé de la carte interne vers la colonne latérale elle-même, avec `self-start` ajouté)
Pourquoi : l'utilisateur a signalé que le panneau défilait TOUJOURS après le correctif de l'entrée précédente. Cause probable : `position: sticky` borne son déplacement à la boîte de son PARENT DIRECT (le "containing block"), pas à celle d'un ancêtre plus lointain — posé sur la carte interne, son parent direct était la colonne latérale (`space-y-6`), dont la hauteur RÉELLE dépend d'un étirement CSS Grid implicite (`align-items: stretch`, comportement par défaut) pour atteindre la hauteur de la colonne principale — un mécanisme qui, en pratique sur cette page, ne donnait apparemment pas assez de marge (cause exacte non confirmée visuellement, aucun navigateur disponible dans cet environnement pour l'inspecter). Correctif plus robuste : `sticky` posé directement sur la colonne latérale, dont le parent direct est le `<form class="grid">` — sa hauteur est TOUJOURS au moins celle de l'étape active la plus longue, par construction du calcul des pistes CSS Grid, indépendamment de tout étirement ou alignement. `self-start` ajouté pour que la colonne garde sa hauteur naturelle (courte) plutôt que d'être elle-même étirée, sans conséquence sur la portée du sticky désormais bornée par le formulaire entier. 358/358 tests (inchangés), build Vite propre, compilation Blade manuelle sans erreur. Non vérifié visuellement dans un navigateur réel — à confirmer par l'utilisateur.

## [2026-09-17 23:45] Barre de navigation (navbar) fixe pendant le défilement de la page
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (les deux `<flux:header>` — navbar mobile ET navbar desktop — reçoivent l'attribut `sticky` intégré de Flux Pro)
Pourquoi : demande explicite de l'utilisateur ("and the navigation bar of the website too") — même besoin que le panneau "Aperçu du document" (entrées précédentes) mais pour la barre du haut, qui défilait avec le reste de la page. Flux fournit déjà un prop `sticky` dédié sur `<flux:header>` (vendor/livewire/flux/stubs/resources/views/flux/header.blade.php) — positionnement calculé dynamiquement via Alpine (`top: $el.offsetTop`, `max-height: calc(100vh - offsetTop)`), déjà testé/livré par le package, préféré à une classe `sticky` manuelle pour rester cohérent avec le composant. 358/358 tests, build Vite propre, compilation Blade manuelle sans erreur.

## [2026-09-17 23:55] Téléphone expéditeur reconnu sans marqueur via l'indicatif international africain (Module 1)
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (extraireExpediteurTelephone() : nouveau repli sans marqueur "Contacts :"/"Tél :" — un numéro préfixé "+" suivi d'un indicatif téléphonique reconnu de la constante INDICATIFS_TELEPHONIQUES_AFRICAINS, listant l'indicatif E.164 de chaque pays d'Afrique ; volontairement PAS étendu au préfixe "00", trop ambigu sans marqueur)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (nouveau test : Cameroun sans marqueur sur le vrai document TBG/CMR/NSIA, Ghana pour couvrir un autre pays que l'exemple de l'utilisateur, non-régression sur un indicatif hors Afrique (+33) et sur un numéro nu sans "+")
Pourquoi : demande explicite de l'utilisateur ("for the coordoee numbers i want you to also take into consideration those that have +237 as country code lets say all country code of africa") — même principe déjà appliqué à l'email (le format seul suffit, sans label) : un préfixe "+" suivi d'un indicatif téléphonique international reconnu est un signal aussi fiable qu'un marqueur explicite. Vérifié directement contre le texte OCR réel du document TBG/CMR/NSIA, où les deux premiers numéros de l'en-tête sont trop dégradés pour être lisibles mais le troisième ("+237 694 006 485") survit intact sans aucun marqueur à proximité — jusqu'ici toujours ignoré par manque de marqueur, maintenant proposé. 359/359 tests (64/64 sur ProcessDocumentOcrTest, +1 nouveau test), `php -l` sans erreur.

## [2026-09-18 00:05] Panneau "Aperçu du document" collé sous la navbar désormais fixe, sans espace visible
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (offset sticky de la colonne latérale : `top-6` → `top-20`)
Pourquoi : demande explicite de l'utilisateur ("on the apercu du document the top there should have space between the header and the card panel like 10-20px") — conséquence directe de l'entrée précédente (navbar rendue `sticky` via `<flux:header>`) : les DEUX éléments sont maintenant fixes en haut de l'écran, mais la colonne latérale se figeait à seulement 24px (`top-6`) du haut du viewport — moins que la hauteur réelle de la navbar (`min-h-14` = 56px + padding) — donc passait EN PARTIE SOUS elle au lieu de s'arrêter juste en dessous avec un espace visible. Offset augmenté à `top-20` (80px), suffisant pour dégager la navbar avec la marge demandée. 359/359 tests (inchangés, changement CSS uniquement), build Vite propre, compilation Blade manuelle sans erreur. Non vérifié visuellement dans un navigateur réel — à confirmer par l'utilisateur.

## [2026-09-18 00:20] Panneau "Aperçu du document" retouchait encore la navbar en fin de défilement — passage de sticky à fixed
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (colonne latérale : `sticky top-20 self-start` retiré ; la carte "Aperçu du document" est désormais enveloppée dans un nouveau bloc `wire:ignore` + `x-data` qui la positionne en `position: fixed` réel (jamais `sticky`), avec un espaceur invisible (`x-ref="espace"`) qui réserve sa place dans le flux normal de la colonne et dont la hauteur est resynchronisée en continu via `ResizeObserver` à chaque changement de hauteur du panneau — zoom, page suivante/précédente, chargement initial du PDF)
Pourquoi : demande explicite de l'utilisateur ("it work wells when we scroll still we reach the end it touched again the navigation bar i want it to be consistent") — cause confirmée : `position: sticky` reste borné par la hauteur de son "containing block", ici le `<form>` du wizard, dont la hauteur varie selon l'ÉTAPE ACTIVE (une seule étape a une hauteur réelle à la fois, les autres sont en `display:none` via `x-show`) — sur une étape plus courte que ce panneau (étape 1 "Scan/Import", 3 "Pièces jointes"...), le panneau manquait de place pour rester collé et se faisait repousser vers le haut (donc vers la navbar) avant la fin réelle du défilement de la page. `position: fixed` n'est borné par AUCUN ancêtre (seulement par le viewport), donc reste à un offset constant depuis la navbar quelle que soit l'étape active ou sa hauteur — règle la classe de bug entière plutôt qu'un cas précis, même logique que le passage du panneau pdf.js à un registre indépendant du DOM plus tôt dans cette session. Position horizontale (gauche/largeur) mesurée sur l'espaceur AVANT de fixer le panneau (mesurer le panneau lui-même après l'avoir fixé aurait renvoyé sa position fixe, pas sa position naturelle) ; désactivé sous 1024px (breakpoint `lg`) où les deux colonnes s'empilent normalement. 359/359 tests (inchangés, changement CSS/JS uniquement), build Vite propre, compilation Blade manuelle sans erreur, recherche `//.*"` sur tout le fichier : aucune occurrence. Non vérifié visuellement dans un navigateur réel — à confirmer par l'utilisateur.

## [2026-09-18 00:35] NIU reconnu sans étiquette quand il est juste à côté du RC (Module 1)
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (extraireExpediteurNiu() : nouveau repli — sans "NIU :"/"Contribuable :" — un jeton commençant par "P" ou "M" suivi d'au moins 6 chiffres consécutifs, immédiatement après le RC sur la même ligne)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (nouveau test : cas propre avec/sans séparateur "|", et deux garde-fous — un RC seul, et un mot français ordinaire comme "Monsieur" juste après le RC, qui ne doivent jamais être proposés comme NIU)
Pourquoi : demande explicite de l'utilisateur ("for the niu since it always start either with P or M is always beside RC take that in consideration as an option too") — même principe que le téléphone/email déjà étendus sans marqueur : un format suffisamment distinctif (ici "P"/"M" + longue suite de chiffres) vaut autant qu'une étiquette explicite. Exige au moins 6 chiffres consécutifs (pas juste `[\w]`) pour ne jamais confondre avec un mot ordinaire commençant par la même lettre juste après le RC dans une phrase (ex. "RC/YAO/.../433 Monsieur Jean..."). Vérifié que le document réel TBG/CMR/NSIA ("RC/DLA/2019/B/942 | M021912751106K", sans aucune étiquette) NE matche PAS avec ce motif — pas un défaut de la regex : l'OCR réel de CE document dégrade ce numéro précis au point de le rendre illisible ("MO2191275N0GK" au lieu de "M021912751106K", O/0 et lettres/chiffres mélangés de façon irrégulière) — limite d'OCR déjà documentée dans l'entrée du 2026-09-17 concernant ce même bandeau clair-sur-sombre, pas un nouveau problème ; le test de non-régression utilise donc un OCR propre reproduisant la même convention plutôt que ce texte précis. 360/360 tests (65/65 sur ProcessDocumentOcrTest, +1 nouveau test), `php -l` sans erreur.

## [2026-09-18 00:35] Bandeau "proposé automatiquement" mentionnait encore l'ancien champ unique "coordonnées"
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (texte du bandeau d'avertissement de l'étape 1 : "coordonnées de l'expéditeur" → "téléphone/email/adresse de l'expéditeur")
Pourquoi : oubli constaté par l'utilisateur via une capture d'écran de l'application — ce texte informatif datait d'avant la séparation du champ "Coordonnées" en Téléphone/Email/Adresse (entrée du 2026-09-17 23:10) et n'avait pas été mis à jour à cette occasion, alors que tous les autres usages (formulaire, fiche courrier, formulaire de modification) l'avaient été. 360/360 tests, build Vite propre, compilation Blade manuelle sans erreur.

## [2026-09-18 00:50] Panneau "Aperçu du document" : retour à `position: fixed`, après une demande de retour en arrière puis de nouveau la même demande
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (même mécanisme `wire:ignore` + `x-data` + espaceur que l'entrée du 2026-09-18 00:20, réintroduit après un revert entre-temps, avec une différence : la hauteur de l'espaceur se resynchronise désormais sur l'évènement `apercu-taille-changee` plutôt que sur un `ResizeObserver` générique)
Fichier(s) : resources/js/document-preview.js (rendre() émet désormais `window.dispatchEvent(new CustomEvent('apercu-taille-changee'))` après chaque rendu — zoom, page suivante/précédente, chargement initial — choix délibéré : un seul point d'émission dans le module plutôt qu'un ResizeObserver DOM générique, plus direct à raisonner et déjà au bon endroit puisque TOUS les changements de hauteur du panneau passent par cette méthode)
Pourquoi : l'utilisateur avait d'abord demandé de revenir à l'état d'avant toute tentative de fixation ("revert back to where it was first"), puis a redemandé explicitement le même comportement ("make it fixed so that when i scroll down it shouldn't cross the navigation bar... and there must be some gap between the navigation bar and the apercu du document card") — le retour en arrière semblait donc destiné à obtenir un état de référence propre plutôt qu'à signaler un défaut précis de la tentative précédente (aucun défaut concret n'a pu être identifié via les questions de clarification posées, l'utilisateur ayant répondu "Something else" sans détail les deux fois). Réimplémenté avec le même raisonnement que l'entrée précédente (sticky insuffisant car borné par la hauteur variable de l'étape active du wizard ; fixed non borné). 360/360 tests, build Vite propre, compilation Blade manuelle sans erreur, `node --check` sur le fichier JS modifié sans erreur, recherche `//.*"` sur le fichier Blade : aucune occurrence. Non vérifié visuellement dans un navigateur réel (aucun navigateur disponible dans cet environnement) — à confirmer par l'utilisateur.

## [2026-09-18 01:05] Grand vide entre le panneau fixe et la boîte "Courrier confidentiel" ("scatter")
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (boîte "Courrier confidentiel" déplacée hors de la colonne latérale — désormais un bloc pleine largeur sous le `<form>`, plutôt qu'un second enfant de la même colonne que le panneau fixe ; espaceur `espace` simplifié — ne réserve plus que largeur/position horizontale, plus de synchronisation de hauteur)
Fichier(s) : resources/js/document-preview.js (dispatch de `apercu-taille-changee` retiré de rendre() — plus aucun écouteur ne s'y intéresse)
Pourquoi : l'utilisateur a montré une capture d'écran ("look how it is scatter") sur une page sans document (brouillon vide) : le panneau "Aperçu du document" (court, juste "Aucun document pour l'instant") s'affichait correctement, mais la boîte "Courrier confidentiel" apparaissait très loin en dessous, avec un grand vide entre les deux. Cause : l'espaceur de la colonne latérale réservait sa hauteur en copiant `panneau.offsetHeight` à un instant donné, valeur qui pouvait ne plus correspondre à la hauteur RÉELLEMENT affichée du panneau une fois celui-ci passé en `position: fixed` (hors flux) — tout écart entre les deux se traduisait par un vide (ou un chevauchement) visible juste en dessous. Plutôt que de chercher à corriger cette synchronisation une nouvelle fois (déjà tentée avec un `ResizeObserver` puis avec l'évènement `apercu-taille-changee`, sans succès visuel confirmé), la boîte "Courrier confidentiel" a été sortie de cette colonne : plus RIEN dans la colonne ne dépend de la hauteur du panneau (la largeur de la colonne vient du gabarit `grid-template-columns` du `<form>`, jamais de son contenu), donc cette classe de bug ne peut plus se reproduire, quelle que soit la hauteur réelle du panneau. 360/360 tests, build Vite propre, compilation Blade manuelle sans erreur, `node --check` sans erreur, recherche `//.*"` : aucune occurrence. Non vérifié visuellement dans un navigateur réel — à confirmer par l'utilisateur.

## [2026-09-18 01:15] Panneau "Aperçu du document" : retour de `position: fixed` à `sticky`, sur demande explicite de l'utilisateur
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (tout le mécanisme JS de positionnement fixe — `wire:ignore`, `x-data` avec gauche/largeur/fixe/positionner()/activer(), espaceur `x-ref="espace"` — retiré ; remplacé par `sticky top-20 self-start` sur la colonne latérale elle-même, même motif déjà utilisé et validé le 2026-09-17 23:35 avant le passage à `fixed`. Boîte "Courrier confidentiel" remise dans cette colonne, juste après le panneau — son déplacement en dehors n'était nécessaire que pour contourner le bug de synchronisation de hauteur propre à `fixed` (entrée précédente), `sticky` ne retire jamais le panneau du flux normal donc n'a jamais eu ce problème)
Pourquoi : demande explicite de l'utilisateur ("please revert the apercu du document panel back to sticky"), après avoir qualifié le comportement de `position: fixed` de "flottant" ("floting") — un aspect inhérent à `fixed` (l'élément passe visuellement AU-DESSUS du contenu qui défile dessous, comme la navbar elle-même) que l'utilisateur ne souhaite finalement pas, malgré la demande explicite d'un comportement "fixed" dans les messages précédents. Limite connue de `sticky`, réexpliquée dans le commentaire Blade : reste borné par la hauteur de l'étape active du wizard, peut décrocher avant la fin du défilement sur une étape courte — compromis accepté par l'utilisateur en échange de l'abandon de l'aspect "flottant". 360/360 tests, build Vite propre, compilation Blade manuelle sans erreur, recherche `//.*"` : aucune occurrence.

## [2026-09-18 01:15] Bordures rouge/vert sur les champs obligatoires du formulaire d'enregistrement (Module 1)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (les 9 champs `required` de l'étape "Informations générales" — sens, priorité, confidentialité, date_mouvement, service_id, objet, type_document [saisie libre ET liste déroulante], mode_reception — reçoivent `invalid:border-brand-danger valid:border-brand-success`, via `class` pour les `<flux:select>` et `class:input` pour les `<flux:input>`)
Pourquoi : demande explicite de l'utilisateur ("add the warning colors on the fill if it is not fill a required it fill red and green for fill and correct") — s'appuie sur les pseudo-classes CSS natives `:invalid`/`:valid` (variantes Tailwind `invalid:`/`valid:`), qui reflètent directement la contrainte HTML5 `required` déjà présente sur ces champs — aucun état à gérer manuellement en JS, la couleur suit automatiquement si le champ a une valeur ou non. Recherche effectuée au préalable (agent dédié) pour confirmer où `<flux:input>`/`<flux:select>` appliquent les classes passées : `<flux:select>` les pose directement sur le `<select>` natif via `class`, mais `<flux:input>` les pose sur un `<div>` englobant — `class:input` (transmission Flux dédiée à l'élément natif) utilisée à la place pour les `<flux:input>`, sans quoi `:invalid`/`:valid` n'aurait jamais matché quoi que ce soit. Les champs déjà pourvus d'une valeur par défaut (sens, priorité, confidentialité, mode_reception) apparaissent verts dès le chargement — cohérent avec la sémantique HTML5 (ils ONT une valeur valide), pas un défaut. 360/360 tests, build Vite propre, compilation Blade manuelle sans erreur.

## [2026-09-18 01:30] Boîte "Courrier confidentiel" ne suivait pas le panneau fixé par `sticky` — chevauchement à l'écran
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (`sticky top-20 self-start` déplacé du wrapper interne du panneau vers la colonne latérale ENTIÈRE (`space-y-6`) — panneau ET boîte "Courrier confidentiel" collent désormais ensemble, comme avant le passage à `position: fixed` du 2026-09-18)
Pourquoi : demande explicite de l'utilisateur ("make the courrier condentiel sticky too"), confirmée par une capture d'écran montrant le bouton "Voir la procédure" remonté et chevauchant le panneau — en réintroduisant `sticky` (entrée du 2026-09-18 01:15), la classe avait été posée par erreur sur un wrapper interne n'englobant QUE le panneau (pas la colonne entière comme documenté dans le commentaire Blade lui-même, qui décrivait le bon comportement sans qu'il soit réellement appliqué) : la boîte "Courrier confidentiel", restée un enfant normal du flux, défilait donc seule au lieu de rester collée avec le panneau. 360/360 tests, build Vite propre, compilation Blade manuelle sans erreur.

## [2026-09-18 01:30] Adresse non détectée quand "BP" est suivi d'une virgule avant le numéro
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (extraireExpediteurAdresse() : le motif de repli "BP ..." tolère désormais une virgule/point-virgule/deux-points optionnel entre "BP" et le numéro)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (nouveau test avec exemple propre "BP, 4568 Yaoundé Cameroun")
Pourquoi : l'utilisateur a signalé des champs non pré-remplis sur un nouveau document réel ("MEGATIM"/"Cauris Digital", destinataire AFG BANK) — diagnostic sur le texte OCR réel du brouillon (id=32) : Adresse restait vide parce que le pied de page écrit "BP, 4568, Buc Ngousso..." avec une virgule juste après "BP", que l'ancien motif (qui n'acceptait qu'un espace) ne reconnaissait pas. Corrigé pour ce cas général. Transparence sur ce document précis : même corrigé, le résultat reste un bloc peu propre ("BP, 4568, Buc Ngousso 1303, BP; Carrefour dokago, + dorriera BP: 14761, 1762 Rue Moukoukoulou,") car son pied de page fusionne TROIS adresses d'agences différentes (Yaoundé/Douala/Brazzaville) sur une seule ligne — limite du contenu source, pas de la regex, qu'aucune correction raisonnable ne peut résoudre sans risquer d'inventer un découpage. Les autres champs vides sur ce document (Organisation, Nom, RC, NIU, Destinataire) restent des limites assumées déjà documentées : aucune convention reconnue (Raison sociale/auto-présentation/forme juridique) ne nomme l'expéditeur ailleurs que dans un logo illisible par l'OCR et des domaines email eux-mêmes orthographiés différemment ("meyatimgroup.com" vs "megatimgroup.com") — deviner à partir d'un domaine email irait à l'encontre du principe "jamais une valeur inventée" déjà appliqué partout ailleurs dans ce fichier. 361/361 tests, `php -l` sans erreur.

## [2026-09-18 01:40] Impossible d'avancer à l'étape suivante du wizard si un champ obligatoire est vide/invalide
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (méthode suivant() du x-data racine du `<section>` : appelle désormais `formulaire.reportValidity()` sur le `<form>` avant d'incrémenter `etape` — bloque et affiche la bulle de validation native du navigateur sur le premier champ invalide si `reportValidity()` renvoie `false`, n'avance jamais dans ce cas)
Pourquoi : demande explicite de l'utilisateur ("put restrictions if one required fill is red or not fill the can't go to the next step"), suite à l'ajout des bordures rouge/vert sur les champs obligatoires (entrée du 2026-09-18 01:15) — la couleur seule n'empêchait pas de continuer malgré un champ manquant. S'appuie sur une propriété peu connue mais standard de la validation de contraintes HTML5 : un champ à l'intérieur d'un ancêtre `display:none` (ici, les étapes du wizard NON actives, cachées via `x-show`) est automatiquement EXEMPTÉ de la validation — `reportValidity()` sur le `<form>` entier ne valide donc, à tout instant, que les champs de l'étape RÉELLEMENT visible, sans qu'il soit nécessaire de restreindre manuellement la vérification à un sous-ensemble de champs. Vérifié qu'aucun champ `required` n'existe hors de l'étape "Informations générales" (la seule à en avoir, 9 champs) — les étapes 1/3/4 ne sont donc jamais bloquées par ce garde-fou. Non couvert par les tests PHPUnit (comportement purement côté navigateur, Livewire::test() n'exécute pas Alpine/JS) — la validation SERVEUR (`$this->form->validate()` dans enregistrer(), Règle n°6) reste le filet de sécurité réel et déjà testé ; ce garde-fou est une amélioration UX, pas un remplacement. 361/361 tests (inchangés), build Vite propre, compilation Blade manuelle sans erreur.

## [2026-09-18 01:55] "Suivant" bloqué dès l'étape 1 — l'hypothèse "display:none exempte de la validation" de l'entrée précédente était fausse
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (méthode suivant() du x-data racine : `formulaire.reportValidity()` sur le `<form>` entier remplacé par une vérification manuelle, champ par champ, restreinte à ceux réellement visibles — `champ.offsetParent !== null` — puis `reportValidity()` appelé sur CE champ précis seulement)
Pourquoi : l'utilisateur a signalé "Suivant" bloqué dès l'étape 1 (aucun champ obligatoire) une fois un document déjà choisi. Cause réelle : contrairement à l'hypothèse de l'entrée précédente, un ancêtre `display:none` (les étapes du wizard non actives, via `x-show`) N'EXEMPTE PAS un champ de la validation de contraintes HTML5 — seuls un ancêtre `<datalist>`, `disabled`, `readonly` (sur certains types) ou `type="hidden"` en exemptent réellement. `reportValidity()` sur le `<form>` entier validait donc TOUJOURS les 9 champs obligatoires de l'étape "Informations générales" — vides par défaut au premier chargement — même en étant sur l'étape 1, bloquant "Suivant" immédiatement. Corrigé en filtrant explicitement sur la visibilité RÉELLE (`offsetParent !== null`, qui est bien `null` pour tout élément à l'intérieur d'un ancêtre `display:none`, contrairement à la validation de contraintes) avant de tester `checkValidity()`. Piège rencontré ET corrigé pendant cette même correction, avant qu'il ne reste en production : le commentaire d'explication ajouté juste avant la méthode, à l'intérieur de l'attribut `x-data`, citait `"Suivant"` entre guillemets doubles — même mécanisme que documenté plusieurs fois cette session (un guillemet double dans un commentaire `//` à l'intérieur d'un attribut `x-data` termine l'attribut HTML au niveau du parseur, avant même l'évaluation du JS) — l'utilisateur a d'ailleurs collé le texte brut qui en résultait, preuve directe. Corrigé en supprimant le commentaire (l'explication vit désormais uniquement ici). 361/361 tests, build Vite propre, compilation Blade manuelle sans erreur, recherche `//.*"` sur tout le fichier : aucune occurrence restante.

## [2026-09-18 02:15] Bandeau de pied de page (RC/NIU) illisible par l'OCR — second passage sur image inversée, seuil de confiance filtré
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (ocrFichier() : nouvel appel à texteBanniereInversee() par page, dont le résultat est ANNEXÉ au texte de la passe normale ; extraireTexteEtConfiance() : nouveau paramètre optionnel $seuilConfianceMot, défaut 0 — comportement d'origine inchangé pour tout appel existant ; nouvelles méthodes texteBanniereInversee() et inverserImage() (GD, `imagefilter(IMG_FILTER_NEGATE)`, PNG/JPEG))
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (nouveau test du filtrage par seuil de confiance sur extraireTexteEtConfiance())
Pourquoi : l'utilisateur a montré une capture zoomée du bandeau de pied de page d'un vrai document (MEGATIM/AFG BANK, "RC/YAO/2022/8/1207 ; NO C : M0622174O0487T") prouvant que le texte est bien lisible à l'œil nu dans le PDF source, alors que le texte OCR extrait ne contenait ni "RC" ni "NIU" à cet endroit — texte blanc sur fond bleu marine, même famille de problème que le bandeau "Raison sociale" illisible du document TBG/CMR/NSIA (entrée du 2026-09-17). Confirmé avec l'utilisateur (AskUserQuestion) avant d'implémenter : la correction double le temps de traitement OCR de CHAQUE document (un second passage Tesseract complet par page), pas seulement ceux ayant ce problème — accepté. Vérifié directement en réexécutant ocrFichier() sur le vrai fichier du brouillon concerné (id=32, toujours en base) : le second passage a bien produit du texte supplémentaire, mais celui-ci ne contient PAS le bandeau RC/NIU recherché — la couleur n'était donc pas la seule cause. 362/362 tests, `php -l` sans erreur.

## [2026-09-18 02:15] Rendu PDF→image porté de 300 à 600 dpi — le bandeau restait illisible même inversé
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (convertirPdfEnImages() : `-r300` → `-r600`, timeout Ghostscript 60s → 120s ; nouvelle méthode augmenterLimiteMemoireSiNecessaire(), appelée en tête d'ocrFichier() ; ProcessDocumentOcr::$timeout et ProcessBrouillonOcr::$timeout ajoutés, 300s)
Pourquoi : le second passage OCR (entrée précédente) n'ayant pas résolu le bandeau RC/NIU, hypothèse retenue après discussion avec l'utilisateur : le texte du bandeau est trop fin en HAUTEUR DE PIXELS à 300 dpi pour que l'analyse de mise en page de Tesseract le reconnaisse même comme une ligne de texte, indépendamment de sa couleur — confirmé avec l'utilisateur (AskUserQuestion) avant d'implémenter, même accord sur le coût (temps de traitement plus long, fichiers intermédiaires ~4x plus lourds, POUR CHAQUE document). Piège réel rencontré ET corrigé en rejouant le test sur le vrai document : à 600 dpi, les images PNG intermédiaires sont assez lourdes une fois décompressées par GD (inverserImage()) pour épuiser la limite mémoire PHP par défaut (128M), faisant planter le job avec une erreur fatale — corrigé en relevant CETTE limite (ini_set, processus courant uniquement, jamais si une limite plus généreuse — dont "-1", illimité — est déjà configurée). `$timeout` explicite ajouté sur les deux Jobs concernés (300s) : le timeout par défaut d'un worker de queue (souvent 60s) ne suffit plus avec un rendu 600 dpi + double passage Tesseract, risquant de faire tuer puis réessayer un job en cours d'exécution normale. Résultat vérifié en rejouant l'OCR sur le vrai document (brouillon id=32) : un NOUVEAU fragment auparavant illisible est bien récupéré ("Ref. : N°19/MGT/DG/RAF/16022026", une référence interne à l'expéditeur — pas un champ actuellement extrait par ce système, qui génère toujours son propre numéro de référence, voir PRD.md) — preuve que le passage à 600 dpi aide réellement en général — MAIS le bandeau RC/NIU recherché reste illisible même à cette résolution ("cU HU Qudriies Alisatla 2 leu el Carlow: Hache GPs ABA Vary..." — toujours du bruit, pas de "RC" ni de suite de chiffres reconnaissable). Conclusion transparente communiquée à l'utilisateur : ce bandeau précis dépasse probablement ce qu'une amélioration du pipeline OCR peut résoudre de façon fiable (qualité d'impression/taille de police à la source, pas seulement résolution ou couleur) — les deux améliorations (inversion + 600 dpi) sont conservées car bénéfiques en général (le nouveau fragment récupéré en est la preuve), mais ce cas précis nécessite une saisie manuelle du RC/NIU par l'agent. 362/362 tests, `php -l` sans erreur.

## [2026-09-18 02:30] Récapitulatif de l'étape "Validation" affichait "—" pour Date/Expéditeur/Destinataire malgré une saisie manuelle
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (méthode suivant() du x-data racine : `this.$wire.$refresh()` ajouté après l'incrément de `etape`)
Pourquoi : l'utilisateur a rempli Date de réception/Expéditeur/Destinataire lui-même, mais le récapitulatif de l'étape "Validation" continuait d'afficher "—" pour ces trois champs. Cause : la navigation entre étapes (`suivant()`/`precedent()`/`aller()`) est purement côté client (Alpine `x-show`, voir le commentaire en tête du fichier — "aucun champ n'est retiré du DOM... rien ne se perd en changeant d'étape") — elle ne déclenche AUCUNE requête Livewire. Ces trois champs utilisent `wire:model` SANS `.live` (contrairement à sens/confidentialité/type_document) : Livewire ne synchronise leur valeur vers le serveur qu'au prochain aller-retour réseau — si aucun AUTRE champ `.live` n'a été modifié entre la saisie et l'arrivée sur l'étape 4, le récapitulatif (`{{ $form->date_mouvement ?: '—' }}` etc., rendu côté serveur) reflète encore l'état d'AVANT la saisie, alors que le champ lui-même affiche bien la valeur tapée à l'écran (jamais perdue, seulement pas encore remontée au serveur). Corrigé en forçant un aller-retour Livewire (`$wire.$refresh()`, la magie Alpine `$wire` déjà exposée automatiquement dans un composant Livewire) après chaque changement d'étape réussi — synchronise tous les champs `wire:model` en attente et re-rend le récapitulatif à jour, sans changer le comportement déféré (toujours pas de requête à chaque frappe) qui motivait l'absence de `.live` sur ces champs au départ. 362/362 tests, build Vite propre, compilation Blade manuelle sans erreur, recherche `//.*"` : aucune occurrence.

## [2026-09-18 02:45] Nouvelle sidebar globale et refonte de "Tous les courriers" (maquette fournie par l'utilisateur, 2026-09-18)
Fichier(s) : resources/views/components/sidebar-item-a-venir.blade.php (nouveau composant : entrée de sidebar visuellement désactivée + badge "Bientôt", réutilisée pour tout module de la maquette sans page réelle derrière)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (structure de sidebar entièrement remplacée : groupes Général/Courriers/Dossiers & Archives/Recherche/Administration/Statistiques & Rapports/Notifications/Paramètres/Aide)
Fichier(s) : app/Livewire/Backend/CourrierList.php (nouvelles propriétés `priorite`/`periode` (préréglages de dates) ; méthodes `ouvrirApercu()`/`fermerApercu()` + computed `courrierApercu()` pour le nouveau panneau latéral ; computed `statistiques()` pour les 4 cartes ; logique de périmètre par profil extraite dans `portee()`, réutilisée par `resultats()` ET `statistiques()` pour ne jamais dupliquer/désynchroniser cette règle de sécurité)
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (mise en page entièrement remplacée : en-tête + boutons, 4 cartes statistiques, recherche + filtres rapides, filtres avancés existants conservés repliés, tableau avec colonne Destinataire + menu Actions, panneau "Aperçu du courrier" au clic sur une ligne réutilisant document-preview.js déjà construit pour registrationForm.blade.php)
Pourquoi : demande explicite de l'utilisateur, avec deux points clarifiés via AskUserQuestion avant d'implémenter : (1) les modules de la maquette sans page réelle (Dossiers & Archives, Organisation, Automatisation, Workflows, SLA & Alertes, Sécurité & Audit, Statistiques en page dédiée, Notifications, Aide — voir DECISIONS.md, tous "net nouveau — rien construit à date") sont inclus quand même dans la sidebar mais visiblement marqués "Bientôt", jamais omis ni pointés vers une page inexistante ; (2) les 4 cartes de "Tous les courriers" ("Total/En traitement/Terminés/En erreur") et le filtre "Fonctionnalité" de la maquette ne correspondaient à aucune donnée réelle — adaptés aux statuts et champs qui existent vraiment (En erreur = échec OCR, la seule vraie notion d'erreur du schéma ; Fonctionnalité → Type de document, un filtre réel). Liens réels déjà construits avant cette maquette (courrier confidentiel, mes courriers, courriers enregistrés) conservés, réorganisés sous la nouvelle structure plutôt que supprimés. Bouton "Supprimer" de la maquette volontairement OMIS du panneau d'aperçu : `CourrierPolicy` n'a aucune capacité `delete` (Règle n°5 CLAUDE.md — jamais de suppression physique sans un flux de validation dédié qui n'existe pas encore) ; l'ajouter aurait fabriqué une action non autorisée. 362/362 tests, build Vite propre, compilation Blade manuelle sans erreur sur les 3 fichiers Blade modifiés.

## [2026-09-18 03:00] Sidebar alignée sur la spécification globale détaillée fournie par l'utilisateur (permissions/scope par rôle, un seul composant)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php ("Recherche" → "Recherche avancée" ; "Statistiques & Rapports" scindé en 2 entrées "Bientôt" (Dashboard statistiques/Rapports) ; nouveau groupe "Notifications" de premier niveau (était seulement la cloche de la navbar) ; "Mon compte" renommé "Paramètres" ; "Aide" devient son propre groupe de premier niveau plutôt qu'un sous-élément de "Mon compte")
Pourquoi : l'utilisateur a fourni une spécification globale bien plus détaillée (sidebar par rôle Administrateur/Responsable de service/Collaborateur/Agent, système de permissions à points + portée GLOBAL/SERVICE/PERSONNEL, restructuration des routes) en demandant explicitement de remplacer la sidebar déjà construite par CELLE-CI. Deux points clarifiés avec l'utilisateur avant d'implémenter (AskUserQuestion) : (1) la contradiction apparente entre "un seul composant de sidebar, jamais dupliqué par rôle" (section 1/8/13 de la spécification) et les 4 maquettes de sidebar aux libellés différents par rôle (sections 2/4/5/6) — tranchée en faveur d'UN seul composant avec visibilité conditionnée par permission, jamais des libellés différents par rôle ; (2) les statuts détaillés de "Tous les courriers" (En attente de transfert, Rejetés, Archivés...) : la section 3 de la spécification demande EXPLICITEMENT des filtres/onglets À L'INTÉRIEUR de "Tous les courriers", jamais des entrées de sidebar séparées — déjà exactement ce qui existe (le filtre Statut de CourrierList, entrée précédente), aucun changement nécessaire sur ce point. Le remplacement du système de permissions existant (hasPrivilege()/Policies) par le nouveau modèle à points + portée, également demandé, N'A PAS été entamé dans cette entrée — changement d'une ampleur et d'un risque bien supérieurs (sécurité/autorisation de toute l'application), traité séparément avec un plan explicite avant toute exécution plutôt que comme un simple renommage de sidebar. 362/362 tests, build Vite propre, compilation Blade manuelle sans erreur.

## [2026-09-18 03:15] Sidebar reconstruite pour correspondre EXACTEMENT à l'image mockup fournie, et en-tête desktop refait (date/heure, rôle, raccourci Ctrl/Cmd+K)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (groupe "Courriers" : liens "Mes courriers"/"Courrier confidentiel"/"Courriers enregistrés", absents de l'image, RETIRÉS de la sidebar — leurs routes/pages existent toujours, seul le lien de sidebar disparaît ; en-tête desktop reconstruit : recherche avec raccourci clavier Ctrl+K ET Cmd+K RÉELS (pas seulement l'indication visuelle "kbd"), date/heure du jour ajoutée, bloc profil reconstruit à la main (nom + profil/rôle affichés l'un sous l'autre, `<flux:profile>` ne supportant pas nativement un sous-texte) plutôt que le simple nom seul d'avant)
Pourquoi : demande explicite de l'utilisateur ("what dit did you cause nothing resemble the image i send you... redo it with that image exactly don't keep things no redo completely even the sidebar drop the current to create this new from the image") — la sidebar structurelle précédente gardait des liens "bonus" hérités d'avant cette maquette (Mes courriers, Courrier confidentiel, Courriers enregistrés) qui n'apparaissent PAS dans l'image ; retirés pour correspondre exactement, décision explicite plutôt qu'un oubli. L'en-tête desktop n'avait jamais été retouché jusqu'ici (recherche simple + cloche + aide + nom seul) alors que l'image montre clairement une date/heure et un rôle sous le nom — corrigé avec des données RÉELLES (l'heure serveur au dernier chargement, le profil réel de l'utilisateur connecté), jamais un chiffre inventé (le badge de notification "3" de l'image n'a PAS été reproduit : Module 7 n'existe pas encore, voir DECISIONS.md). Non vérifié visuellement dans un navigateur réel (aucun navigateur disponible dans cet environnement) — demandé à l'utilisateur de partager une capture de ce qui s'affiche VRAIMENT pour corriger le reste avec certitude plutôt que de deviner. 362/362 tests, build Vite propre, compilation Blade manuelle sans erreur, recherche `//.*"` : aucune occurrence.

## [2026-09-18 03:15] Filtres rapides et panneau "Aperçu" de "Tous les courriers" alignés plus précisément sur l'image
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (bouton "Filtres" du bandeau supérieur et lien "Filtres avancés" de la barre de recherche unifiés sur UN SEUL état Alpine partagé — deux scopes séparés, essayés d'abord, ne communiquaient jamais entre eux ; recherche + "Filtres avancés" sur une ligne, les 4 filtres rapides Type de document/Statut/Priorité/Période réunis sur UNE SEULE ligne en dessous, comme dans l'image ; panneau "Aperçu du courrier" : recherche dans le document et plein écran ajoutés à la barre d'outils, en réutilisant exactement le motif recherche/TextLayer déjà construit pour registrationForm.blade.php)
Pourquoi : bug réel trouvé en relisant le fichier avant de continuer : le bouton "Filtres" du bandeau supérieur et le panneau de filtres avancés utilisaient chacun leur PROPRE variable Alpine `avance`, dans deux `x-data` différents — cliquer sur le bouton du haut ne dépliait donc jamais rien, un vrai défaut fonctionnel au-delà de la simple fidélité visuelle à l'image. Recherche/plein écran dans le panneau d'aperçu : présents dans l'image, absents de la première version de ce panneau — ajoutés en réutilisant le code déjà écrit et testé pour le panneau équivalent de registrationForm.blade.php plutôt que d'en réinventer un autre. 362/362 tests, build Vite propre, compilation Blade manuelle sans erreur.

## [2026-09-18 03:30] Case à cocher de sélection, libellé "Fonctionnalité", bouton "Supprimer" désactivé — alignement plus strict sur l'image
Fichier(s) : app/Livewire/Backend/CourrierList.php (nouvelle propriété `selectionnes` (tableau d'ids, Règle n°2) + méthode `basculerSelectionPage()` pour la case "tout sélectionner" de l'en-tête du tableau)
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (colonne de cases à cocher ajoutée au tableau, réellement fonctionnelle — la sélection persiste — même sans action de masse construite dessus ; filtre "Type de document" relabellisé "Fonctionnalité" pour correspondre littéralement au libellé de l'image ; bouton "Supprimer" ajouté au panneau d'aperçu, désactivé avec l'infobulle "Bientôt disponible" — même principe que Importer/Exporter — car `CourrierPolicy` n'a toujours aucune capacité `delete` (Règle n°5))
Pourquoi : demande explicite et répétée de l'utilisateur de coller à l'image plutôt que d'adapter aux données réelles pour ces trois points précis. Case à cocher choisie fonctionnelle (pas décorative) : cocher/décocher persiste réellement, jamais un contrôle qui ferait semblant. 362/362 tests, build Vite propre, compilation Blade manuelle sans erreur.

## [2026-09-18 03:40] "Cannot read properties of undefined (reading 'obtenir')" sur le panneau "Aperçu du courrier"
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (`@assets @vite('resources/js/document-preview.js') @endassets` ajouté en tête de la section — absent jusqu'ici)
Pourquoi : bug réel signalé par l'utilisateur (message d'erreur exact collé) en testant le panneau "Aperçu du courrier" ajouté cette session. Cause : le panneau réutilise le module `document-preview.js` (qui définit `window.DocumentPreview`, voir registrationForm.blade.php) — copié pour le code Alpine du panneau, mais son propre `@vite(...)` n'avait jamais été copié avec lui, donc `window.DocumentPreview` restait `undefined` sur cette page précise, faisant échouer `window.DocumentPreview.obtenir(...)` dès l'ouverture du panneau. Corrigé en ajoutant le même bloc `@assets`/`@vite` que registrationForm.blade.php. 362/362 tests, build Vite propre, compilation Blade manuelle sans erreur.

## [2026-09-18 03:50] Fond de la zone de contenu principal réglé directement sur le layout global
Fichier(s) : resources/views/layouts/app.blade.php (`<flux:main>` reçoit désormais une classe de fond explicite — `bg-blue-100`, réglée directement par l'utilisateur dans son éditeur pendant cette session — plutôt que de dépendre du fond de `<body>` transparaissant à travers une zone qui n'a par défaut aucun fond propre)
Pourquoi : demande explicite de l'utilisateur ("change the main content backgroud color to reflect the one on the image"), puis ajustée directement par lui dans le fichier. `<flux:main>` (zone `[grid-area:main]`) n'a par défaut aucune couleur de fond — elle dépend de ce qu'il y a derrière elle, ce qui rendait le résultat moins prévisible que de fixer la couleur directement sur cette boîte précise. 362/362 tests, build Vite propre, compilation Blade manuelle sans erreur.

## [2026-09-21 12:00] Rapport de statut des 10 modules (fichier livré à l'utilisateur)
Fichier(s) : RAPPORT-STATUT-MODULES-2026-09-21.txt (nouveau, racine du projet)
Pourquoi : demande explicite de l'utilisateur — lister ce qui est terminé, partiel ou jamais commencé sur les 10 modules de PRD.md §3, et ce qui a été touché depuis le début du projet. Audit complet par sous-agent (lecture intégrale de DECISIONS.md et CHANGELOG-AGENT.md + vérification directe du code) : Modules 1/2/4 TERMINÉ, Modules 3/5/6/8/9/10 PARTIEL, Module 7 (Alertes) NON COMMENCÉ. Cause racine identifiée pour Module 7 et le volet SLA du Module 5 : le Scheduler Laravel n'est enregistré nulle part dans le projet.

## [2026-09-21 12:15] Correctif — messages de validation Laravel affichés en texte brut ("validation.required") au lieu d'un vrai message
Fichier(s) : lang/fr/validation.php (nouveau), lang/fr/auth.php (nouveau), lang/fr/passwords.php (nouveau), lang/en/validation.php (nouveau, généré par `php artisan lang:publish`), lang/en/auth.php (nouveau), lang/en/passwords.php (nouveau)
Pourquoi : bug réel signalé par l'utilisateur (capture d'écran de la modale "Modifier l'utilisateur" — le champ Service affichait littéralement "validation.required" au lieu d'un message d'erreur) — préexistant à cette session, affectait TOUTE validation de formulaire de l'application, pas seulement la modale utilisateurs. Cause : aucun fichier `lang/{locale}/validation.php` n'existait dans le projet (seul `pagination.php` avait été publié), donc `trans('validation.required')` ne trouvait aucune traduction et Laravel retombait sur la clé brute. Traduction française complète ajoutée (mêmes clés que le fichier anglais généré par Laravel 11/12).

## [2026-09-21 12:20] Correctif — collision Windows entre `__('Validation')` et le fichier `lang/fr/validation.php` fraîchement ajouté
Fichier(s) : lang/fr.json, lang/en.json (entrée `"Validation": "Validation"` ajoutée aux deux)
Pourquoi : régression découverte immédiatement après le correctif précédent — dès que `lang/fr/validation.php` a existé, le libellé d'étape "Validation" du formulaire d'enregistrement (`registrationForm.blade.php`, étape 4 du wizard + le titre de section) a cassé le rendu de toute la page (`TypeError: htmlspecialchars(): Argument #1 must be of type string, array given`). Cause exacte, confirmée par un script de diagnostic isolé : `__('Validation')` est une clé sans point, donc Laravel tente de la résoudre comme un GROUPE de fichier de langue (`lang/fr/Validation.php`) plutôt que comme une entrée JSON ; sur un système de fichiers insensible à la casse (Windows, cette machine de dev), `lang/fr/Validation.php` correspond au `lang/fr/validation.php` qui vient d'être créé, donc `__('Validation')` renvoyait le tableau ENTIER des messages de validation au lieu du mot "Validation". Sur le serveur de production (Linux, sensible à la casse), ce bug précis ne se serait pas produit, mais la présence de traductions JSON explicites pour tout mot-clé qui coïnciderait avec un futur fichier de langue réservé (`validation`/`auth`/`passwords`/`pagination`) est la protection robuste à appliquer systématiquement — c'est le mécanisme déjà en place ici (le lookup JSON est prioritaire et court-circuite la résolution par fichier/groupe). Suite complète : 391/391 tests après correctif (0 régression, contre 59 échecs juste après la publication des fichiers de langue).

## [2026-09-21 14:00] Module 3 — "Dossiers & Archives" : migrations, modèles et politique du nouveau dossier de classement
Fichier(s) : database/migrations/2026_09_21_120000_create_dossiers_classement_table.php (nouveau)
Fichier(s) : database/migrations/2026_09_21_120100_create_dossier_classement_user_table.php (nouveau)
Fichier(s) : database/migrations/2026_09_21_120200_create_dossier_classement_historiques_table.php (nouveau)
Fichier(s) : database/migrations/2026_09_21_120300_add_dossier_classement_id_to_courriers_table.php (nouveau)
Fichier(s) : app/Models/DossierClassement.php (nouveau)
Fichier(s) : app/Models/DossierClassementHistorique.php (nouveau)
Fichier(s) : app/Policies/DossierClassementPolicy.php (nouveau)
Pourquoi : demande explicite de l'utilisateur (maquette "Dossiers & Archives") — reprend Module 3 "Dossiers de classement", resté au stade privilèges-seulement depuis le 2026-09-16 (voir DECISIONS.md, "Prochaine étape (pas commencée) : modèle DossierClassement + migration + DossierClassementPolicy + UI"). PREMIER modèle auto-référencé (parent_id) de ce projet. Confirmé avec l'utilisateur avant ce chantier (AskUserQuestion) : partage par utilisateur précis (deux boîtes, comme "Destinataires de transfert"), pas par privilège ; arborescence démarrant VIDE, aucun dossier fabriqué. Index composite `dossier_classement_id, created_at` de la table historiques renommé explicitement (`dossier_classement_hist_dossier_id_created_at_idx`) — le nom auto-généré dépassait la limite MySQL de 64 caractères.

## [2026-09-21 14:05] Module 3/9 — troisième mécanisme cumulatif d'accès (dossier de classement) et règle "archivé = lecture seule"
Fichier(s) : app/Models/Courrier.php (relation `dossierClassement()`, `dossier_classement_id` ajouté au fillable, nouvelle méthode `scopeVisiblePar()`)
Fichier(s) : app/Policies/CourrierPolicy.php (`accesDossierSuffisant()` + garde `statut === 'archive'` ajoutés à `view`/`update`/`affecter`/`traiter`/`valider`/`transferer`/`validerService`)
Fichier(s) : app/Livewire/Backend/CourrierList.php (`portee()` délègue désormais à `Courrier::scopeVisiblePar()`)
Pourquoi : specifications-modules-GEC.md Module 9 et DECISIONS.md (2026-09-16) sont explicites — confidentialité numérique, périmètre par privilège, ET accès par dossier de classement "se cumulent", jamais l'un ne remplace l'autre ; un courrier non classé (dossier_classement_id null) n'est jamais affecté par ce troisième mécanisme. La logique de périmètre (`CourrierList::portee()`) a été extraite dans un scope Eloquent partagé plutôt que recopiée une troisième fois à la main (déjà un risque réel constaté lors de l'ajout de la confidentialité le même jour) — `CourrierPolicy`, `CourrierList` et le nouveau `DossierClassementList` s'appuient désormais sur le même point de vérité. Règle Module 9 "les documents archivés restent consultables mais ne sont plus modifiables" ajoutée sur les 6 abilities mutantes, jamais sur `view()`.

## [2026-09-21 14:10] Module 9 — archivage automatique "Traité" → "Archivé" : première tâche planifiée du projet
Fichier(s) : app/Services/WorkflowService.php (`'traite' => ['archive']` ajouté aux TRANSITIONS, `archiverAutomatiquement()` nouveau, `statutsActifs()` corrigé pour exclure explicitement 'traite')
Fichier(s) : app/Jobs/ArchiverCourriersTraitesJob.php (nouveau)
Fichier(s) : bootstrap/app.php (`->withSchedule()` ajouté — premier usage du Scheduler Laravel dans ce projet)
Pourquoi : confirmé avec l'utilisateur (AskUserQuestion) — construire le vrai mécanisme automatique plutôt qu'une vue en lecture seule des courriers "Traité", puisque specifications-modules-GEC.md Module 9 l'exige explicitement ("un courrier clôturé est automatiquement transféré vers l'archivage") et que l'audit du statut des 10 modules (RAPPORT-STATUT-MODULES-2026-09-21.txt) avait identifié l'absence totale de Scheduler comme cause racine bloquant aussi le Module 7. Bug réel trouvé et corrigé pendant l'implémentation : `statutsActifs()` dérivait de `array_keys(TRANSITIONS)`, donc ajouter une transition sortante à 'traite' le faisait compter à tort comme "actif" partout (tableau de bord, file d'attente, charge par collaborateur) — corrigé en excluant 'traite' explicitement. Note opérationnelle : nécessite un vrai cron `* * * * * php artisan schedule:run` (ou `php artisan schedule:work` en dev) pour se déclencher réellement — rien d'autre dans ce projet n'a jamais dépendu du Scheduler avant ce changement.

## [2026-09-21 14:20] Module 3/9 — page "Dossiers & Archives" (arbre + contenu + détails)
Fichier(s) : app/Livewire/Backend/DossierClassementList.php (nouveau)
Fichier(s) : resources/views/livewire/frontend/dossierClassementList.blade.php (nouveau)
Fichier(s) : resources/views/components/dossier-tree-node.blade.php (nouveau — premier composant Blade récursif de ce projet)
Fichier(s) : routes/web.php (route `dossiers-classement.index`)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (3 placeholders `<x-sidebar-item-a-venir>` remplacés par de vrais liens)
Pourquoi : construit depuis la maquette fournie par l'utilisateur — arbre de dossiers (grille à 3 colonnes, panneaux latéraux sticky, convention permanente déjà établie), table centrale scopée au nœud sélectionné, panneau "Détails du dossier" avec actions réelles (créer sous-dossier/déplacer/partager/supprimer). Décisions confirmées avec l'utilisateur avant ce chantier : "Dossier surveillé" du menu = simple lien vers la fonctionnalité de scan déjà réelle (pas un nouveau concept, malgré le nom identique) ; "Archives"/"Courriers généraux"/"Tous les dossiers" sont des nœuds VIRTUELS (aucune ligne en base) plutôt que des dossiers fabriqués. "Importer" (maquette) désactivé avec l'infobulle "Bientôt disponible" — même principe qu'ailleurs dans l'application, jamais de fonctionnalité fabriquée. Le composant `<ui-disclosure>` récursif respecte deux contraintes vérifiées directement dans le JS de Flux (`flux-lite.min.js`) : le chevron doit être le premier `<button>` du sous-arbre, et un nœud sans enfant ne doit jamais être enveloppé dans `<ui-disclosure>` (sinon le panneau replié/déplié résoudrait sur la ligne elle-même).

## [2026-09-21 14:30] Tests — "Dossiers & Archives" (CRUD, gate cumulatif, archivage automatique, sidebar)
Fichier(s) : tests/Feature/Dossiers/DossierClassementListTest.php (nouveau)
Fichier(s) : tests/Feature/Courriers/CourrierDossierAccesTest.php (nouveau)
Fichier(s) : tests/Feature/Jobs/ArchiverCourriersTraitesJobTest.php (nouveau)
Fichier(s) : tests/Feature/Services/WorkflowServiceTest.php (tests `archiverAutomatiquement()` ajoutés)
Fichier(s) : tests/Feature/SidebarTest.php (2 tests ajoutés pour le groupe "Dossiers & Archives")
Pourquoi : Règle n°7 — couverture du CRUD de dossiers (création/renommage/déplacement avec anti-cycle/partage individuel et en masse/suppression bloquée si non vide), du troisième gate cumulatif sur CourrierPolicy (confidentialité + privilège + accès dossier doivent TOUS passer), et du job d'archivage (dispatch réel sur la queue, enregistrement sur le Scheduler via `schedule:list`, transition réelle avec trace "système"). Suite complète : 429/429 tests après ce chantier (0 régression sur les 391 tests précédents).

## [2026-09-18 04:00] Panneau "Aperçu du courrier" de "Tous les courriers" : toujours affiché, jamais masqué, premier résultat par défaut
Fichier(s) : app/Livewire/Backend/CourrierList.php (courrierApercu() : sans sélection explicite, recharge désormais le PREMIER résultat de la page courante au lieu de renvoyer null ; méthode fermerApercu() retirée, plus jamais appelée)
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (bouton "Fermer"/X du panneau retiré — il n'y a plus d'état "masqué" vers lequel revenir ; surlignage de la ligne active comparé à `$this->courrierApercu?->id` plutôt qu'à la seule propriété `courrierApercuId`, pour rester correct quand c'est le défaut, pas une sélection explicite, qui est affiché)
Pourquoi : demande explicite de l'utilisateur ("those cards panel should always be shown not mask and the should show the first courier by default"), au vu des 3 nouvelles maquettes fournies (détail/édition d'un courrier) qui montrent toutes un panneau d'aperçu permanent, jamais masquable. 362/362 tests (17/17 sur CourrierListTest), build Vite propre, compilation Blade manuelle sans erreur.

## [2026-09-18 04:15] Page "Modifier un courrier" : panneau "Aperçu du courrier" toujours visible, même motif que la fiche détail
Fichier(s) : app/Livewire/Backend/EditForm.php (nouvelle méthode #[Computed] courrier() — rechargée à chaque requête, jamais un modèle Eloquent en propriété publique, Règle n°2)
Fichier(s) : resources/views/livewire/frontend/editForm.blade.php (réécriture complète en deux colonnes : formulaire d'édition inchangé à gauche, panneau "Aperçu du courrier" (canevas pdfjs-dist/document-preview.js, même motif que showCourrier.blade.php et "Tous les courriers") + liste des pièces jointes déjà attachées à droite)
Pourquoi : suite explicite de la demande de l'utilisateur sur les 3 maquettes "Détail du courrier" ("this what i want for the edit, details, transfere page of a courier") — même principe de panneau d'aperçu permanent appliqué à la page d'édition. AUCUNE logique du formulaire (validation, champs, service masqué pour un courrier entrant, upload de pièce jointe) n'a été modifiée, uniquement enveloppée dans la nouvelle disposition à deux colonnes. Pas de duplication des blocs "Informations expéditeur/destinataire" en lecture seule à droite (contrairement à la fiche détail) : ces champs sont déjà les champs éditables du formulaire à gauche, les répéter en lecture seule aurait été redondant. 11/11 tests EditFormTest, 362/362 tests (suite complète), build Vite propre.

## [2026-09-18 04:30] Page "Courriers à traiter" (Module 4) : même panneau "Aperçu du courrier" toujours visible, premier résultat par défaut
Fichier(s) : app/Livewire/Backend/WorkflowQueue.php (nouvelle propriété courrierApercuId + méthode ouvrirApercu() + #[Computed] courrierApercu() — même motif que CourrierList : sans sélection explicite, affiche le PREMIER résultat de la page courante plutôt que rien)
Fichier(s) : resources/views/livewire/frontend/workflowQueue.blade.php (réécriture complète en deux colonnes : tableau à gauche, ligne cliquable ouvrant l'aperçu (wire:click="ouvrirApercu(...)") au lieu de naviguer directement vers la fiche complète ; panneau "Aperçu du courrier" toujours affiché à droite, avec un bouton "Voir la fiche complète" pour accéder aux actions du circuit de validation, qui restent exclusivement sur showCourrier.blade.php)
Pourquoi : suite de la même demande utilisateur ("...transfere page of a courier") — "Courriers à traiter" (WorkflowQueue) est la page de file d'attente/transfert du circuit de validation (Module 4), donc la page visée par "transfere page". Même principe de panneau d'aperçu permanent, avec le premier résultat de la page en défaut (cohérent avec CourrierList). Le clic sur une ligne ouvre désormais l'aperçu sur place au lieu de recharger toute la page vers la fiche détail — le lien vers le numéro de référence garde `wire:click.stop` pour permettre d'accéder directement à la fiche complète sans passer par l'aperçu, comme sur "Tous les courriers". AUCUNE logique de périmètre par profil (Responsable de service/Collaborateur/DGA) n'a été modifiée. 5/5 tests WorkflowQueueTest, 362/362 tests (suite complète), build Vite propre.

## [2026-09-18 05:00] Fond de la zone de contenu principal réaligné sur le token de marque --color-brand-surface (#F5F8FC)
Fichier(s) : resources/views/layouts/app.blade.php (`<flux:main>` : `bg-slate-50` -> `bg-brand-surface`)
Pourquoi : demande explicite de l'utilisateur ("this the main section background color #F5F8FC") — cette valeur exacte existe déjà comme `--color-brand-surface` dans app.css (commenté "fond de page général"), donc réutilisation du token de marque plutôt qu'une nouvelle classe Tailwind ad hoc proche mais non identique (bg-slate-50 = #f8fafc). Build Vite propre.

## [2026-09-18 05:15] Bordures des cartes et en-têtes/zones secondaires alignés sur la palette exacte fournie par l'utilisateur
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php, courriersEnregistres.blade.php, dashboard.blade.php, editForm.blade.php, mesCourriers.blade.php, privilegeList.blade.php, profilList.blade.php, registrationForm.blade.php, regleList.blade.php, showCourrier.blade.php, userList.blade.php, workflowQueue.blade.php (bordures de cartes `border-zinc-200` -> `border-brand-border` ; en-têtes de tableau `<thead class="bg-zinc-50 ...">` -> `bg-brand-surface-soft` ; en-têtes de "boîte" (profilList/userList, ex. "Tous les privilèges") et bandeau d'info de regleList.blade.php : `bg-zinc-50` -> `bg-brand-surface-soft`)
Pourquoi : demande explicite de l'utilisateur ("All the cards colors should be white bg"), suivie d'un tableau précis de correspondance couleur -> classe (Cartes #FFFFFF/bg-white — déjà en place partout, vérifié par grep avant toute modification ; Bordures de carte #E2E8F0/border-slate-200 ; En-têtes/zones secondaires #F8FAFC/bg-slate-50 ; Sidebar #0B1F3A et boutons primaires #2563EB — déjà corrects, `--color-brand-navy` et `--color-accent`/`--color-brand-blue` existants, aucun changement nécessaire). `border-zinc-200` (#e4e4e7, gris neutre) et `bg-zinc-50` (#fafafa) ne correspondaient pas exactement aux hex demandés (#E2E8F0/#F8FAFC, teinte bleutée cohérente avec le reste du système de couleurs GEC) — remplacés par les tokens de marque déjà définis dans app.css (`--color-brand-border: #E2E8F0`, `--color-brand-surface-soft: #F8FAFC`), qui correspondent exactement, plutôt que par les classes Tailwind `border-slate-200`/`bg-slate-50` littérales de la demande, pour rester cohérent avec l'usage existant de ces mêmes tokens ailleurs (ex. placeholders "Aucun document"). États interactifs (`hover:bg-zinc-50`) et le `<pre>` du texte OCR NON touchés — hors périmètre de la demande (couleurs de cartes/en-têtes, pas d'états de survol). 362/362 tests, build Vite propre.

## [2026-09-18 05:30] Pages à panneau aperçu : largeur pleine (fin du gap mort à droite) + panneau rendu sticky partout
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php, editForm.blade.php, workflowQueue.blade.php, courrierList.blade.php (`<section class="w-full max-w-6xl">`/`max-w-7xl` -> `<section class="w-full">` ; colonne latérale du panneau "Aperçu du courrier" (`<div class="space-y-6">` ou le panneau lui-même sur courrierList.blade.php, qui n'a pas de colonne enveloppante) : ajout de `sticky top-20 self-start`, même motif déjà établi sur registrationForm.blade.php)
Pourquoi : capture d'écran de l'utilisateur montrant un très grand espace blanc mort à droite du panneau "Aperçu du courrier" sur la fiche détail (`gecs.test/courriers/33`, marqué d'un X rouge par l'utilisateur) — cause : `max-w-6xl` (1152px) plafonnait la largeur de la section bien en-dessous de la largeur réelle de la zone de contenu principal sur un écran large, alors que la grille interne (`lg:grid-cols-[minmax(0,1fr)_360px]`) aurait pu occuper tout l'espace disponible. Retiré sur les 4 pages qui partagent ce même motif à deux colonnes (fiche détail/édition/file de traitement/liste), pas seulement celle capturée. Demande explicite complémentaire de l'utilisateur ("don't forget to always make the preview sticky on every page it is been used") : le panneau reste visible pendant le défilement du contenu de gauche sur les 4 pages, comme déjà décidé pour registrationForm.blade.php (voir CHANGELOG-AGENT.md, 2026-09-18 01:15). 364/364 tests, build Vite propre.

## [2026-09-18 05:45] Traductions manquantes de la pagination Laravel (texte anglais brut malgré la locale FR)
Fichier(s) : lang/fr/pagination.php (nouveau — previous/next en français)
Fichier(s) : lang/en/pagination.php (nouveau — previous/next en anglais, valeurs stock Laravel)
Fichier(s) : lang/fr.json (nouveau — Showing/to/of/results/Pagination Navigation/Go to page :page traduits en français)
Pourquoi : en comparant la maquette "Tous les courriers" ("Affichage de 1 à 10 sur 482 courriers") à la pagination réellement affichée par `{{ $this->resultats->links() }}` (vue vendor/laravel/framework .../pagination/tailwind.blade.php), constaté qu'aucun fichier lang/{locale}/pagination.php n'existait dans le projet (seul lang/en.json, pour traduire le FRANÇAIS — la convention du projet — vers l'anglais) : les clés `__('pagination.previous')`/`__('pagination.next')` (fichier+clé, jamais résolues) s'affichaient littéralement comme "pagination.previous"/"pagination.next", et les clés simples `__('Showing')`/`__('to')`/`__('of')`/`__('results')` (clés ANGLAISES posées par Laravel lui-même, à l'inverse de la convention du projet) s'affichaient en anglais brut même en locale FR par défaut (config('app.locale') = 'fr'). Corrigé avec les fichiers de traduction Laravel standards plutôt qu'en publiant/réécrivant la vue vendor — "résultats" plutôt que "courriers" littéral (mot générique, correct sur TOUTES les pages paginées de l'app — utilisateurs, privilèges, règles — pas seulement les courriers). Résultat : "Affichage de 1 à 10 sur 482 résultats" + "Précédent"/"Suivant" sur toute pagination de l'app, sans changement de code applicatif. 364/364 tests, build Vite propre.

## [2026-09-18 06:00] "En traitement" rejoint la couleur Info (bleu) au lieu de Warning (orange)
Fichier(s) : resources/views/components/statut-badge.blade.php (`'en_traitement'` déplacé du groupe warning vers le groupe info, rejoint `'enregistre'` déjà bleu ; commentaire mis à jour)
Pourquoi : nouvelle maquette "Tous les courriers" en table fournie par l'utilisateur montrant "En traitement" en pastille bleue — question posée explicitement (AskUserQuestion) car ça contredisait la règle documentée précédente ("GEC Master Color System", statuts actifs = orange) ; réponse de l'utilisateur : "en traitement is blue en attante in oragne terminier in green, erreur in rouge everywhere same color consistent" — seul en_traitement change de groupe, en_validation/en_cours_de_transfert/en_attente_* restent orange, traite/archive/affecte restent vert, rejete reste rouge. Aucun test n'asserte les classes CSS du badge (vérifié par grep) — 364/364 tests, build Vite propre.

## [2026-09-18 06:15] "Tous les courriers" : en-tête de tableau, ligne, tri réel de "N° Courrier", fil d'ariane, en-tête "Liste des courriers (:n)"
Fichier(s) : app/Livewire/Backend/CourrierList.php (nouvelles propriétés #[Url] `$tri`/`$direction` + constante `COLONNES_TRIABLES` whitelist (numero_reference, date_mouvement) + méthode `trierPar()` (bascule asc/desc, ignore une colonne non whitelistée) ; `resultats()` : `->latest('date_mouvement')` -> `->orderBy()` dynamique, revalidé contre la whitelist à chaque appel (jamais confiance dans la valeur `#[Url]` seule, Règle n°6, un `?tri=...` arbitraire dans l'URL ne peut pas atteindre orderBy() sans repasser par ce filtre))
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (fil d'ariane Accueil(icône)/Courriers/Tous les courriers ; cartes statistiques : icône en carré plein `bg-brand-{couleur}`/`text-white` au lieu d'un cercle pâle `/10` ; nouveau titre "Liste des courriers (:total)" au-dessus du tableau ; filtres rapides Fonctionnalité/Statut/Priorité/Période : `:label` au-dessus du champ au lieu de `:placeholder` dans le champ ; en-tête de tableau : fond `bg-brand-blue-pale` (bleu pâle, pas `bg-brand-surface-soft` gris) + texte normal `text-sm font-medium text-zinc-600` au lieu de `text-xs uppercase text-zinc-500` ; colonne "N° Courrier" cliquable (`wire:click.stop="trierPar('numero_reference')"`) avec icône `chevron-up-down` — tri RÉEL, pas décoratif ; cellule numéro de référence en `text-brand-blue` (lien visuel) ; hauteur de ligne `py-2` -> `py-3` sur toutes les cellules)
Fichier(s) : tests/Feature/Courriers/CourrierListTest.php (2 nouveaux tests : trierPar() bascule la direction et réordonne réellement les résultats ; trierPar() ignore une colonne non whitelistée)
Pourquoi : demande explicite de l'utilisateur avec 2 captures d'écran successives, de plus en plus précises, de la maquette "Tous les courriers" ("i want the exact table format... with the design field the text colors and color", puis "i want this table card design exactly") — alignement détail par détail plutôt qu'approximatif : fil d'ariane, style des cartes statistiques, titre de section réel (compte total réel, pas fabriqué), style d'en-tête de tableau, et l'icône de tri ⇕ visible sur "N° Courrier" — implémentée comme un VRAI tri (jamais une icône décorative qui ne ferait rien au clic, cohérent avec le principe déjà établi de ne jamais fabriquer une fonctionnalité qui a l'air de marcher). 364/364 tests (19/19 sur CourrierListTest), build Vite propre.

## [2026-09-18 06:30] "Tous les courriers" : barre de recherche + filtres rapides/avancés regroupés dans une carte blanche
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (barre de recherche, bouton "Filtres avancés", les 4 filtres rapides, les filtres avancés repliables et "Réinitialiser les filtres" : déplacés à l'intérieur d'un unique conteneur `rounded-2xl border border-brand-border bg-white p-4 shadow-sm`, au lieu d'être posés à même le fond de page ; `<flux:separator class="mt-6" />` retiré (la bordure de la carte fait déjà cette séparation visuelle))

## [2026-09-22 09:00] Nouvelle page admin "Services" — configuration administrateur des services/directions (CRUD)
Fichier(s) : database/migrations/2026_09_22_110000_add_actif_to_services_table.php (nouvelle colonne `actif` boolean, défaut true — un service désactivé disparaît des listes de sélection sans casser les enregistrements existants qui le référencent)
Fichier(s) : app/Models/Service.php (`$attributes = ['actif' => true]`, `actif` ajouté à `$fillable`, `casts()` retournant `actif => boolean`)
Fichier(s) : app/Policies/ServicePolicy.php (nouveau — viewAny/create/update/delete délégués à `hasPrivilege('services.gerer')`, jamais un rôle en dur, même gabarit que RegleClassementPolicy)
Fichier(s) : database/seeders/PrivilegeSeeder.php (nouvelle entrée `services.gerer`)
Fichier(s) : app/Models/Privilege.php (`MODULES` : nouvelle entrée `services`)
Fichier(s) : app/Livewire/Backend/ServiceList.php (nouveau — liste/recherche, création, modification, bascule actif/inactif, suppression bloquée si le service est référencé par au moins un courrier ou un utilisateur — même règle "désactivation plutôt que suppression physique si référencée" que DossierClassement)
Fichier(s) : resources/views/livewire/frontend/serviceList.blade.php (nouveau — gabarit "Utilisateurs & Accès" : recherche + tableau pleine largeur + une seule modale partagée création/modification, PAS le gabarit formulaire-en-ligne de RegleList/ProfilList)
Fichier(s) : routes/web.php (route `admin/services` -> ServiceList, gardée par `$this->authorize('viewAny', Service::class)` dans `mount()`)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (nouvel item "Services" dans le groupe Administration, affiché seulement si `@can('viewAny', App\Models\Service::class)`)
Fichier(s) : tests/Feature/Admin/ServiceListTest.php (nouveau — création, unicité nom/code, regex du code, modification, bascule actif, suppression bloquée si référencé par un courrier ou un utilisateur, suppression autorisée si inutilisé, recherche, privilège requis pour ouvrir la page, privilège seedé par défaut pour Administrateur)
Pourquoi : Module "Configuration administrateur" (specifications-modules-GEC.md, section transversale) — "toutes les listes de valeurs utilisées dans les champs à sélection doivent être gérables par l'Administrateur... sans intervention développeur", jamais codées en dur ; la liste des 14 services n'était jusqu'ici modifiable que par un développeur via ServiceSeeder.php. Demande explicite de l'utilisateur ("where can i add sevices" / "do that then"), suite à l'ajout du champ Service optionnel pour les utilisateurs (réceptionniste à l'Accueil, qui n'est pas un des 14 services officiels). Le gabarit UI a été corrigé en cours de construction sur retour explicite de l'utilisateur ("no mirror userlist") : la première version imitait le formulaire-en-ligne de RegleList, remplacée par le gabarit modale de UserList. 451/451 tests, Pint propre, build Vite propre.

## [2026-09-22 09:10] Correctif : import manquant faisait planter la route /admin/services en dehors des tests
Fichier(s) : routes/web.php (ajout de `use App\Livewire\Backend\ServiceList;`, absent malgré la référence `ServiceList::class` dans la route `admin.services` ajoutée juste avant)
Pourquoi : le composant est testé directement via `Livewire::test(ServiceList::class)` (import résolu dans le fichier de test), donc les 451 tests passaient sans jamais exercer la résolution de classe de la route elle-même — la route réelle aurait levé une erreur "Class ServiceList not found" au premier chargement de /admin/services. Détecté par relecture explicite de routes/web.php après le build, avant validation finale de la fonctionnalité. `php artisan route:list --name=admin.services` confirme la résolution correcte après correction ; Pint propre.

## [2026-09-22 09:30] Correctif : privilège "services.gerer" absent de la vraie base locale (page /admin/services inaccessible)
Fichier(s) : (aucun fichier modifié — exécution de `php artisan db:seed --class=PrivilegeSeeder --force` contre la base MySQL réelle `gec`)
Pourquoi : signalé par l'utilisateur ("je n'arrive pas a acceder a service page"). Cause : `PrivilegeSeeder.php` avait été modifié pour ajouter la clé `services.gerer` (voir entrée précédente), mais ce seeder n'avait été exécuté que dans la base SQLite isolée des tests (TestCase::setUp() la reseed automatiquement à chaque run) — jamais contre la vraie base de développement, qui ne connaissait donc pas ce privilège. `ServicePolicy::viewAny` retournait donc `false` pour tout le monde, y compris admin@test.local (profil Administrateur), et `$this->authorize('viewAny', Service::class)` dans `ServiceList::mount()` renvoyait un 403. Vérifié avant/après via un script de diagnostic jetable (`hasPrivilege('services.gerer')` NON pour tous les utilisateurs avant, OUI pour Administrateur après) — le seeder utilise `firstOrCreate`/`syncWithoutDetaching`, donc purement additif, sans risque pour la donnée existante (voir la règle permanente en mémoire sur les commandes destructrices contre la vraie base).

## [2026-09-22 10:15] Classer un courrier dans un dossier de classement — 3 points d'entrée (fiche, liste, page Dossiers & Archives)
Fichier(s) : app/Policies/CourrierPolicy.php (nouvelle ability `classer()` — délègue à `view()`, sans le blocage `statut === 'archive'` des autres abilities mutantes)
Fichier(s) : app/Services/DossierClassementService.php (nouveau — `classer()`/`retirer()`, transaction + double historique — `CourrierHistorique` ET `DossierClassementHistorique` — même esprit que WorkflowService mais sans machine à états)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (propriété `dossierAClasserId`, computed `peutClasser`/`dossiersAccessibles`, méthodes `ouvrirClassement()`/`classerDansDossier()`/`retirerDuDossier()`)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (bloc "Dossier de classement" dans "Actions rapides" + modale `classement-dossier-modal`, gated par `peutClasser`, toujours actionnable indépendamment du statut du courrier)
Fichier(s) : app/Livewire/Backend/CourrierList.php (propriété `dossierChoisi`, computed `dossiersAccessibles`/`peutClasserAuMoinsUnCourrier`, méthode `classerSelection()` — mirroir de `transfererSelection()` existante, sans filtre de statut ni privilège fixe)
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (bouton "Classer la sélection" + modale `classer-selection-liste-modal`, juste après "Transférer la sélection")
Fichier(s) : app/Livewire/Backend/DossierClassementList.php (propriétés `rechercheCourriersAAjouter`/`courriersAAjouter`, computed `courriersDisponiblesPourAjout()`, méthodes `ouvrirAjoutCourriers()`/`ajouterCourriersSelection()`/`retirerCourrierDuDossier()`)
Fichier(s) : resources/views/livewire/frontend/dossierClassementList.blade.php (action rapide "Ajouter des courriers" + entrée "Retirer du dossier" dans le menu Actions de la table + modale `dossier-ajout-courriers`)
Fichier(s) : tests/Feature/Courriers/CourrierDossierAccesTest.php (3 nouveaux tests : `classer()` suit exactement `view()`, reste autorisé sur un courrier archivé, reste bloqué par un accès dossier insuffisant)
Fichier(s) : tests/Feature/Services/DossierClassementServiceTest.php (nouveau — 4 tests : classer/déplacer/retirer avec double historique, `retirer()` sur un courrier déjà non classé ne fait rien)
Fichier(s) : tests/Feature/Courriers/ShowCourrierTest.php (4 nouveaux tests : visibilité du bloc, classement, refus d'un dossier non accessible — Règle n°6, retrait)
Fichier(s) : tests/Feature/Courriers/CourrierListTest.php (4 nouveaux tests : bouton caché sans dossier accessible, classement en masse, ignore un courrier hors périmètre, refuse un dossier non accessible)
Fichier(s) : tests/Feature/Dossiers/DossierClassementListTest.php (4 nouveaux tests : recherche exclut les courriers déjà classés, ajout en masse, un utilisateur PARTAGÉ peut ajouter, retrait depuis la table)
Pourquoi : demande explicite de l'utilisateur ("les trois", en réponse à la question sur les trois points d'entrée proposés pour "mettre un courrier dans un dossier créé") — fonctionnalité vérifiée absente du code (le champ `courriers.dossier_classement_id` existait déjà et servait au filtrage/aux droits d'accès depuis le chantier "Dossiers & Archives" du 2026-09-21, mais rien ne l'écrivait). Décision d'autorisation tranchée avec l'utilisateur via AskUserQuestion : un utilisateur simplement PARTAGÉ sur un dossier (lecture, deux boîtes existantes) peut aussi y ajouter des courriers — réponse explicite "oui et ça serait attribué pas des privilèges", confirmant que ce n'est PAS un nouveau privilège global mais le même mécanisme déjà en place pour `DossierClassementPolicy::view()` (créateur/responsable/partagé/gerer_tout), exactement la même règle déjà utilisée pour "créer un sous-dossier". 470/470 tests, Pint propre, build Vite propre.

## [2026-09-22 10:45] Pagination manquante sur Services et le picker "Ajouter des courriers"
Fichier(s) : app/Livewire/Backend/ServiceList.php (`services()` : `->get()` → `->paginate(10)` (Règle n°3), ajout du trait `WithPagination`, hook `updated()` qui réinitialise la page sur changement de recherche ; correctif d'un bug latent non lié trouvé au passage : `supprimer()` appelait `$this->nouveau()`, une méthode qui n'existe pas dans cette classe — remplacé par le même reset de formulaire que `ouvrirCreation()`)
Fichier(s) : resources/views/livewire/frontend/serviceList.blade.php (`{{ $this->services->links() }}` sous le tableau)
Fichier(s) : app/Livewire/Backend/DossierClassementList.php (`courriersDisponiblesPourAjout()` : `->limit(15)->get()` → `->paginate(10, ['*'], 'courriersAAjouterPage')`, nom de pagination dédié pour ne pas entrer en conflit avec la pagination "page" de la table centrale déjà affichée en même temps ; `ouvrirAjoutCourriers()`/`updated()` réinitialisent cette pagination dédiée)
Fichier(s) : resources/views/livewire/frontend/dossierClassementList.blade.php (`->links()` dans la modale "Ajouter des courriers", affiché seulement une fois une recherche tapée — vide sinon, pas de paginateur à afficher)
Fichier(s) : tests/Feature/Admin/ServiceListTest.php (nouveau test : 12 services créés, page de 10, total 12)
Fichier(s) : tests/Feature/Dossiers/DossierClassementListTest.php (nouveau test : 12 courriers correspondant à la recherche, page de 10, total 12)
Pourquoi : signalé explicitement par l'utilisateur ("PAGINATIONS", puis "LES DEUX PREMIER" en réponse à une question de clarification identifiant les deux pages concernées) — les deux listes chargeaient toute la donnée correspondante d'un coup, contrairement à la Règle n°3 ("toujours paginer les listes — jamais de ::all()/chargement complet sur une table qui va grossir") déjà respectée partout ailleurs dans l'app (UserList, CourrierList, RegleList). 472/472 tests, Pint propre, build Vite propre.

## [2026-09-22 13:00] Module "Organisation" — vraie hiérarchie Direction → Service → Sous-service (remplace la page plate "Services")
Fichier(s) : database/migrations/2026_09_22_130000_add_hierarchie_to_services_table.php (ajoute `parent_id` self-FK, `type` (direction/service/sous_service, défaut 'service'), soft deletes — additive, les 14 services réels existants deviennent des nœuds racine `type=service` inchangés, aucune Direction fabriquée)
Fichier(s) : app/Models/Service.php (self-référencé — `parent()`/`enfants()`, `compterUtilisateursDescendants()`/`compterCourriersDescendants()`/`estDescendantDe()`/`cheminComplet()` copiés du patron `DossierClassement` ; `estGerePar()`/`idsGeresPar()` — consolidation hiérarchie-aware de "qui gère ce nœud" ; `optionsHierarchiques()` — liste plate indentée réutilisée par les 7 sélecteurs dupliqués, filtre `actif=true` par défaut (défaut manquant partout avant))
Fichier(s) : app/Models/Courrier.php (`estGereParUtilisateur()` nouveau ; `scopeVisiblePar()` — branche "Responsable de service" devient hiérarchie-aware via `Service::idsGeresPar()`)
Fichier(s) : app/Models/NumeroSequence.php (nettoyage : `service_id`/`service()` retirés — code mort depuis la migration du 2026-09-15 qui avait déjà supprimé cette colonne)
Fichier(s) : app/Policies/ServicePolicy.php (`create()` accepte désormais `?Service $parent` — même convention d'appel que `DossierClassementPolicy::create()` ; `services.gerer` reste un privilège global, pas de restriction par branche)
Fichier(s) : app/Policies/CourrierPolicy.php (`view()`/`affecter()`/`valider()` — les 3 branches "responsable de service" utilisent désormais `Courrier::estGereParUtilisateur()`, hiérarchie-aware)
Fichier(s) : app/Livewire/Backend/OrganisationList.php (nouveau, remplace ServiceList.php — arbre + cartes de statistiques réellement calculées + panneau "Détails de l'entité", même patron que DossierClassementList)
Fichier(s) : resources/views/livewire/frontend/organisationList.blade.php (nouveau, remplace serviceList.blade.php)
Fichier(s) : resources/views/components/service-tree-node.blade.php (nouveau — composant récursif, copie du patron `dossier-tree-node.blade.php`)
Fichier(s) : app/Livewire/Backend/WorkflowQueue.php, app/Livewire/Backend/Dashboard.php (branches "Responsable de service" rendues hiérarchie-aware via `Service::idsGeresPar()`, même consolidation que CourrierPolicy)
Fichier(s) : app/Livewire/Backend/CourrierList.php, DossierClassementList.php, EditForm.php, RegistrationForm.php, RegleList.php, ShowCourrier.php, UserList.php (les 7 `#[Computed] services()` dupliqués délèguent désormais à `Service::optionsHierarchiques()` ; blades correspondants affichent `$service->libelle_indente` — corrige au passage un défaut trouvé en explorant le code : aucun ne filtrait les services désactivés)
Fichier(s) : routes/web.php (`admin/services` → `admin/organisation`, nom de route `admin.services` → `admin.organisation`)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (placeholder "Organisation" déjà réservé depuis une session antérieure devient un vrai lien ; ancien lien "Services" retiré, redondant)
Fichier(s) : tests/Feature/Admin/OrganisationListTest.php (nouveau, remplace ServiceListTest.php — CRUD par type, arbre à 3 niveaux, anti-cycle au déplacement, suppression bloquée si enfants/courriers/utilisateurs, statistiques réellement calculées, un service existant reste un nœud racine `type=service` inchangé)
Fichier(s) : tests/Feature/Courriers/CourrierOrganisationAccesTest.php (nouveau — `estGereParUtilisateur()` reconnaît un responsable direct ET un responsable d'un ancêtre, refuse un non-ancêtre ; `CourrierPolicy::view/affecter/valider` et `scopeVisiblePar()` hiérarchie-aware)
Pourquoi : demande explicite de l'utilisateur, à partir d'une maquette "Organisation" (arbre + cartes de statistiques + panneau de détails) puis d'une explication conceptuelle détaillée : "REMPLACER le modèle plat actuel par une vraie hiérarchie Direction → Service → Sous-service partout dans l'app". Décisions de périmètre tranchées explicitement dans le plan (validé par l'utilisateur) : le modèle réel a 3 niveaux (Direction/Service/Sous-service), pas les 4 de la maquette (Unités/Postes traités comme des responsables, déjà couverts par `responsable_id`) ; "Lieux géographiques" et les chiffres/noms d'exemple de la maquette (5/12/28/124, "Direction Générale"...) sont explicitement hors périmètre — jamais fabriqués (Règle n°6). Approche choisie après exploration complète du code (3 agents en parallèle) : étendre la table `services` existante plutôt que créer 3 nouveaux modèles — aucune des FK existantes (`courriers.service_id`/`service_propose_id`/`service_responsable_id`/`direction_origine_id`, `users.service_id`, `dossiers_classement.service_id`, `regles_classement.service_propose_id`) n'a besoin de changer. Un vrai bug (`<flux:icon.{{ $variable }} />`, interpolation dans le nom d'un tag Blade) a été trouvé et corrigé en cours de route — voir memory `livewire_flux_gotchas`. 496/496 tests, Pint propre, build Vite propre.

## [2026-09-22 11:00] Correctif : "Dossier lié" (fiche courrier) montrait un champ sans rapport avec le vrai classement en dossier
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (carte "Détails complémentaires" : le libellé "Dossier lié" affiche désormais `$courrier->dossierClassement?->nom` (le VRAI dossier de classement, Module 3) au lieu de `dossier_reference` (texte libre, sans rapport, saisi via "Modifier") ; la référence externe reste affichée en second, clairement identifiée "Réf. externe : …")
Fichier(s) : tests/Feature/Courriers/ShowCourrierTest.php (nouveau test : le nom du dossier de classement ET la référence externe s'affichent tous les deux, chacun à sa place)
Pourquoi : signalé par l'utilisateur avec une capture d'écran ("the dossier lie not functioning or showing on the ui") après avoir classé un courrier dans le dossier "test" — le champ "Dossier lié" de la carte "Détails complémentaires" restait à "—" car il pointait vers `dossier_reference`, un champ texte libre PRÉ-EXISTANT et totalement indépendant du nouveau système de classement en dossier construit plus tôt cette session (le panneau "Actions rapides", lui, affichait déjà correctement le vrai dossier — c'est la coïncidence de libellé "Dossier lié"/"Dossier de classement" qui a créé la confusion). Les deux informations restent disponibles, mais "Dossier lié" désigne maintenant sans ambiguïté le classement réel. 473/473 tests, Pint propre, build Vite propre.

## [2026-09-22 11:15] Audit statique de cohérence CSS/design — 2 tokens de couleur oubliés + 3 pages avec un style d'en-tête de tableau obsolète
Fichier(s) : resources/css/app.css (`--color-brand-table-header-text` : `#475569` → `#404040` ; `--color-brand-disabled-text` : `#64748B` → `#404040` — les deux étaient des reliquats de la palette du 2026-09-17 jamais mis à jour lors du passage au système à deux noirs du 2026-09-18, voir memory text_color_black_hierarchy)
Fichier(s) : resources/views/livewire/frontend/serviceList.blade.php (en-tête de tableau : ancien style `bg-brand-table-header text-xs uppercase` → nouveau style `bg-brand-blue-pale text-sm font-medium text-zinc-600`, aligné sur courrierList/dashboard/regleList/workflowQueue/mesCourriers/courriersEnregistres depuis le 2026-09-18)
Fichier(s) : resources/views/livewire/frontend/userList.blade.php (même correctif d'en-tête de tableau)
Fichier(s) : resources/views/livewire/frontend/dossierClassementList.blade.php (même correctif d'en-tête de tableau)
Pourquoi : demande explicite de l'utilisateur ("go and check each css style and correct them... am seeing errors of design"), sans outil de capture d'écran/navigateur disponible dans cette session (confirmé et clarifié avec l'utilisateur via AskUserQuestion, qui a choisi l'audit statique du code plutôt que d'envoyer des captures). Vérifié systématiquement contre les règles déjà documentées en mémoire (palette de marque exacte, hiérarchie à deux noirs, style de carte unique, panneaux latéraux sticky, badge de statut partagé) plutôt que sur un jugement esthétique nouveau : aucune classe `text-gray-*`/`text-slate-*` (familles de couleur incorrectes), aucune couleur Tailwind nommée hors-marque (`bg-red-500` etc.) dans les vues de l'app, aucun `match()` de statut dupliqué hors `x-statut-badge`, tous les tableaux utilisent la même carte `rounded-2xl border bg-white shadow-sm`, tous les panneaux latéraux suivent la convention sticky (sauf l'exception documentée de courrierList) — les deux vrais écarts trouvés sont ceux corrigés ci-dessus : deux variables de couleur oubliées lors d'un renommage global, et trois pages (Services/Utilisateurs & Accès/Dossiers & Archives, toutes construites APRÈS la refonte des en-têtes de tableau du 2026-09-18) qui n'avaient jamais reçu le nouveau style. 473/473 tests (inchangés, correctifs purement visuels), Pint propre, build Vite propre.

## [2026-09-22 11:30] Correctif : la page du document s'affichait deux fois, empilée, dans le panneau "Aperçu du courrier"
Fichier(s) : resources/js/document-preview.js (compteur de génération `idRendu` dans `ApercuDocument` ; `rendre()` vérifie ce compteur après CHAQUE `await` — récupération de la page, rendu du canvas, rendu de la couche de texte — et abandonne silencieusement si un appel plus récent a démarré entre-temps, avant de toucher au DOM)
Pourquoi : signalé par l'utilisateur avec une capture d'écran (la fiche courrier `/courriers/1` — la même page numérisée apparaissait deux fois, empilée verticalement, dans le panneau "Aperçu du courrier"). Cause racine identifiée dans le code de `rendre()` : la méthode vide `this.conteneur.innerHTML = ''` puis attend (`await this.pdf.getPage()`) avant d'ajouter le nouveau rendu — si `rendre()` est appelé une seconde fois pendant que le premier appel est encore "en l'air" (plausible si `init()` est rappelé par Alpine/Livewire pendant qu'un premier chargement PDF.js n'a pas fini, ex. un événement temps réel — `ocr.termine`/`statut.change`/`classement.propose` — provoquant un remorphage juste après l'ouverture de la fiche), les deux appels peuvent tous les deux vider LE CONTENEUR avant que l'un ou l'autre n'ait eu le temps d'y ajouter sa page — laissant les deux pages empilées au lieu d'une seule. Un seul point de correction (`ApercuDocument`, la classe partagée par TOUS les usages du panneau : fiche courrier, "Tous les courriers", formulaire d'enregistrement, file d'attente) — jamais un patch par page. Pas de couverture PHPUnit (JS pur, ce projet n'a pas de test runner JS) : vérifié par lecture du code et build Vite propre ; 473/473 tests PHP inchangés (aucun fichier PHP touché).

## [2026-09-22 12:00] Table admin "Documents importés depuis le dossier surveillé" sur la page Numérisation & OCR
Fichier(s) : database/migrations/2026_09_22_120000_add_source_to_courrier_brouillons_table.php (nouvelle colonne `source`, défaut 'manuel', additive)
Fichier(s) : app/Models/CourrierBrouillon.php (constantes `SOURCE_MANUEL`/`SOURCE_DOSSIER_SURVEILLE`, `source` ajouté à `$fillable`)
Fichier(s) : app/Services/BrouillonScanService.php (`creer()` accepte un paramètre `$source` optionnel, défaut manuel — aucun appelant existant cassé)
Fichier(s) : app/Livewire/Backend/ScanPremier.php (`numeriserAutomatique()` passe désormais `SOURCE_DOSSIER_SURVEILLE` ; nouveau `#[Computed] importsDossierSurveille()` — historique complet, PAS limité aux imports en attente, périmètre par privilège `brouillons.utiliser_tout` réutilisé, pas de nouveau privilège)
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (`numeriserAutomatique()` → `SOURCE_DOSSIER_SURVEILLE` ; `importerFichier()` → `SOURCE_MANUEL` explicite)
Fichier(s) : resources/views/livewire/frontend/scanPremier.blade.php (nouveau tableau paginé sous le bloc watcher existant : Nom du fichier / Importé par (masqué sans le privilège) / Date d'import / Statut OCR (même badge inline que showCourrier) / Document (lien "Continuer l'enregistrement" ou "Enregistré le :date") — le journal Alpine éphémère existant reste inchangé, gardé en plus)
Fichier(s) : tests/Feature/Courriers/ScanPremierTest.php (7 nouveaux tests : source manuel/dossier_surveille, exclusion des scans manuels du tableau, inclusion des imports déjà finalisés, périmètre par privilège)
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php (2 nouveaux tests : source correcte pour numeriserAutomatique()/importerFichier())
Pourquoi : demande explicite de l'utilisateur ("since enregistre already has them [la liste des brouillons en attente] I want you to make it as a list table showing to the admin the import and documents inside the dossier surveille"). Deux décisions tranchées via AskUserQuestion : le tableau ne montre que les imports RÉUSSIS (pas les échecs/rejets, qui auraient nécessité une toute nouvelle table d'historique d'import puisque rien n'est aujourd'hui persisté pour un fichier rejeté) ; le journal Alpine en direct reste en plus du tableau (utile pendant qu'on surveille depuis son propre poste, le tableau étant lui persistant et visible par n'importe quel admin même après rechargement/depuis un autre poste). 481/481 tests, Pint propre, build Vite propre.

## [2026-09-22 12:15] "Numérisation & OCR" restylée sur le gabarit "Utilisateurs & Accès"
Fichier(s) : app/Livewire/Backend/ScanPremier.php (trait `WithPagination` ajouté — la pagination du tableau ne fonctionnait qu'en rechargement de page complet sans lui ; nouvelle propriété `rechercheImports` + `updated()` qui réinitialise la pagination dédiée `importsPage` au changement de recherche ; `importsDossierSurveille()` filtre désormais sur `nom_original`)
Fichier(s) : resources/views/livewire/frontend/scanPremier.blade.php (page entièrement restructurée : `<section class="max-w-2xl">` étroit remplacé par `<div>` pleine largeur avec fil d'ariane + en-tête icône/titre/sous-titre, même gabarit que userList.blade.php/serviceList.blade.php ; chaque section — scan manuel, dossier surveillé, tableau d'imports — devient sa propre carte blanche `rounded-2xl border bg-white shadow-sm` au lieu de simples `<flux:separator>` ; nouvelle carte de recherche au-dessus du tableau, même style que UserList)
Fichier(s) : tests/Feature/Courriers/ScanPremierTest.php (nouveau test : la recherche filtre par nom de fichier)
Pourquoi : demande explicite de l'utilisateur juste après la construction du tableau précédent ("IT SHOULD LOOK LIKE USERLIST") — la page était une colonne étroite de formulaire (`max-w-2xl`), écrasant le nouveau tableau admin dans une largeur bien plus petite que toutes les autres pages de listing de l'app (Services, Utilisateurs & Accès, Dossiers & Archives, Tous les courriers), qui suivent toutes le même gabarit pleine largeur avec fil d'ariane + en-tête + carte de recherche. En corrigeant, trouvé et corrigé au passage un vrai defaut fonctionnel : le tableau paginait déjà mais sans le trait `WithPagination`, ses liens de pagination auraient forcé un rechargement complet de page au lieu d'une navigation Livewire fluide. 482/482 tests, Pint propre, build Vite propre.

## [2026-09-22 10:30] Correctif : le sélecteur "Responsable/Service" reste bloqué sur un choix, impossible de revenir à "Aucun"
Fichier(s) : resources/views/livewire/frontend/serviceList.blade.php (sélecteur "Responsable" de la modale Service)
Fichier(s) : resources/views/livewire/frontend/userList.blade.php (sélecteurs "Service" des modales Ajouter ET Modifier un utilisateur)
Pourquoi : signalé par l'utilisateur ("the '— Aucun pour l'instant —' is freeze") sur le sélecteur Responsable de la nouvelle page /admin/services. Cause : l'attribut `placeholder` de `<flux:select>` génère un `<option value="" disabled selected>` — un `<select>` HTML natif ne permet JAMAIS de re-sélectionner une option `disabled` une fois qu'une vraie valeur a été choisie, donc impossible de revenir à "Aucun" via l'interface. Corrigé en ajoutant, EN PLUS du placeholder (gardé pour l'affichage initial), une vraie `<flux:select.option value="">` cliquable — pattern déjà éprouvé dans ce projet (`regleList.blade.php`, sélecteur `serviceProposeId`, jamais touché par ce bug). Même défaut présent sur les deux sélecteurs "Service" (nullable, ajouté le 2026-09-22 pour la réceptionniste) de "Utilisateurs & Accès" — corrigé par la même occasion avant que l'utilisateur ne le rencontre là aussi. `responsableId`/`ajoutServiceId`/`editionServiceId` sont tous `?int`, donc la valeur vide `""` se caste automatiquement en `null` côté Livewire (même mécanisme que `serviceProposeId`). 470/470 tests, Pint propre, build Vite propre.
Pourquoi : demande explicite de l'utilisateur ("why are the search not in a card like on the image same as the tout les courier") — la maquette regroupe recherche + filtres dans sa propre carte blanche, cohérente avec les cartes statistiques et la carte du tableau juste au-dessus/en-dessous, alors que notre version posait ces contrôles directement sur le fond `--color-brand-surface`. 364/364 tests, build Vite propre.

## [2026-09-18 06:45] Cohérence de l'en-tête (icône + titre) et du style de tableau sur TOUTES les pages à liste de courriers/tableau
Fichier(s) : resources/views/livewire/frontend/workflowQueue.blade.php, mesCourriers.blade.php, courriersEnregistres.blade.php, privilegeList.blade.php, regleList.blade.php, userList.blade.php (en-tête de page : icône dans un cercle plein `bg-brand-blue` à gauche du titre/sous-titre, même motif que "Tous les courriers" et la fiche détail — auparavant un simple `<flux:heading level="1">` sans icône)
Fichier(s) : workflowQueue.blade.php, mesCourriers.blade.php, courriersEnregistres.blade.php, dashboard.blade.php (table "Derniers courriers enregistrés"), privilegeList.blade.php, regleList.blade.php, userList.blade.php (table "Utilisateurs et leurs privilèges") : en-tête de tableau `bg-brand-blue-pale`/`text-sm font-medium text-zinc-600` (au lieu de `bg-brand-surface-soft`/`text-xs uppercase`) + lignes `py-3` (au lieu de `py-2`) + colonne identifiante (numéro de référence, nom de privilège, nom d'utilisateur) en `text-brand-blue`, même style que "Tous les courriers" (voir entrée du 2026-09-18 06:15)
Fichier(s) : workflowQueue.blade.php, mesCourriers.blade.php, courriersEnregistres.blade.php : titre "Liste des courriers (:total)" ajouté au-dessus du tableau (compte réel via `->total()` du paginateur), même motif que "Tous les courriers"
Pourquoi : demande explicite de l'utilisateur ("same thing and you most amintain this consistency an all the pages that has tables") après la refonte du style de tableau/en-tête de "Tous les courriers" — étendu à toutes les pages listant des courriers ou d'autres entités en tableau, pour que l'identité visuelle (couleur d'en-tête de tableau, hauteur de ligne, lien bleu sur la colonne principale, en-tête de page avec icône) soit la même partout plutôt que propre à une seule page. profilList.blade.php non touché (pas de `<table>`, deux boîtes de sélection) ; editForm.blade.php/showCourrier.blade.php non touchés (pas des pages de liste — showCourrier avait déjà son propre en-tête à icône depuis sa refonte du 2026-09-17 18:10). 364/364 tests, build Vite propre.

## [2026-09-18 07:00] Groupe sidebar "Courriers" replié par défaut, comme tous les autres groupes
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (`<flux:sidebar.group :heading="__('Courriers')" expandable expanded="true" ...>` -> `expanded="false"`)
Pourquoi : demande explicite de l'utilisateur ("arrange the sidebar") — comparé à la maquette de référence déjà fournie (capture `gecs.test/courriers/33`), "Courriers" était le SEUL groupe replié/déplié affiché ouvert par défaut (Dossiers & Archives/Recherche/Administration/Paramètres sont tous `expanded="false"`), alors que la maquette le montre fermé comme les autres, même en étant sur une page de courrier. Confirmé dans le stub Flux (vendor/livewire/flux/.../sidebar/group.blade.php) qu'`expanded` est un état d'ouverture initial statique, jamais recalculé selon la route active — donc ce n'était pas un comportement automatique à préserver, juste un réglage figé resté incohérent avec le reste. 364/364 tests, build Vite propre.

## [2026-09-18 07:15] Sidebar : un seul groupe replié/déplié ouvert à la fois + le groupe de la page active reste déplié après navigation
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (groupes repliables "Courriers"/"Dossiers & Archives"/"Recherche"/"Administration"/"Paramètres" enveloppés dans `<ui-disclosure-group exclusive>` — primitive native Flux déjà enregistrée globalement dans vendor/livewire/flux/dist/flux-lite.min.js (`A('disclosure-group', ei)`), jamais exposée par un composant `<flux:...>` dédié mais utilisable directement comme élément personnalisé ; `expanded="false"` figé -> `:expanded="request()->routeIs([...]) ? 'true' : 'false'"` par groupe (Courriers : courriers.rechercher/nouveau/numeriser-nouveau/a-traiter ; Administration : admin.utilisateurs/profils/privileges/regles ; Paramètres : profile.edit ; Dossiers & Archives reste figé à false, aucune route réelle derrière ses liens "Bientôt" ; Recherche reste figé à false pour éviter un conflit — "Recherche avancée" et "Tous les courriers" (groupe Courriers) pointent vers EXACTEMENT la même route, ambiguïté préexistante non résolue ici, hors périmètre de cette demande)
Pourquoi : deux demandes explicites de l'utilisateur : (1) "the should collapse when click on them and then when clicking on another one the should close why that one stays open" — comportement accordéon (un seul groupe ouvert à la fois), qu'aucun composant `<flux:sidebar.group>` ne fournit seul (chaque groupe gère son propre état, indépendant des autres) — trouvé et utilisé l'élément personnalisé `<ui-disclosure-group exclusive>` déjà livré avec Flux à cet effet exact (ferme tous les autres `ui-disclosure` descendants dès qu'un `lofi-disclosable-change` signale une ouverture), plutôt que de réinventer cette logique en Alpine. (2) "when clicking on the items to navigate a page the group still stays expanded not close" — `expanded` était figé à `false` pour tous les groupes après le correctif précédent (2026-09-18 07:00), donc se refermait après CHAQUE navigation, y compris vers une page qu'il contient lui-même ; recalculé à chaque rendu d'après la route active, pour que le groupe contenant la page ouverte reste déplié. Non vérifié dans un vrai navigateur (aucun outil de ce type disponible dans cette session) — l'API interne Flux (`_disclosable`, attribut `open` lu uniquement à l'amorçage du composant, `data-flux-sidebar-group-dropdown` etc.) suggère que ça fonctionne à travers un remorphage `wire:navigate` (le composant est un élément personnalisé standard, pas un état Alpine local invisible au serveur), mais reste à confirmer par l'utilisateur en conditions réelles. 364/364 tests, build Vite propre.

## [2026-09-18 07:30] Sidebar : structure "Courriers" accidentellement effacée hors session — restaurée
Fichier(s) : resources/views/layouts/app/sidebar.blade.php
Pourquoi : un système-reminder a signalé le fichier modifié sur disque hors des outils de cette session (probablement l'IDE de l'utilisateur) — les lignes d'ouverture du groupe "Courriers" (le commentaire, `<ui-disclosure-group exclusive>`, `@php($courriersOuvert = ...)`, la balise `<flux:sidebar.group ... heading="Courriers" ...>`) avaient disparu, laissant les items du groupe orphelins et une balise fermante `</flux:sidebar.group>` sans ouverture correspondante — HTML/Blade cassé. Restauré à l'identique (structure d'ouverture reconstituée depuis le contexte encore présent juste avant/après) plutôt que d'ignorer l'incohérence, conformément à la consigne de signaler un changement qui semble erroné plutôt que de le défaire silencieusement. Vérifié par un test de rendu de page complète (DashboardTest) en plus du linting — 367/367 tests, build Vite propre.

## [2026-09-18 07:45] Sidebar : @persist entier + resynchronisation JS du lien actif — l'approche par route (wire:key/expanded) ne suffisait pas
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (`<flux:sidebar>...</flux:sidebar>` enveloppé dans `@persist('app-sidebar')`/`@endpersist`, même mécanisme déjà utilisé pour `@persist('toast')` plus bas dans ce même fichier ; tous les `wire:key="sidebar-groupe-..."` de l'entrée précédente retirés — devenus sans effet une fois le bloc persistant, la resynchronisation par clé ne s'applique qu'AVANT que Livewire ne cesse de remorpher ce nœud ; `expanded` par route conservé mais uniquement comme état du tout premier rendu de la session, plus jamais réévalué ensuite ; nouveau `<script>` en bas de fichier écoutant `livewire:navigated`, comparant `href` (pathname+search) de chaque `[data-flux-sidebar-item]` à l'URL courante et basculant l'attribut `data-current` — le même attribut dont dépendent déjà tous les styles "actif" de Flux (vendor/livewire/flux/stubs/.../sidebar/item.blade.php, variantes Tailwind `data-current:...`), donc aucune classe interne Flux à deviner/dupliquer)
Pourquoi : demandes explicites répétées de l'utilisateur montrant que le correctif précédent (route-aware `expanded` + `wire:key` forçant un remontage) ne suffisait pas en conditions réelles ("the sidebar is still closing after i click on his child", puis "the sidebar shouldn't refresch as the main refreshes or even when it refreshes it should still stay open if we open it", puis confirmé explicitement via AskUserQuestion : l'état ouvert/fermé doit être piloté UNIQUEMENT par les clics de l'utilisateur, jamais recalculé selon la page visitée). Cause racine du échec précédent : `<ui-disclosure>` (primitive Flux, vendor/livewire/flux/dist/flux-lite.min.js) ne relit son attribut "open" qu'à l'amorçage (`connectedCallback`), jamais en continu — un remorphage `wire:navigate` qui corrige juste l'attribut sur le même nœud DOM ne change donc jamais l'affichage réel, et un `wire:key` différent le remonte à chaque fois qu'il DEVRAIT rester ouvert selon la route, provoquant exactement le clignotement/fermeture que l'utilisateur signalait. `@persist` retire le nœud du morphing wire:navigate PLUTÔT QUE de chercher à le remorpher "correctement" : plus aucune fermeture inattendue, au prix du surlignage `data-current` qui, lui, ne se recalculerait plus après le tout premier rendu sans la resynchronisation JS ajoutée en contrepartie. Clarifié ensuite avec l'utilisateur (AskUserQuestion) qu'une page visitée directement (lien profond, recherche) ne doit PAS auto-ouvrir son groupe si l'utilisateur ne l'a jamais cliqué — comportement "clic uniquement" confirmé comme le comportement voulu, pas un défaut restant à corriger. 367/367 tests, build Vite propre. Non vérifiable en conditions réelles dans cet outil (aucun navigateur disponible dans cette session) — l'utilisateur a confirmé par la suite (AskUserQuestion) que le comportement "clic uniquement, jamais d'ouverture automatique selon la page visitée" correspond à ce qu'il attend, sans signaler de nouveau clignotement/fermeture depuis ce changement précis.

## [2026-09-18 08:15] Cause RÉELLE trouvée : `:expanded="... ? 'true' : 'false'"` ne s'ouvrait JAMAIS — comparaison stricte contre une chaîne, pas un booléen
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (les 3 groupes repliables dynamiques — Courriers/Administration/Paramètres — passaient `:expanded="condition ? 'true' : 'false'"`, converti par erreur en CHAÎNE PHP "true"/"false" ; changé en `:expanded="condition"`, le booléen brut, sans conversion)
Fichier(s) : tests/Feature/SidebarTest.php (nouveau — 4 tests : Courriers déplié sur une page du groupe/replié ailleurs, Administration déplié sur une page d'administration/replié sur le tableau de bord — extraction du bloc `<ui-disclosure>` précis par intitulé pour vérifier son propre attribut "open" sans dépendre d'un autre groupe de la page)
Pourquoi : l'utilisateur a signalé, capture d'écran à l'appui, que "Courriers" restait replié même sur `courriers/nouveau` (une route de son propre whitelist) après un rechargement complet — donc pas un problème de morphing/persistance (déjà exclu par un rechargement dur), un problème dans le calcul lui-même. Cause exacte, confirmée par relecture du stub Flux (vendor/livewire/flux/stubs/.../sidebar/group.blade.php, `@if ($expanded === true) open @endif`) : ce test utilise l'opérateur de comparaison STRICTE `===` contre le booléen `true` — or `:expanded="... ? 'true' : 'false'"` produit la CHAÎNE `"true"` ou `"false"` (héritage d'une habitude à convertir en chaîne pour un attribut HTML, non pertinente ici puisque `:expanded` passe une vraie valeur PHP au composant, pas un attribut HTML brut) ; en PHP, `"true" === true` vaut `false`, donc AUCUNE des deux chaînes ne satisfait jamais la comparaison — chaque groupe s'affichait replié quelle que soit la route, sur TOUTE la durée du bug (déjà présent dans les 2 entrées précédentes de ce changelog sur le sujet, qui diagnostiquaient donc le mauvais symptôme). Confirmé par un test qui échouait avant le correctif et passe après (vérifié explicitement avant de conclure, pas seulement supposé). 371/371 tests (4 nouveaux sur SidebarTest), build Vite propre.

## [2026-09-18 08:30] "Modifier le courrier" refait entièrement selon la nouvelle maquette (en-tête/statut/actions, onglets, panneau document, nouveaux champs réels)
Fichier(s) : database/migrations/2026_09_18_070000_add_direction_origine_echeance_type_traitement_note_to_courriers_table.php (nouveau — 5 colonnes nullable : direction_origine_id (FK services, nullOnDelete), echeance (date), type_traitement (string), expediteur_fonction (string), note_interne (text))
Fichier(s) : app/Models/Courrier.php ($fillable étendu, cast `echeance` => date, nouvelle relation directionOrigine())
Fichier(s) : app/Livewire/Backend/EditForm.php (nouvelles propriétés directionOrigineId/echeance/typeTraitement/expediteurFonction/noteInterne — directement sur le composant, PAS sur le CourrierForm partagé avec RegistrationForm — chargées dans mount(), validées+fusionnées dans enregistrerModification() avant champsModifies()/fill()/save(), TOUTES nullable)
Fichier(s) : resources/views/livewire/frontend/editForm.blade.php (réécriture complète : fil d'ariane, en-tête icône+titre+badge de statut réel+boutons Retour/Enregistrer/Annuler, 2 onglets réels "Informations générales"/"Pièces jointes (:n)", cartes Informations générales/Expéditeur-Destinataire/Résumé-Commentaire à gauche, panneau "Document principal" (même canevas pdfjs-dist qu'avant) + carte "Informations complémentaires" + bandeau d'info bleu à droite)
Fichier(s) : tests/Feature/Courriers/EditFormTest.php (3 nouveaux tests : chargement des nouveaux champs au montage, enregistrement + trace dans l'historique, non-obligation de ces champs)
Pourquoi : demande explicite de l'utilisateur avec capture d'écran détaillée ("this is how i want the modify/edit design to look like you have to adopt this style drop that orther one"), clarifiée en 2 points via AskUserQuestion avant implémentation : (1) les champs de la maquette absents du schéma (Direction d'origine, Échéance, Type de traitement, Fonction de l'expéditeur, Commentaire/Note interne) sont ajoutés pour de vrai en base plutôt que fabriqués côté vue — réponse "Add the new fields for real" ; TOUS nullable et SANS astérisque "obligatoire" dans la vue (aucune règle métier ne les impose, une exigence stricte casserait l'édition de tout courrier existant qui ne les a pas encore renseignés) ; (2) seuls 2 onglets réels sur cette page ("Informations générales" et "Pièces jointes") — réponse "Just the two real tabs" — les onglets Historique/Affectation & Transfert/Traitement & Workflow/Suivi & Traçabilité de la maquette dupliqueraient la logique de circuit qui vit déjà sur showCourrier.blade.php, hors périmètre. "Type"/"Catégorie"/"Service émetteur" de la maquette identifiés comme de simples relibellés des champs existants sens/type_document/service_id (specifications-modules-GEC.md emploie déjà "catégorie" comme synonyme de type de document) — jamais dupliqués sous un nouveau nom malgré leur apparence de "nouveaux champs" dans l'image. "Service destinataire" de la maquette délibérément omis (redondant avec "Service émetteur" = le même service_id, l'ajouter à nouveau sous un autre libellé aurait dupliqué la même donnée sous deux champs distincts). 371/371 tests (11/11 + 3 nouveaux sur EditFormTest), build Vite propre, migration exécutée avec succès.

## [2026-09-18 08:45] @persist('app-sidebar') cassait la grille de mise en page Flux (barre du haut étirée sur toute la largeur, sidebar avec ses propres barres de défilement)
Fichier(s) : resources/css/app.css (2 nouvelles règles : `[x-persist="app-sidebar"] { display: contents; }` + `body:has(> [x-persist="app-sidebar"]) { grid-template-areas: ... }`)
Pourquoi : capture d'écran annotée de l'utilisateur ("check now i highlithted it") montrant la barre de navigation desktop étirée sur toute la largeur de l'écran (par-dessus la colonne de la sidebar) et la sidebar avec des barres de défilement internes inattendues — régression directe de l'entrée précédente (@persist('app-sidebar')). Cause exacte, trouvée en lisant vendor/livewire/flux/dist/flux.css : la mise en page de Flux repose sur `*:has(>[data-flux-main]) { display: grid; grid-template-areas: "header header header" / "sidebar main aside" / ... }` PUIS `*:has(>[data-flux-sidebar]+[data-flux-header]) { grid-template-areas: "sidebar header header" / ... }` (affine la zone d'en-tête pour ne PAS s'étendre par-dessus la colonne sidebar) — les DEUX sélecteurs `:has(> ...)` exigent que l'élément visé soit un ENFANT DIRECT du conteneur de grille. Le `<div x-persist="app-sidebar">` ajouté par @persist s'interpose entre `<body>` et `[data-flux-sidebar]`, donc la DEUXIÈME règle (celle qui protège la colonne sidebar de l'en-tête) ne matche plus jamais — `:has()` interroge le DOM réel, pas le rendu, donc aucune astuce purement visuelle ne peut la faire "matcher à nouveau" tant que ce div existe entre les deux. Corrigé avec deux règles combinées, aucune ne suffisant seule : `display: contents` sur le div (le rend transparent pour l'algorithme de grille — ses enfants, dont [data-flux-sidebar], redeviennent des items directs de la grille de <body>, donc leur `grid-area` déclaré s'applique de nouveau) + une règle équivalente à celle de Flux mais ciblée sur le div lui-même (`body:has(> [x-persist="app-sidebar"])`) puisque la sélection originale de Flux ne peut structurellement plus matcher. Cette dernière règle gagne sur celle de Flux par spécificité CSS (`body` > `*`), sans dépendre de l'ordre des fichiers. 371/371 tests, build Vite propre (règles confirmées présentes dans le CSS compilé).

## [2026-09-18 09:00] Barre de défilement horizontale parasite en bas de la sidebar (persistait après le correctif de grille)
Fichier(s) : resources/css/app.css (`[data-flux-sidebar] { overflow-x: hidden; }`)
Pourquoi : capture d'écran de l'utilisateur ("the scroll bar fro[m] the down side is till there") confirmant que le correctif de grille précédent avait bien réparé la barre du haut mais PAS cette barre de défilement horizontale, restée scopée à la sidebar elle-même — donc un problème distinct, pas un résidu du même bug. Cause probable : la sidebar est fixée à `w-64` (16rem) sans jamais définir `overflow-x` (laissé à `visible` par défaut) ; un groupe déplié (`ps-7`, 28px d'indentation en plus pour ses enfants) réduit la largeur réellement disponible pour ses items, et si un libellé plus un badge "Bientôt" dépasse malgré le `truncate` déjà posé sur le texte (le badge, lui, ne l'est pas), le navigateur affiche une barre de défilement horizontale plutôt que de simplement rogner. Corrigé en désactivant purement ce défilement horizontal (jamais voulu ici — un libellé doit se tronquer en "…", pas devenir scrollable) plutôt que de chercher à retrouver l'item précis en cause. 371/371 tests, build Vite propre.

## [2026-09-18 09:15] Badge texte "Bientôt" retiré des liens de sidebar non encore construits
Fichier(s) : resources/views/components/sidebar-item-a-venir.blade.php (`:badge="__('Bientôt')"` retiré de `<flux:sidebar.item>`)
Pourquoi : demande explicite de l'utilisateur ("remove the bientot tag on the sidebar"). Le style grisé + non cliquable (`opacity-60`/`pointer-events-none`, déjà en place) reste seul à signaler que ces liens ne sont pas encore actifs — jamais présentés comme cliquables/fonctionnels pour autant, cohérent avec le principe déjà établi de ne jamais fabriquer une fonctionnalité qui a l'air de marcher. Contribuait aussi à la largeur excédentaire de chaque ligne repérée dans le correctif de la barre de défilement horizontale de l'entrée précédente (le badge n'est pas tronqué comme le libellé). Uniquement dans la sidebar (demande scopée) — les infobulles "Bientôt disponible" sur d'autres pages (boutons Importer/Exporter, carte "Notifications" du tableau de bord, etc.) ne sont pas concernées. 371/371 tests, build Vite propre.

## [2026-09-18 09:30] "Tous les courriers" : panneau "Aperçu du courrier" — `sticky` retiré, retour à un simple item de grille aligné en haut
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (panneau : `sticky top-20 self-start` -> `self-start` seul)
Pourquoi : capture d'écran annotée de l'utilisateur montrant le panneau démarrant visiblement plus bas que le tableau ("i want the panel should start from up... like on the other image", comparé à la maquette où le panneau démarre exactement au même niveau que "Liste des courriers"). Cause plausible : `sticky` calcule sa position selon le défilement de la page ET la hauteur de sa propre ligne de grille — sur cette page précise, le tableau ne fait que 10 lignes par page (peu de hauteur), donc la fenêtre de "collage" du panneau est très courte et son comportement au défilement peut le faire paraître décalé par rapport à un simple alignement statique en haut. `self-start` SEUL garantit un positionnement toujours identique, sans dépendre de l'état de défilement — le plus robuste pour "démarrer en haut" à coup sûr. Les 3 AUTRES pages à panneau aperçu (showCourrier/editForm/workflowQueue, voir CHANGELOG-AGENT.md 2026-09-18 05:30) gardent `sticky`, non concernées par cette capture précise. 371/371 tests, build Vite propre. Diagnostic fait sans navigateur réel (aucun outil de ce type dans cette session) — à confirmer par l'utilisateur après rechargement.

## [2026-09-18 09:45] "Tous les courriers" : le panneau "Aperçu" démarre désormais sous les boutons, aux côtés des KPI + recherche + tableau (pas seulement du tableau)
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (restructuration : la grille à 2 colonnes `lg:grid-cols-[minmax(0,1fr)_380px]` englobe maintenant TOUT le contenu de gauche — cartes statistiques, callout de recherche navbar, carte recherche/filtres, "Liste des courriers" + tableau — dans une colonne `space-y-6` unique, avec le panneau comme second enfant/sibling de cette grille ; auparavant seul le bloc tableau+pagination était dans cette grille, les cartes/recherche restaient pleine largeur AU-DESSUS)
Pourquoi : demande explicite de l'utilisateur suite au correctif précédent ("no i want the panel to start under the buttons and it should be side by side with the research form and the table and the kpi") — le panneau doit courir sur toute la hauteur du contenu de gauche, démarrant juste sous la ligne de titre/boutons, pas seulement à côté du tableau. Div correctement rééquilibrées après restructuration (comptées manuellement une par une avant de considérer la modification terminée, pas seulement supposées correctes). 371/371 tests (19/19 sur CourrierListTest), build Vite propre.

## [2026-09-18 10:00] Convention de mise en page du panneau "Aperçu" — règle permanente enregistrée en mémoire pour toutes les pages actuelles ET futures
Pourquoi : demande explicite de l'utilisateur ("it should be like that on every page that will have the panel and even the feture page that will have [it] so take note on that cause those will [be] how you will be designing") — pas un correctif de plus, une convention de conception permanente. Vérifié une par une que showCourrier.blade.php/editForm.blade.php/workflowQueue.blade.php suivent déjà cette règle (leur grille à 2 colonnes englobe déjà tout leur contenu de gauche depuis leur construction initiale) — seul courrierList.blade.php avait besoin d'être corrigé (entrée précédente). Enregistré dans le système de mémoire long terme (memory/apercu_panel_layout_convention.md, type "feedback") pour que cette règle soit appliquée automatiquement à toute nouvelle page de ce type dans une future session, sans avoir à être réexpliquée. Aucun fichier de code modifié dans cette entrée — uniquement de la documentation/mémoire.

## [2026-09-18 10:15] "Tous les courriers" : cartes statistiques (KPI) reformatées en version compacte
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (les 4 cartes : icône réduite (size-11→size-9, icône interne size-5→size-4), padding réduit (p-4→p-3), coins moins arrondis (rounded-2xl→rounded-xl), libellé+chiffre regroupés côte à côte de l'icône au lieu de libellé+icône sur une ligne puis gros chiffre en dessous — carte sensiblement moins haute ; `gap-4`→`gap-3` entre les cartes ; `truncate` ajouté sur le libellé et le texte de tendance pour ne jamais casser la mise en page si le texte est trop long dans cette largeur réduite)
Pourquoi : demande explicite de l'utilisateur ("reduce the kpi and arange them to well present those data inside it"), suite à la restructuration de l'entrée précédente qui a réduit la largeur disponible pour ces cartes (elles partagent maintenant la colonne de gauche avec le panneau "Aperçu", au lieu de s'étendre sur toute la largeur de la page) — l'ancien format à 2 lignes tenait moins bien dans cet espace plus étroit. 371/371 tests (19/19 sur CourrierListTest), build Vite propre.

## [2026-09-18 11:00] "Détail du courrier" refait selon la nouvelle maquette (résumé 3 colonnes, parcours relibellé, nouveaux champs réels, "Fichiers joints"/"Actions rapides")
Fichier(s) : database/migrations/2026_09_18_100000_add_dossier_sla_service_responsable_reference_externe_to_courriers_table.php (nouveau — 4 colonnes nullable : dossier_reference (string), sla_jours (unsignedSmallInteger), service_responsable_id (FK services, nullOnDelete), reference_externe (string) ; "Date de traitement prévue" de la maquette RÉUTILISE `echeance` déjà ajouté pour "Modifier le courrier" — pas de doublon)
Fichier(s) : app/Models/Courrier.php ($fillable étendu, nouvelle relation serviceResponsable())
Fichier(s) : app/Livewire/Backend/EditForm.php (dossierReference/slaJours/serviceResponsableId/referenceExterne : mêmes principes que le lot précédent — chargés/validés/enregistrés, réponse "Add them for real... wire them into the edit form too")
Fichier(s) : resources/views/livewire/frontend/editForm.blade.php (4 champs ajoutés à la carte "Informations générales")
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (`etapesParcours()` réécrit : 5 étapes génériques Création/Affectation/Traitement/Réponse/Clôture — au lieu des 6 statuts bruts — avec une date RÉELLE par étape franchie, retrouvée dans l'historique via les vrais noms d'action de WorkflowService (creation/affectation/traitement_demarre/soumis_validation/validation_acceptee), jamais une date fabriquée ; "archive" traité comme équivalent de "traite" pour cette frise ; `courrier()` : ajout de `serviceResponsable` à l'eager loading)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (réécriture substantielle : carte "Informations générales" passée de 2 à 3 colonnes façon maquette (N° Courrier + bouton copier, Type/Priorité/Confidentialité en badges/puces colorées, Statut/Catégorie/Service émetteur/Service destinataire/Date d'envoi, Expéditeur/Destinataire/Mode de réception/Référence externe) — RC/NIU/téléphone/email repliés dans un `<details>` "Coordonnées détaillées" plutôt que supprimés (données réelles, jamais rendues invisibles) ; "Parcours du courrier" affiche désormais un sous-libellé par étape (date réelle ou "En cours"/"En attente"/"Non clôturé") ; "Détails complémentaires" : nouveau bloc de 6 champs (Dossier lié/Date de traitement prévue/Collaborateur assigné/SLA/Service responsable/Motif-Commentaire) ajouté AU-DESSUS du contenu existant (OCR/Classement/Mots-clés, conservés tels quels) ; panneau "Aperçu du courrier" : bouton "Voir en plein écran" + navigation de page précédent/suivant ajoutés (réutilisent des méthodes déjà réelles de document-preview.js, déjà utilisées sur registrationForm.blade.php, jamais utilisées ici avant) ; nouvelle carte "Fichiers joints (:n)" à droite avec lien "Voir tous" qui bascule simplement l'onglet Alpine vers "Pièces jointes" (pas une liste dupliquée) ; nouvelle carte "Actions rapides" (grille 2×2) : Transférer/Imprimer réellement fonctionnels (Transférer bascule vers l'onglet Circuit + déclenche `$dispatch('modal-show', { name: 'transferer-modal' })`, le mécanisme natif de `<flux:modal.trigger>` — vérifié dans vendor/livewire/flux — pas inventé ; Imprimer réutilise la route bordereau déjà dans l'en-tête), Répondre/Créer une note désactivés+"Bientôt disponible" (aucun modèle de réponse/note distinct de l'historique))
Fichier(s) : tests/Feature/Courriers/ShowCourrierTest.php (2 nouveaux tests : affichage des nouveaux champs, 5 étapes relibellées du parcours)
Pourquoi : demande explicite de l'utilisateur avec capture d'écran détaillée ("i want you to use this design exactly for the detail page"), clarifiée en 2 points via AskUserQuestion avant implémentation (même démarche que pour "Modifier le courrier") : (1) les 4 champs de "Détails complémentaires" sans colonne existante — réponse "Add them for real" ; (2) les 4 boutons "Actions rapides" — réponse "Real 2 (link to existing actions), disable the other 2" pour Répondre/Créer une note, qui n'ont aucune fonctionnalité réelle derrière à ce jour. "Service émetteur"/"Service destinataire" de la maquette confirmés comme relibellés de expediteur_organisation/service_id existants (pas de nouveaux champs, cohérent avec l'analyse déjà faite pour "Modifier le courrier"). Compilation Blade vérifiée manuellement (Blade::compileString() + php -l sur le PHP compilé) avant tout test, aucune trace de "@php" résiduel ni de guillemet double suspect dans un bloc x-data — piège déjà rencontré plusieurs fois cette session sur ce même fichier. 373/373 tests (12/12 sur ShowCourrierTest), build Vite propre, migration exécutée avec succès.

## [2026-09-18 11:15] "Parcours du courrier" mis à jour en temps réel (diffusion Echo/Reverb à chaque transition, pas seulement au clic de l'auteur)
Fichier(s) : app/Events/CourrierStatutChange.php (nouveau — ShouldBroadcastNow sur le même canal privé `courrier.{courrierId}` que OcrTermine/ClassementPropose, déjà autorisé dans routes/channels.php ; broadcastAs 'statut.change')
Fichier(s) : app/Services/WorkflowService.php (`CourrierStatutChange::dispatch($courrier->id, $statut)` ajouté à CHAQUE point qui change réellement `statut` : affecter(), transferer(), validerService(), et une seule fois dans `transitionnerSimple()` — couvre donc aussi demarrerTraitement/soumettrePourValidation/valider/renvoyerPourCorrection/mettreEnAttente/reprendre/rejeter sans dupliquer l'appel dans chacun. reaffecter() non concerné : ne change jamais `statut`, seulement l'affectation)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (nouveau listener `#[On('echo-private:courrier.{courrierId},.statut.change')] statutChange()` — invalide TOUTES les propriétés #[Computed] qui dépendent du statut (courrier, etapesParcours, peutModifier/Affecter/Traiter/Valider/Transferer/ValiderService, collaborateursDuService, destinatairesTransfert), pas seulement `$this->courrier`, sinon le parcours ou les boutons d'action resteraient figés sur l'ancien statut ; `reinitialiserCirculation()` (appelée après CHAQUE action de circuit par l'auteur lui-même) étendue à la même liste, par cohérence — ne dépendait auparavant que de `courrier`/`collaborateursDuService`)
Fichier(s) : tests/Feature/Services/WorkflowServiceTest.php (3 nouveaux tests : l'événement est diffusé à chaque étape du cycle complet — affecter/démarrerTraitement/soumettrePourValidation/valider —, et séparément pour transferer()/validerService(), qui ne passent pas par transitionnerSimple())
Fichier(s) : tests/Feature/Courriers/ShowCourrierTest.php (1 nouveau test : la fiche ouverte se met à jour quand un AUTRE utilisateur change le statut pendant que la page reste affichée, même motif que le test OCR déjà existant)
Pourquoi : demande explicite de l'utilisateur ("ok ;ake the pacour du courier work live in real time at each step of the courier") — jusqu'ici, "Parcours du courrier" ne se mettait à jour que via un rechargement complet de la page, ou par coïncidence si l'AUTEUR d'une action rechargeait $this->courrier pour une autre raison (aucune propriété liée au parcours n'était explicitement invalidée après une transition). Même principe déjà établi pour l'OCR (Règle n°1, notification par Job/Event plutôt que wire:poll) étendu au circuit de validation : un collègue qui affecte/traite le même courrier dans un AUTRE onglet doit se refléter ici sans action de l'utilisateur qui regarde la fiche. 377/377 tests (19/19 WorkflowServiceTest, 13/13 ShowCourrierTest), build Vite propre.

## [2026-09-18 11:30] Correctif — "Création" (et les étapes déjà franchies) restaient marquées non franchies sur une piste annexe du parcours (transfert/attente/rejet)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (`etapesParcours()` : les statuts hors des 5 étapes standard — en_attente_de_transfert/en_cours_de_transfert/en_attente_information/rejete — sont désormais mappés vers une position EFFECTIVE avant de calculer `atteinte`, au lieu de laisser `array_search()` échouer pour tout et traiter "rien n'est encore arrivé" ; nouvelle méthode `dernierePositionAvantPause()` — retrouve dans l'historique la dernière action de progression réelle avant une mise en attente/un rejet, jamais une position inventée ; "Création" (index 0) est désormais TOUJOURS considérée franchie pour une fiche consultable, indépendamment de la piste actuelle — le courrier existe forcément déjà)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (icônes de la frise : toute étape franchie OU en cours affiche désormais une coche (verte pour "Création", bleue pour les autres) au lieu d'un numéro — seule une étape vraiment future affiche une icône neutre (horloge) ; connecteurs entre étapes franchies passés du vert au bleu, cohérent avec les coches)
Fichier(s) : tests/Feature/Courriers/ShowCourrierTest.php (nouveau test : "Création" reste franchie pour un courrier "en_attente_de_transfert", "Affectation" reste non franchie, le bandeau "hors du parcours standard" reste affiché)
Pourquoi : l'utilisateur a comparé deux captures d'écran côte à côte (l'état réel de l'app sur un courrier "en_attente_de_transfert" montrant TOUTES les étapes non franchies, contre sa maquette de référence sur un courrier "en_traitement" montrant Création/Affectation correctement cochées avec de vraies dates) et demandé d'identifier l'erreur. Cause exacte : `array_search('en_attente_de_transfert', $ordre)` renvoie `false` (cette valeur n'existe pas dans les 5 statuts standard), donc `$indexActuel` valait `false` et AUCUNE étape — pas même "Création", qui a forcément déjà eu lieu puisque le courrier existe — n'était jamais marquée franchie. 378/378 tests, build Vite propre.

## [2026-09-18 16:30] Correctif — une action de circuit réussie affichait à tort "Action impossible : la fiche a changé entre-temps"
Fichier(s) : app/Services/WorkflowService.php (nouvelle méthode privée `diffuserChangementStatut()` : encapsule `CourrierStatutChange::dispatch()` dans un `try/catch (Throwable $e) { report($e); }` — même principe "non bloquant" que `deplacerFichier()` juste au-dessus pour les échecs S3 ; appelée depuis `affecter()`, `transferer()`, `validerService()` et `transitionnerSimple()` à la place de l'appel direct à `dispatch()`)
Fichier(s) : tests/Feature/Services/WorkflowServiceTest.php (nouveau test `test_un_echec_de_diffusion_temps_reel_n_empeche_pas_la_transition()` : enregistre un driver de broadcasting factice qui lève systématiquement `BroadcastException`, puis vérifie que `affecter()` réussit malgré tout et que le statut est bien persisté)
Pourquoi : l'utilisateur a signalé avoir vu le message "Action impossible : la fiche a changé entre-temps, elle vient d'être actualisée" après un simple clic unique sur un bouton d'action, sans autre onglet/session ouvert. Investigation du log (`storage/logs/laravel.log`) : `Pusher error: cURL error 7: Failed to connect to localhost:8080` au moment exact de l'action — le serveur Reverb local n'était pas joignable. `CourrierStatutChange` est `ShouldBroadcastNow` (diffusion SYNCHRONE, ajoutée dans la fonctionnalité temps réel du parcours, voir entrée du 2026-09-18 11:15) et `Illuminate\Broadcasting\BroadcastException` ÉTEND `RuntimeException` — l'échec de connexion au serveur de diffusion remontait donc tel quel jusqu'au `catch (RuntimeException $e)` de `ShowCourrier::executer()`, conçu pour un tout autre cas (transition métier refusée par `verifierTransition()`). La transition avait en réalité déjà réussi et été commitée en base AVANT l'appel de diffusion — seule la notification temps réel best-effort échouait. 379/379 tests.

## [2026-09-18 17:00] "Parcours du courrier" : nouvelle étape "Transfert" ajoutée après "Création"
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (`etapesParcours()` passe de 5 à 6 étapes conceptuelles — clés renommées 'creation'/'transfert'/'affecte'/'en_traitement'/'en_validation'/'traite' (au lieu de réutiliser directement le statut brut 'enregistre', ambigu : il désigne aussi bien "jamais transféré" — sortant/sinistre auto-routé, voir RegistrationForm::enregistrer() — que "transfert validé par la DGA", voir WorkflowService::validerService()) ; 'enregistre'/'en_attente_de_transfert'/'en_cours_de_transfert' sont tous mappés sur l'étape "Transfert" ; "courante" pour "Transfert" restreint aux statuts LITTÉRALEMENT en cours de transfert (ou rejeté pendant le transfert), pas simplement égal à statutEffectif, sinon un courrier déjà revenu à 'enregistre' (transfert fini ou jamais requis) l'affichait encore comme "En cours" ; nouveau sous-libellé "Non applicable" quand l'étape est franchie sans date ET sans être courante (courrier sortant/sinistre qui n'a jamais eu besoin de transfert) ; `dernierePositionAvantPause()` mise à jour avec les mêmes clés)
Fichier(s) : tests/Feature/Courriers/ShowCourrierTest.php (test "5 étapes" renommé "6 étapes" + assertion "Transfert" ; `firstWhere('statut', 'enregistre')` → `'creation'` ; 2 nouveaux tests : "Non applicable" pour un courrier sortant jamais transféré, date réelle affichée une fois `service_valide_dga` tracé)
Pourquoi : demande explicite de l'utilisateur ("on the parcour add transfere after creation") — la frise "Parcours du courrier" (Module 4/1, maquette du 2026-09-18) doit refléter le sous-circuit de transfert (réceptionniste → DGA, voir SRS-GEC.pdf/DECISIONS.md "Destinataires de transfert") comme une étape visible à part, entre Création et Affectation, plutôt que de la laisser invisible dans le statut "Création" comme avant. 381/381 tests.

## [2026-09-18 17:15] Échelle de taille de texte Tailwind relevée globalement (toutes pages)
Fichier(s) : resources/css/app.css (bloc `@theme` : `--text-xs` à `--text-3xl` et leurs `--text-*--line-height` associés surchargés — +1px sur xs/sm/base/lg/xl (12→13/14→15/16→17/18→19/20→21px), +2px sur 2xl/3xl (24→26/30→32px), hauteur de ligne augmentée du même delta absolu pour garder le rythme visuel proportionnel ; `text-4xl`/`text-5xl` non touchés, usage limité à welcome.blade.php)
Pourquoi : demande explicite de l'utilisateur ("increase the font size of the text" puis "all co;plete pages everywhere there are too light" — texte jugé trop petit partout dans l'app). Une surcharge des variables `--text-*` de l'échelle Tailwind v4 (au lieu de modifier chaque usage de `text-xs`/`text-sm`/etc. dans des dizaines de fichiers Blade un par un) s'applique automatiquement à CHAQUE page existante ET future qui utilise ces classes. `npm run build` relancé (pas de watcher, voir mémoire piègesLivewireFlux). 381/381 tests (changement CSS pur, aucune régression fonctionnelle attendue ni constatée).

## [2026-09-18 17:30] Graisse de texte allégée puis annulée, couleur de texte principal passée au noir pur
Fichier(s) : resources/css/app.css (deux ajustements successifs du bloc `@theme` : (1) `--font-weight-semibold`/`--font-weight-bold` temporairement descendus à 500/600 suite à "no bold" ; (2) `--color-brand-text-primary` passé de #172033 à #000000 suite à "black not light black or light gray but black" ; (3) l'utilisateur a ensuite précisé vouloir l'inverse du (1) — "i want a pure black for text i don't want that light black it should be bold" — donc `--font-weight-semibold`/`--font-weight-bold` restaurés à 600/700, le noir pur du (2) étant conservé)
Pourquoi : itérations successives de l'utilisateur sur la lisibilité du texte (suite à la demande précédente d'agrandir les tailles) — le résultat final voulu est un texte noir pur (#000000, pas la teinte bleu-nuit #172033 du système de couleurs GEC ni les gris secondaires/muted, INCHANGÉS) ET gras (échelle de graisse Tailwind par défaut, pas allégée). `npm run build` relancé à chaque étape. 381/381 tests (changements CSS purs).

## [2026-09-18 17:45] "Détail du courrier" : valeurs de la carte "Informations générales" mises en gras
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (carte "Informations générales" : chaque `<flux:text>` affichant une VALEUR de champ — N° Courrier, Objet, Priorité, Confidentialité, Catégorie, Service émetteur/destinataire, Date d'envoi, Expéditeur, Destinataire, Mode de réception, Référence externe, et les coordonnées repliées Téléphone/Email/RC/NIU — passe de `font-medium`/aucune classe de graisse à `font-bold` ; les LIBELLÉS (`text-xs uppercase text-zinc-500`) restent inchangés, la hiérarchie label-clair/valeur-grasse est volontaire)
Pourquoi : demande explicite de l'utilisateur avec capture d'écran de la page "Détail du courrier" ("the should be bold text") — après le passage au noir pur, les valeurs de champ restaient en graisse normale/medium alors que l'utilisateur les veut visiblement grasses, cohérent avec la demande précédente "it should be bold". 16/16 tests ShowCourrierTest, npm run build relancé.

## [2026-09-18 18:00] Nuances zinc-400/500 (texte) passées au noir pur
Fichier(s) : resources/css/app.css (bloc `@theme` : `--color-zinc-400` et `--color-zinc-500` passés de #94A3B8/#64748B à #000000 — exactement les deux nuances documentées comme usages TEXTE — libellés de champ `text-zinc-500`, texte secondaire/placeholders `text-zinc-400` — par opposition à zinc-50/200/300 qui sont des fonds/bordures et zinc-600-950 qui restent le mode sombre réel de la sidebar, INCHANGÉS, voir mémoire dashboard_module10_navigation)
Pourquoi : demande explicite de l'utilisateur ("change the zinc to black for texts", fichier app.css ouvert dans l'IDE) — continuation de la série de demandes sur la lisibilité du texte (taille, graisse, couleur du texte principal) : les libellés/texte secondaire encore en gris zinc restaient trop clairs par rapport au reste du texte désormais noir pur. 381/381 tests, npm run build relancé.

## [2026-09-18 18:15] Correctif — système à deux niveaux de noir (« secondary black » ≠ « primary black »), plus de tout-au-noir-pur uniforme
Fichier(s) : resources/css/app.css (bloc `@theme` : `--color-brand-text-secondary`/`--color-brand-text-muted` passés de #64748B/#94A3B8 à #404040 — "secondary black", au lieu du noir pur — `--color-brand-text-primary` reste #000000 — "primary black" ; `--color-zinc-400`/`--color-zinc-500` (mis au noir pur à l'étape précédente) réalignés sur cette même teinte "secondary black" #404040 plutôt que #000000)
Pourquoi : l'utilisateur a précisé, après le passage précédent de zinc-400/500 au noir pur, que ce n'était pas ce qu'il voulait — "some should be secondary black and others primary black" : une hiérarchie à DEUX teintes de noir doit subsister (libellé plus clair que la valeur), au lieu d'un noir pur uniforme partout qui aplatit toute distinction visuelle entre libellé et contenu. 381/381 tests, npm run build relancé.

## [2026-09-18 18:30] "Détail du courrier" : onglets passés de boutons-pilules à des tabs soulignés
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (barre d'onglets Général/Pièces jointes/Circuit de traitement/Historique/Réponses/Commentaires : `<flux:button>` avec variant primary/ghost + fond de carte blanc remplacés par de simples `<button>` sur une ligne de base commune (`border-b border-brand-border`), chaque onglet portant son propre `border-b-2` — bleu pour l'onglet actif (`:class` Alpine sur `onglet === '...'`), transparent sinon ; Réponses/Commentaires désactivés gardent le même style avec `opacity-50`)
Pourquoi : demande explicite de l'utilisateur ("i want them as tab with a line underneath them when we switch to one not button") — remplace le style bouton/pilule par le motif classique "tabs soulignés" (ligne sous l'onglet actif uniquement, pas de fond coloré). 381/381 tests, npm run build relancé.

## [2026-09-18 18:45] Tableau de bord : panneau latéral (Tâches du jour/Notifications/Calendrier) rendu sticky
Fichier(s) : resources/views/livewire/frontend/dashboard.blade.php (colonne de droite `<div class="space-y-6">` → `<div class="sticky top-20 self-start space-y-6">` — exactement le même motif déjà utilisé pour le panneau de droite de editForm/registrationForm/workflowQueue/showCourrier, jusqu'ici absent du tableau de bord)
Pourquoi : demande explicite de l'utilisateur, capture d'écran du tableau de bord à l'appui — "the side panel of the dashboard ... should be sticky everywhere on each page there [it's] been called" : le panneau latéral du dashboard était la seule page à deux colonnes de l'app sans ce comportement déjà établi ailleurs. Le panneau "Aperçu du courrier" de courrierList reste volontairement NON sticky (décision explicite antérieure du 2026-09-18 09:30, non concernée par cette demande qui vise spécifiquement le panneau du dashboard). 381/381 tests, npm run build relancé.

## [2026-09-18 19:00] Tableau de bord : KPI + actions rapides + "Derniers courriers" déplacés dans la même colonne que le panneau latéral
Fichier(s) : resources/views/livewire/frontend/dashboard.blade.php (restructuration complète : SEULE la bannière "Bonjour :nom" reste hors grille, pleine largeur ; les cartes KPI, les 4 tuiles d'actions rapides et la section "Derniers courriers enregistrés" sont désormais TOUTES à l'intérieur de la colonne gauche `space-y-6 lg:col-span-2` d'une grille `grid gap-6 lg:grid-cols-3` commençant juste sous la bannière, avec le panneau latéral sticky (Tâches du jour/Notifications/Calendrier) comme sibling de droite sur toute cette hauteur — au lieu de KPI/actions rapides pleine largeur PUIS seulement la table à côté du panneau)
Pourquoi : demande explicite de l'utilisateur avec capture d'écran ("not the same thing the panel should be side by side with the kpi too but the welcome message should be full width") — applique au tableau de bord la même convention permanente déjà établie pour toute page à panneau latéral (voir memory apercu_panel_layout_convention : le panneau doit être sibling de TOUT le contenu de gauche dès sous le header, pas seulement d'un tableau plus bas dans la page). 381/381 tests (dont 8/8 DashboardTest confirmant que le Blade restructuré compile correctement), npm run build relancé.

## [2026-09-18 19:10] Tableau de bord : plafond max-w-6xl retiré
Fichier(s) : resources/views/livewire/frontend/dashboard.blade.php (`<section class="w-full max-w-6xl">` → `<section class="w-full">` — le tableau de bord occupe désormais toute la largeur de la zone de contenu principal au lieu d'être plafonné à 1152px)
Pourquoi : demande explicite de l'utilisateur ("use the complete space on that dashboard main") — juste après la restructuration de la grille KPI/panneau latéral, le contenu restait plafonné et laissait un vide inutilisé sur les écrans larges. Changement scopé à `dashboard.blade.php` uniquement, pas une règle appliquée à toutes les pages (plusieurs autres vues partagent encore `max-w-6xl`, non concernées par cette demande). 381/381 tests, npm run build relancé.

## [2026-09-18 19:20] Tableau de bord : cartes KPI compactées + carte "Notifications" restylée (sans données fabriquées)
Fichier(s) : resources/views/livewire/frontend/dashboard.blade.php ((1) 4 cartes KPI : même traitement compact déjà appliqué à courrierList.blade.php — `p-4`/`rounded-2xl`/icône `size-11 rounded-full` → `p-3`/`rounded-xl`/icône `size-9 rounded-lg`, libellé+chiffre regroupés sur une ligne à côté de l'icône au lieu du chiffre en dessous ; (2) carte "Notifications" : zone en pointillés `border-dashed`/`bg-zinc-50` remplacée par le même style de carte blanche + bandeau d'en-tête que "Tâches du jour"/"Calendrier" juste en dessous — le contenu reste "Bientôt disponible.", AUCUNE notification fabriquée, Module 7/SendMailAlertJob n'existe toujours pas)
Pourquoi : demande explicite de l'utilisateur avec capture d'écran — "the kpis reduce it the notification panel design doesn't match the image". Le second point (voir Rule n°5/n°6 CLAUDE.md, mémoire dashboard_module10_navigation) est traité comme un écart de STYLE VISUEL (carte en pointillés vs carte blanche à bandeau dans la maquette), jamais comme une demande de fabriquer de fausses notifications. 381/381 tests, npm run build relancé.

## [2026-09-18 19:35] "Tâches du jour" : repère visuel décoratif ajouté devant chaque tâche, sans quota/progression fabriqués
Fichier(s) : resources/views/livewire/frontend/dashboard.blade.php (chaque `<li>` de la liste "Tâches du jour" : ajout d'un petit carré (`size-3.5 rounded-sm border-2`) devant le numéro/objet du courrier, purement décoratif — pas un vrai `<input type="checkbox">`, aucun état "fait"/coché, aucun wire:click)
Pourquoi : l'utilisateur a comparé deux captures d'écran (le "Tâches du jour" réel de l'app vs la maquette GPT montrant des cases à cocher + barres de progression avec quotas fabriqués comme "Enregistrer 5 courriers entrants 2/5") et demandé si c'était le même design — ce n'était pas le cas, la maquette invente des objectifs/quotas quotidiens qui n'existent nulle part dans le spec GEC. Question posée à l'utilisateur sur comment résoudre l'écart ; réponse : "Keep it real, restyle only" — garder la vraie liste de courriers assignés (Dashboard::tachesDuJour()), ajouter seulement le repère visuel de case à cocher de la maquette, sans jamais fabriquer de progression/quota (Règle n°5/n°6). 381/381 tests, npm run build relancé.

## [2026-09-18 19:50] Tableau de bord : colonne du panneau latéral remise en largeur fixe (320px), pas 1/3 de la page
Fichier(s) : resources/views/livewire/frontend/dashboard.blade.php (`grid gap-6 lg:grid-cols-3` + `lg:col-span-2` sur la colonne de gauche → `grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]`, même motif que editForm/workflowQueue/showCourrier qui utilisent déjà `[minmax(0,1fr)_360px]`)
Pourquoi : demande explicite de l'utilisateur avec capture d'écran de la maquette GPT du 2026-09-16 — "look at your design/style layout does it resemble this one if not it should resemble exactly". Comparaison faite, plusieurs écarts identifiés (taille des cartes KPI — contredirait la demande "reduce" du même utilisateur un peu plus tôt, structure de sidebar — déjà reconstruite sur plusieurs sessions pour coller au vrai système de droits/rôles) ; question posée pour clarifier le périmètre exact ; réponse : "Just narrow the right panel" — seule la largeur du panneau latéral (1/3 de page → 320px fixe) est concernée, rien d'autre. 381/381 tests, npm run build relancé.

## [2026-09-21 09:00] Page "Utilisateurs & Accès" reconstruite depuis la maquette fournie par l'utilisateur — migrations
Fichier(s) : database/migrations/2026_09_21_090000_add_profil_admin_columns_to_users_table.php (colonnes `telephone`/`poste`/`photo_path`/`actif`/`derniere_connexion_le`/`niveau_confidentialite` sur `users`, toutes nullable/valeur par défaut sûre)
Fichier(s) : database/migrations/2026_09_21_090100_add_type_to_privileges_table.php (colonne `type` enum lecture/ecriture/administratif sur `privileges`, rétro-remplie pour les 26 privilèges déjà seedés)
Pourquoi : demande explicite de l'utilisateur — 5 captures d'écran d'une maquette "Utilisateurs & Accès" (table + modales Modifier/Ajouter/Gestion des permissions), stack demandée React+shadcn refusée en plan (voir DECISIONS.md/CLAUDE.md, aucun nouveau framework front) au profit du stack Livewire/Flux existant, confirmé via AskUserQuestion en mode plan. Ces deux migrations comblent les champs de la maquette qui n'avaient encore aucune donnée réelle derrière (Règle n°6 — jamais un champ affiché sans backing réel).

## [2026-09-21 09:05] Idem — modèles (User/Courrier/Privilege) et confidentialité numérique hiérarchique
Fichier(s) : app/Models/User.php (fillable étendu, casts `actif`/`derniere_connexion_le`/`niveau_confidentialite`, `photoUrl()`, `niveauConfidentialiteLabel()`, `niveauConfidentialiteEffectif()` — garde-fou anti-verrouillage Administrateur, même principe que PrivilegePolicy::gerer() ; `protected $attributes` avec valeurs par défaut EN MÉMOIRE pour `actif`/`niveau_confidentialite`, voir bug ci-dessous)
Fichier(s) : app/Models/Courrier.php (`NIVEAUX_CONFIDENTIALITE` — table de correspondance partagée normale=1/confidentiel=2/tres_confidentiel=3, `niveauConfidentialiteNumerique()` — pas de migration de la colonne `confidentialite` elle-même, déjà lue comme chaîne par de nombreux fichiers)
Fichier(s) : app/Models/Privilege.php (fillable + `type` ; `moduleCle()` dérive le module depuis le préfixe de `cle` ; `MODULES` — libellé+icône des 6 modules RÉELS du seeder, pas les 8 modules fictifs de la maquette)
Fichier(s) : app/Policies/CourrierPolicy.php (nouvelle méthode privée `niveauSuffisant()`, appelée en premier dans view()/update()/affecter()/traiter()/valider()/transferer()/validerService() — cumulatif avec le système de privilèges existant, jamais un remplacement, voir DECISIONS.md "Confidentialité numérique hiérarchique")
Fichier(s) : app/Livewire/Backend/CourrierList.php (`portee()` filtre désormais aussi par `niveauConfidentialiteEffectif()` — un courrier trop confidentiel n'apparaît même plus dans les listes, pas seulement bloqué à l'ouverture)
Pourquoi : décision explicite de l'utilisateur en mode plan ("Build full enforcement now") — la maquette affiche un champ "Niveau de confidentialité" par utilisateur ; DECISIONS.md documentait déjà ce point comme "le changement le plus structurant" resté net-nouveau. Bug réel trouvé en testant : `User::factory()->create()` laisse l'attribut en mémoire à `null` (seule la base applique son DEFAULT à l'INSERT, jamais relu après) — `actingAs()` réutilisant cette même instance pour toute la durée d'un test, `Auth::user()->niveau_confidentialite` valait `null` partout et bloquait la quasi-totalité des ~370 tests existants (`null < 1` vrai en PHP) ; corrigé en dupliquant le défaut EN MÉMOIRE via `protected $attributes`, pas seulement au niveau SQL. Second bug réel : un Administrateur (niveau par défaut 1) se retrouvait bloqué sur ses propres courriers confidentiels — corrigé par `niveauConfidentialiteEffectif()`, même garde-fou anti-verrouillage que PrivilegePolicy::gerer(). 385/385 tests.

## [2026-09-21 09:10] Idem — "Activer le compte" et "Dernière connexion" rendus réels (Fortify + listener)
Fichier(s) : app/Providers/FortifyServiceProvider.php (`Fortify::authenticateUsing()` — un compte `actif = false` ne peut plus se connecter, même avec le bon mot de passe ; seul point d'intégration nécessaire, confirmé qu'aucun code n'appelle `Auth::attempt()` directement ailleurs)
Fichier(s) : app/Listeners/EnregistrerDerniereConnexion.php (nouveau — écoute `Illuminate\Auth\Events\Login`, `forceFill()` car `derniere_connexion_le` est délibérément hors `$fillable`)
Fichier(s) : app/Providers/AppServiceProvider.php (`Event::listen(Login::class, EnregistrerDerniereConnexion::class)`)
Pourquoi : Règle n°6 — un toggle "Activer le compte"/une colonne "Dernière connexion" affichés sans effet réel seraient une fonctionnalité fabriquée. Les deux mécanismes s'appuient sur les points d'extension officiels (Fortify/événement Login natif) plutôt que de dupliquer la logique dans chaque flux de connexion.

## [2026-09-21 09:15] Idem — catalogue de privilèges figé, /admin/privileges retiré
Fichier(s) : app/Livewire/Backend/PrivilegeList.php, resources/views/livewire/frontend/privilegeList.blade.php, tests/Feature/Admin/PrivilegeListTest.php (supprimés)
Fichier(s) : routes/web.php (route `admin.privileges` retirée)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (lien "Privilèges" retiré du groupe "Administration", liste de routes `:expanded` mise à jour)
Fichier(s) : database/seeders/PrivilegeSeeder.php (`type` ajouté par entrée pour que tout futur `migrate:fresh --seed` reste correct)
Pourquoi : décision explicite de l'utilisateur en mode plan ("Drop it, catalog becomes fixed") — la maquette de la modale "Gestion des permissions" ne montre aucun moyen de créer une nouvelle définition de privilège, seulement de cocher/décocher celles qui existent déjà. Les 26 privilèges existants restent le catalogue complet ; en ajouter un nouveau redevient un changement de code (entrée PrivilegeSeeder), plus une action admin. /admin/profils (assignation PAR PROFIL + création de profil) reste une page séparée, INCHANGÉE (décision explicite : "Keep profil-level bulk assignment somewhere").

## [2026-09-21 09:20] Idem — page "Utilisateurs & Accès" reconstruite (table + 3 modales)
Fichier(s) : app/Livewire/Backend/UserList.php (réécriture complète — table paginée avec recherche/4 filtres/tri, modale "Ajouter un utilisateur" avec mot de passe aléatoire + lien de réinitialisation réel (`Password::sendResetLink()`, jamais un envoi d'identifiants fabriqué — aucune messagerie de ce type n'existe dans ce projet), modale "Modifier l'utilisateur" à 6 onglets soulignés (Informations générales/Service & Profil/Rôles & Permissions/Niveau de confidentialité/Paramètres de compte/**Destinataires de transfert** — 6e onglet absent de la maquette mais ajouté pour ne pas perdre cette fonctionnalité réelle existante, Règle n°6), modale "Gestion des permissions" avec modules RÉELS + étiquette Lecture/Écriture/Administratif + résumé calculé depuis l'effectif réel de l'utilisateur (jamais les chiffres fictifs de la maquette) ; les méthodes d'assignation de privilèges de l'ancienne page sont conservées telles quelles (mêmes relations Eloquent), seule la présentation change)
Fichier(s) : resources/views/livewire/frontend/userList.blade.php (réécriture complète)
Fichier(s) : app/Services/AvatarService.php (nouveau — upload de photo de profil sur S3, même schéma que PieceJointeService sans la traçabilité/copie de secours propres aux courriers)
Pourquoi : cœur de la demande de l'utilisateur — reconstruire cette page depuis la maquette, dans le stack Livewire/Flux existant. "Ajouter un utilisateur"/"Modifier l'utilisateur" gardent UN SEUL champ "Nom complet" plutôt que Nom+Prénom séparés de la maquette (décision par défaut annoncée dans le plan, non re-demandée — éviter de scinder la colonne `name` lue dans des dizaines de fichiers du projet).

## [2026-09-21 09:25] Idem — tests
Fichier(s) : tests/Feature/Admin/UserListTest.php (réécriture complète — table/filtres/tri, ajout/modification réels, photo S3, compte désactivé bloqué à la connexion, permissions par module avec vraies données, destinataires de transfert)
Fichier(s) : tests/Feature/Courriers/CourrierConfidentialiteTest.php (nouveau — le niveau de confidentialité bloque même un privilège "voir_tout"/"modifier_tout", garde-fou Administrateur, accès direct par URL bloqué pas seulement les listes)
Fichier(s) : tests/Feature/Auth/AuthenticationTest.php (2 tests ajoutés — dernière connexion enregistrée, compte désactivé refusé)
Fichier(s) : tests/Feature/Courriers/RegistrationFormConfidentielTest.php (fixture corrigée — l'agent de `test_un_courrier_confidentiel_ne_peut_pas_etre_scanne` a désormais un niveau de confidentialité explicite, sinon bloqué par le nouveau garde-fou avant même d'atteindre le garde-fou `$estConfidentiel` que ce test vise à prouver)
Pourquoi : Règle n°7 — couverture du nominal ET des refus pour toute la fonctionnalité reconstruite. 385/385 tests, npm run build relancé.

## [2026-09-21 10:00] Correctif — modale "Modifier l'utilisateur" restructurée pour vraiment ressembler à la maquette
Fichier(s) : resources/views/livewire/frontend/userList.blade.php (modale "user-edition" : liste d'onglets horizontale soulignée en haut → grille 3 colonnes `[180px_minmax(0,1fr)_240px]` avec liste d'onglets VERTICALE à gauche + panneau "Aperçu du compte" PERSISTANT à droite — visible sur TOUS les onglets, pas seulement "Informations générales" — reflétant l'état enregistré réel de l'utilisateur, statut/email/téléphone/service/profil/niveau de confidentialité. Modale "user-ajout" : ajout d'une carte "Récapitulatif" live dans la colonne de droite, champs passés en `wire:model.live` pour qu'elle se mette réellement à jour au fur et à mesure de la saisie)
Pourquoi : demande explicite de l'utilisateur avec capture d'écran comparative — "there are not the same design when i compare yours with the images i send you ... this user modal ... doesn't match". La première passe avait remplacé la structure à 3 colonnes de la maquette (onglets verticaux + panneau de contexte persistant) par de simples onglets horizontaux sans aucun panneau d'aperçu. 385/385 tests, npm run build relancé.

## [2026-09-21 10:15] Correctif — champs "Nom"/"Prénom" scindés (cosmétique, une seule colonne `name`)
Fichier(s) : app/Livewire/Backend/UserList.php (nouvelles propriétés `ajoutPrenom`/`editionPrenom` ; `ouvrirEdition()` scinde `$user->name` sur le premier espace ; `ajouter()`/`enregistrerEdition()` recombinent `"{$nom} {$prenom}"` dans la seule colonne `name` existante, jamais deux colonnes séparées)
Fichier(s) : resources/views/livewire/frontend/userList.blade.php (modales "Ajouter"/"Modifier" : "Nom complet" → deux champs "Nom"/"Prénom" côte à côte, comme la maquette ; carte "Récapitulatif" affiche la recombinaison en direct)
Fichier(s) : tests/Feature/Admin/UserListTest.php (tests ajustés pour les deux champs ; nouveau test `test_nom_et_prenom_sont_scindes_a_laffichage_et_recombines_sans_perte` — un nom à 3 mots survit à l'aller-retour scission/recombinaison sans perte)
Pourquoi : demande explicite de l'utilisateur — "i want the exact informations as found that images (i want a replica of it)". Clarifié via AskUserQuestion : scission cosmétique uniquement (deux champs à l'écran), PAS de nouvelle colonne `prenom` en base — `users.name` est lu dans des dizaines de fichiers du projet, une vraie scission aurait été un chantier séparé bien plus risqué pour un gain purement visuel. 386/386 tests, npm run build relancé.

## [2026-09-21 10:25] Correctif — "Service et profil" et "Niveau de confidentialité" fusionnés sous "Informations générales"
Fichier(s) : resources/views/livewire/frontend/userList.blade.php (modale "Modifier l'utilisateur" : les 3 panneaux `x-show="onglet === 'general'"` / `'service'` / `'confidentialite'` fusionnés en UN seul panneau continu affiché dès que l'un des 3 onglets correspondants est actif (`x-show="['general', 'service', 'confidentialite'].includes(onglet)"`) — les 3 entrées de la liste d'onglets restent visibles telles quelles (fidèles à la maquette) mais pointent désormais vers ce même panneau fusionné)
Pourquoi : demande explicite de l'utilisateur — "what about service et profil under profile" — dans la maquette, "Service et profil" (et "Niveau de confidentialité") apparaissent comme des sections À L'INTÉRIEUR du même panneau "Informations générales", jamais masquées derrière un clic sur un onglet séparé ; la première reconstruction à onglets strictement indépendants ne rendait pas ça. 386/386 tests, npm run build relancé.

## [2026-09-21 10:40] La DGA confirme/corrige désormais le niveau de confidentialité réel d'un courrier (pas seulement le choix figé de la réceptionniste)
Fichier(s) : app/Services/WorkflowService.php (`validerService()` prend un nouveau paramètre `string $confidentialite`, persisté avec le service ; trace une entrée d'historique `confidentialite_modifiee_dga` distincte SEULEMENT si la DGA a réellement changé le niveau proposé par la réceptionniste, jamais une ligne "confirmé" bruyante pour rien)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (nouvelle propriété `confidentialiteSelectionnee`, pré-remplie avec le choix de la réceptionniste dans `mount()` — jamais vide, un courrier réellement confidentiel dès l'enveloppe reste protégé même avant validation DGA ; `validerService()` valide et transmet la valeur choisie)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (panneau "Valider le service" : nouveau `<flux:select>` "Niveau de confidentialité" à côté du choix de service)
Fichier(s) : app/Models/User.php (`niveauConfidentialiteEffectif()` étendu à la DGA, pas seulement l'Administrateur — bug réel trouvé en testant : une DGA avec le niveau par défaut (1, Normal) se retrouvait bloquée par CourrierPolicy::niveauSuffisant() sur le courrier même qu'elle doit ouvrir pour en décider le niveau — situation impossible, corrigée par le même garde-fou anti-verrouillage que l'Administrateur)
Fichier(s) : tests/Feature/Services/WorkflowServiceTest.php, tests/Feature/Courriers/CircuitCourrierTest.php, tests/Feature/Courriers/CourrierConfidentialiteTest.php (signature mise à jour partout + nouveaux tests : la DGA change réellement le niveau, aucune trace si inchangé, la DGA voit tout malgré un niveau stocké bas)
Pourquoi : demande explicite de l'utilisateur — "the niveau of profiles are different from the niveau of documents ... it's only when the dga select now the confidentiality that represent the niveau ... that they can be access". Clarifié via AskUserQuestion : le champ de la réceptionniste à l'enregistrement reste nécessaire (un courrier confidentiel dès l'enveloppe, jamais ouvert par la réceptionniste, doit pouvoir être marqué tel quel immédiatement — réponse de l'utilisateur), mais c'est la valeur confirmée/corrigée par la DGA qui devient définitive pour la comparaison au niveau de l'utilisateur. 390/390 tests, npm run build relancé.

## [2026-09-21 10:55] Correctif — retrait de l'exception cachée pour la DGA dans le garde-fou de confidentialité
Fichier(s) : app/Models/User.php (`niveauConfidentialiteEffectif()` : le bypass ajouté pour 'DGA' à l'étape précédente est retiré — reste UNIQUEMENT 'Administrateur', garde-fou anti-verrouillage déjà établi avec précédent réel dans PrivilegePolicy::gerer())
Fichier(s) : tests/Feature/Courriers/CircuitCourrierTest.php (le test de la modale "Valider le service" configure désormais explicitement `niveau_confidentialite: 3` sur la DGA de test, au lieu de compter sur un bypass caché)
Fichier(s) : tests/Feature/Courriers/CourrierConfidentialiteTest.php (le test "DGA voit tout même avec un niveau normal" — qui prouvait justement le bypass retiré — remplacé par deux tests : une DGA JAMAIS configurée reste bloquée comme n'importe qui, une DGA correctement configurée (niveau réellement élevé en base) voit tout)
Pourquoi : correction explicite de l'utilisateur — "no i think the dga profile will have the permissins or niveau elevated that can see all document" — le niveau élevé d'une DGA doit être une DONNÉE réelle que l'administrateur configure (colonne `niveau_confidentialite`, page "Utilisateurs & Accès"), jamais une exception cachée basée sur le nom du profil dans le code (contrairement à Administrateur, qui a un précédent explicite et documenté pour son propre garde-fou). 391/391 tests.

## [2026-09-21 11:20] Confidentialité : passage à une échelle NUMÉRIQUE à 5 niveaux (plus de libellés "Normale"/"Confidentiel"/"Très confidentiel")
Fichier(s) : database/migrations/2026_09_21_110000_convert_courriers_confidentialite_to_numeric.php (nouveau — `courriers.confidentialite` passe d'un enum string 3 valeurs à un entier 1-5 ; colonne temporaire + bascule, l'index dédié existant doit être retiré AVANT dropColumn() sous SQLite, sinon erreur — trouvé en lançant les tests ; mapping des données existantes : normale=1/confidentiel=2/tres_confidentiel=3, niveaux 4/5 nouveaux, jamais utilisés tant que personne ne les choisit)
Fichier(s) : app/Models/Courrier.php (colonne `confidentialite` castée `integer` ; `NIVEAUX_CONFIDENTIALITE`/`niveauConfidentialiteNumerique()` supprimés, plus besoin de table de correspondance ; `protected $attributes` avec `confidentialite => 1` — même correctif "défaut en mémoire" déjà appliqué à User, pour que les futurs `Courrier::create()` sans confidentialite explicite ne recréent pas le même piège de test)
Fichier(s) : app/Models/User.php (nouvelle constante `NIVEAU_CONFIDENTIALITE_MAX = 5`, un seul endroit à changer si l'échelle évolue encore ; `niveauConfidentialiteEffectif()` utilise ce plafond pour le garde-fou Administrateur ; `niveauConfidentialiteLabel()` supprimée — plus de libellé FR, affichage numérique nu partout)
Fichier(s) : app/Policies/CourrierPolicy.php, app/Livewire/Backend/CourrierList.php (comparaisons numériques directes `$courrier->confidentialite`, plus de table de correspondance chaîne→entier)
Fichier(s) : app/Livewire/Backend/Forms/CourrierForm.php, app/Livewire/Backend/RegistrationForm.php, app/Livewire/Backend/RegistrationFormConfidentiel.php, app/Livewire/Backend/ScanForm.php, app/Livewire/Backend/ShowCourrier.php, app/Livewire/Backend/UserList.php, app/Services/WorkflowService.php, app/Http/Controllers/CourrierAccuseReceptionController.php (types/validations/comparaisons passés en entier ; `RegistrationFormConfidentiel::$niveauConfidentialite` restreint à 2-5, jamais 1/Normal — un courrier passant par ce formulaire dédié EST déjà confidentiel par définition)
Fichier(s) : resources/views/livewire/frontend/{showCourrier,courrierList,editForm,registrationForm,registrationFormConfidentiel,userList}.blade.php, resources/views/pdf/{accuse-reception,bordereau}.blade.php (dropdowns/radios générés par `range(1, User::NIVEAU_CONFIDENTIALITE_MAX)`, valeurs ET libellés = le nombre lui-même ; badges affichent le chiffre nu avec un dégradé de couleur par plage plutôt qu'un cas par valeur exacte)
Fichier(s) : tests/Feature/Courriers/{CircuitCourrierTest,CourrierConfidentialiteTest,CourrierListTest,EditFormTest,RegistrationFormConfidentielTest,RegistrationFormTest}.php, tests/Feature/Services/WorkflowServiceTest.php (toutes les chaînes 'normale'/'confidentiel'/'tres_confidentiel' remplacées par les entiers correspondants 1/2/3)
Pourquoi : demande explicite de l'utilisateur — "the niveau should be numbers not confidential or what ever but numbers 1,2,3,4,5 etc". Clarifié via AskUserQuestion : échelle réellement élargie à 5 niveaux (pas seulement un réaffichage numérique des 3 niveaux existants). 391/391 tests, npm run build relancé.

## [2026-09-21 11:35] Correctif — les niveaux de confidentialité affichent "Niveau :n", jamais un chiffre nu
Fichier(s) : resources/views/livewire/frontend/{showCourrier,courrierList,editForm,registrationForm,registrationFormConfidentiel,userList}.blade.php, resources/views/pdf/bordereau.blade.php (toute valeur/étiquette de confidentialité affichée à l'écran — options de dropdown/radio, badges, cartes "Récapitulatif"/"Aperçu du compte" — passée de `{{ $niveau }}` nu à `{{ __('Niveau :n', ['n' => $niveau]) }}` ; seul l'attribut `value="{{ $niveau }}"` des options reste le chiffre brut, jamais affiché tel quel)
Fichier(s) : tests/Feature/Courriers/CourrierListTest.php (assertion resserrée de `assertSee('2')` à `assertSee('Niveau 2')`, pour vérifier réellement le nouveau libellé plutôt qu'un chiffre isolé qui aurait pu matcher n'importe où sur la page)
Pourquoi : correction explicite de l'utilisateur (majuscules) — "THE LABEL SHOULD BE NIVEAU 1 OR LEVEL 1" — la passe précédente avait retiré TOUS les libellés (y compris le mot "Niveau"), affichant un chiffre nu sans contexte ; corrigé pour garder le préfixe "Niveau" partout, sans réintroduire les anciens libellés catégoriels "Normal"/"Confidentiel"/"Très confidentiel". 391/391 tests, npm run build relancé.

## [2026-09-21 19:45] Correctif — base de données locale réelle vidée par erreur (`migrate:fresh`)
Fichier(s) : aucun fichier de code — incident opérationnel, consigné ici pour traçabilité
Pourquoi : en vérifiant les nouvelles migrations "Dossiers & Archives", `php artisan migrate:fresh --seed` a été exécuté deux fois SANS vérifier au préalable que `.env` pointait vers la vraie base MySQL locale (`DB_DATABASE=gec`), pas une base de test isolée (`phpunit.xml` force `DB_CONNECTION=sqlite`/`:memory:` uniquement pour l'environnement `testing`) — a vidé tous les courriers/brouillons réels de l'utilisateur. Dommage confirmé : 0 courrier restant (contre des dizaines avant), seuls les comptes de test reseedés survivent. Fichiers physiques non touchés (seule la base de données l'est) : 22 fichiers sous `courriers/`, 8 sous `brouillons/` toujours présents sur le disque S3/MinIO — dont le brouillon de la scan réalisée par l'utilisateur après l'incident (`brouillons/b5619b33-...pdf`, créé après coup), dont l'enregistrement `courrier_brouillons` avait bien survécu mais dont l'OCR n'avait jamais pu se terminer faute de worker de queue actif (voir entrée suivante) — traité manuellement et réassigné au bon compte (`cree_par_id` pointait par erreur vers Admin Test après le reseed, corrigé vers Agent Test). Aucune sauvegarde disponible pour les 21 fichiers plus anciens (2026-09-03 → 09-17) — reconstruction proposée à l'utilisateur, pas encore tranchée.

## [2026-09-21 19:50] Infrastructure — worker de queue et serveur Reverb démarrés manuellement (session locale)
Fichier(s) : aucun fichier de code — processus locaux démarrés en arrière-plan pour cette session de développement (`php artisan queue:work --queue=ocr,indexation,replication,default`, `php artisan reverb:start`)
Pourquoi : aucun des deux ne tournait en arrière-plan sur ce poste — la file d'attente avait 2 jobs bloqués (OCR + réplication du brouillon post-incident), et le port Reverb (8080) était injoignable, empêchant tout retour temps réel déjà câblé dans l'application (CourrierStatutChange, BrouillonOcrTermine) de fonctionner malgré le code existant. Démarrés manuellement pour débloquer les tests de l'utilisateur ; ATTENTION — ces deux processus ne survivent pas à un redémarrage et rien ne les relance automatiquement en cas de plantage : nécessite un vrai service Windows/superviseur pour un usage de développement continu, pas encore mis en place.

## [2026-09-21 19:55] Module 1 — le NIU peut aussi être étiqueté "N°" sur le document source
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (`extraireExpediteurNiu()` — nouveau repli `/\bN°\s*:?\s*([PM]\d{6,15}[A-Za-z]?)\b/u`, entre l'étiquette "Contribuable" et le repli "à côté du RC")
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (nouveau test `test_le_niu_est_extrait_quand_etiquete_n_degre_au_lieu_de_niu`)
Pourquoi : demande explicite de l'utilisateur — un document réel note le NIU sous "N° :" sans jamais écrire "NIU" ni "Contribuable". "N°" seul étant beaucoup trop générique pour être accepté tel quel (numéro de téléphone/adresse/RC), le nouveau repli exige la même forme de NIU camerounais déjà établie ailleurs dans cette méthode (P/M suivi d'au moins 6 chiffres) — jamais une valeur acceptée sur la seule base de l'étiquette. 430/430 tests.

## [2026-09-21 20:05] Module 1 — l'organisation expéditrice peut être devinée depuis le domaine d'un email, en dernier recours
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (`extraireExpediteurOrganisation()` — nouvel appel à `organisationDepuisDomaineEmail()` après `organisationPresDunBlocCoordonnees()` ; nouvelle méthode privée `organisationDepuisDomaineEmail()`)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (nouveau test `test_lorganisation_est_proposee_depuis_le_domaine_dun_email_en_dernier_recours`)
Pourquoi : demande explicite de l'utilisateur, sur un vrai document dont l'expéditeur ne s'identifie que via son email ("kpamela@easytechgroup.net", aucune forme juridique ni bloc de coordonnées reconnaissable) — jusqu'ici cette combinaison ne proposait aucune organisation du tout. Réutilise `extraireExpediteurEmail()` (jamais une seconde extraction divergente) ; exclut explicitement les fournisseurs d'email publics/génériques (gmail, yahoo, hotmail, outlook...) pour ne jamais proposer leur nom comme s'il s'agissait d'une entreprise, et le domaine Nsia lui-même (même filtre que `premiereOrganisationHorsNsia()`). Aucune tentative de scinder le domaine en mots ("easytechgroup" reste "Easytechgroup", jamais deviné "Easy Tech Group") — devinerait une structure absente du texte source ; l'agent corrige via le bandeau "à vérifier" déjà en place pour toute proposition automatique. 431/431 tests.

## [2026-09-22 09:00] Correctif — le numéro RC avalait le mot suivant quand l'OCR le colle sans espace
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (`extraireExpediteurRc()` — dernière partie du motif changée de `[\w\/\-]{3,30}` à `[\w\/\-]{2,29}\d`, force la capture à s'arrêter sur un chiffre)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (nouveau test `test_le_rc_ne_colle_pas_le_mot_suivant_sans_espace`)
Pourquoi : bug réel signalé par l'utilisateur sur le même document EASYTECH GROUP SA — l'OCR colle "Contr." (début de "Contribuable") directement après le numéro sans espace ("...3204Contr."), et `[\w\/\-]{3,30}` (`\w` inclut les lettres) avalait ce fragment, donnant "RC/DLA/2020/B/3204Contr" au lieu de "RC/DLA/2020/B/3204". Tous les numéros RC réels déjà couverts par les tests existants se terminent par le numéro de séquence (un chiffre), jamais une lettre — la capture exige donc désormais un chiffre final, vérifié sans régression sur les deux autres vrais documents déjà couverts (ITSC Sarl, Ste SAPDIST SARL). 432/432 tests.

## [2026-09-22 09:05] Correctif (retour terrain) — email non reconstruit sur un document où l'OCR a perdu le "@" et des lettres autour
Fichier(s) : aucun — investigation, pas de changement de code
Pourquoi : sur le même document EASYTECH GROUP SA, l'email "kpamela@easytechgroup.net" est resté vide dans le formulaire — vérifié directement dans le texte OCR stocké : l'OCR a non seulement perdu le caractère "@" mais aussi de vraies lettres autour ("kpamela" → "kpa"/"mel" sur deux lignes, "easytechgroup" → "asytechgroup") — pas une simple coupure récupérable, de vraies lettres manquent. Reconstruire "à l'aveugle" reviendrait à deviner quelles lettres complètent le mot, une valeur inventée (contraire à la Règle n°6) — laissé volontairement vide pour saisie manuelle par l'agent (le document scanné reste visible dans l'aperçu). Documenté explicitement plutôt que de fabriquer une correction qui aurait semblé fonctionner sans l'être. Investigation approfondie menée à la demande de l'utilisateur (rendu 600dpi, recadrage isolé de la ligne, seuil de luminosité) : confirme que le texte en lien hypertexte bleu souligné dégrade la lecture au-delà de ce que ce pipeline peut corriger de façon fiable — pas intégré au pipeline (nécessitait un recadrage manuel, et le résultat restait encore faux même amélioré).

## [2026-09-22 09:15] Module 1 — la date pré-remplie est désormais celle du jour, pas la date écrite par l'expéditeur
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (`mount()` : repli sur `now()->format('Y-m-d')` quand `dateDepuisTampon()` ne résout rien ; méthode `dateDepuisTexteCourrier()` supprimée entièrement, avec elle la propriété `$dateProposeeAutomatiquement` — plus aucun appelant)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (bandeau "Proposée depuis la date du courrier — à vérifier" retiré, devenu mort)
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php (`test_la_date_est_proposee_depuis_le_texte_du_courrier_sans_tampon` → `test_la_date_par_defaut_est_celle_du_jour_sans_tampon` ; `test_le_tampon_reste_prioritaire_sur_la_date_du_texte_du_courrier` renommé/simplifié ; `test_la_date_du_tampon_naffiche_pas_a_verifier_meme_si_dautres_champs_sont_proposes` et `test_janvier_est_reconnu_comme_mois_en_toutes_lettres_pas_seulement_juillet` supprimés — leur prémisse/chemin de code n'existe plus ; `test_une_date_de_tampon_hors_plage_calendaire_nest_pas_silencieusement_decalee` mis à jour : un tampon invalide retombe désormais sur la date du jour, plus sur un champ vide)
Pourquoi : demande explicite de l'utilisateur — depuis l'abandon du tampon papier (2026-09-04), la seule source de pré-remplissage restante pour "Date de réception" était la date ÉCRITE par l'expéditeur en tête du courrier, qui est sa date de RÉDACTION/ENVOI, pas la date de RÉCEPTION chez Nsia (peut différer de plusieurs jours) — un mauvais proxy maintenant que le tampon (qui, lui, actait un vrai événement de dépôt) n'est plus utilisé. La date du jour de l'enregistrement est un point de départ nettement plus fiable, librement corrigible par l'agent. Le tampon, quand il résout malgré tout une date valide, reste prioritaire (vraie trace de dépôt). `MOIS_FRANCAIS` conservée telle quelle (encore utilisée par `dateDepuisTampon()`). 430/430 tests (392 + 38, deux tests retirés dont le chemin de code a disparu).

## [2026-09-22 09:30] Correctif — les champs du formulaire ne se remplissaient qu'au rechargement une fois l'OCR terminé après sélection
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (nouvelle méthode privée `appliquerBrouillon()` — factorise `mount()` ; `brouillonOcrTermine()` l'appelle désormais aussi sur le brouillon actuellement ouvert)
Pourquoi : bug réel signalé par l'utilisateur ("why do i have to refresh before the ocr finished to extract the text after i selected it") — `brouillonOcrTermine()` (déclenché par la diffusion temps réel `echo-private:App.Models.User.{agentId},.brouillon.ocr.termine`, voir DECISIONS.md "Watcher automatique") rafraîchissait déjà le statut affiché dans la liste déroulante, mais jamais les CHAMPS du formulaire (objet, expéditeur...) du brouillon actuellement ouvert. Un agent qui sélectionnait un brouillon AVANT la fin de l'OCR (navigation vers `?brouillonId=X`, qui exécute `preremplirDepuisBrouillon()` une seule fois, à ce moment précis où `texte_ocr` est encore vide) restait donc bloqué avec un formulaire vide jusqu'à un rechargement manuel de la page, malgré l'infrastructure temps réel déjà en place. Le canal étant par AGENT (pas par brouillon), la méthode peut désormais être appelée pour un événement concernant un AUTRE brouillon que celui ouvert — sans effet dans ce cas (rappliquer sur un brouillon inchangé est un no-op), corrige le cas visé quand c'est le même.

## [2026-09-22 09:35] Module 1 — le NIU reconnaît aussi l'étiquette abrégée "Cont[r]. N°"
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (`extraireExpediteurNiu()` — nouveau repli `/\bCont(?:r)?\.?\s*N°\s*:?\s*([\w]{5,20})/iu`, entre "Contribuable" en toutes lettres et le repli générique "N°" seul)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (nouveau test `test_le_niu_est_extrait_quand_etiquete_cont_n_degre`)
Pourquoi : demande explicite de l'utilisateur, sur le même vrai document EASYTECH GROUP SA — l'OCR colle "Contr." (abrégé de "Contribuable") directement après le numéro RC, suivi de "N°" puis du NIU ("...3204Contr. N° M062015196381P"). Étiquette explicite comme "Contribuable" en toutes lettres (accepte n'importe quelle valeur plausible, pas seulement la forme P/M du repli générique "N°" seul, plus bas dans la liste des replis). 431/431 tests.

## [2026-09-22 09:45] Correctif — l'objet tronquait un intitulé sur deux lignes tout en majuscules sans mot de liaison reconnu
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (`extraireObjetDeLaMentionExplicite()` — second signal de recollement ajouté : deux lignes consécutives tout en majuscules, en plus du mot de liaison existant ; nouvelle méthode privée `estEnMajusculesUniquement()`)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (nouveau test `test_lobjet_recolle_la_ligne_suivante_quand_les_deux_sont_tout_en_majuscules`)
Pourquoi : bug réel signalé par l'utilisateur ("objet doesn't take all the info") sur un vrai document (CDS Technologies Sarl) — "Objet : OFFRE SPECIALE DE DEUX MOIS SDG" se coupait avant "DE CONNEXION INTERNET PAR FIBRE OPTIQUE GRATUITE" car la 1ère ligne ne se termine par aucun mot de liaison reconnu ("SDG", probablement du bruit OCR d'un tampon de service voisin). Un objet tout en majuscules qui continue sur une ligne ELLE AUSSI tout en majuscules est un second signal fiable (même principe déjà utilisé par `organisationPresDunBlocCoordonnees()` dans ce fichier). Vérifié sur la ligne SUIVANTE (pas seulement la ligne déjà captée), sinon une 2e ligne également en majuscules continuerait d'avaler la formule d'appel qui suit.

## [2026-09-22 09:50] Correctif — une formule d'introduction ("L'entreprise X…") polluait le nom de l'organisation proposé
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (`premiereOrganisationHorsNsia()` — retire désormais une formule d'introduction en tête de candidat avant de le retenir)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (nouveau test `test_une_formule_dintroduction_nest_pas_avalee_dans_le_nom_via_le_motif_suffixe`)
Pourquoi : découvert en vérifiant le RC/NIU du même document CDS Technologies (demande de l'utilisateur "the number now and the niu") — "L'entreprise CDS Technologies Sarl" était proposé comme organisation au lieu de "CDS Technologies Sarl". Cause : le motif "forme juridique en suffixe" capture toute une fenêtre de mots précédant "Sarl/SA/...", et `$mot` (majuscule initiale + reste quelconque) ne distingue pas un vrai mot du nom d'une formule d'introduction ("L'entreprise", "La société"...) qui commence, elle aussi, par une majuscule de début de phrase. RC et NIU eux-mêmes étaient déjà corrects sur ce document — aucun changement nécessaire là. 433/433 tests.

## [2026-09-24 10:00] Dépôt GitHub — settings.local.json retiré du suivi git
Fichier(s) : .gitignore
Fichier(s) : .claude/settings.local.json (retiré de l'index git via `git rm --cached`, conservé sur le disque)
Pourquoi : mise en place du dépôt GitHub (MRDEEP1020/gecs) — `settings.local.json` contient les permissions Claude Code personnelles de ce poste, modifiées à chaque commande approuvée ; il avait été inclus par erreur dans le commit initial et ne doit pas être partagé (seul `.claude/settings.json`, le hook de journalisation de la Règle n°8, reste versionné).

## [2026-09-22 10:00] Correctif — le NIU ne reconnaissait que "Cont[r]. N°", pas l'ordre inversé "N° Cont[r]."
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (`extraireExpediteurNiu()` — nouveau repli `/\bN°\s*Cont(?:r)?\.?\s*:?\s*([\w]{5,20})/iu`, entre le repli "Cont[r]. N°" du 09h35 et le repli générique "N°" seul)
Fichier(s) : tests/Feature/Jobs/ProcessDocumentOcrTest.php (nouveau test `test_le_niu_est_extrait_quand_etiquete_n_degre_cont_dans_lordre_inverse`)
Pourquoi : demande explicite de l'utilisateur ("for the niu also add if he find this too N° cont.") — le repli ajouté à 09h35 ne couvrait que l'ordre "Cont[r]. N°" vu sur le document EASYTECH GROUP SA ; un second vrai document (NOW TECHNOLOGIES CENTER Sarl) utilise l'ordre INVERSE, "N°" avant "cont." : "RC/DLA/2018/B/2407 N° cont. MOQ71812712493". La valeur elle-même reste partiellement dégradée par l'OCR sur ce document précis ("MOQ..." au lieu d'un "M0..." probable) — limite de lecture, pas une régression du motif, laissée telle quelle pour vérification par l'agent plutôt que de deviner les caractères manquants (Règle n°6). 434/434 tests.

## [2026-09-22 10:30] Module 1/4 — bouton "Transférer la sélection" ajouté sur "Tous les courriers"
Fichier(s) : app/Livewire/Backend/CourrierList.php (`$destinataireChoisi`, `destinatairesTransfert()`, `peutTransfererAuMoinsUnCourrier()`, `transfererSelection()` — copie retargée de `MesCourriers::transfererSelection()`)
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (bouton + modale "Transférer à", visibles uniquement via `@if ($this->peutTransfererAuMoinsUnCourrier)`)
Fichier(s) : tests/Feature/Courriers/CourrierListTest.php (4 nouveaux tests : bouton caché sans privilège, transfert multiple réussi, courrier hors périmètre ignoré, destinataire non autorisé refusé)
Pourquoi : demande explicite de l'utilisateur ("no button to transfer courier in bulk why") — la sélection par case à cocher (2026-09-18) était déjà réellement fonctionnelle mais sans AUCUNE action de masse construite dessus sur cette page ; le transfert en masse n'existait que sur "Mes courriers" (page dédiée réceptionniste). Confirmé explicitement avec l'utilisateur avant de construire : le bouton doit être gardé par le système de PRIVILÈGES existant (`courriers.transferer_tout`/`courriers.transferer_propre`), jamais un rôle "Administrateur" en dur ("i told the are permission not specific attribute for one person") — la visibilité du bouton est une pure aide UX (éviter un bouton qui échouerait silencieusement), l'autorisation réelle reste vérifiée courrier par courrier via `CourrierPolicy::transferer()` (confidentialité + privilège + accès dossier, déjà tous appliqués par cette ability), exactement comme `MesCourriers` le fait déjà. 438/438 tests.

## [2026-09-22 10:45] Correctif — impossible de créer/modifier un utilisateur sans lui choisir un service (réceptionniste à l'accueil)
Fichier(s) : app/Livewire/Backend/UserList.php (`ajoutServiceId`/`editionServiceId` — validation passée de `required` à `nullable`)
Fichier(s) : resources/views/livewire/frontend/userList.blade.php (attribut `required` retiré des deux `<flux:select>` Service, placeholder explicite "— Aucun (ex. accueil) —")
Fichier(s) : tests/Feature/Admin/UserListTest.php (test existant `test_ajouter_un_utilisateur_valide_les_champs_requis` corrigé ; 2 nouveaux tests : création sans service, modification pour retirer le service d'un utilisateur existant)
Pourquoi : demande explicite de l'utilisateur — "the receptionist... doesn't have a service only accueil... i can't save without chosing the service". La colonne `users.service_id` est déjà nullable en base depuis sa création (`2026_09_04_110000_add_service_id_to_users_table.php`) ; seule cette validation Livewire l'empêchait à tort. Une réceptionniste (profil Agent) n'a structurellement jamais besoin de `service_id` — `Courrier::scopeVisiblePar()` scope l'Agent par `historiques` (qui a créé le courrier), jamais par service. "Accueil" n'a pas été ajouté comme un 15e service de la liste officielle (SDG/DAF/DT/DSIN/ACG/DI/DC/SANTE/SJ/DCOM/RH/RAG/TRANS/Secrétariat Général, voir specifications-modules-GEC.md annexe) — ce serait inventer une décision d'organisation non confirmée par le client ; l'utilisateur peut simplement rester sans service. 440/440 tests.

## [2026-09-22 15:00] Module "Organisation" v2 — Phase 0 : annulation du chantier "hiérarchie sur services" du tour précédent
Fichier(s) : database/migrations/2026_09_22_140000_drop_hierarchie_from_services_table.php (nouvelle — retire parent_id/type/soft deletes ajoutés par la migration v1 du tour précédent)
Fichier(s) : app/Models/Service.php (retour au modèle plat d'origine — parent()/enfants()/cheminComplet()/etc. retirés)
Fichier(s) : app/Models/Courrier.php (estGereParUtilisateur() supprimée, scopeVisiblePar() revient à whereHas('service', responsable_id))
Fichier(s) : app/Policies/CourrierPolicy.php (view/affecter/valider reviennent à la comparaison directe $user->id === $courrier->service?->responsable_id)
Fichier(s) : app/Livewire/Backend/WorkflowQueue.php, app/Livewire/Backend/Dashboard.php (mêmes reverts)
Fichier(s) : app/Livewire/Backend/CourrierList.php, DossierClassementList.php, EditForm.php, RegistrationForm.php, RegleList.php, ShowCourrier.php, UserList.php (les 7 #[Computed] services() reviennent au modèle plat, filtre actif conservé)
Fichier(s) : Suppression de app/Livewire/Backend/OrganisationList.php, resources/views/livewire/frontend/organisationList.blade.php, resources/views/components/service-tree-node.blade.php, tests/Feature/Admin/OrganisationListTest.php, tests/Feature/Courriers/CourrierOrganisationAccesTest.php
Pourquoi : l'utilisateur a fourni une spécification technique v2 nettement plus riche (Company/Site/Department/Service/Sub-service, organization_units dédiée) qui REMPLACE explicitement le travail v1 (Direction/Service/Sous-service sur la table `services` plate) construit et testé plus tôt dans la même session — confirmé via AskUserQuestion ("Remplacer entièrement"). Revert complet et vérifié (470/470 tests) avant de reconstruire sur la nouvelle architecture, pour repartir d'un état propre sans double hiérarchie.

## [2026-09-22 15:30] Module "Organisation" v2 — Phase 1 : base de données + modèles (organization_units, pont vers services)
Fichier(s) : database/migrations/2026_09_22_150000_create_organization_units_table.php (nouvelle table auto-référencée : parent_id, service_id — le PONT vers les 14 services réels existants, name, code, type, description, responsible_user_id, status, sort_order, soft deletes)
Fichier(s) : database/migrations/2026_09_22_150100_create_organization_unit_user_table.php (nouvelle — rattachement de TRAVAIL, spec §5)
Fichier(s) : database/migrations/2026_09_22_150200_create_organization_unit_perimetre_user_table.php (nouvelle — périmètre de VISIBILITÉ, spec §19, distinct du rattachement de travail)
Fichier(s) : app/Models/OrganizationUnit.php (nouveau modèle — même patron auto-référencé que DossierClassement : parent()/enfants()/compterXDescendants()/estDescendantDe()/cheminComplet(), plus service()/responsable()/utilisateurs()/utilisateursPerimetre(), typeEnfantPropose(), idsServicesReelsSousArbre(), enfantsActifsDe())
Fichier(s) : app/Models/User.php (organizationUnits()/organizationUnitsPerimetre() — deux BelongsToMany distinctes)
Fichier(s) : app/Policies/OrganizationUnitPolicy.php (nouvelle — viewAny/view/create/update/deplacer/deactivate/manageUsers, AUCUNE ability delete — spec §15 "ne jamais supprimer physiquement")
Fichier(s) : database/seeders/PrivilegeSeeder.php (6 nouvelles clés organisation.view/create/update/deactivate/manage_users/manage_structure)
Fichier(s) : app/Models/Privilege.php (MODULES gagne l'entrée 'organisation')
Fichier(s) : database/factories/OrganizationUnitFactory.php (nouvelle)
Pourquoi : spécification technique complète fournie par l'utilisateur pour le module "Organisation" — décision d'architecture clé actée avec l'utilisateur (AskUserQuestion, "tout construire dans cette session") : `organization_units` devient la VRAIE structure organisationnelle (site/agence, département, service, sous-service), un pont léger `service_id` relie un nœud à l'un des 14 services réels EXISTANTS plutôt que de migrer `courriers.service_id` et toute la chaîne qui en dépend (génération de numéro, gates de CourrierPolicy, ClassificationService) — chantier hors de proportion avec le reste de la demande et risqué pour une logique déjà testée.

## [2026-09-22 16:00] Module "Organisation" v2 — Phase 2 : page /admin/organisation (arbre, onglets, panneau de détails)
Fichier(s) : app/Livewire/Backend/OrganisationIndex.php (nouveau composant — remplace OrganisationList, un seul composant Livewire conformément à la Règle n°2 du projet, jamais de composants imbriqués)
Fichier(s) : resources/views/livewire/frontend/organisationIndex.blade.php (nouvelle vue — arbre gauche/contenu centre à onglets Utilisateurs-Responsable-Informations-Paramètres/panneau détails droite, palette app.css existante réutilisée telle quelle)
Fichier(s) : resources/views/components/organization-tree-node.blade.php (nouveau composant Blade récursif — <flux:icon :icon="$variable" /> utilisé dès le départ, jamais <flux:icon.{{ $variable }} />, piège déjà rencontré deux fois ce projet, voir mémoire livewire_flux_gotchas.md)
Fichier(s) : routes/web.php (admin/organisation pointe maintenant vers OrganisationIndex::class)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (gate @can repointé vers App\Models\OrganizationUnit::class)
Fichier(s) : tests/Feature/Admin/OrganisationIndexTest.php (nouveau — 13 tests : CRUD par type avec cohérence parent/enfant validée côté serveur, anti-cycle au déplacement, jamais de suppression possible, rattachement/retrait d'utilisateur, statistiques réellement calculées, recherche par nom et par utilisateur rattaché)
Pourquoi : spec §7-§12 (menu contextuel "Ajouter un élément", formulaire, onglets, panneau de détails, recherche globale) — implémentation complète de l'arbre organisationnel demandé par l'utilisateur, avec le principe "ne jamais fabriquer de données" respecté (structure vide au départ, aucun site/service fictif pré-rempli).

## [2026-09-22 16:30] Module "Organisation" v2 — Phase 3 : cascade de transfert de courrier (Site → Département → Service/Unité)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php ($serviceSelectionne remplacé par $siteSelectionneId/$departementSelectionneId/$uniteSelectionneeId en cascade ; validerService() résout le service réel via le pont uniteFinale()->service_id, WorkflowService::validerService() lui-même INCHANGÉ)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (3 <flux:select> en cascade remplacent le sélecteur de service unique)
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (même cascade pour le sélecteur "sortant" ; updatedSiteSelectionneId()/updatedDepartementSelectionneId()/updatedUniteSelectionneeId() résolvent form->service_id automatiquement)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (même cascade côté "sortant")
Fichier(s) : tests/Feature/Courriers/CircuitCourrierTest.php (4 tests mis à jour pour la cascade, nouveau helper departementPonte())
Fichier(s) : tests/Feature/Courriers/RegistrationFormTest.php (test de préselection obsolète remplacé par un test de résolution de service_id via la cascade)
Pourquoi : spec §1 (Company→Site→Department→Service/Sous-service optionnel→Users) et §16 (impact sur courriers, cascade de transfert) — la cascade navigue dans la nouvelle hiérarchie organisationnelle mais n'écrit toujours que sur `courriers.service_id` existant via le pont, aucune modification de segmentClassement() ni de la génération du numéro de référence. 483/483 tests après correction des régressions.

## [2026-09-22 17:00] Module "Organisation" v2 — Phase 4 : périmètre d'accès (4ème gate cumulatif + onglet UserList)
Fichier(s) : app/Models/Courrier.php (nouvelle méthode estDansLePerimetreDe() — opt-in comme le gate dossier existant : un utilisateur sans périmètre assigné n'est pas restreint, un courrier sans service_id encore assigné (en attente de validation DGA) n'est jamais affecté par ce gate non plus, pour ne pas casser validerService() ; nouvelle clause dans scopeVisiblePar())
Fichier(s) : app/Policies/CourrierPolicy.php (nouvelle private accesPerimetreSuffisant(), appelée dans view/update/affecter/traiter/valider/transferer/validerService — même emplacement exact que accesDossierSuffisant(), les 4 gates se cumulent)
Fichier(s) : app/Livewire/Backend/UserList.php (nouvel onglet "Périmètre d'accès" dans la modale "Modifier l'utilisateur" — même patron two-box que "Destinataires de transfert" existant, sur organization_unit_perimetre_user)
Fichier(s) : resources/views/livewire/frontend/userList.blade.php (contenu de l'onglet "Périmètre d'accès")
Fichier(s) : tests/Feature/Admin/PerimetreOrganisationTest.php (nouveau — 10 tests : opt-in sans périmètre, voir_tout insuffisant hors périmètre, sous-arbre Site→Département→Service correctement résolu, isolation entre deux agences, courrier sans service pas restreint, gerer_tout ne court-circuite pas ce gate, scopeVisiblePar(), écriture UI sur la table pivot)
Pourquoi : spec §18 ("le frontend ne doit jamais être la seule protection... les règles doivent également être appliquées côté backend") et §19 (périmètre d'accès, cases à cocher) — demande explicite de l'utilisateur de tout construire dans cette session y compris l'intégration Permissions. Décision de conception : le gate périmètre ne bloque que les courriers qui PORTENT un service_id à l'intérieur du sous-arbre géré — jamais un courrier non encore assigné, par cohérence avec le principe déjà établi pour le gate dossier (accesDossierSuffisant()) et pour ne pas bloquer le workflow DGA existant. 493/493 tests, Pint clean, npm run build OK. Module "Organisation" v2 complet (Phases 0-4).

## [2026-09-22 18:00] Page 403 personnalisée — remplace la page brute de Laravel sur un refus d'accès
Fichier(s) : resources/views/errors/403.blade.php (nouvelle — habillage complet de l'app (sidebar/en-tête réels), message générique honnête + liste des causes cumulatives possibles (confidentialité, dossier, périmètre organisationnel, privilège), bouton "Retour au tableau de bord")
Fichier(s) : tests/Feature/ErrorPagesTest.php (nouveau — vérifie qu'un refus d'accès HTTP réel affiche cette page, pas la page brute de Laravel)
Pourquoi : demande explicite de l'utilisateur, après diagnostic en direct d'un cas réel (DGA bloqué sur GEC-2026-000001 par le gate dossier de classement, voir échange précédent) — "rather than showing that page we should show him like what happening and error or something". Message volontairement générique sur la cause exacte : les gates de CourrierPolicy (confidentialité, dossier, périmètre, privilège) sont CUMULATIFS, un refus peut venir de plusieurs à la fois, inventer une cause unique serait parfois faux. Piège trouvé en testant : `$errors` n'est partagé GLOBALEMENT (View::share) que par le middleware ShareErrorsFromSession du groupe 'web', absent quand l'exception d'autorisation est levée dans mount() d'un composant testé via Livewire::test() — le champ de recherche <flux:input> de la sidebar (partagée par toute page) en a besoin en interne (@error), donc 22 tests ->assertForbidden() existants échouaient avant le correctif (View::share('errors', ...) explicite si pas déjà partagé, dans errors/403.blade.php). 494/494 tests après correction, Pint clean, npm run build OK.

## [2026-09-22 18:30] Lien direct vers l'onglet "Circuit de traitement" depuis la file "Transferts" (WorkflowQueue)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (nouvelle propriété #[Url(as: 'onglet')] public string $ongletInitial — liste blanche ONGLETS_VALIDES, repli sur 'general' dans mount() si la valeur reçue est inconnue)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (x-data="{ onglet: 'general' }" devient x-data="{ onglet: @js($ongletInitial) }" — le mécanisme d'onglet Alpine existant n'est pas modifié, seule sa valeur INITIALE devient pilotable depuis l'URL)
Fichier(s) : resources/views/livewire/frontend/workflowQueue.blade.php (les 2 liens vers courriers.show — référence dans le tableau, et "Voir la fiche complète" du panneau aperçu — ajoutent ?onglet=circuit)
Fichier(s) : tests/Feature/Courriers/ShowCourrierTest.php (2 nouveaux tests : ?onglet=circuit ouvre bien directement sur "Circuit de traitement" ; une valeur d'onglet inconnue retombe sur 'general', jamais une page sans onglet visible)
Pourquoi : demande explicite de l'utilisateur après diagnostic en direct — "why do i have to navigate there first where is that shortcut one" : la file "Transferts" (Module 4, WorkflowQueue) et son panneau aperçu ouvraient toujours un courrier sur l'onglet par défaut "Général", forçant systématiquement un clic supplémentaire sur "Circuit de traitement" pour accéder à affecter/traiter/valider/transférer/confirmer le service — alors que c'est littéralement la seule raison d'être de cette page. Portée volontairement limitée à WorkflowQueue (pas aux autres liens vers courriers.show — dashboard, "Mes courriers", etc. — qui pointent vers une simple consultation, pas une action de circuit à accomplir). 496/496 tests, Pint clean (repo entier), npm run build OK.

## [2026-09-23 09:00] Cascade de transfert — le Site devient réellement optionnel (Département racine)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (departementsDisponibles() ne retourne plus collect() quand siteSelectionneId est null — délègue toujours à OrganizationUnit::enfantsActifsDe(), qui gère nativement parent_id=null)
Fichier(s) : app/Livewire/Backend/RegistrationForm.php (même correctif, méthode dupliquée à l'identique)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (le sélecteur "Site" gagne une option vide RÉELLE — piège flux:select "freeze" déjà rencontré 3 fois — et le sélecteur "Département" s'affiche dès que departementsDisponibles n'est pas vide, plus seulement quand un Site est choisi)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (même correctif ; l'attribut `required` du sélecteur Site est retiré, celui du sélecteur Département est conservé)
Fichier(s) : tests/Feature/Courriers/CircuitCourrierTest.php, tests/Feature/Courriers/RegistrationFormTest.php (2 nouveaux tests : un Département racine, sans Site, reste utilisable pour résoudre un service_id réel dans les deux flux)
Pourquoi : demande explicite de l'utilisateur (2026-09-22/23) — "If the real Site/Agence name is not present in the database, leave the site unassigned/null for now... Do not create a fake site name." Un vrai Département ("Direction des Sinistres") a été créé directement en base à la racine (parent_id null, ponté au vrai service DSIN) faute de nom de site réel confirmé — la cascade devait donc supporter ce cas dès le départ, pas seulement en théorie. 498/498 tests après correction.

## [2026-09-23 09:30] Fix réel — privilèges organisation.* jamais propagés à la vraie base (même piège déjà documenté)
Pourquoi (pas de fichier — opération de données uniquement) : re-exécution de `php artisan db:seed --class=PrivilegeSeeder --force` contre la vraie base de dev. Les 6 clés organisation.view/create/update/deactivate/manage_users/manage_structure (ajoutées en Phase 1 de la v2, voir plus haut) n'avaient jamais atteint la vraie table `privileges` — seule la base SQLite isolée des tests se réensemence automatiquement à chaque exécution. Constaté en direct : l'administrateur ne voyait AUCUN lien "Organisation" dans la sidebar malgré `@can('viewAny', OrganizationUnit::class)` correct dans le code — exactement le même piège que celui documenté pour `services.gerer` (voir mémoire systeme_privileges). Vérifié : opération purement additive (firstOrCreate), 33 privilèges au total après, aucun doublon, aucune autre assignation modifiée.

## [2026-09-23 10:00] Modale "Ajouter/Modifier une entité" — le sélecteur "Type" ne rafraîchissait rien
Fichier(s) : resources/views/livewire/frontend/organisationIndex.blade.php (wire:model → wire:model.live sur le <flux:radio.group> "Type" ; modale élargie de max-w-lg à max-w-xl, corrigeant un défilement horizontal qui coupait le libellé "Sous-service" ; nouvelle description textuelle sous le sélecteur, qui change selon le type choisi)
Fichier(s) : app/Livewire/Backend/OrganisationIndex.php (updated() — un changement de type vers site/sous_service vide désormais servicePontNoeud, qui n'était sinon jamais réinitialisé malgré la disparition du champ à l'écran)
Fichier(s) : tests/Feature/Admin/OrganisationIndexTest.php (2 nouveaux tests : le champ "Service réel lié" apparaît/disparaît et se réinitialise en changeant de type ; la description sous le sélecteur change bien selon l'onglet)
Pourquoi : demande explicite de l'utilisateur ("i want you to make all the tabs here to work", puis "on the form it shows the same tabs") — le sélecteur de Type segmenté était en wire:model DÉFÉRÉ alors que la visibilité du champ "Service réel lié" dépend d'un @if PHP évalué seulement à un aller-retour serveur : cliquer sur un onglet changeait visuellement le bouton radio (comportement natif du <input>) mais aucun autre champ du formulaire ne se mettait à jour avant la soumission. Par ailleurs, Département et Service/Unité affichent délibérément le même champ "Service réel lié" (les deux peuvent être pontés à un service réel, spec §1) — rien ne prouvait donc visuellement qu'un changement d'onglet entre ces deux-là avait été pris en compte, d'où la nouvelle description contextuelle. 500/500 tests, Pint clean (repo entier), npm run build OK, caches vue/config vidés.

## [2026-09-23 11:00] Page "Utilisateurs & Accès" — la colonne/le sélecteur "Service" devient "Département"
Fichier(s) : app/Models/OrganizationUnit.php (nouvelles departementOuAncetreDepartement()/departementLabelParServiceId() — remonte l'ancêtre de type Département d'un nœud ponté, même patron que cheminComplet(), sur une Collection déjà chargée)
Fichier(s) : app/Livewire/Backend/UserList.php (remplace ajoutServiceId/editionServiceId/serviceFiltreId par la même cascade Site→Département→Service/Unité que ShowCourrier/RegistrationForm ; users.service_id — colonne INCHANGÉE, toujours utilisée partout ailleurs dans l'app — résolu via le pont, jamais remplacé ; nouvelle preselectionnerCascadeEditionDepuisService() pour préremplir l'édition depuis le service déjà enregistré ; filtre "Département" utilise idsServicesReelsSousArbre() pour inclure tout le sous-arbre, pas une égalité stricte sur un service_id unique)
Fichier(s) : resources/views/livewire/frontend/userList.blade.php (filtre, en-tête de colonne, cellule de table, "Récapitulatif" de la modale Ajouter, cascade dans les modales Ajouter/Modifier, "Aperçu du compte" — tous "Service" → "Département")
Fichier(s) : tests/Feature/Admin/UserListTest.php (nouveau helper departementPonte() ; tests existants adaptés à la cascade ; 2 nouveaux tests : la colonne remonte jusqu'au Département ancêtre même via un pont plus profond — ex. Sinistre Santé → Direction des Sinistres — et un service pas encore organisé n'apparaît jamais dans la carte, "—" affiché plutôt qu'une valeur fabriquée)
Pourquoi : demande explicite de l'utilisateur — "on the table... change the service to department... since we assign them in a department first before service. on the details page it shows all their info with the org related to them." `users.service_id` reste la colonne réellement utilisée par le reste de l'application (affectation, scopeVisiblePar, etc., jamais touchée) — seule la source de la SAISIE et de l'AFFICHAGE change pour naviguer l'organigramme plutôt qu'une liste plate de 17 services, cohérent avec le principe déjà établi "pont, pas remplacement" pour les courriers. 502/502 tests, Pint clean (repo entier), npm run build OK, caches vue/config vidés.

## [2026-09-23 11:30] Colonne "Département" — unifie les TROIS signaux distincts (responsable, rattachement, pont service_id)
Fichier(s) : app/Models/OrganizationUnit.php (nouvelle idsSousArbre() — aplatit les IDS DES NŒUDS eux-mêmes, pas leur service ponté, pour le filtre "rattachement direct")
Fichier(s) : app/Livewire/Backend/UserList.php (nouvelle tousLesNoeudsOrganisation() computed partagée, responsible_user_id inclus ; nouvelle departementLabelDe(User) — vérifie DANS L'ORDRE : (1) responsable désigné d'un nœud, (2) rattachement de travail direct organization_unit_user, (3) pont service_id, chacun bubblé jusqu'au Département ancêtre ; utilisateurs()/utilisateurEnEdition() eager-chargent désormais organizationUnits ; le filtre "Département" matche les trois mécanismes, pas seulement le pont service_id)
Fichier(s) : resources/views/livewire/frontend/userList.blade.php (table + "Aperçu du compte" appellent désormais $this->departementLabelDe($utilisateur) au lieu de lire directement la carte service_id)
Fichier(s) : tests/Feature/Admin/UserListTest.php (3 nouveaux tests : rattachement direct affiché sans service_id, filtre matche ce rattachement, responsable désigné affiché ET filtrable sans rattachement ni service_id)
Pourquoi : demande explicite de l'utilisateur après avoir constaté qu'un collaborateur rattaché depuis la page Organisation ("Collaborateur DI") restait affiché "—" ici — puis "what about those responsable too" (même symptôme pour "Responsable DI"/"Responsable DSIN", définis comme responsables depuis Organisation mais sans rattachement ni service_id). Les trois mécanismes (responsable, rattachement, pont service_id) sont des signaux INDÉPENDANTS qui n'avaient jamais été réconciliés — la colonne doit refléter n'importe lequel des trois, pas seulement le dernier construit. 505/505 tests, Pint clean (repo entier), npm run build OK, caches vue/config vidés.

## [2026-09-23 14:00] Périmètre de visibilité unique — fuite de courriers confidentiels dans 4 listes corrigée
Fichier(s) : app/Models/Courrier.php (scopeVisiblePar() : bloc "par profil" réécrit sur les PRIVILÈGES courriers.voir_tout/voir_service/voir_propre/voir_affecte/voir_dga, miroir exact de CourrierPolicy::view() — plus de comparaison du nom de profil ; aucun privilège de consultation => aucun résultat)
Fichier(s) : app/Livewire/Backend/Dashboard.php (courriersVisibles() et tachesDuJour() passent par visiblePar() au lieu d'une copie locale)
Fichier(s) : app/Livewire/Backend/CourriersEnregistres.php (idem)
Fichier(s) : app/Livewire/Backend/WorkflowQueue.php (idem)
Fichier(s) : app/Livewire/Backend/MesCourriers.php (visiblePar() cumulé avec le filtre "créé par moi")
Fichier(s) : tests/Feature/Courriers/VisibiliteListesTest.php (nouveau — courrier niveau 5 jamais listé pour un niveau 1 sur les 4 pages ; niveau autorisé toujours listé ; utilisateur sans profil ne voit rien)
Pourquoi : Module 9/Règle n°6 — audit des pages du 2026-09-23 : un courrier de niveau 5 refusé en consultation (403) restait affiché (numéro + objet) sur le tableau de bord, "Courriers enregistrés", la file d'attente et "Mes courriers", car ces pages dupliquaient un périmètre par nom de profil sans le niveau de confidentialité, l'accès dossier ni le périmètre organisationnel ; en plus, un utilisateur sans profil (inscription publique) voyait tous les courriers sur le tableau de bord. Voir DECISIONS.md "Périmètre de visibilité unique".

## [2026-09-23 14:20] "Numérisation & OCR" réservé aux profils autorisés à enregistrer
Fichier(s) : app/Livewire/Backend/ScanPremier.php (nouveau mount() : authorize('create', Courrier::class))
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (lien "Numérisation & OCR" entouré de @can('create', Courrier))
Fichier(s) : resources/views/livewire/frontend/dossierClassementList.blade.php (lien "Dossier surveillé" entouré du même @can)
Fichier(s) : tests/Feature/Courriers/ScanPremierTest.php (2 tests "non autorisé" : refus dès mount() + route HTTP ; nouveau test : lien masqué pour Collaborateur, affiché pour Agent)
Pourquoi : Module 1/2, Règle n°6 — audit des pages du 2026-09-23 : le lien était affiché à tous les profils et la page s'ouvrait pour DGA/Responsable/Collaborateur, mais l'upload était ensuite refusé (403) — page en apparence cassée pour eux.

## [2026-09-23 15:00] SLA réel + alertes/relances automatiques (Module 5/7, plus des stubs)
Fichier(s) : config/gec.php (nouveau — sla.jours_defaut=10, sla.par_type=[] (valeurs à confirmer avec Nsia), sla.seuil_risque_jours=2, sla.relance_jours=2, surchargeables par .env)
Fichier(s) : database/migrations/2026_09_23_140000_add_sla_columns_to_courriers_table.php (nouveau — date_limite (date, indexée), alerte_risque_le, alerte_retard_le ; rattrapage de date_limite pour les courriers existants)
Fichier(s) : app/Services/SlaCalculatorService.php (stub remplacé : calculerDateLimite() échéance > sla_jours > délai du type > défaut, jours calendaires ; calculerStatutDelai() a_temps/a_risque/en_retard, jamais en retard si clôturé)
Fichier(s) : app/Models/Courrier.php (fillable/casts ; booted() : date_limite recalculée quand echeance/sla_jours/type_document/date_mouvement changent, alertes ré-armées par requête directe si la date limite change ; scopeEnRetard())
Fichier(s) : app/Jobs/SendMailAlertJob.php (stub remplacé : "bientôt en retard" une fois par date limite au collaborateur affecté (sinon responsable), "en retard" au collaborateur + responsable du service puis relance tous les N jours ; destinataire du transfert DGA / agent créateur selon l'étape ; filtré par CourrierPolicy::view() ; trace d'historique alerte_risque/alerte_retard auteur null ; échec par courrier journalisé sans bloquer le lot)
Fichier(s) : app/Notifications/CourrierEnRetardNotification.php (stub remplacé : email avec référence, date limite, lien vers la fiche ; objet omis si confidentialite > 1)
Fichier(s) : bootstrap/app.php (SendMailAlertJob planifié toutes les heures, "alertes-sla")
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php ("Date de traitement prévue" = date_limite réelle + badge "En retard"/"Bientôt en retard")
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (commentaire "SLA & Alertes" mis à jour — l'entrée reste "Bientôt", pas de page d'administration)
Fichier(s) : tests/Feature/Jobs/SendMailAlertJobTest.php (nouveau — dispatch queue, scheduler, retard, risque, dans les temps, clôturé, anti-doublon + relance, ré-armement, confidentialité destinataire et contenu email)
Fichier(s) : tests/Feature/Services/SlaCalculatorServiceTest.php (nouveau — ordre de priorité des délais, recalcul sur modification, statuts)
Pourquoi : Module 5 (SLA basique, délai fixe par type) et Module 7 (alertes et relances) — audit des pages du 2026-09-23 : SlaCalculatorService renvoyait toujours "à temps" et SendMailAlertJob/CourrierEnRetardNotification étaient vides et jamais planifiés, donc aucun retard n'était jamais détecté ni signalé. Voir DECISIONS.md "SLA et alertes".

## [2026-09-23 15:10] Tableau de bord : cartes "En retard" et "Délai moyen de traitement" (Module 10)
Fichier(s) : app/Jobs/RefreshDashboardStatsJob.php (stub remplacé : délai moyen (clôture 'validation_acceptee' − date_mouvement) des 90 derniers jours, stocké somme+nombre PAR SERVICE en cache ; delaiMoyen() pondéré sur un ensemble de services)
Fichier(s) : app/Livewire/Backend/Dashboard.php (courriersEnRetard() via visiblePar()->enRetard() ; delaiMoyen() lu depuis le cache — toute l'entreprise pour voir_tout, ses services pour voir_service, masqué sinon ; un seul recalcul demandé toutes les 10 min si le cache est vide)
Fichier(s) : resources/views/livewire/frontend/dashboard.blade.php (6 cartes en 2 rangées de 3 au lieu de 4 en ligne)
Fichier(s) : bootstrap/app.php (RefreshDashboardStatsJob planifié toutes les 15 min, "statistiques-tableau-de-bord")
Fichier(s) : tests/Feature/Jobs/RefreshDashboardStatsJobTest.php (nouveau — dispatch queue, scheduler, moyenne globale/par service, cartes affichées pour l'admin, masquée pour un collaborateur)
Pourquoi : Module 10 / PRD §3.10 ("volumes, retards, délai moyen") — audit des pages du 2026-09-23 : les retards et le délai moyen manquaient, et RefreshDashboardStatsJob était vide (Règle n°1 : agrégat historique calculé en job, jamais à l'affichage).

## [2026-09-23 15:20] ARCHITECTURE.md : composants Livewire réels vs stubs du scaffold
Fichier(s) : ARCHITECTURE.md (CourrierList n'est plus "à construire" ; ajout de Dashboard.php ; ValidationCircuit.php et DashboardHome.php marqués comme stubs vides jamais routés, à supprimer)
Pourquoi : audit des pages du 2026-09-23 — les deux stubs n'ont ni route ni vue (leur render() planterait) ; leur suppression a été bloquée par la politique de permissions de la session, le document signale donc explicitement leur statut pour un tiers qui reprendrait le projet (PRD §6, documentation).

## [2026-09-23 15:30] Inscription publique désactivée
Fichier(s) : config/fortify.php (Features::registration() retiré)
Fichier(s) : resources/views/livewire/auth/login.blade.php (lien "Sign up" affiché seulement si la route register existe — sinon la page de connexion planterait)
Fichier(s) : tests/Feature/Auth/RegistrationTest.php (tests du starter kit remplacés : /register 404, POST sans effet, page de connexion sans lien d'inscription)
Pourquoi : Module 9 (droits d'accès) — audit des pages du 2026-09-23 : n'importe qui pouvait créer un compte sans profil ni niveau de confidentialité sur un outil interne ; les comptes sont créés par un administrateur sur "Utilisateurs & Accès". Voir DECISIONS.md "Inscription publique désactivée".

## [2026-09-23 15:40] Accusé de réception confidentiel imprimable par la réceptionniste qui l'a enregistré
Fichier(s) : app/Policies/CourrierPolicy.php (nouvelle ability imprimerAccuseReception() : view() OU créateur avec courriers.voir_propre)
Fichier(s) : app/Http/Controllers/CourrierAccuseReceptionController.php (Gate 'imprimerAccuseReception' au lieu de 'view')
Fichier(s) : tests/Feature/Courriers/RegistrationFormConfidentielTest.php (nouveau test : créateur 200 sur l'accusé mais 403 sur la fiche ; autre agent 403)
Pourquoi : Module 1 "Cas particulier : courrier confidentiel" — "un accusé de réception est généré et remis au déposant" ; constaté pendant l'audit du 2026-09-23 : la réceptionniste (niveau 1) recevait 403 sur l'accusé du courrier (niveau ≥ 2) qu'elle venait d'enregistrer. L'accusé ne montre que ce qu'elle a saisi (référence, date, nom sur l'enveloppe), jamais l'objet.

## [2026-09-23 15:50] DECISIONS.md : 3 décisions de l'audit des pages
Fichier(s) : DECISIONS.md (entrées "Périmètre de visibilité unique", "SLA et alertes", "Inscription publique désactivée")
Pourquoi : Règle "décision technique nouvelle → l'écrire dans DECISIONS.md" (ARCHITECTURE-ESSENTIALS.md) — référencées par les entrées de changelog ci-dessus ; précise que les délais SLA par type restent à fournir par le client et que le nettoyage des brouillons reste volontairement non planifié.

## [2026-09-23 17:00] Catalogue : 13 privilèges de menu (menus pilotés par privilège)
Fichier(s) : database/seeders/PrivilegeSeeder.php (dashboard.voir, courriers.numeriser, courriers.creer_confidentiel, dossiers_classement.archives, utilisateurs.gerer, statistiques.consulter, statistiques.rapports, administration.automatisation/workflows/sla/audit, general.notifications, general.aide — défauts = profils qui voyaient déjà l'entrée ; seuls les menus "à venir" reçoivent un défaut direction/administration) ; seedé immédiatement contre la vraie base (46 privilèges)
Fichier(s) : app/Models/Privilege.php (MODULES : utilisateurs, statistiques, administration, general)
Fichier(s) : app/Policies/CourrierPolicy.php (numeriser(), renumeriser() = numeriser + update, creerConfidentiel())
Fichier(s) : app/Policies/PrivilegePolicy.php (gererUtilisateurs() = gerer() ou utilisateurs.gerer, même garde-fou anti-verrouillage Administrateur)
Pourquoi : Module 9 / Système de privilèges — demande explicite de l'utilisateur ("all in sidebar should be permission even the submenu and menu") : chaque entrée de menu doit être assignable par profil/utilisateur depuis l'administration. Voir DECISIONS.md "Menus pilotés par privilège".

## [2026-09-23 17:10] Sidebar : chaque entrée et chaque groupe pilotés par privilège
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (tableau $menu calculé une fois ; chaque entrée, y compris "Bientôt", derrière son privilège ; chaque groupe affiché seulement si une entrée l'est — corrige aussi "Administration" qui ignorait organisation.view ; cloche et aide de la barre supérieure derrière general.notifications/general.aide ; "Paramètres" et "Déconnexion" toujours visibles)
Pourquoi : même demande ; sans ça un utilisateur voyait des titres de groupe vides et des entrées "Bientôt" sans rapport avec son rôle.

## [2026-09-23 17:20] Pages et boutons alignés sur les nouveaux privilèges
Fichier(s) : app/Livewire/Backend/ScanPremier.php (authorize 'numeriser' ; sans courriers.creer, pas de redirection vers l'enregistrement après un scan manuel)
Fichier(s) : resources/views/livewire/frontend/scanPremier.blade.php ("Continuer l'enregistrement" / "Enregistrer directement" seulement avec courriers.creer)
Fichier(s) : app/Livewire/Backend/ScanForm.php (authorize 'renumeriser')
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (bouton Numériser/Re-numériser derrière 'renumeriser')
Fichier(s) : app/Livewire/Backend/RegistrationFormConfidentiel.php (authorize 'creerConfidentiel')
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (bouton "Scanner" derrière 'numeriser', encart "Courrier confidentiel ?" derrière 'creerConfidentiel')
Fichier(s) : app/Livewire/Backend/Dashboard.php (mount : sans dashboard.voir, redirection vers les paramètres du compte)
Fichier(s) : resources/views/livewire/frontend/dashboard.blade.php (chaque tuile d'action rapide a son propre privilège)
Fichier(s) : app/Livewire/Backend/DossierClassementList.php (nœud "Archives" refusé côté serveur sans dossiers_classement.archives, y compris via ?noeud=archives)
Fichier(s) : resources/views/livewire/frontend/dossierClassementList.blade.php (entrée "Archives" et lien "Dossier surveillé" derrière leurs privilèges)
Pourquoi : Règle n°6 — masquer une entrée de menu ne suffit pas, la page et ses raccourcis doivent exiger le même privilège (sinon lien direct = accès).

## [2026-09-23 17:30] "Utilisateurs & Accès" séparé de "Profils" (utilisateurs.gerer / privileges.gerer)
Fichier(s) : app/Livewire/Backend/UserList.php (mount 'gererUtilisateurs' ; modifier/activer/réinitialiser/destinataires via autoriserCompte() ; créer, permissions, périmètre restent 'gerer' ; profil et niveau postés ignorés sans privileges.gerer ; compte Administrateur ou détenteur de privileges.gerer intouchable sans privileges.gerer)
Fichier(s) : resources/views/livewire/frontend/userList.blade.php (bouton Ajouter et "Gérer les permissions" derrière 'gerer' ; onglets Permissions/Périmètre masqués et champs Profil/Niveau désactivés sans 'gerer' ; aucune action sur un compte protégé)
Fichier(s) : tests/Feature/MenuPrivilegesTest.php (nouveau — menus par profil, aucun titre vide, un privilège ouvre son groupe, redirection tableau de bord, opérateur de scan, confidentiel, archives côté serveur, séparation comptes/droits)
Fichier(s) : tests/Feature/Courriers/VisibiliteListesTest.php (utilisateur sans profil : redirection au lieu d'un tableau de bord vide)
Pourquoi : deux entrées de menu = deux privilèges ; mais gérer les comptes sans gérer les droits ne doit jamais permettre de se donner le profil Administrateur ni de prendre le contrôle d'un compte administrateur (changer son email puis réinitialiser le mot de passe). 539/539 tests, Pint propre.

## [2026-09-23 17:40] DECISIONS.md : menus pilotés par privilège
Fichier(s) : DECISIONS.md (entrée "Menus pilotés par privilège")
Pourquoi : consigne la règle des défauts (préserver l'accès existant), les deux exceptions volontaires (Paramètres, Déconnexion) et la séparation utilisateurs.gerer / privileges.gerer avec son garde-fou anti-escalade.

## [2026-09-23 18:00] Télécharger / imprimer le bordereau séparés de la consultation
Fichier(s) : database/seeders/PrivilegeSeeder.php (courriers.telecharger, courriers.imprimer_bordereau — défaut : tous les profils, comme avant ; courriers.supprimer — Administrateur seul) ; seedé immédiatement contre la vraie base (49 privilèges)
Fichier(s) : app/Policies/CourrierPolicy.php (telecharger(), imprimerBordereau() = privilège ET view() ; delete() = privilège, jamais archivé, ET view())
Fichier(s) : app/Policies/CourrierBrouillonPolicy.php (telecharger() = utiliser() ET courriers.telecharger)
Fichier(s) : app/Http/Controllers/CourrierDocumentDownloadController.php (Gate 'telecharger')
Fichier(s) : app/Http/Controllers/PieceJointeDownloadController.php (Gate 'telecharger' sur le courrier)
Fichier(s) : app/Http/Controllers/BrouillonDocumentDownloadController.php (Gate 'telecharger' sur le brouillon)
Fichier(s) : app/Http/Controllers/CourrierBordereauController.php (Gate 'imprimerBordereau')
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (boutons Télécharger/Imprimer d'en-tête, pièces jointes (nom seul sans le privilège), panneau aperçu, "Fichiers joints", "Actions rapides > Imprimer")
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (bouton télécharger du panneau, pièces jointes)
Fichier(s) : resources/views/livewire/frontend/editForm.blade.php (pièces jointes, bouton télécharger)
Fichier(s) : resources/views/livewire/frontend/workflowQueue.blade.php (bouton télécharger du panneau)
Fichier(s) : resources/views/livewire/frontend/registrationForm.blade.php (3 boutons de téléchargement du brouillon)
Pourquoi : Module 9 — demande explicite de l'utilisateur ("all of them") : télécharger le document original et imprimer le bordereau deviennent des droits assignables, distincts de la consultation (l'aperçu à l'écran reste soumis à la seule consultation).

## [2026-09-23 18:10] "Supprimer" un courrier : suppression logique, motif obligatoire (courriers.supprimer)
Fichier(s) : app/Livewire/Backend/CourrierList.php (supprimerCourrier(int) : id revérifié, authorize 'delete', motif 5-500 caractères, entrée d'historique 'suppression' (auteur, motif) puis soft delete dans la même transaction)
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (bouton "Supprimer" réel derrière @can('delete') au lieu d'un bouton désactivé "Bientôt", modale de confirmation avec motif)
Fichier(s) : tests/Feature/Courriers/ActionsCourrierPrivilegesTest.php (nouveau — voir sans télécharger/imprimer, avec privilèges OK, privilège sans consultation refusé, brouillon, suppression logique + historique + 404 ensuite, archivé refusé, sans privilège refusé)
Pourquoi : Règle n°5 ("soft delete + validation d'un rôle admin uniquement si suppression réellement nécessaire", jamais un courrier archivé) — demande explicite de l'utilisateur ("all of them"). 546/546 tests, Pint propre, build Vite OK.

## [2026-09-23 18:30] "Tous les courriers" : filtres repliés, seule la barre de recherche reste visible
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (les 4 listes Fonctionnalité/Statut/Priorité/Période et "Réinitialiser les filtres" rejoignent le panneau replié "Filtres avancés" ; état initial déplié si l'un de ces filtres (ou objet) est déjà actif)
Pourquoi : Module 8 — demande explicite de l'utilisateur ("mask the research inputs only leave the long one and bring the tables up") : le tableau remonte d'une rangée entière ; aucun filtre supprimé, tous restent accessibles via "Filtres" / "Filtres avancés". 34/34 tests CourrierList + actions.

## [2026-09-23 18:45] "Transférer / Classer la sélection" affichés seulement avec une sélection
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (les deux boutons (auparavant toujours visibles, désactivés sans sélection) regroupés dans une barre d'actions affichée seulement si au moins un courrier est coché : compteur "n courrier(s) sélectionné(s)", Transférer, Classer, "Désélectionner" ; chaque bouton garde son propre droit ; modales inchangées)
Fichier(s) : tests/Feature/Courriers/CourrierListTest.php (nouveau test : barre absente sans sélection, présente avec, absente après désélection ; les 2 tests "sans privilège" cochent désormais un courrier pour tester réellement le privilège)
Pourquoi : Module 3/4 — demande explicite de l'utilisateur ("make the two button transfer and classe appear only when we have selected"). 28/28 tests CourrierList, Pint propre.

## [2026-09-23 19:30] Organisation : un Service / Sous-service crée ou relie automatiquement son service réel
Fichier(s) : app/Services/ServiceReelSynchroniseur.php (nouveau — sans lien choisi : relie le service existant de même nom, sinon crée `services` (nom, code unique ≤ 10 dérivé du code du nœud ou des initiales, responsable, actif) ; ensuite : renommage et responsable reportés seulement si nœud et service étaient alignés, service désactivé seulement si aucun autre nœud actif ne l'utilise)
Fichier(s) : app/Livewire/Backend/OrganisationIndex.php (synchroniser() appelé après enregistrerNoeud(), basculerStatut() et definirCommeResponsable())
Fichier(s) : resources/views/livewire/frontend/organisationIndex.blade.php ("Service réel lié" aussi pour Sous-service ; option vide = "— Créer automatiquement —" pour Service/Sous-service, texte d'aide adapté)
Fichier(s) : tests/Feature/Admin/ServiceReelSynchroniseurTest.php (nouveau — création + code, liaison sans doublon, code unique suffixé, département sans effet, renommage reporté, service relié à la main jamais renommé, désactivation partagée, responsable reporté)
Pourquoi : Module 3/4/6 — la page Organisation est le seul endroit où l'on gère les services (confirmé par l'utilisateur, pas de page Services), mais routage DGA, service d'un courrier, règles, recherche, dossiers et responsable destinataire des courriers/alertes lisent la table `services` : un service créé dans l'organigramme n'était sélectionnable nulle part. 555/555 tests, Pint propre.

## [2026-09-23 20:30] Chaque action / lecture = un privilège (26 nouvelles clés)
Fichier(s) : database/seeders/PrivilegeSeeder.php (courriers.reaffecter, soumettre_validation, renvoyer_correction, rejeter, mettre_en_attente, reprendre, classer, valider_classement, gerer_mots_cles, voir_historique, voir_texte_ocr, voir_pieces_jointes, voir_statistiques, voir_enregistres, voir_mes_courriers, imprimer_accuse ; dashboard.statistiques, derniers_courriers, delai_moyen ; dossiers_classement.voir, modifier, partager, supprimer ; regles_classement.voir, reanalyser ; profils.creer — défauts = profils qui pouvaient déjà agir) ; seedé immédiatement contre la vraie base (75 privilèges)
Fichier(s) : app/Models/Privilege.php (module "profils")
Fichier(s) : app/Policies/CourrierPolicy.php (abilities reaffecter, soumettreValidation, renvoyerCorrection, rejeter, mettreEnAttente, reprendre, validerClassement, gererMotsCles, voirHistorique, voirTexteOcr, voirPiecesJointes, voirEnregistres, voirMesCourriers ; classer et imprimerAccuseReception exigent leur privilège — toujours cumulés avec la portée existante)
Fichier(s) : app/Policies/RegleClassementPolicy.php (viewAny = voir ou gerer ; create/update/delete = gerer ; reanalyser)
Fichier(s) : app/Policies/DossierClassementPolicy.php (viewAny = voir/creer/gerer_tout ; update/partager/delete = gerer_tout ou (privilège dédié ET créateur))
Fichier(s) : app/Policies/PrivilegePolicy.php (creerProfil)
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (droits() computed ; chaque action du circuit, du classement et des mots-clés autorise sa propre ability ; abiliteCirculationCourante() retirée)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (onglets Pièces jointes / Historique, texte OCR, validation du classement, mots-clés, soumettre, renvoyer, réaffecter, attente, reprendre, rejeter — chacun derrière son droit)
Fichier(s) : app/Livewire/Backend/CourrierList.php (recherche dans le contenu + extrait ignorés sans voir_texte_ocr ; statistiques non calculées sans voir_statistiques ; classement en masse exige courriers.classer)
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (cartes, filtre Contenu, colonne Extrait, pièces jointes du panneau)
Fichier(s) : resources/views/livewire/frontend/editForm.blade.php (pièces jointes derrière voirPiecesJointes)
Fichier(s) : app/Http/Controllers/PieceJointeDownloadController.php (+ voirPiecesJointes)
Fichier(s) : app/Livewire/Backend/CourriersEnregistres.php, app/Livewire/Backend/MesCourriers.php (voirEnregistres / voirMesCourriers)
Fichier(s) : app/Livewire/Backend/Dashboard.php, resources/views/livewire/frontend/dashboard.blade.php (statistiques, délai moyen, derniers courriers, tuile "Mes enregistrements" — chacun son privilège, vérifié côté serveur)
Fichier(s) : app/Livewire/Backend/RegleList.php, resources/views/livewire/frontend/regleList.blade.php (nouvelle() autorisée ; formulaire et boutons de ligne derrière gerer ; Réanalyser derrière reanalyser)
Fichier(s) : app/Livewire/Backend/ProfilList.php, resources/views/livewire/frontend/profilList.blade.php (creerProfil)
Fichier(s) : app/Livewire/Backend/DossierClassementList.php, resources/views/livewire/frontend/dossierClassementList.blade.php ("Ajouter des courriers" / "Retirer du dossier" derrière courriers.classer)
Fichier(s) : tests/Feature/ActionsLecturesPrivilegesTest.php (nouveau — rejeter avec/sans, portée jamais remplacée, historique + OCR, recherche contenu ignorée, classer + mots-clés, pages Mes courriers / Enregistrés, blocs du tableau de bord, règles en lecture seule, partager/supprimer ses dossiers, créer un profil)
Fichier(s) : tests/Feature/SidebarTest.php, tests/Feature/Dossiers/DossierClassementListTest.php, tests/Feature/MenuPrivilegesTest.php (la page Dossiers dépend désormais de dossiers_classement.voir)
Pourquoi : Module 9 — demande explicite de l'utilisateur ("make every action/read on this app a permission") : plusieurs actions distinctes partageaient un même privilège (ex. valider = valider + renvoyer + rejeter) et plusieurs lectures n'en avaient aucun (historique, texte OCR, blocs du tableau de bord). Voir DECISIONS.md "Chaque action / lecture = un privilège". 565/565 tests, Pint propre.

## [2026-09-23 21:00] Page "Profils" refaite sur le modèle de "Utilisateurs & Accès"
Fichier(s) : app/Livewire/Backend/ProfilList.php (réécrit : recherche, tableau des profils (utilisateurs, permissions x/total, répartition lecture/écriture/administratif), modale "Gestion des permissions" par module identique à UserList — basculerPermission, tout (dé)sélectionner limité au module affiché ; création → ouvre directement les permissions du nouveau profil ; droit revérifié à chaque action)
Fichier(s) : resources/views/livewire/frontend/profilList.blade.php (réécrit : fil d'Ariane, en-tête + "Nouveau profil", carte de recherche, tableau avec menu d'actions "Gérer les permissions" / "Voir les utilisateurs", modales Nouveau profil + Gestion des permissions)
Fichier(s) : app/Models/Profil.php (relation users())
Fichier(s) : app/Livewire/Backend/UserList.php (profilFiltreId en #[Url], pour le lien "Voir les utilisateurs")
Fichier(s) : tests/Feature/Admin/ProfilListTest.php (réécrit pour la nouvelle page : tableau et compteurs, recherche, décocher retire réellement le droit, cocher/décocher, modules, tout sélectionner limité au module, création → permissions, nom déjà pris, accès refusé, droit revérifié à chaque action, garde-fou Administrateur, lien Voir les utilisateurs)
Pourquoi : Module 9 — demande explicite de l'utilisateur ("make profile page as userlist") : remplace le sélecteur + "deux boîtes" (retenu le 2026-09-15) par la même présentation que "Utilisateurs & Accès". Voir DECISIONS.md "Page Profils alignée sur Utilisateurs & Accès". 565/565 tests, Pint propre, build Vite OK.

## [2026-09-23 21:30] Tableau de bord : chaque carte/KPI sa propre permission
Fichier(s) : database/seeders/PrivilegeSeeder.php (dashboard.statistiques scindée en dashboard.courrier_entrant/courrier_sortant/en_attente/urgents/en_retard — une clé par carte, mêmes 4 profils par défaut qu'avant ; + dashboard.notifications et dashboard.calendrier, nouvelles, pour les 2 cartes du panneau latéral jusque-là jamais gardées ; ancienne clé dashboard.statistiques supprimée explicitement du catalogue, cascade sur ses assignations) ; seedé immédiatement contre la vraie base (81 privilèges)
Fichier(s) : app/Livewire/Backend/Dashboard.php (courrierEntrantAujourdhui()/courrierSortantAujourdhui()/enAttenteDeTraitement()/courriersUrgents()/courriersEnRetard() retournent désormais `null` sans leur privilège propre, chacune vérifiée indépendamment — remplace peutVoirStatistiques() qui gouvernait les 5 d'un bloc ; nouvelles peutVoirNotifications()/peutVoirCalendrier())
Fichier(s) : resources/views/livewire/frontend/dashboard.blade.php (chacune des 5 cartes KPI dans son propre @if ; la rangée entière ne s'affiche que si au moins une des 6 cartes (5 KPI + délai moyen) est visible ; cartes "Notifications" et "Calendrier" du panneau latéral désormais chacune derrière son privilège — auparavant toujours affichées à tout utilisateur connecté)
Fichier(s) : tests/Feature/DashboardKpiPrivilegesTest.php (nouveau — toutes les cartes par défaut pour un Agent, chaque carte KPI a une clé vraiment indépendante des 4 autres, rangée absente sans aucune des 6, Notifications/Calendrier indépendantes l'une de l'autre, Administrateur garde tout, ancienne clé absente du catalogue ; assertions sur les computed properties via Livewire::test()->get(...) plutôt que sur du texte — "Notifications"/"Calendrier" apparaissent aussi dans la sidebar sans rapport, une assertion HTML brute donnait un faux échec)
Fichier(s) : tests/Feature/ActionsLecturesPrivilegesTest.php (test_blocs_du_tableau_de_bord adapté à la nouvelle clé dashboard.courrier_entrant)
Pourquoi : Module 10 — demande explicite de l'utilisateur ("on tableau de board all kpi and card there should be permission") : les 5 cartes principales partageaient une seule clé, et les cartes décoratives "Notifications"/"Calendrier" n'en avaient aucune. Voir DECISIONS.md "Chaque carte du tableau de bord sa propre permission". 571/571 tests (mémoire CLI relevée pour la suite complète, voir memory test_suite_memory_limit.md — pas une régression), Pint propre, build Vite OK.

## [2026-09-23 22:00] Étendu à "Tous les courriers" et "Règles de classement" — audit page par page des cartes restantes
Fichier(s) : database/seeders/PrivilegeSeeder.php (courriers.voir_statistiques scindée en courriers.voir_carte_total/en_traitement/termines/en_erreur, une clé par carte, mêmes 4 profils par défaut qu'avant ; ancienne clé supprimée explicitement du catalogue, même traitement que dashboard.statistiques) ; seedé immédiatement contre la vraie base (84 privilèges)
Fichier(s) : app/Livewire/Backend/CourrierList.php (statistiques() ne calcule plus que les cartes autorisées — const CARTES fait le lien clé/privilège ; peutVoirStatistiques() retirée, remplacée)
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (chaque carte affichée seulement si présente dans $this->statistiques ; la rangée entière ne s'affiche que si au moins une carte l'est)
Fichier(s) : app/Livewire/Backend/RegleList.php (courriersAReanalyser() retourne `null` sans regles_classement.reanalyser — ce compteur n'existe que pour justifier le bouton "Réanalyser")
Fichier(s) : resources/views/livewire/frontend/regleList.blade.php (toute la carte "X courrier(s) sans décision de classement" derrière regles_classement.reanalyser, plus seulement le bouton — un lecteur avec seulement regles_classement.voir ne la voit plus)
Fichier(s) : tests/Feature/PagesCardsPrivilegesTest.php (nouveau — CourrierList : toutes les cartes par défaut, chaque carte indépendante des 3 autres, tableau vide sans aucune, Administrateur garde tout, ancienne clé absente du catalogue ; RegleList : compteur absent sans le privilège, visible avec)
Pourquoi : demande explicite de l'utilisateur ("same thing for all the pages, each card should be a permission, go one page by page") — audit systématique de toutes les pages (grep des conteneurs "carte" dans les 14 vues frontend, computed properties de chaque composant Backend) : seules "Tous les courriers" (4 cartes partageant une clé) et "Règles de classement" (compteur jamais gardé, seul le bouton l'était) avaient un écart réel de ce type. Les autres pages (Mes courriers, Courriers enregistrés, file d'attente, Organisation, Dossiers, Utilisateurs & Accès, Profils, formulaires) n'ont pas de carte de statistiques agrégées ni de panneau totalement ungated — leurs panneaux (Informations générales, Parcours du courrier, Détails complémentaires sur la fiche courrier, arbres/panneaux de détail sur Organisation/Dossiers) sont le contenu même de l'élément affiché, déjà couvert par le droit de consultation de cet élément (voir DECISIONS.md pour le détail du périmètre retenu). 578/578 tests, Pint propre, build Vite OK.

## [2026-09-23 22:45] "Paramètres système" — numéro de référence et SLA configurables sans redéploiement
Fichier(s) : database/migrations/2026_09_23_135000_create_parametres_table.php (nouveau — table singleton `parametres`, ligne id=1 créée par la migration elle-même : numero_reference_prefixe/numero_reference_chiffres_sequence, sla_jours_defaut/sla_seuil_risque_jours/sla_relance_jours, sla_par_type (JSON) ; horodatage délibérément AVANT 2026_09_23_140000_add_sla_columns_to_courriers_table, déjà appliquée, dont le rattrapage appelle SlaCalculatorService — sur une installation neuve `parametres` doit exister en premier)
Fichier(s) : app/Models/Parametre.php (nouveau — actuel() singleton mis en cache Redis/DB ; ne met en cache QUE le tableau d'attributs bruts, jamais l'objet Eloquent complet — un modèle sérialisé directement revenait en `__PHP_Incomplete_Class` une fois réellement testé contre CACHE_STORE=database, invisible avec le driver 'array' des tests ; invaliderCache() explicite après toute écriture ; delaiSlaPour($type))
Fichier(s) : app/Services/NumeroReferenceGenerator.php (préfixe et nombre de chiffres de la séquence lus depuis Parametre::actuel() au lieu du sprintf('GEC-%d-%06d') codé en dur ; la séquence déjà consommée en base n'est jamais rejouée par un changement de format)
Fichier(s) : app/Services/SlaCalculatorService.php, app/Jobs/SendMailAlertJob.php (jours_defaut/seuil_risque_jours/relance_jours/par_type lus depuis Parametre::actuel() au lieu de config('gec.sla.*'))
Fichier(s) : config/gec.php (supprimé — entièrement remplacé par la table `parametres`, éditable depuis l'UI sans redéploiement)
Fichier(s) : app/Livewire/Backend/ParametreSysteme.php, resources/views/livewire/frontend/parametreSysteme.blade.php (nouveau composant + vue — formulaire préfixe/chiffres avec aperçu du PROCHAIN numéro réel (lecture seule de NumeroSequence, jamais incrémentée), délais SLA, délai par type de document pour chacun des 9 types de CourrierForm::TYPES_DOCUMENT ; gardé par le privilège administration.sla déjà existant, pas de nouvelle clé)
Fichier(s) : routes/web.php (route admin/parametres → ParametreSysteme)
Fichier(s) : resources/views/layouts/app/sidebar.blade.php (entrée "SLA & Alertes" — jusque-là `x-sidebar-item-a-venir`, "Bientôt disponible" — devient un vrai lien "Paramètres système" ; groupe Administration reste déplié sur cette page)
Fichier(s) : tests/Feature/Services/NumeroReferenceGeneratorTest.php, tests/Feature/Services/ParametreTest.php, tests/Feature/Admin/ParametreSystemeTest.php (nouveaux — format par défaut et configuré, séquence jamais rejouée, singleton + cache + invalidation, valeur mise en cache = tableau de scalaires round-trippable par serialize()/unserialize() natif (régression du piège ci-dessus), délai par type avec repli, accès/refus par privilège, enregistrement préfixe/SLA/par-type, validation du préfixe, aperçu réactif sans écriture)
Fichier(s) : tests/Feature/Jobs/SendMailAlertJobTest.php, tests/Feature/Services/SlaCalculatorServiceTest.php, tests/Feature/MenuPrivilegesTest.php (adaptés : Parametre au lieu de config('gec.*'), libellé "Paramètres système")
Pourquoi : demande explicite de l'utilisateur ("things like reference format ... those small things that usually need to be coded has to [be] done through the UI now") — PRD.md/DECISIONS.md notaient déjà ce manque ("Configuration administrateur des listes de référence... codées en dur... contrairement à l'exigence transversale confirmée par le client"). Voir DECISIONS.md "Paramètres système configurables" pour le périmètre retenu (format + SLA) et ce qui en est volontairement exclu (types de document, modes de réception, priorité, confidentialité — listes plus larges, hors scope de cette itération). Migration appliquée contre la vraie base (`php artisan migrate`, jamais `migrate:fresh`). 593/593 tests, Pint propre, build Vite OK.

## [2026-09-23 23:15] "Paramètres système" — mise en page alignée sur Organisation (panneau latéral + colonne principale)
Fichier(s) : resources/views/livewire/frontend/parametreSysteme.blade.php (réécrit : panneau latéral gauche STICKY listant les 3 catégories (Numéro de référence, SLA — Délais, SLA — Par type de document), même carte + `<nav>` que organisationIndex.blade.php "Structure de l'organisation" ; colonne principale affichant la catégorie active ; bascule purement Alpine (x-data="{ section: ... }", x-show — même patron que les onglets de showCourrier/editForm), aucune propriété Livewire ajoutée ; UN SEUL formulaire sous-jacent, "Enregistrer" soumet toutes les catégories d'un coup quel que soit l'onglet affiché)
Pourquoi : demande explicite de l'utilisateur, pendant que je diagnostiquais un rapport d'erreur Livewire ("use the design of organisation for parametre a side panel showing all config with a main displaying them") — remplace la première version (3 cartes empilées, tout visible en un seul défilement) par la même disposition à deux colonnes que la page Organisation. app/Livewire/Backend/ParametreSysteme.php inchangé (mêmes propriétés publiques, mêmes tests). 593/593 tests (1024M nécessaire pour un run complet fiable désormais, voir memory test_suite_memory_limit.md — la suite a grossi), Pint propre, build Vite OK.

## [2026-09-23 23:20] Diagnostic : "Property [$numeroReferenceChiffresSequence] not found"
Contexte : rapporté par l'utilisateur sur le serveur Herd réel juste après la mise en ligne de "Paramètres système". Le composant et la vue, relus intégralement, étaient corrects (la propriété est bien publique, le binding correspond) ; `Livewire::test()` (qui exerce le même mécanisme de mise à jour/réhydratation qu'une vraie interaction navigateur) passait déjà pour ce champ précis avant ce rapport. Aucun bug de code trouvé. Action : `php artisan optimize:clear` (config/cache/compiled/events/routes/views) lancé contre l'environnement réel — `Livewire::test()` tourne dans un process PHPUnit entièrement séparé (SQLite mémoire, cache 'array') qui ne touche JAMAIS le vrai process Herd, donc une staleness (vue compilée, cache config) invisible aux tests mais réelle sur le serveur vivant restait possible. Non reproduit depuis. Si ça revient : un rechargement complet du navigateur (pas seulement wire:navigate, qui garde le JS Livewire/Alpine en mémoire d'une page à l'autre) est la première chose à essayer côté client.

## [2026-09-24 09:00] "Paramètres système" — Groupe A : 5 réglages numériques ex-`const` codées en dur
Fichier(s) : database/migrations/2026_09_24_090000_add_group_a_settings_to_parametres_table.php (nouveau — 5 colonnes sur `parametres`, mêmes valeurs par défaut que les const remplacées : niveau_confidentialite_max=5, scan_resolution_minimale=600, ocr_confiance_minimale=55, ocr_longueur_minimale_texte=20, dashboard_delai_moyen_periode_jours=90)
Fichier(s) : app/Models/Parametre.php ($fillable/casts() étendus aux 5 nouvelles colonnes)
Fichier(s) : app/Models/User.php (ex-`const NIVEAU_CONFIDENTIALITE_MAX` → `User::niveauConfidentialiteMax(): int`, lit Parametre::actuel())
Fichier(s) : app/Livewire/Backend/ScanForm.php (ex-`const RESOLUTION_MINIMALE` → `ScanForm::resolutionMinimale(): int`) ; app/Livewire/Backend/ScanPremier.php, app/Livewire/Backend/RegistrationForm.php (appelants mis à jour, `ScanPremier::RESOLUTION_MINIMALE = ScanForm::RESOLUTION_MINIMALE` supprimée — const-référençant-const devenue inutile)
Fichier(s) : app/Jobs/ProcessDocumentOcr.php (ex-`const CONFIANCE_MINIMALE`/`LONGUEUR_MINIMALE_TEXTE` → `ProcessDocumentOcr::confianceMinimale()`/`longueurMinimaleTexte()`) ; app/Jobs/ProcessBrouillonOcr.php (appelant mis à jour)
Fichier(s) : app/Jobs/RefreshDashboardStatsJob.php (ex-`const PERIODE_JOURS = 90` → `Parametre::actuel()->dashboard_delai_moyen_periode_jours`)
Fichier(s) : app/Livewire/Backend/Forms/CourrierForm.php, app/Livewire/Backend/RegistrationFormConfidentiel.php, app/Livewire/Backend/ShowCourrier.php, app/Livewire/Backend/UserList.php (x2), resources/views/livewire/frontend/{userList,showCourrier,registrationFormConfidentiel,registrationForm,editForm,courrierList}.blade.php (tous les appels `User::NIVEAU_CONFIDENTIALITE_MAX` remplacés par `User::niveauConfidentialiteMax()`)
Pourquoi : demande explicite de l'utilisateur ("Group A + Group B" en réponse à la question "quels réglages ajouter ensuite à Paramètres système") — voir DECISIONS.md "Paramètres système configurables — Groupe A/B". Ces 5 valeurs pilotaient un comportement métier (Module 9 confidentialité, Module 2 scan/OCR, Module 10 tableau de bord) jusque-là figé au code, contrairement à la contrainte transversale du client (voir PRD.md).

## [2026-09-24 09:01] "Paramètres système" — Groupe B : listes de référence gérables (type de document, mode de réception, priorité)
Fichier(s) : database/migrations/2026_09_24_090100_create_listes_reference_table.php (nouveau — table `listes_reference` (type/valeur/ordre/actif/protege), seedée avec les 9 types de document + 4 modes de réception + 4 priorités déjà en dur ; `protege=true` sur "Sinistre" (CourrierForm::estUnSinistre() dépend de la chaîne exacte), "email"/"fax"/"depot_physique" (détectés/écrits tels quels par l'OCR ou valeur par défaut), et les 4 priorités (pilotent des match() de couleur en dur dans plusieurs vues) — les autres valeurs restent librement renommables)
Fichier(s) : app/Models/ListeReference.php (nouveau — valeursActives($type) mis en cache Redis/DB et invalidé automatiquement via booted()/saved()/deleted() (pas d'invalidation manuelle à chaque appelant, contrairement à Parametre) ; toutes($type) pour l'écran d'administration)
Fichier(s) : app/Livewire/Backend/Forms/CourrierForm.php (ex-`const TYPES_DOCUMENT` → `CourrierForm::typesDocument(): array`, lit ListeReference ; `mode_reception`/`priorite` passent d'un `Rule::in([...])` codé en dur à `Rule::in(ListeReference::valeursActives(...))` ; `type_document` reste volontairement SANS Rule::in, inchangé)
Fichier(s) : app/Livewire/Backend/EditForm.php, app/Livewire/Backend/RegistrationForm.php (x2), app/Livewire/Backend/ParametreSysteme.php, resources/views/livewire/frontend/{registrationForm,editForm,courrierList}.blade.php (appels `CourrierForm::TYPES_DOCUMENT` → `CourrierForm::typesDocument()`)
Pourquoi : "Group B" de la même demande utilisateur — ces 3 listes étaient les seules VRAIES listes de référence identifiées (par opposition aux 5 scalaires du Groupe A) nécessitant un vrai CRUD (ajout/renommage/activation/ordre), pas juste une valeur numérique. La désactivation retire uniquement une valeur des NOUVEAUX formulaires (Rule::in dynamique) — elle ne touche jamais aux courriers déjà enregistrés avec cette valeur, ni à l'OCR (qui écrit les chaînes littérales indépendamment de cette table).

## [2026-09-24 09:02] "Paramètres système" — UI Groupe A/B (4 nouvelles catégories dans le panneau latéral)
Fichier(s) : app/Livewire/Backend/ParametreSysteme.php (5 propriétés publiques Groupe A + save dans enregistrer() ; propriétés/actions Groupe B : ajouterTypeDocument()/ajouterModeReception()/ajouterPriorite(), ouvrirRenommage()/enregistrerRenommage()/annulerRenommage(), basculerActif() (refuse de désactiver la DERNIÈRE valeur active d'une liste — sinon Rule::in([]) rejetterait tout), deplacerValeur() (haut/bas, échange d'ordre avec le voisin) ; chaque action mutante revérifie administration.sla elle-même (mount() n'est pas rappelé entre deux actions Livewire sur un composant déjà monté, Règle n°6) ; computed listesTypeDocument()/listesModeReception()/listesPriorite() (Eloquent uniquement via #[Computed], jamais en propriété publique, Règle n°2))
Fichier(s) : resources/views/livewire/frontend/parametreSysteme.blade.php (4 nouvelles entrées de nav : "Confidentialité & qualité scan/OCR" (Groupe A) + 3 listes Groupe B générées par @foreach factorisé ; renommage en ligne, bouton crayon masqué si `protege` (avec tooltip cadenas), bascule actif/inactif (icône eye/eye-slash), flèches haut/bas ; `wire:keydown.enter.prevent` sur les inputs d'ajout/renommage pour ne pas déclencher par erreur le submit du formulaire "Enregistrer" englobant (les <flux:button> internes sont type="button" par défaut, vérifié dans vendor/livewire/flux, donc pas de risque de ce côté))
Pourquoi : suite directe des deux entrées précédentes — même patron de mise en page que la page Organisation (déjà établi le 2026-09-23), actions Groupe B immédiates (comme UserList::ajouter()) plutôt que groupées sous "Enregistrer" (qui reste réservé aux 10 réglages scalaires Numéro/SLA/Groupe A).

## [2026-09-24 09:05] Tests Groupe A/B + application à la vraie base + correctif cache
Fichier(s) : tests/Feature/Services/ListeReferenceTest.php (nouveau), tests/Feature/Services/ParametreTest.php, tests/Feature/Admin/ParametreSystemeTest.php, tests/Feature/Courriers/ScanFormTest.php, tests/Feature/Jobs/ProcessDocumentOcrTest.php, tests/Feature/Jobs/RefreshDashboardStatsJobTest.php (étendus — défauts Groupe A, valeurs actives/cache/invalidation Groupe B, protection contre le renommage, garde-fou "dernière valeur active", réordonnancement, seuils dynamiques bout-en-bout pour ScanForm/ProcessDocumentOcr/RefreshDashboardStatsJob)
Pourquoi : couverture des deux nouveaux groupes de réglages avant mise en production, conformément à la Règle n°7. Migrations appliquées contre la vraie base MySQL `gec` via `php artisan migrate --force` (jamais `migrate:fresh`, voir memory never_migrate_fresh_real_db.md) — additif uniquement, aucune donnée touchée. Incident constaté juste après : `Parametre::actuel()` restait en cache AVANT l'application de la migration (clé `gec.parametres` peuplée plus tôt dans la session), donc les 5 nouvelles colonnes manquaient au tableau d'attributs mis en cache → `Cannot assign null to property ...$niveauConfidentialiteMax of type int` sur le serveur réel. Corrigé par `php artisan cache:clear` puis `optimize:clear` ; vérifié directement via `php artisan tinker` contre la vraie base (pas seulement les tests, qui utilisent CACHE_STORE=array et n'auraient jamais révélé ce piège). 614/614 tests, Pint propre, `npm run build` OK.

## [2026-09-24 10:00] Validation DGA du service : 403 après succès + unité périmée
Fichier(s) : app/Livewire/Backend/ShowCourrier.php (validerService() : message distinct si aucun département n'est choisi ; refus d'une unité qui n'appartient pas au département sélectionné (valeur restée en mémoire d'un choix précédent) ; après une validation réussie, redirection vers "Courriers à traiter" si la DGA ne peut plus consulter le courrier ; executer() renvoie désormais un booléen de succès)
Fichier(s) : resources/views/livewire/frontend/showCourrier.blade.php (cascade Département / Service-Unité : wire:key sur chaque niveau conditionnel, option vide RÉELLE sur Département (même correctif que Site), Service/Unité en wire:model.live au lieu de différé)
Fichier(s) : tests/Feature/Courriers/CircuitCourrierTest.php (3 tests de régression : redirection au lieu d'un 403, unité d'un autre département refusée, message "aucun département")
Pourquoi : Module 4 (circuit de validation DGA) — rapport de l'utilisateur "ça ne marche pas". Reproduit en exécutant le vrai composant contre la vraie base MySQL (transaction annulée, aucune donnée modifiée) et d'après l'historique réel : la validation RÉUSSISSAIT (courriers #4 à 15:54:11 et #2 à 17:52:58), mais (1) le re-rendu suivant revérifiait `view`, que la DGA perd dès que le courrier quitte `en_cours_de_transfert` (branche voir_dga de CourrierPolicy::view()) → page 403 juste après le succès ; (2) le courrier #2 a été validé vers "Sinistre Santé" alors que l'écran montrait "Departement Informatique" — unité d'un autre département restée en mémoire (sélecteur différé et sans wire:key). Droits de la DGA vérifiés : courriers.dga_valider_service et voir_dga bien présents, destinataire_transfert_id = DGA Test. Remarque hors correctif : le courrier #1 (en attente, transféré à DGA Test) lui reste invisible car classé dans le dossier #1 auquel elle n'a pas accès — comportement voulu du 3e gate cumulatif (voir DECISIONS.md), signalé à l'utilisateur. 617/617 tests, Pint propre.

## [2026-09-24 10:30] Libellés de statut lisibles — 'enregistre' affiché "Transféré — à affecter"
Fichier(s) : app/Models/Courrier.php (nouvelle constante LIBELLES_STATUT + Courrier::libelleStatut() — seul point de vérité des libellés de statut ; repli sur la valeur brute sans "_" pour un statut inconnu)
Fichier(s) : resources/views/components/statut-badge.blade.php (affiche le libellé au lieu de la valeur brute ; classe CSS `capitalize` retirée, les libellés sont déjà correctement accentués/capitalisés)
Fichier(s) : resources/views/livewire/frontend/courrierList.blade.php (filtre "Statut" généré depuis LIBELLES_STATUT au lieu de 10 options recopiées à la main)
Fichier(s) : resources/views/pdf/bordereau.blade.php (le bordereau imprimait la valeur brute, ex. "en_cours_de_transfert")
Fichier(s) : lang/en.json (traduction "Transféré — à affecter")
Fichier(s) : tests/Feature/Courriers/ShowCourrierTest.php (assertion du badge mise à jour + test du nouveau libellé)
Pourquoi : Module 4/5 — question de l'utilisateur "pourquoi le statut passe de en cours de transfert à enregistré quand la DGA transfère au service". Comportement voulu (le 3e sous-statut "Transféré" du SRS est la valeur existante 'enregistre', décision du 2026-09-15 de ne pas ajouter de valeur d'enum — voir WorkflowService), mais le badge affichait la valeur brute "Enregistre", comme un retour en arrière. Option retenue par l'utilisateur : corriger l'affichage uniquement (aucune migration, aucun changement de workflow). 618/618 tests, Pint propre.

