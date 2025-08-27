<?php

namespace App\Notifications;

use App\Models\FishingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class SessionConfirmationsRequested extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<int, array{id:int,species:string,count:int,total_weight_kg:float|null,caught_at:?string}> $items
     */
    public function __construct(
        public FishingSession $session,
        public array $items
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $count = count($this->items);
        $title = $this->session->title ?: 'Ribolovačka sesija';
        $list  = collect($this->items)
            ->take(10) // ako ima baš mnogo, skratimo preview
            ->map(function($c) {
                $date = $c['caught_at'] ? \Carbon\Carbon::parse($c['caught_at'])->format('d.m.Y H:i') : '—';
                $w = $c['total_weight_kg'] !== null ? number_format((float)$c['total_weight_kg'], 3, ',', '.') . ' kg' : '—';
                return "• #{$c['id']} — {$c['species']} — kom: {$c['count']} — {$w} — {$date}";
            })
            ->implode("\n");

        $url = url('/catches?assigned=me'); // FE ruta za "Ulovi dodeljeni meni"

        $mail = (new MailMessage)
            ->subject("Potvrda ulova: {$count} za pregled (sesija: {$title})")
            ->line("Zatražene su tvoje potvrde za ulove u sesiji: {$title}.")
            ->line($list);

        if ($count > 10) {
            $mail->line("… i još " . ($count - 10) . " ulova.");
        }

        return $mail->action('Otvori listu za pregled', $url);
    }

    public function toArray($notifiable): array
    {
        return [
            'type'       => 'session_confirmations_requested',
            'session_id' => $this->session->id,
            'count'      => count($this->items),
            'catch_ids'  => array_column($this->items, 'id'),
            'message'    => 'Zatražene potvrde za više ulova iz sesije.',
        ];
    }
}
