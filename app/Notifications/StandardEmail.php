<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StandardEmail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public array $data,
        public array|string|null $attachments = null
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {

        $mail = (new MailMessage())
            ->from($this->data['from_address'], $this->data['from_name'])
            ->subject($this->data['subject'])
            ->markdown($this->data['markdown'], [
                'notifiable' => $notifiable,
                'data' => $this->data,
                'logo' => $this->data['logo'] ?? null,
            ]);

        // ✅ Optional attachments support
        if ($this->attachments) {
            $attachments = is_array($this->attachments)
                ? $this->attachments
                : [$this->attachments];

            foreach ($attachments as $file) {
                if (file_exists($file)) {
                    $mail->attach($file, [
                        'as' => basename($file),
                        'mime' => mime_content_type($file) ?: null,
                    ]);
                }
            }
        }

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
