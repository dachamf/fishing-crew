<?php

namespace App\Notifications;

use App\Models\CatchConfirmation;
use App\Models\FishingCatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CatchConfirmationUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public FishingCatch $catch,
        public CatchConfirmation $confirmation
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = url("/catches/{$this->catch->id}");
        $status = $this->confirmation->status;

        return (new MailMessage)
            ->subject("Potvrda za tvoj ulov: {$status}")
            ->line("Korisnik #{$this->confirmation->confirmed_by} je označio: {$status}.")
            ->when($this->confirmation->note, fn($m) => $m->line("Napomena: {$this->confirmation->note}"))
            ->action('Pogledaj ulov', $url);
    }

    public function toArray($notifiable): array
    {
        return [
            'type'           => 'confirmation_updated',
            'catch_id'       => $this->catch->id,
            'confirmed_by'   => $this->confirmation->confirmed_by,
            'status'         => $this->confirmation->status,
            'note'           => $this->confirmation->note,
            'message'        => "Potvrda ažurirana: {$this->confirmation->status}",
        ];
    }
}
