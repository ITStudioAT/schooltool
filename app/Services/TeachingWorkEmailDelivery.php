<?php

namespace App\Services;

class TeachingWorkEmailDelivery
{
    /** @return array{mode: string, available: bool, label: string} */
    public function context(): array
    {
        if (! app()->isLocal()) {
            return ['mode' => 'office_outlook', 'available' => true, 'label' => 'Office/Outlook'];
        }

        $mailpit = config('mail.default') === 'smtp'
            && in_array(strtolower((string) config('mail.mailers.smtp.host')), ['localhost', '127.0.0.1', '::1'], true)
            && (int) config('mail.mailers.smtp.port') === 1025
            && blank(config('mail.mailers.smtp.url'));

        return ['mode' => 'mailpit', 'available' => $mailpit,
            'label' => $mailpit ? 'Testversand über Mailpit' : 'Lokaler Versand gesperrt – Mailpit nicht konfiguriert'];
    }
}
