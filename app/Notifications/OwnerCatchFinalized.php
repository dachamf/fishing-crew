<?php

namespace App\Notifications;

use App\Models\FishingCatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OwnerCatchFinalized extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public FishingCatch $catch,
        public string $finalStatus // 'approved' | 'rejected'
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = url("/catches/{$this->catch->id}");
        $species = $this->catch->species_label
            ?? $this->catch->species
            ?? $this->catch->species_name
            ?? '-';

        return (new MailMessage)
            ->subject("Tvoj ulov je {$this->finalStatus}")
            ->line("Ulov #{$this->catch->id} — {$species} je {$this->finalStatus}.")
            ->action('Pogledaj ulov', $url);
    }

    public function toArray($notifiable): array
    {
        return [
            'type'     => 'owner_catch_finalized',
            'catch_id' => $this->catch->id,
            'status'   => $this->finalStatus,
            'message'  => "Ulov {$this->finalStatus}.",
        ];
    }
}
