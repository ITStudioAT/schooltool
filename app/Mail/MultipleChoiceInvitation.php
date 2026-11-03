<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MultipleChoiceInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $testTitle,
        public string $studentName,
        public string $accessUrl,
        public string $addressForm = 'sie',
        public string $firstName = '',
        public string $lastName = '',
        public string $sex = '',
        public ?string $subjectLine = null,
        public ?string $messageBody = null,
        public string $startMode = 'common',
        public ?string $closingDeadline = null,
        public int $durationSeconds = 0,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine ?? 'SchoolTool: Einladung zu '.$this->testTitle,
        );
    }

    public function greeting(): string
    {
        $firstName = trim($this->firstName);
        $lastName = trim($this->lastName);
        $sex = strtolower(trim($this->sex));
        if ($this->addressForm === 'du') {
            $name = $firstName !== '' ? $firstName : trim($this->studentName);

            return match ($sex) {
                'm' => 'Lieber '.$name,
                'w', 'f' => 'Liebe '.$name,
                default => 'Hallo '.$name,
            };
        }
        if ($lastName !== '') {
            return match ($sex) {
                'm' => 'Sehr geehrter Herr '.$lastName,
                'w', 'f' => 'Sehr geehrte Frau '.$lastName,
                default => 'Guten Tag '.trim($this->studentName),
            };
        }

        return 'Guten Tag '.trim($this->studentName);
    }

    public function messageText(): string
    {
        if ($this->messageBody !== null) {
            return $this->messageBody;
        }

        $invitation = $this->addressForm === 'du'
            ? 'für dich wurde der Multiple-Choice-Test'
            : 'für Sie wurde der Multiple-Choice-Test';
        $login = $this->addressForm === 'du'
            ? 'Du kannst den Test auch nach deiner gewohnten Schüleranmeldung unter Multiple Choice öffnen. Beide Zugänge führen zu demselben Versuch.'
            : 'Sie können den Test auch nach Ihrer gewohnten Schüleranmeldung unter Multiple Choice öffnen. Beide Zugänge führen zu demselben Versuch.';
        $waiting = $this->addressForm === 'du'
            ? 'Vor dem gemeinsamen Start wartest du im Warteraum. Den Countdown startet deine Lehrperson.'
            : 'Vor dem gemeinsamen Start warten Sie im Warteraum. Den Countdown startet Ihre Lehrperson.';
        if ($this->startMode === 'individual') {
            $minutes = rtrim(rtrim(number_format($this->durationSeconds / 60, 2, ',', ''), '0'), ',');
            $waiting = $this->addressForm === 'du'
                ? 'Du startest den Test einmal selbst mit „Test jetzt starten“. Ab dann hast du '.$minutes.' Minuten; die Zeit läuft auch weiter, wenn du die Seite verlässt.'
                : 'Sie starten den Test einmal selbst mit „Test jetzt starten“. Ab dann haben Sie '.$minutes.' Minuten; die Zeit läuft auch weiter, wenn Sie die Seite verlassen.';
            $waiting .= ' Spätestes Testende: '.$this->closingDeadline.'. Bei spätem Start bleibt weniger Zeit.';
        }
        $privacy = $this->addressForm === 'du'
            ? 'Dieser Link ist persönlich. Bitte gib ihn nicht weiter.'
            : 'Dieser Link ist persönlich. Bitte geben Sie ihn nicht weiter.';

        return implode("\n\n", [
            $this->greeting().',', $invitation.' '.$this->testTitle.' freigegeben.',
            $login, $waiting, $privacy,
        ]);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $url = e($this->accessUrl);
        $paragraphs = preg_split('/\n\s*\n/', trim(str_replace(["\r\n", "\r"], "\n", $this->messageText())));
        $body = implode('', array_map(fn (string $paragraph): string => '<p>'.str_replace("\n", '<br>', e($paragraph)).'</p>', $paragraphs));

        return new Content(htmlString: <<<HTML
            <!doctype html><html lang="de"><body style="font-family: Arial, Helvetica, sans-serif; color: #17352c; line-height: 1.6;">
            <div data-mc-message="true">{$body}</div>
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: 22px 0;">
                <tr><td bgcolor="#1b5e4b" style="border-radius: 8px; padding: 14px 24px; text-align: center;">
                    <a href="{$url}" style="display: inline-block; font-family: Arial, Helvetica, sans-serif; font-weight: bold; color: #ffffff; text-decoration: none; line-height: 1.4;">Persönlichen Test öffnen&nbsp; →</a>
                </td></tr>
            </table>
            </body></html>
            HTML);
    }
}
