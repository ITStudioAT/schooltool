<?php

use App\Mail\PersonalTeachingBackupMail;
use App\Models\PersonalTeachingBackup;
use App\Models\User;
use App\Services\PersonalTeachingBackupDeliveryService;
use App\Services\PersonalTeachingBackupFileService;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    config(['mail.default' => 'smtp']);
    Mail::fake();
    $this->teacher = User::factory()->make([
        'id' => 23, 'school_id' => 4, 'schoolyear_id' => 7,
        'email' => 'teacher@example.test',
    ]);
    $this->backup = Mockery::mock(PersonalTeachingBackup::class)->makePartial();
    $this->backup->forceFill([
        'id' => 17, 'user_id' => 23, 'school_id' => 4,
        'created_at' => '2026-09-10 12:30:00', 'mail_status' => 'pending',
    ]);
    $this->backup->shouldReceive('save')->andReturn(true)->byDefault();
    $this->files = Mockery::mock(PersonalTeachingBackupFileService::class);
    $this->delivery = new PersonalTeachingBackupDeliveryService($this->files);
});

test('personal backups are immediately emailed once to the stored owner address', function () {
    $this->files->shouldReceive('contents')->once()->with($this->backup)->andReturn('authenticated-encrypted-backup');
    $this->files->shouldReceive('filename')->once()->with($this->backup)->andReturn('unterricht-sicherung-17.schooltool');
    $this->backup->shouldReceive('save')->once()->andReturn(true);

    $this->delivery->deliver($this->teacher, $this->backup);
    $this->delivery->deliver($this->teacher, $this->backup);

    Mail::assertSent(PersonalTeachingBackupMail::class, function (PersonalTeachingBackupMail $mail): bool {
        return $mail->hasTo('teacher@example.test')
            && count($mail->to) === 1 && $mail->cc === [] && $mail->bcc === []
            && $mail->backupFilename === 'unterricht-sicherung-17.schooltool';
    });
    Mail::assertSentCount(1);
    Mail::assertNothingQueued();
    expect($this->backup->mail_status)->toBe('sent')
        ->and($this->backup->mailed_at)->not->toBeNull();
});

test('a failed personal backup email keeps the backup and records a download fallback', function () {
    $this->files->shouldReceive('contents')->once()->andReturn('authenticated-encrypted-backup');
    $this->files->shouldReceive('filename')->once()->andReturn('unterricht-sicherung-17.schooltool');
    $pending = Mockery::mock(PendingMail::class);
    $pending->shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP secret should never reach the response'));
    Mail::shouldReceive('to')->once()->with('teacher@example.test')->andReturn($pending);
    $this->backup->shouldReceive('save')->once()->andReturn(true);
    $this->backup->shouldNotReceive('delete');

    $this->delivery->deliver($this->teacher, $this->backup);

    expect($this->backup->id)->toBe(17)
        ->and($this->backup->mail_status)->toBe('failed')
        ->and($this->backup->mail_message)->toContain('herunter')
        ->and($this->backup->mail_message)->not->toContain('SMTP secret')
        ->and($this->backup->mailed_at)->toBeNull();
});

test('oversized personal backup attachments remain available without trying to send email', function () {
    $this->files->shouldReceive('contents')->once()->andReturn(str_repeat('x', PersonalTeachingBackupDeliveryService::MAX_ATTACHMENT_BYTES + 1));
    $this->files->shouldNotReceive('filename');
    $this->backup->shouldNotReceive('delete');

    $this->delivery->deliver($this->teacher, $this->backup);

    Mail::assertNothingOutgoing();
    expect($this->backup->mail_status)->toBe('too_large')
        ->and($this->backup->mail_message)->toContain('10 MiB')
        ->and($this->backup->mailed_at)->toBeNull();
});

test('invalid stored email addresses do not discard a personal backup', function (string $email) {
    $this->teacher->email = $email;
    $this->files->shouldNotReceive('contents');

    $this->delivery->deliver($this->teacher, $this->backup);

    Mail::assertNothingOutgoing();
    expect($this->backup->mail_status)->toBe('failed')
        ->and($this->backup->mail_message)->toContain('E-Mail-Adresse');
})->with(['', 'invalid', 'teacher@schooltool.noemail']);

test('personal backups are not written to non-delivery mail transports', function (string $mailer) {
    config(['mail.default' => $mailer]);
    $this->files->shouldNotReceive('contents');

    $this->delivery->deliver($this->teacher, $this->backup);

    Mail::assertNothingOutgoing();
    expect($this->backup->mail_status)->toBe('failed')
        ->and($this->backup->mail_message)->toContain('nicht eingerichtet');
})->with(['log', 'array', 'failover']);

test('delivery refuses a backup owned by another user or school', function (array $attributes) {
    $this->backup->forceFill($attributes);
    $this->files->shouldNotReceive('contents');
    $this->backup->shouldNotReceive('save');

    expect(fn () => $this->delivery->deliver($this->teacher, $this->backup))->toThrow(HttpException::class);
    Mail::assertNothingOutgoing();
})->with([
    'different owner' => [['user_id' => 24]],
    'different school' => [['school_id' => 5]],
]);

test('personal backup mail attaches the encrypted artifact and explains its restoration', function () {
    $mail = new PersonalTeachingBackupMail('authenticated-encrypted-backup', 'unterricht-sicherung-17.schooltool', '10.09.2026 12:30');

    $mail->assertHasSubject('Ihre persönliche Unterrichts-Datensicherung');
    $mail->assertHasAttachedData('authenticated-encrypted-backup', 'unterricht-sicherung-17.schooltool', ['mime' => 'application/octet-stream']);
    $mail->assertSeeInHtml('10.09.2026 12:30');
    $mail->assertSeeInText('verschlüsselt');
    $mail->assertDontSeeInHtml('authenticated-encrypted-backup');
});
