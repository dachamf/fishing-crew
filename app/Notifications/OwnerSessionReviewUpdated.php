<?php


namespace App\Notifications;

use App\Models\FishingSession;
use App\Models\SessionReview;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OwnerSessionReviewUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public FishingSession $session,
        public SessionReview  $review,
        public ?User          $reviewer = null
    )
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $title = $this->session->title ?: 'Ribolovačka sesija';
        $url = url("/sessions/{$this->session->id}");
        $who = $this->reviewer?->name ?? "Korisnik #{$this->review->reviewer_id}";
        $note = $this->review->note ? "Napomena: {$this->review->note}" : null;

        $m = (new MailMessage)
            ->subject("{$who} je {$this->review->status} sesiju: {$title}")
            ->line("Nominovani '{$who}' je označio status: {$this->review->status}.")
            ->when($note, fn($msg) => $msg->line($note))
            ->action('Otvori sesiju', $url);

        return $m;
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'owner_session_review_updated',
            'session_id' => $this->session->id,
            'reviewer_id' => $this->review->reviewer_id,
            'status' => $this->review->status,
            'note' => $this->review->note,
            'message' => 'Ažuriran glas nad sesijom.',
        ];
    }
}
