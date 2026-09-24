<?php

namespace App\Notifications;

use App\Models\Courrier;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Module 7 — alerte SLA (avant et après dépassement de la date limite).
// Envoyée via SendMailAlertJob — jamais directement depuis un contrôleur.
// Pas ShouldQueue : le job qui l'envoie tourne déjà en file d'attente
// (Règle n°1) et doit savoir si l'envoi a échoué pour ne pas marquer
// l'alerte comme envoyée.
class CourrierEnRetardNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Courrier $courrier,
        public bool $enRetard,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $courrier = $this->courrier;
        $dateLimite = $courrier->date_limite?->format('d/m/Y');

        $message = (new MailMessage)
            ->subject($this->enRetard
                ? __('[GEC] Courrier en retard : :ref', ['ref' => $courrier->numero_reference])
                : __('[GEC] Courrier bientôt en retard : :ref', ['ref' => $courrier->numero_reference]))
            ->greeting(__('Bonjour :nom,', ['nom' => $notifiable->name]))
            ->line($this->enRetard
                ? __('Le courrier :ref a dépassé sa date limite de traitement (:date).', ['ref' => $courrier->numero_reference, 'date' => $dateLimite])
                : __('Le courrier :ref arrive à sa date limite de traitement (:date).', ['ref' => $courrier->numero_reference, 'date' => $dateLimite]));

        // Règle n°6 — un email sort du système : jamais l'objet d'un courrier
        // confidentiel, seulement sa référence (la fiche reste protégée par
        // CourrierPolicy::view() derrière le lien).
        if ($courrier->confidentialite <= 1) {
            $message->line(__('Objet : :objet', ['objet' => $courrier->objet]));
        }

        return $message->action(__('Ouvrir le courrier'), route('courriers.show', $courrier->id));
    }
}
