<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Courriel GesParc générique : un objet, quelques lignes, un bouton vers la page concernée. */
class GesParcMessage extends Notification
{
    use Queueable;

    /** @param  string[]  $lines */
    public function __construct(
        public string $subject,
        public array $lines,
        public string $actionText,
        public string $actionUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('[GesParc] '.$this->subject)
            ->greeting('Bonjour '.$notifiable->name.',');
        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $mail->action($this->actionText, $this->actionUrl)
            ->salutation("— GesParc, gestion du parc informatique\nMessage automatique, merci de ne pas y répondre.");
    }
}
