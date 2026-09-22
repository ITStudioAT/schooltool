<?php

use App\Models\User;
use App\Notifications\StandardEmail;
use App\Notifications\StandardEmailWithAttachment;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    // Create test attachment file in a safe location
    $this->testFilePath = storage_path('app/test-notifications/test-attachment.txt');
    $testDir = dirname($this->testFilePath);

    if (! is_dir($testDir)) {
        mkdir($testDir, 0775, true);
    }

    file_put_contents($this->testFilePath, 'Test attachment content');
});

afterEach(function () {
    // Clean up test files
    if (isset($this->testFilePath) && file_exists($this->testFilePath)) {
        @unlink($this->testFilePath);
    }

    $testDir = storage_path('app/test-notifications');
    if (is_dir($testDir)) {
        @rmdir($testDir);
    }
});

describe('StandardEmail Notification', function () {
    it('skips serialized retired module mail jobs before rendering missing templates or models', function (string $template) {
        config(['mail.default' => 'array', 'schooltool.preview.instance' => false]);
        Mail::purge('array');
        $retiredModels = [];

        foreach (['App\\Models\\TutoringOffer', 'App\\Models\\TutoringOfferRequest', 'App\\Models\\TutoringSubject'] as $class) {
            expect(class_exists($class))->toBeFalse();
            $retiredModels[] = unserialize(sprintf('O:%d:"%s":0:{}', strlen($class), $class), ['allowed_classes' => false]);
        }

        $notification = new StandardEmail([
            'from_address' => 'sender@example.test',
            'from_name' => 'School',
            'subject' => 'Retired module message',
            'markdown' => $template,
            'data' => ['models' => $retiredModels],
        ]);
        $recipient = (new AnonymousNotifiable)->route('mail', 'recipient@example.test');
        $job = unserialize(serialize(new SendQueuedNotifications($recipient, $notification, ['mail'])));

        expect($job->notification->data['data']['models'][0])->toBeInstanceOf(__PHP_Incomplete_Class::class);

        $job->handle(app(ChannelManager::class));

        expect(Mail::mailer('array')->getSymfonyTransport()->messages())->toHaveCount(0);
    })->with([
        'mails.admin.confirmTutoringUser',
        'mails.admin.informTutoringUserIsConfirmed',
        'mails.homepage.offerCreatedOrUpdated',
        'mails.tutoring.offerRequest',
        'mails.tutoring.offerRequestStorno',
        'mails.tutoring.offerDeleted',
        'mails.tutoring.offerConfirmedOrRefused',
    ]);

    it('still delivers serialized shared login code mail jobs', function () {
        config(['mail.default' => 'array', 'schooltool.preview.instance' => false]);
        Mail::purge('array');

        $notification = new StandardEmail([
            'from_address' => 'sender@example.test',
            'from_name' => 'School',
            'subject' => 'Login code',
            'markdown' => 'mails.homepage.sendCode',
            'token_2fa' => '123456',
            'token-expire-time' => 15,
        ]);
        $recipient = (new AnonymousNotifiable)->route('mail', 'recipient@example.test');
        $job = unserialize(serialize(new SendQueuedNotifications($recipient, $notification, ['mail'])));

        $job->handle(app(ChannelManager::class));

        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        expect($messages)->toHaveCount(1)
            ->and($messages->first()->getOriginalMessage()->getSubject())->toBe('Login code')
            ->and($messages->first()->getOriginalMessage()->getHtmlBody())->toContain('123456');
    });

    it('can be instantiated with required data', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
        ];

        $notification = new StandardEmail($data);

        expect($notification)->toBeInstanceOf(StandardEmail::class)
            ->and($notification)->toBeInstanceOf(ShouldBeEncrypted::class)
            ->and($notification->data)->toBe($data)
            ->and($notification->attachments)->toBeNull();
    });

    it('can be instantiated with attachments', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
        ];
        $attachments = [$this->testFilePath];

        $notification = new StandardEmail($data, $attachments);

        expect($notification->attachments)->toBe($attachments);
    });

    it('attaches sensitive in-memory data without writing a file', function () {
        $notification = new StandardEmail([
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
        ], [
            'data' => 'pdf-content',
            'name' => 'mandate.pdf',
            'options' => ['mime' => 'application/pdf'],
        ]);

        $mailMessage = $notification->toMail(User::factory()->make());

        expect($mailMessage->rawAttachments)->toBe([[
            'data' => 'pdf-content',
            'name' => 'mandate.pdf',
            'options' => ['mime' => 'application/pdf'],
        ]]);
    });

    it('uses mail channel', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
        ];

        $notification = new StandardEmail($data);
        $user = User::factory()->make();

        $channels = $notification->via($user);

        expect($channels)->toBe(['mail']);
    });

    it('creates mail message with correct data', function () {
        $data = [
            'from_address' => 'sender@example.com',
            'from_name' => 'John Doe',
            'subject' => 'Welcome to SchoolTool',
            'markdown' => 'mail.welcome',
        ];

        $notification = new StandardEmail($data);
        $user = User::factory()->make(['email' => 'user@example.com']);

        $mailMessage = $notification->toMail($user);

        expect($mailMessage)->toBeInstanceOf(MailMessage::class)
            ->and($mailMessage->subject)->toBe('Welcome to SchoolTool')
            ->and($mailMessage->markdown)->toBe('mail.welcome')
            ->and($mailMessage->viewData)->toHaveKey('data')
            ->and($mailMessage->viewData['data'])->toBe($data);
    });

    it('includes logo in markdown data when provided', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
            'logo' => 'path/to/logo.png',
        ];

        $notification = new StandardEmail($data);
        $user = User::factory()->make();

        $mailMessage = $notification->toMail($user);

        expect($mailMessage->viewData['logo'])->toBe('path/to/logo.png');
    });

    it('renders the SEPA mail markdown view with a logo', function () {
        $markdown = new Markdown(app('view'), [
            'paths' => [resource_path('views/vendor/mail')],
        ]);

        $html = $markdown->render('spa::mails.homepage.sendSepaMandate', [
            'data' => [
                'subject' => 'SEPA-Lastschriftmandat als PDF',
                'from_name' => 'Test School',
            ],
            'logo' => 'https://example.com/logo.png',
        ])->toHtml();

        expect($html)
            ->toContain('SEPA-Lastschriftmandat als PDF')
            ->toContain('bestätigte SEPA-Lastschriftmandat als PDF');
    });

    it('handles single attachment as string', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
        ];

        $notification = new StandardEmail($data, $this->testFilePath);
        $user = User::factory()->make();

        $mailMessage = $notification->toMail($user);

        expect($mailMessage->attachments)->toHaveCount(1)
            ->and($mailMessage->attachments[0]['file'])->toBe($this->testFilePath);
    });

    it('handles multiple attachments as array', function () {
        // Create second test file
        $testFile2 = storage_path('app/test-notifications/test-attachment-2.txt');
        file_put_contents($testFile2, 'Second test file');

        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
        ];

        $notification = new StandardEmail($data, [$this->testFilePath, $testFile2]);
        $user = User::factory()->make();

        $mailMessage = $notification->toMail($user);

        expect($mailMessage->attachments)->toHaveCount(2);

        // Cleanup second file
        @unlink($testFile2);
    });

    it('ignores non-existent attachment files', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
        ];

        $notification = new StandardEmail($data, 'path/to/nonexistent/file.txt');
        $user = User::factory()->make();

        $mailMessage = $notification->toMail($user);

        expect($mailMessage->attachments)->toHaveCount(0);
    });

    it('includes mime type for attachments', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
        ];

        $notification = new StandardEmail($data, $this->testFilePath);
        $user = User::factory()->make();

        $mailMessage = $notification->toMail($user);

        expect($mailMessage->attachments[0]['options']['mime'])->not->toBeNull();
    });

    it('implements ShouldQueue interface', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test',
            'subject' => 'Test',
            'markdown' => 'mail.test',
        ];

        $notification = new StandardEmail($data);

        expect($notification)->toBeInstanceOf(ShouldQueue::class);
    });

    it('returns empty array from toArray method', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test',
            'subject' => 'Test',
            'markdown' => 'mail.test',
        ];

        $notification = new StandardEmail($data);
        $user = User::factory()->make();

        expect($notification->toArray($user))->toBe([]);
    });
});

describe('StandardEmailWithAttachment Notification', function () {
    it('can be instantiated with required data', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
        ];

        $notification = new StandardEmailWithAttachment($data);

        expect($notification)->toBeInstanceOf(StandardEmailWithAttachment::class)
            ->and($notification->data)->toBe($data)
            ->and($notification->attachments)->toBeNull();
    });

    it('can be instantiated with attachments', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
        ];
        $attachments = [$this->testFilePath];

        $notification = new StandardEmailWithAttachment($data, $attachments);

        expect($notification->attachments)->toBe($attachments);
    });

    it('uses mail channel', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
        ];

        $notification = new StandardEmailWithAttachment($data);
        $user = User::factory()->make();

        $channels = $notification->via($user);

        expect($channels)->toBe(['mail']);
    });

    it('creates mail message with correct data', function () {
        $data = [
            'from_address' => 'sender@example.com',
            'from_name' => 'Jane Smith',
            'subject' => 'Your Report',
            'markdown' => 'mail.report',
        ];

        $notification = new StandardEmailWithAttachment($data);
        $user = User::factory()->make(['email' => 'user@example.com']);

        $mailMessage = $notification->toMail($user);

        expect($mailMessage)->toBeInstanceOf(MailMessage::class)
            ->and($mailMessage->subject)->toBe('Your Report')
            ->and($mailMessage->markdown)->toBe('mail.report')
            ->and($mailMessage->viewData)->toHaveKey('data')
            ->and($mailMessage->viewData['data'])->toBe($data);
    });

    it('includes logo in markdown data when provided', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
            'logo' => 'path/to/logo.png',
        ];

        $notification = new StandardEmailWithAttachment($data);
        $user = User::factory()->make();

        $mailMessage = $notification->toMail($user);

        expect($mailMessage->viewData['logo'])->toBe('path/to/logo.png');
    });

    it('handles single attachment as string', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
        ];

        $notification = new StandardEmailWithAttachment($data, $this->testFilePath);
        $user = User::factory()->make();

        $mailMessage = $notification->toMail($user);

        expect($mailMessage->attachments)->toHaveCount(1)
            ->and($mailMessage->attachments[0]['file'])->toBe($this->testFilePath);
    });

    it('handles multiple attachments as array', function () {
        // Create second test file
        $testFile2 = storage_path('app/test-notifications/test-attachment-2.txt');
        file_put_contents($testFile2, 'Second test file');

        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
        ];

        $notification = new StandardEmailWithAttachment($data, [$this->testFilePath, $testFile2]);
        $user = User::factory()->make();

        $mailMessage = $notification->toMail($user);

        expect($mailMessage->attachments)->toHaveCount(2);

        // Cleanup second file
        @unlink($testFile2);
    });

    it('ignores non-existent attachment files', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
        ];

        $notification = new StandardEmailWithAttachment($data, 'path/to/nonexistent/file.txt');
        $user = User::factory()->make();

        $mailMessage = $notification->toMail($user);

        expect($mailMessage->attachments)->toHaveCount(0);
    });

    it('implements ShouldQueue interface', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test',
            'subject' => 'Test',
            'markdown' => 'mail.test',
        ];

        $notification = new StandardEmailWithAttachment($data);

        expect($notification)->toBeInstanceOf(ShouldQueue::class);
    });

    it('returns empty array from toArray method', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test',
            'subject' => 'Test',
            'markdown' => 'mail.test',
        ];

        $notification = new StandardEmailWithAttachment($data);
        $user = User::factory()->make();

        expect($notification->toArray($user))->toBe([]);
    });

    it('does not include attachment options in StandardEmailWithAttachment', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
        ];

        $notification = new StandardEmailWithAttachment($data, $this->testFilePath);
        $user = User::factory()->make();

        $mailMessage = $notification->toMail($user);

        // StandardEmailWithAttachment uses simpler attach() without options
        expect($mailMessage->attachments[0]['file'])->toBe($this->testFilePath)
            ->and(isset($mailMessage->attachments[0]['options']['as']))->toBeFalse();
    });
});

describe('Notification Integration Tests', function () {
    it('both notifications extend base Notification class', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test',
            'subject' => 'Test',
            'markdown' => 'mail.test',
        ];

        $standard = new StandardEmail($data);
        $withAttachment = new StandardEmailWithAttachment($data);

        expect($standard)->toBeInstanceOf(Illuminate\Notifications\Notification::class)
            ->and($withAttachment)->toBeInstanceOf(Illuminate\Notifications\Notification::class);
    });

    it('both notifications use Queueable trait', function () {
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test',
            'subject' => 'Test',
            'markdown' => 'mail.test',
        ];

        $standard = new StandardEmail($data);
        $withAttachment = new StandardEmailWithAttachment($data);

        // Check that methods from Queueable trait are available
        expect(method_exists($standard, 'onQueue'))->toBeTrue()
            ->and(method_exists($withAttachment, 'onQueue'))->toBeTrue();
    });

    it('can send notifications to user', function () {
        Notification::fake();

        $user = User::factory()->make();
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
        ];

        $user->notify(new StandardEmail($data));

        Notification::assertSentTo($user, StandardEmail::class);
    });

    it('can send notifications with attachments to user', function () {
        Notification::fake();

        $user = User::factory()->make();
        $data = [
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'markdown' => 'mail.test',
        ];

        $user->notify(new StandardEmailWithAttachment($data, $this->testFilePath));

        Notification::assertSentTo($user, StandardEmailWithAttachment::class, function ($notification) {
            return $notification->attachments !== null;
        });
    });
});
