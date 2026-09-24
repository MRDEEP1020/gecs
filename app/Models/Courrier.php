<?php

namespace App\Models;

use App\Services\SlaCalculatorService;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Courrier extends Model
{
    use SoftDeletes;

    // Valeur par défaut EN MÉMOIRE, en plus du DEFAULT SQL de la migration —
    // un `Courrier::create()` sans "confidentialite" explicite laisse
    // l'attribut absent/`null` sur l'instance retournée tant qu'elle n'est
    // pas relue depuis la base (voir le même correctif déjà appliqué à
    // User, memory utilisateurs_acces_maquette_2026_09_21.md, "piège
    // trouvé en testant") — corrigé ici une fois pour toutes plutôt que de
    // continuer à l'expliciter dans chaque helper de test.
    protected $attributes = [
        'confidentialite' => 1,
    ];

    // Module 5 (2026-09-23, voir DECISIONS.md "SLA et alertes") — date
    // limite recalculée à chaque changement d'une de ses sources, quel que
    // soit l'écran ou le service qui enregistre le courrier. Une nouvelle
    // date limite ré-arme les alertes (Module 7) : un délai prolongé doit
    // pouvoir à nouveau prévenir avant/après la nouvelle échéance.
    protected static function booted(): void
    {
        static::saving(function (Courrier $courrier) {
            if (! $courrier->exists || $courrier->isDirty(['echeance', 'sla_jours', 'type_document', 'date_mouvement'])) {
                $courrier->date_limite = SlaCalculatorService::calculerDateLimite(
                    $courrier->echeance,
                    $courrier->sla_jours,
                    $courrier->type_document,
                    $courrier->date_mouvement,
                );
            }
        });

        // Ré-armement par requête directe plutôt qu'en assignant null sur
        // l'instance : une instance chargée AVANT l'envoi d'une alerte (par
        // SendMailAlertJob, dans un autre processus) a déjà null en mémoire,
        // et Eloquent ne sauvegarderait alors pas ce "changement".
        static::saved(function (Courrier $courrier) {
            if ($courrier->wasChanged('date_limite')) {
                static::query()->whereKey($courrier->getKey())->update(['alerte_risque_le' => null, 'alerte_retard_le' => null]);
                $courrier->setRawAttributes(array_merge($courrier->getAttributes(), ['alerte_risque_le' => null, 'alerte_retard_le' => null]), true);
            }
        });
    }

    // Module 5/7 — courrier encore actif dont la date limite est dépassée.
    public function scopeEnRetard(Builder $query): Builder
    {
        return $query
            ->whereIn('statut', WorkflowService::statutsActifs())
            ->whereDate('date_limite', '<', today());
    }

    // "confidentialite" est un ENTIER (1 à 5) depuis le 2026-09-21 —
    // demande explicite de l'utilisateur : "the niveau should be numbers
    // not confidential or what ever but numbers 1,2,3,4,5 etc". Comparé
    // directement à User::niveauConfidentialiteEffectif() dans
    // CourrierPolicy — plus de table de correspondance chaîne→entier
    // (voir la migration 2026_09_21_110000 pour l'historique : normale=1/
    // confidentiel=2/tres_confidentiel=3 avant cette date).
    protected $fillable = [
        'numero_reference',
        'numero_tampon_detecte',
        'reference_externe',
        'sens',
        'date_mouvement',
        'echeance',
        'date_limite',
        'alerte_risque_le',
        'alerte_retard_le',
        'sla_jours',
        'expediteur_nom',
        'expediteur_fonction',
        'expediteur_organisation',
        'expediteur_telephone',
        'expediteur_email',
        'expediteur_adresse',
        'expediteur_rc',
        'expediteur_niu',
        'destinataire',
        'note_interne',
        'dossier_reference',
        'objet',
        'type_document',
        'sous_type_sinistre',
        'mode_reception',
        'type_traitement',
        'service_id',
        'direction_origine_id',
        'service_responsable_id',
        'dossier_classement_id',
        'statut',
        'priorite',
        'confidentialite',
        'fichier_path',
        'texte_ocr',
        'ocr_statut',
        'ocr_confiance',
        'ocr_traite_le',
        'classement_statut',
        'type_document_propose',
        'service_propose_id',
        'destinataire_transfert_id',
        'classement_regle_id',
        'classement_propose_le',
        'classement_analyse_le',
    ];

    protected function casts(): array
    {
        return [
            'date_mouvement' => 'date',
            'echeance' => 'date',
            'date_limite' => 'date',
            'alerte_risque_le' => 'datetime',
            'alerte_retard_le' => 'datetime',
            'confidentialite' => 'integer',
            'ocr_traite_le' => 'datetime',
            'classement_propose_le' => 'datetime',
            'classement_analyse_le' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    // Maquette "Modifier le courrier" (2026-09-18) — direction d'origine,
    // distincte de service_id (le service concerné/qui traite le courrier) :
    // net nouveau, jamais renseigné automatiquement, toujours nullable.
    public function directionOrigine(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'direction_origine_id');
    }

    // Maquette "Détail du courrier" (2026-09-18) — service ultimement
    // responsable/redevable du courrier, distinct de service_id (le
    // service concerné/qui le traite actuellement) : net nouveau,
    // toujours nullable.
    public function serviceResponsable(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_responsable_id');
    }

    // Module 3 — proposition du système, distincte de la valeur saisie/validée.
    public function servicePropose(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_propose_id');
    }

    // Module 3 — dossier de classement dans lequel ce courrier est rangé
    // (au plus un). Null = non classé, jamais restreint par le gate
    // d'accès par dossier (voir CourrierPolicy::accesDossierSuffisant()).
    public function dossierClassement(): BelongsTo
    {
        return $this->belongsTo(DossierClassement::class);
    }

    // Module 1/4 — la personne choisie par la réceptionniste dans la modale
    // "Transférer à" (voir DECISIONS.md "Destinataires de transfert") ;
    // null pour un courrier jamais transféré ou transféré avant ce
    // changement.
    public function destinataireTransfert(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinataire_transfert_id');
    }

    public function regleClassement(): BelongsTo
    {
        return $this->belongsTo(RegleClassement::class, 'classement_regle_id');
    }

    // Module 3 — tags secondaires (source : regle | ocr | manuel).
    public function motsCles(): BelongsToMany
    {
        return $this->belongsToMany(MotCle::class, 'courrier_mot_cle')
            ->withPivot('source')
            ->orderBy('libelle');
    }

    // Module 5 — historique append-only, jamais reconstitué depuis updated_at
    public function historiques(): HasMany
    {
        return $this->hasMany(CourrierHistorique::class)->latest('created_at');
    }

    // Module 6 — journal append-only des (ré)affectations, la plus récente en tête.
    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class)->latest('created_at');
    }

    // Affectation en cours (relation "ofMany" — une seule ligne, résolue par
    // une sous-requête corrélée) : utilisable dans whereHas()/with() sans les
    // pièges d'un ->first() sur une collection chargée en mémoire.
    public function affectationCourante(): HasOne
    {
        return $this->hasOne(Affectation::class)->latestOfMany();
    }

    // Pièces jointes (distinct de fichier_path, réservé au document principal
    // scanné du Module 2 — voir ARCHITECTURE.md).
    public function piecesJointes(): HasMany
    {
        return $this->hasMany(PieceJointe::class)->latest('created_at');
    }

    // Module 2/3 — texte OCR utilisable pour le classement/les mots-clés
    // uniquement s'il a été jugé lisible ("après un OCR réussi" — voir
    // DECISIONS.md Module 3). Un texte en echec_qualite est du bruit
    // potentiel : il reste affiché sur la fiche (transparence), mais ne doit
    // jamais alimenter une proposition de classement ni un tag automatique.
    public function texteOcrExploitable(): ?string
    {
        return $this->ocr_statut === 'reussi' ? $this->texte_ocr : null;
    }

    // Module 2 — l'extension d'origine est préservée telle quelle à
    // l'enregistrement (voir RegistrationForm::enregistrer()), jamais forcée
    // à ".pdf" : un scan JPG/PNG reste une image. Utilisé pour choisir le
    // bon aperçu (visionneuse PDF.js pour un PDF, affichage natif du
    // navigateur pour une image).
    public function estUnDocumentPdf(): bool
    {
        return $this->fichier_path !== null
            && Str::lower(pathinfo($this->fichier_path, PATHINFO_EXTENSION)) === 'pdf';
    }

    // Module 9 — utilisé par CourrierPolicy::view() : l'auteur de la création
    // n'est pas dupliqué en colonne sur `courriers`, l'historique en fait foi (Règle n°5).
    public function estCreeParUtilisateur(User $user): bool
    {
        return $this->historiques()
            ->where('action', 'creation')
            ->where('auteur_id', $user->id)
            ->exists();
    }

    // Module 1/4 — segment "service" utilisé pour construire un chemin de
    // stockage (courriers/{annee}/{segment}/...) — voir RegistrationForm,
    // PieceJointeService, ScanForm, WorkflowService::validerService(). Un
    // courrier entrant en attente de validation DGA/ADJ n'a pas encore de
    // service (voir DECISIONS.md, synchronisation SRS-GEC.pdf) : ses fichiers
    // sont provisoirement rangés sous ce segment, puis déplacés vers le vrai
    // code service une fois `service_id` assigné par le DGA.
    public const SEGMENT_SERVICE_EN_ATTENTE = '_en_attente';

    // Libellé affiché de chaque statut — seul point de vérité (badge, filtre
    // "Tous les courriers", bordereau PDF). 'enregistre' est le 3e
    // sous-statut "Transféré" du SRS (voir WorkflowService, décision du
    // 2026-09-15 de ne pas ajouter de valeur d'enum) : affiché comme tel
    // plutôt que "Enregistre", qui laissait croire à un retour en arrière
    // après la validation DGA (demande de l'utilisateur, 2026-09-24).
    public const LIBELLES_STATUT = [
        'en_attente_de_transfert' => 'En attente de transfert',
        'en_cours_de_transfert' => 'En cours de transfert',
        'enregistre' => 'Transféré — à affecter',
        'affecte' => 'Affecté',
        'en_traitement' => 'En traitement',
        'en_validation' => 'En validation',
        'en_attente_information' => 'En attente d\'information',
        'traite' => 'Traité',
        'archive' => 'Archivé',
        'rejete' => 'Rejeté',
    ];

    public static function libelleStatut(?string $statut): string
    {
        return isset(self::LIBELLES_STATUT[$statut])
            ? __(self::LIBELLES_STATUT[$statut])
            : str_replace('_', ' ', (string) $statut);
    }

    public function segmentClassement(): string
    {
        return $this->service?->code ?? self::SEGMENT_SERVICE_EN_ATTENTE;
    }

    // Module Organisation v2 (2026-09-22, spec §18/§19) — QUATRIÈME gate
    // cumulatif, même principe opt-in que le gate dossier ci-dessus : un
    // utilisateur SANS périmètre assigné (organizationUnitsPerimetre vide)
    // n'est pas restreint par ce gate. Un courrier SANS service_id (entrant
    // en attente de validation DGA/ADJ, voir SEGMENT_SERVICE_EN_ATTENTE)
    // n'est jamais affecté par ce gate non plus — même principe que le gate
    // dossier : on ne restreint que ce qui porte réellement l'attribut
    // gaté, sinon validerService() (qui autorise justement sur le courrier
    // AVANT que service_id ne soit posé) serait cassée pour tout DGA ayant
    // un périmètre assigné. Sinon, l'utilisateur ne voit/n'agit que sur les
    // courriers dont le service (via le pont OrganizationUnit::service_id)
    // est sous l'une des entités accordées.
    public function estDansLePerimetreDe(User $user): bool
    {
        $perimetre = $user->organizationUnitsPerimetre;

        if ($perimetre->isEmpty() || $this->service_id === null) {
            return true;
        }

        return in_array($this->service_id, OrganizationUnit::idsServicesReelsSousArbre($perimetre), true);
    }

    // Module 8 — périmètre "que peut voir cet utilisateur", point de vérité
    // unique partagé par CourrierPolicy (par enregistrement),
    // CourrierList::portee() et DossierClassementList (au niveau requête) —
    // extrait de CourrierList::portee() le 2026-09-21 en ajoutant le
    // troisième gate (dossier de classement) pour ne plus tenir cette
    // logique synchronisée à la main à 3 endroits (déjà un risque réel
    // constaté lors de l'ajout de la confidentialité numérique le même
    // jour).
    //
    // 2026-09-23 — le bloc "périmètre par profil" est désormais basé sur les
    // PRIVILÈGES, miroir exact de CourrierPolicy::view() (voir DECISIONS.md
    // "Périmètre de visibilité unique") : auparavant il comparait le NOM du
    // profil, si bien qu'un utilisateur sans profil (ou d'un profil
    // personnalisé) n'était filtré par AUCUN bloc et voyait tous les
    // courriers dans les listes, alors que la Policy lui refusait chacun
    // individuellement. Aucun privilège de consultation => aucun résultat.
    // Ce scope est aussi, depuis ce même jour, le seul utilisé par
    // Dashboard, WorkflowQueue, CourriersEnregistres et MesCourriers.
    public function scopeVisiblePar(Builder $query, User $user): Builder
    {
        return $query
            ->where('confidentialite', '<=', $user->niveauConfidentialiteEffectif())
            ->unless($user->hasPrivilege('courriers.voir_tout'), fn ($q) => $q->where(function ($q) use ($user) {
                $q->whereRaw('1 = 0');

                if ($user->hasPrivilege('courriers.voir_service')) {
                    $q->orWhereHas('service', fn ($q) => $q->where('responsable_id', $user->id));
                }

                if ($user->hasPrivilege('courriers.voir_propre')) {
                    $q->orWhereHas('historiques', fn ($q) => $q->where('action', 'creation')->where('auteur_id', $user->id));
                }

                if ($user->hasPrivilege('courriers.voir_affecte')) {
                    $q->orWhereHas('affectationCourante', fn ($q) => $q->where('user_id', $user->id));
                }

                if ($user->hasPrivilege('courriers.voir_dga')) {
                    $q->orWhere(fn ($q) => $q->where('statut', 'en_cours_de_transfert')
                        ->where(fn ($q) => $q->whereNull('destinataire_transfert_id')->orWhere('destinataire_transfert_id', $user->id)));
                }
            }))
            // Module 3/9 — "les trois se cumulent" (DECISIONS.md 2026-09-16) :
            // gerer_tout court-circuite (même logique que
            // CourrierPolicy::accesDossierSuffisant()) ; sinon un courrier
            // CLASSÉ n'est visible que via ownership/partage du dossier ; un
            // courrier non classé n'est jamais affecté par ce bloc.
            ->when(! $user->hasPrivilege('dossiers_classement.gerer_tout'), fn ($q) => $q->where(fn ($q) => $q
                ->whereNull('dossier_classement_id')
                ->orWhereHas('dossierClassement', fn ($q) => $q
                    ->where('cree_par_id', $user->id)
                    ->orWhere('responsable_id', $user->id)
                    ->orWhereHas('utilisateursAutorises', fn ($q) => $q->where('users.id', $user->id)))))
            // Module Organisation v2 — QUATRIÈME gate cumulatif, opt-in comme
            // le gate dossier ci-dessus (voir estDansLePerimetreDe()) : un
            // utilisateur sans périmètre assigné n'est pas restreint ici ; un
            // courrier sans service_id (en attente de validation DGA/ADJ)
            // n'est jamais affecté par ce gate non plus.
            ->when($user->organizationUnitsPerimetre->isNotEmpty(), fn ($q) => $q
                ->where(fn ($q) => $q
                    ->whereNull('service_id')
                    ->orWhereIn('service_id', OrganizationUnit::idsServicesReelsSousArbre($user->organizationUnitsPerimetre))));
    }
}
