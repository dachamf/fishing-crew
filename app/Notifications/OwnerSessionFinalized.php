<?php
namespace App\Notifications;

use App\Models\FishingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;


class OwnerSessionFinalized extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(public FishingSession $session, public string $finalStatus) {}

    public function via($notifiable): array { return ['database','mail']; }

    public function toMail($notifiable): MailMessage
    {
        $title = $this->session->title ?: 'Ribolovačka sesija';
        $url = url("/sessions/{$this->session->id}");
        $count = $this->session->catches()->count();

        return (new MailMessage)
            ->subject("Sesija je {$this->finalStatus} ({$title})")
            ->line("Tvoja sesija '{$title}' je {$this->finalStatus}.")
            ->line("Ukupno ulova: {$count}.")
            ->action('Otvori sesiju', $url);
    }

    public function toArray($notifiable): array
    {
        return [
            'type'       => 'owner_session_finalized',
            'session_id' => $this->session->id,
            'status'     => $this->finalStatus,
            'message'    => "Sesija {$this->finalStatus}.",
        ];
    }
}
