<?php

namespace Tests\Feature\Console;

use App\Mail\ReminderJobFinishedMail;
use Illuminate\Support\Facades\Mail;

it('sends reminder email to configured super admin email', function () {
    Mail::fake();

    config([
        'schooltool.sa_email' => 'kron@naturwelt.at',
        'schooltool.sa_first_name' => 'Guenther',
        'schooltool.sa_last_name' => 'Kron',
    ]);

    $this->artisan('mail:send-reminder')->assertExitCode(0);

    Mail::assertSent(ReminderJobFinishedMail::class, function (ReminderJobFinishedMail $mail): bool {
        return $mail->hasTo('kron@naturwelt.at')
            && $mail->hasSubject('Reminder');
    });
});

it('fails when super admin email is missing', function () {
    Mail::fake();

    config([
        'schooltool.sa_email' => '',
        'schooltool.sa_first_name' => 'Guenther',
        'schooltool.sa_last_name' => 'Kron',
    ]);

    $this->artisan('mail:send-reminder')->assertExitCode(1);

    Mail::assertNothingSent();
});
