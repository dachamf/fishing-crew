<?php

namespace App\Notifications;

use App\Models\FishingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionReviewTokenLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public FishingSession $session,
        public string $url
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database']; // prilagodi po želji
    }

    public function toMail($notifiable): MailMessage
    {
        $title = $this->session->title ?: "Sesija #{$this->session->id}";

        return (new MailMessage)
            ->subject("Potvrda sesije: {$title}")
            ->greeting('Zdravo!')
            ->line("Pozvani ste da potvrdite sesiju \"{$title}\".")
            ->action('Otvori potvrdu', $this->url)
            ->line('Klikom na dugme možete odobriti ili odbiti bez prijave.');
    }

    public function toArray($notifiable): array
    {
        return [
            'session_id' => $this->session->id,
            'url'        => $this->url,
        ];
    }
}
