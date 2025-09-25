<?php

namespace App\Notifications;

use App\Models\FishingSession;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OwnerSessionConfirmationUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public FishingSession $session,
        public ?User $actor,
        public string $decision, // 'approved' | 'rejected'
        public string $url
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database']; // prilagodi po želji
    }

    public function toMail($notifiable): MailMessage
    {
        $title = $this->session->title ?: "Sesija #{$this->session->id}";
        $who   = $this->actor?->profile?->display_name
            ?: $this->actor?->name
                ?: "Nominovani korisnik";

        $verb = $this->decision === 'approved' ? 'odobrio' : 'odbijio';

        return (new MailMessage)
            ->subject("{$who} je {$verb} sesiju: {$title}")
            ->greeting('Obaveštenje')
            ->line("{$who} je {$verb} tvoju sesiju \"{$title}\".")
            ->action('Otvori sesiju', $this->url);
    }

    public function toArray($notifiable): array
    {
        return [
            'session_id' => $this->session->id,
            'actor_id'   => $this->actor?->id,
            'decision'   => $this->decision,
            'url'        => $this->url,
        ];
    }
}
