<?php

namespace App\Notifications;

use App\Models\CatchConfirmation;
use App\Models\FishingCatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CatchChangeRequested extends Notification implements ShouldQueue
{
    use Queueable;


    protected array $channels = ['database', 'mail'];

    public function __construct(
        public FishingCatch $catch,
        public CatchConfirmation $confirmation
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function setChannels(array $channels): self
    {
        $this->channels = $channels;
        return $this;
    }

    public function toMail($notifiable): MailMessage
    {
        $url = url("/catches/{$this->catch->id}");

        return (new MailMessage)
            ->subject('Zatražene izmene za tvoj ulov')
            ->line('Jedan od potvrđivača je zatražio izmene.')
            ->when($this->confirmation->note, fn($m) => $m->line("Napomena: {$this->confirmation->note}"))
            ->action('Pogledaj ulov', $url);
    }

    public function toArray($notifiable): array
    {
        return [
            'type'         => 'changes_requested',
            'catch_id'     => $this->catch->id,
            'confirmed_by' => $this->confirmation->confirmed_by,
            'note'         => $this->confirmation->note,
            'suggested'    => $this->confirmation->suggested_payload ?? null,
            'message'      => 'Tražene izmene na ulovu.',
        ];
    }
}
