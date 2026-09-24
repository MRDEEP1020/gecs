<?php

namespace App\Livewire\Backend;

use App\Models\Courrier;
use App\Models\CourrierHistorique;
use App\Models\User;
use App\Services\NumeroReferenceGenerator;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Module 1 — "Cas particulier : courrier confidentiel" (specifications-modules-GEC.md) :
// processus DIFFÉRENT du flux normal, pas une variante du même formulaire.
// L'agent n'ouvre ni ne scanne jamais le courrier, relève seulement le nom
// visible sur l'enveloppe, et l'envoie directement à un destinataire précis
// (RH ou DGA/ADJ DGA) — jamais de classification, jamais d'OCR. Composant
// séparé de RegistrationForm (pas un simple `if` dedans) : par construction,
// AUCUN champ d'upload n'existe ici (pas de WithFileUploads), donc aucun
// chemin ne permet d'y attacher un fichier par erreur — la garantie "jamais
// scanné" vient de l'absence même du mécanisme, pas d'une simple validation.
// Réutilise `destinatairesTransfert()` (voir DECISIONS.md "Destinataires de
// transfert") : la même liste, curatée par agent par l'administrateur, sert
// aussi bien le transfert différé (courrier normal) que l'envoi immédiat
// (courrier confidentiel) — les deux sont conceptuellement "à qui l'agent a
// le droit d'envoyer un courrier".
#[Title('Enregistrer un courrier confidentiel')]
class RegistrationFormConfidentiel extends Component
{
    public string $nomEnveloppe = '';

    public string $dateReception = '';

    // Entier 2-5 (2026-09-21, "numbers 1,2,3,4,5 etc") — jamais 1 (Normal)
    // ici : par définition, un courrier qui passe par ce formulaire dédié
    // EST confidentiel, voir User::NIVEAU_CONFIDENTIALITE_MAX.
    public int $niveauConfidentialite = 2;

    public ?int $destinataireSystemeId = null;

    public ?string $derniereReference = null;

    public ?int $derniereCourrierId = null;

    // Privilège dédié courriers.creer_confidentiel depuis le 2026-09-23
    // (menus pilotés par privilège, voir DECISIONS.md).
    public function mount(): void
    {
        $this->authorize('creerConfidentiel', Courrier::class);

        $this->dateReception = now()->format('Y-m-d');
    }

    // Même liste que ShowCourrier/MesCourriers — voir leur commentaire pour
    // le détail du design (DECISIONS.md "Destinataires de transfert").
    #[Computed]
    public function destinatairesTransfert()
    {
        return Auth::user()->destinatairesTransfert()->orderBy('name')->get(['users.id', 'users.name']);
    }

    public function enregistrer(NumeroReferenceGenerator $generateur): void
    {
        $this->authorize('creerConfidentiel', Courrier::class);

        $data = $this->validate([
            'nomEnveloppe' => ['required', 'string', 'max:255'],
            'dateReception' => ['required', 'date'],
            'niveauConfidentialite' => ['required', 'integer', 'between:2,'.User::niveauConfidentialiteMax()],
        ]);

        // Règle n°6 — jamais fait confiance à l'ID posté, même si la liste
        // affichée dans le formulaire vient déjà de $this->destinatairesTransfert.
        $destinataire = Auth::user()->destinatairesTransfert()->where('users.id', $this->destinataireSystemeId)->first();

        if (! $destinataire) {
            $this->addError('destinataireSystemeId', __('Choisissez un destinataire.'));

            return;
        }

        $courrier = DB::transaction(function () use ($data, $generateur, $destinataire) {
            // "Enregistré avec un minimum d'informations... transmis
            // directement, sans passer par la classification automatique" —
            // service_id reste null (jamais de Module 3 ici), statut passe
            // directement à 'enregistre' (pas de circuit de transfert/
            // validation DGA, le courrier est déjà "chez" son destinataire).
            // fichier_path/texte_ocr restent null : "pas de scan, pas d'OCR,
            // pas d'historique de contenu" (spec) — ce composant ne les
            // renseigne jamais.
            $courrier = Courrier::create([
                'numero_reference' => $generateur->generer(),
                'sens' => 'entrant',
                'date_mouvement' => $data['dateReception'],
                'destinataire' => $data['nomEnveloppe'],
                'objet' => __('Correspondance confidentielle (non ouverte)'),
                'type_document' => __('Correspondance confidentielle'),
                'mode_reception' => 'depot_physique',
                'service_id' => null,
                'statut' => 'enregistre',
                'confidentialite' => $data['niveauConfidentialite'],
                'destinataire_transfert_id' => $destinataire->id,
            ]);

            CourrierHistorique::create([
                'courrier_id' => $courrier->id,
                'auteur_id' => Auth::id(),
                'action' => 'creation',
                'commentaire' => null,
            ]);

            // Règle n°5 — trace explicite de l'envoi direct, même esprit que
            // WorkflowService::transferer() pour un courrier normal (mais
            // sans passer par lui : il n'y a pas d'étape "en attente de
            // transfert" à quitter ici, l'envoi est immédiat dès la création).
            CourrierHistorique::create([
                'courrier_id' => $courrier->id,
                'auteur_id' => Auth::id(),
                'action' => 'transfert',
                'commentaire' => "Courrier confidentiel envoyé directement à {$destinataire->name}",
            ]);

            return $courrier;
        });

        $this->derniereReference = $courrier->numero_reference;
        $this->derniereCourrierId = $courrier->id;

        $this->reset('nomEnveloppe', 'niveauConfidentialite', 'destinataireSystemeId');
        $this->dateReception = now()->format('Y-m-d');

        Flux::toast(variant: 'success', text: __('Courrier confidentiel enregistré sous la référence :ref.', ['ref' => $courrier->numero_reference]));
    }

    public function render()
    {
        return view('frontend::registrationFormConfidentiel');
    }
}
