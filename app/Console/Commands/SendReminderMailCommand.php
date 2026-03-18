<?php

namespace App\Console\Commands;

use App\Mail\ReminderJobFinishedMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;

class SendReminderMailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:send-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sendet eine Reminder-E-Mail an die konfigurierte Super-Admin-Adresse.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $recipientEmail = (string) config('schooltool.sa_email', '');

        if ($recipientEmail === '') {
            $this->error('SA_EMAIL ist nicht konfiguriert.');

            return self::FAILURE;
        }

        $recipientName = trim(sprintf(
            '%s %s',
            (string) config('schooltool.sa_first_name', ''),
            (string) config('schooltool.sa_last_name', '')
        ));

        $recipient = $recipientName !== ''
            ? new Address($recipientEmail, $recipientName)
            : $recipientEmail;

        Mail::to($recipient)->send(new ReminderJobFinishedMail);

        $this->info("Reminder-E-Mail wurde an {$recipientEmail} versendet.");

        return self::SUCCESS;
    }
}
