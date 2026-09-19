<?php

use App\Models\FeaturePreviewSetting;
use App\Models\School;
use App\Models\User;
use App\Notifications\StandardEmail;
use App\Services\FeaturePreviewMailService;
use App\Services\FeaturePreviewService;
use App\Services\UserService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    config([
        'database.default' => 'preview_mail_test',
        'database.connections.preview_mail_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'schooltool.preview.instance' => true,
        'schooltool.preview.url' => 'https://preview.example.test',
        'schooltool.preview.live_url' => 'https://live.example.test',
        'schooltool.preview.expected_host' => 'preview.example.test',
        'app.url' => 'https://preview.example.test',
        'app.key' => 'base64:'.base64_encode(str_repeat('m', 32)),
        'mail.default' => 'array',
        'mail.mailers.array' => ['transport' => 'array'],
        'mail.mailers.preview_alternative' => ['transport' => 'array'],
        'mail.from.address' => 'noreply@example.test',
        'mail.from.name' => 'SchoolTool',
        'queue.default' => 'sync',
        'cache.default' => 'array',
    ]);
    DB::purge('preview_mail_test');
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    URL::forceRootUrl('https://preview.example.test');
    URL::forceScheme('https');
    Mail::purge('array');
    Mail::purge('preview_alternative');

    Schema::create('schools', function (Blueprint $table): void {
        $table->id();
        foreach (['long_name', 'short_name', 'email', 'logo', 'color'] as $column) {
            $table->string($column)->nullable();
        }
        $table->boolean('is_selectable')->default(true);
        $table->timestamps();
    });
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('school_id');
        $table->unsignedBigInteger('schoolyear_id')->nullable();
        foreach (['last_name', 'first_name', 'phone', 'email', 'password', 'remember_token', 'token_2fa'] as $column) {
            $table->string($column)->nullable();
        }
        foreach (['email_verified_at', 'confirmed_at', 'token_2fa_expires_at'] as $column) {
            $table->timestamp($column)->nullable();
        }
        $table->boolean('is_active')->default(true);
        $table->boolean('feature_preview_allowed')->default(false);
        $table->boolean('use_school_color_for_admin_ui')->default(true);
        $table->text('tutoring_filter')->nullable();
        $table->timestamps();
    });
    (require database_path('migrations/2025_10_16_163810_create_permission_tables.php'))->up();
    Schema::table('roles', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->school = School::factory()->create();
    $this->user = User::factory()->create(['school_id' => $this->school->id, 'email' => 'tester@example.test']);
    Role::create(['name' => 'student', 'guard_name' => 'web']);
    $this->user->assignRole('student');
    $this->recipientAllowed = true;
    $this->recipientChecks = [];

    $this->partialMock(FeaturePreviewService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('authenticationRecipientAllowed')->andReturnUsing(function (User $user, string $recipient, string $kind, ?int $schoolyearId): bool {
            $this->recipientChecks[] = [$user->id, $recipient, $kind, $schoolyearId];

            return $this->recipientAllowed;
        });
    });
});

afterEach(function (): void {
    DB::purge('preview_mail_test');
    DB::purge('preview_control');
});

function previewAuthenticationNotification(string $purpose = 'login', string $kind = 'account', ?string $recipient = null): StandardEmail
{
    return (new StandardEmail([
        'from_address' => 'noreply@example.test',
        'from_name' => 'SchoolTool',
        'subject' => 'Ihr persönlicher Code',
        'markdown' => 'mails.homepage.sendCode',
        'token_2fa' => 'private-code-123456',
        'token-expire-time' => 15,
    ]))->forPreviewAuthentication(test()->user, $recipient ?? test()->user->email, $purpose, $kind, 42);
}

function previewMailTransport(string $mailer = 'array'): ArrayTransport
{
    $transport = Mail::mailer($mailer)->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);

    return $transport;
}

test('preview delivers marked authentication mail with preview subject body and links', function (string $purpose): void {
    Notification::route('mail', $this->user->email)->notify(previewAuthenticationNotification($purpose));

    $messages = previewMailTransport()->messages();
    expect($messages)->toHaveCount(1);
    $message = $messages->first()->getOriginalMessage();
    expect($message->getSubject())->toBe('[VORSCHAU] Ihr persönlicher Code')
        ->and($message->getHtmlBody())->toContain('VORSCHAU:', 'https://preview.example.test/', 'private-code-123456')
        ->and($message->getTextBody())->toContain('VORSCHAU:', 'https://preview.example.test/', 'private-code-123456')
        ->and($message->getTo()[0]->getAddress())->toBe($this->user->email)
        ->and($this->recipientChecks)->toHaveCount(2);
})->with(['login', 'password_reset']);

test('preview forwards exact account secondary and parent identity to central admission', function (string $kind): void {
    Notification::route('mail', 'parent@example.test')->notify(previewAuthenticationNotification('login', $kind, 'parent@example.test'));

    expect(previewMailTransport()->messages())->toHaveCount(1)
        ->and($this->recipientChecks)->toBe([
            [$this->user->id, 'parent@example.test', $kind, 42],
            [$this->user->id, 'parent@example.test', $kind, 42],
        ]);
})->with(['second_factor', 'teaching_parent', 'restaurant_parent']);

test('preview blocks authentication when current central admission denies the identity', function (): void {
    $this->recipientAllowed = false;
    Notification::route('mail', $this->user->email)->notify(previewAuthenticationNotification());

    expect(previewMailTransport()->messages())->toBeEmpty();
});

test('real mail delivery follows fresh live grants and revocations instead of snapshot flags', function (): void {
    (require database_path('migrations/2026_09_16_105645_create_feature_preview_settings_table.php'))->up();
    FeaturePreviewSetting::factory()->create(['enabled' => true]);
    snapshotFeaturePreviewControlForTests();
    app()->instance(FeaturePreviewService::class, new FeaturePreviewService);

    DB::connection('preview_control')->table('users')->where('id', $this->user->id)->update(['feature_preview_allowed' => true]);
    Notification::route('mail', $this->user->email)->notify(previewAuthenticationNotification());
    expect(previewMailTransport()->messages())->toHaveCount(1);

    DB::connection('preview_control')->table('users')->where('id', $this->user->id)->update(['feature_preview_allowed' => false]);
    Notification::route('mail', $this->user->email)->notify(previewAuthenticationNotification());
    expect(previewMailTransport()->messages())->toHaveCount(1);
});

test('preview blocks delivery when central authorization is unavailable', function (): void {
    $this->partialMock(FeaturePreviewService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('authenticationRecipientAllowed')->andThrow(new RuntimeException('private connection details'));
    });
    Log::spy();
    Log::shouldReceive('channel')->with('single')->andReturnSelf();

    Notification::route('mail', $this->user->email)->notify(previewAuthenticationNotification());

    expect(previewMailTransport()->messages())->toBeEmpty();
    Log::shouldHaveReceived('info')->once()->withArgs(fn (string $event, array $context): bool => $context['reason'] === 'authorization_unavailable'
        && ! str_contains(json_encode($context), 'private connection details'));
});

test('preview rechecks revocation immediately before transport', function (): void {
    Event::listen(NotificationSending::class, function (): void {
        $this->recipientAllowed = false;
    });
    Notification::route('mail', $this->user->email)->notify(previewAuthenticationNotification());

    expect(previewMailTransport()->messages())->toBeEmpty()
        ->and($this->recipientChecks)->toHaveCount(2);
});

test('preview blocks unmarked code notifications including sepa and registration', function (): void {
    $notification = previewAuthenticationNotification();
    $notification->previewAuthentication = null;
    $notification->data['subject'] = 'Code zur SEPA-Bestätigung';
    Notification::route('mail', $this->user->email)->notify($notification);

    expect(previewMailTransport()->messages())->toBeEmpty()
        ->and($this->recipientChecks)->toBeEmpty();
});

test('preview blocks ordinary mailables and explicitly selected mailers', function (string $mailer): void {
    Mail::mailer($mailer)->to('outsider@example.test')->send(new class extends Mailable
    {
        public function build(): static
        {
            return $this->subject('Private business message')->html('<p>Private business content</p>');
        }
    });

    expect(previewMailTransport($mailer)->messages())->toBeEmpty();
})->with(['array', 'preview_alternative']);

test('preview blocks marked authentication mail through an insecure named transport', function (): void {
    config(['mail.mailers.preview_insecure' => ['transport' => 'log', 'channel' => 'single']]);
    Log::spy();
    Log::shouldReceive('channel')->with('single')->andReturnSelf();

    $sent = Mail::mailer('preview_insecure')->send(['raw' => 'private-code-123456'], [
        '__laravel_notification' => StandardEmail::class,
        '__schooltool_preview_auth' => previewAuthenticationNotification()->previewAuthentication,
    ], fn ($message) => $message->to($this->user->email)->subject('Code'));

    expect($sent)->toBeNull()
        ->and($this->recipientChecks)->toBeEmpty();
    Log::shouldNotHaveReceived('debug');
    Log::shouldHaveReceived('info')->once()->withArgs(fn (string $event, array $context): bool => $context['reason'] === 'unsafe_configuration');
});

test('preview validates actual recipients after configured aliases are applied', function (): void {
    config(['schooltool.email_aliases' => ['tester@example.test' => 'actual-tester@example.test']]);
    Notification::route('mail', $this->user->email)->notify(previewAuthenticationNotification());

    $messages = previewMailTransport()->messages();
    expect($messages)->toHaveCount(1)
        ->and($messages->first()->getOriginalMessage()->getTo()[0]->getAddress())->toBe('actual-tester@example.test')
        ->and($this->recipientChecks[0][1])->toBe('tester@example.test');
});

test('preview blocks recipient substitution and extra cc bcc immediately before transport', function (string $mode): void {
    $notification = previewAuthenticationNotification();
    Mail::send(['raw' => 'private-code-123456'], [
        '__laravel_notification' => StandardEmail::class,
        '__schooltool_preview_auth' => $notification->previewAuthentication,
    ], function ($message) use ($mode): void {
        $message->to($mode === 'to' ? 'outsider@example.test' : $this->user->email)->subject('Code');
        if ($mode === 'cc' || $mode === 'bcc') {
            $message->{$mode}('outsider@example.test');
        }
    });

    expect(previewMailTransport()->messages())->toBeEmpty();
})->with(['to', 'cc', 'bcc']);

test('preview rejects wrong school metadata and authentication attachments', function (string $mode): void {
    $notification = previewAuthenticationNotification();
    if ($mode === 'school') {
        $notification->previewAuthentication['school_id'] = $this->school->id + 1;
    } else {
        $notification->attachments = ['data' => 'private attachment', 'name' => 'private.txt'];
    }
    Notification::route('mail', $this->user->email)->notify($notification);

    expect(previewMailTransport()->messages())->toBeEmpty();
})->with(['school', 'attachment']);

test('preview blocks unsafe mail configuration', function (string $key, string $value): void {
    config([$key => $value]);
    expect(app(FeaturePreviewMailService::class)->configurationIsSafe())->toBeFalse();

    Notification::route('mail', $this->user->email)->notify(previewAuthenticationNotification());

    expect(previewMailTransport()->messages())->toBeEmpty();
})->with([
    'live app url' => ['app.url', 'https://live.example.test'],
    'insecure url' => ['app.url', 'http://preview.example.test'],
    'wrong expected host' => ['schooltool.preview.expected_host', 'live.example.test'],
]);

test('preview blocks authentication mail containing live or external links', function (string $url): void {
    Mail::send(['html' => new HtmlString('<a href="'.$url.'">Login</a>')], [
        '__laravel_notification' => StandardEmail::class,
        '__schooltool_preview_auth' => previewAuthenticationNotification()->previewAuthentication,
    ], fn ($message) => $message->to($this->user->email)->subject('Code'));

    expect(previewMailTransport()->messages())->toBeEmpty();
})->with(['https://live.example.test/admin', 'https://outsider.example.test/reset', 'http://preview.example.test/admin']);

test('preview audit records only metadata without addresses codes or message content', function (): void {
    Log::spy();
    Log::shouldReceive('channel')->with('single')->andReturnSelf();
    $notification = previewAuthenticationNotification();
    $notification->previewAuthentication = null;
    Notification::route('mail', $this->user->email)->notify($notification);

    Log::shouldHaveReceived('info')->once()->withArgs(function (string $event, array $context): bool {
        $encoded = json_encode($context);

        return $event === 'feature_preview.mail_intercepted'
            && $context['reason'] === 'unmarked_message'
            && $context['recipient_count'] === 1
            && $context['recipient_hashes'] === [hash('sha256', $this->user->email)]
            && ! str_contains($encoded, $this->user->email)
            && ! str_contains($encoded, 'private-code-123456')
            && ! str_contains($encoded, 'Ihr persönlicher Code');
    });
});

test('explicit public login helper marks authentication while email change remains blocked', function (): void {
    app(UserService::class)->sendCode($this->user, 'Code zum Login', $this->user->email, 'login');
    expect(previewMailTransport()->messages())->toHaveCount(1);

    app(UserService::class)->sendEmailVerification($this->user, 'new-address@example.test');
    expect(previewMailTransport()->messages())->toHaveCount(1);
});

test('main mail delivery remains unrestricted and receives no preview decoration', function (): void {
    config(['schooltool.preview.instance' => false]);
    $notification = previewAuthenticationNotification();
    $notification->previewAuthentication = null;
    Notification::route('mail', 'ordinary@example.test')->notify($notification);
    Mail::raw('Ordinary content', fn ($message) => $message->to('outsider@example.test')->subject('Ordinary subject'));

    $messages = previewMailTransport()->messages();
    expect($messages)->toHaveCount(2)
        ->and($messages->first()->getOriginalMessage()->getSubject())->toBe('Ihr persönlicher Code')
        ->and($messages->last()->getOriginalMessage()->getSubject())->toBe('Ordinary subject')
        ->and($this->recipientChecks)->toBeEmpty();
});
