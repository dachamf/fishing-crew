<?php

namespace App\Notifications;

use App\Models\FishingCatch;
use App\Models\FishingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CatchConfirmationRequested extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $channels = ['database', 'mail'];

    public function setChannels(array $channels): self
    {
        $this->channels = $channels;
        return $this;
    }

    public function via($notifiable): array
    {
        return $this->channels;
    }

    public function __construct(
        public FishingCatch $catch,
        public ?FishingSession $session = null
    ) {}

    public function toMail($notifiable): MailMessage
    {
        $title = $this->session?->title ?: 'Ribolovačka sesija';
        $url = url("/catches/{$this->catch->id}"); // FE ruta

        return (new MailMessage)
            ->subject("Potvrda ulova potrebna (#{$this->catch->id})")
            ->line("Zatražena je tvoja potvrda za ulov u sesiji: {$title}.")
            ->line("Vrsta: ".($this->catch->species_label ?? $this->catch->species ?? $this->catch->species_name ?? '-'))
            ->action('Otvori ulov', $url);
    }

    public function toArray($notifiable): array
    {
        return [
            'type'       => 'confirmation_requested',
            'catch_id'   => $this->catch->id,
            'session_id' => $this->session?->id,
            'group_id'   => $this->catch->group_id,
            'message'    => 'Zatražena tvoja potvrda za ulov.',
        ];
    }
}
