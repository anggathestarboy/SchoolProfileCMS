<?php

namespace App\Modules\ActivityLog\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConfirmActivityLogDeletion extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $year, public readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('activity-log.confirm-delete', ['token' => $this->token]);

        return (new MailMessage)
            ->subject("Confirm: permanently delete {$this->year} activity log entries")
            ->line("A request was made to permanently delete every activity log entry from {$this->year}.")
            ->line('This cannot be undone. If you did not request this, ignore this email — nothing will happen without your confirmation.')
            ->action('Confirm deletion', $url)
            ->line('The deletion will run automatically at 00:00 on the first night after you confirm.')
            ->line('This confirmation link expires in 24 hours.');
    }
}
