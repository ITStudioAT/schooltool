<?php

use App\Services\FeaturePreviewMailService;
use App\Services\FeaturePreviewSmtpTransport;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\Auth\PlainAuthenticator;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\AbstractStream;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config([
        'schooltool.preview.instance' => true,
        'schooltool.preview.url' => 'https://preview.example.test',
        'schooltool.preview.live_url' => 'https://live.example.test',
        'schooltool.preview.expected_host' => 'preview.example.test',
        'app.url' => 'https://preview.example.test',
        'mail.default' => 'preview_smtp_test',
        'mail.mailers.preview_smtp_test' => [
            'transport' => 'smtp',
            'scheme' => 'smtp',
            'host' => 'smtp.example.test',
            'port' => 587,
            'username' => 'synthetic-preview-user',
            'password' => 'synthetic-preview-password',
            'require_tls' => true,
            'timeout' => 10,
        ],
    ]);
});

it('resolves mandatory verified SMTP without connecting to the server', function (): void {
    $transport = Mail::mailer()->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(EsmtpTransport::class)
        ->and($transport->isTlsRequired())->toBeTrue()
        ->and($transport->isAutoTls())->toBeTrue()
        ->and($transport->getStream()->getStreamOptions()['ssl']['verify_peer'] ?? true)->toBeTrue()
        ->and($transport->getStream()->getStreamOptions()['ssl']['verify_peer_name'] ?? true)->toBeTrue()
        ->and(app(FeaturePreviewMailService::class)->configurationIsSafe())->toBeTrue()
        ->and(Mail::mailer()->getSymfonyTransport())->toBeInstanceOf(FeaturePreviewSmtpTransport::class);
});

it('rejects unsafe effective SMTP configuration including URL overrides', function (array $overrides): void {
    config(['mail.mailers.preview_smtp_test' => array_replace(config('mail.mailers.preview_smtp_test'), $overrides)]);

    expect(app(FeaturePreviewMailService::class)->configurationIsSafe())->toBeFalse();
})->with([
    'optional STARTTLS' => [['require_tls' => false]],
    'disabled STARTTLS' => [['auto_tls' => false]],
    'disabled certificate verification' => [['verify_peer' => false]],
    'URL disables required TLS' => [['url' => 'smtp://smtp.example.test:587?require_tls=false']],
    'URL disables automatic TLS' => [['url' => 'smtp://smtp.example.test:587?auto_tls=false']],
    'URL disables certificate verification' => [['url' => 'smtp://smtp.example.test:587?verify_peer=0']],
    'URL selects unencrypted transport' => [['url' => 'log://default']],
    'malformed URL' => [['url' => 'smtp://smtp.example.test:invalid']],
    'missing username' => [['username' => null]],
    'missing password' => [['password' => null]],
    'invalid hostname' => [['host' => 'smtp.example.test/path']],
    'invalid port' => [['port' => 70000]],
    'unbounded timeout' => [['timeout' => 0]],
]);

it('accepts implicit TLS with certificate verification', function (): void {
    config([
        'mail.mailers.preview_smtp_test.scheme' => 'smtps',
        'mail.mailers.preview_smtp_test.port' => 465,
        'mail.mailers.preview_smtp_test.require_tls' => false,
        'mail.mailers.preview_smtp_test.auto_tls' => false,
    ]);

    expect(Mail::mailer()->getSymfonyTransport()->getStream()->isTLS())->toBeTrue()
        ->and(app(FeaturePreviewMailService::class)->configurationIsSafe())->toBeTrue();
});

it('detects weakened certificate options on an already resolved transport', function (array $options): void {
    $transport = Mail::mailer()->getSymfonyTransport();
    $transport->getStream()->setStreamOptions(['ssl' => $options]);

    expect(app(FeaturePreviewMailService::class)->configurationIsSafe())->toBeFalse();
})->with([
    'unverified certificate' => [['verify_peer' => false]],
    'unverified hostname' => [['verify_peer_name' => false]],
    'self signed certificate' => [['allow_self_signed' => true]],
]);

it('allows the array transport only inside the test runtime', function (): void {
    config(['mail.mailers.preview_smtp_test' => ['transport' => 'array']]);
    expect(app(FeaturePreviewMailService::class)->configurationIsSafe())->toBeTrue();

    $this->app['env'] = 'production';
    expect(app(FeaturePreviewMailService::class)->configurationIsSafe())->toBeFalse();
});

it('requires TLS by default only for the preview instance', function (?string $flag, bool $expected): void {
    $name = 'SCHOOLTOOL_PREVIEW_INSTANCE';
    $previousEnv = $_ENV[$name] ?? null;
    $previousServer = $_SERVER[$name] ?? null;
    $previousProcess = getenv($name);
    if ($flag === null) {
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($name);
    } else {
        $_ENV[$name] = $_SERVER[$name] = $flag;
        putenv("{$name}={$flag}");
    }

    try {
        $configuration = require config_path('mail.php');
        $fixture = config('mail.mailers.preview_smtp_test');
        $fixture['require_tls'] = $configuration['mailers']['smtp']['require_tls'];
        config(['mail.mailers.preview_smtp_test' => $fixture]);

        expect(Mail::mailer()->getSymfonyTransport()->isTlsRequired())->toBe($expected);
    } finally {
        if ($previousEnv === null) {
            unset($_ENV[$name]);
        } else {
            $_ENV[$name] = $previousEnv;
        }
        if ($previousServer === null) {
            unset($_SERVER[$name]);
        } else {
            $_SERVER[$name] = $previousServer;
        }
        putenv($previousProcess === false ? $name : "{$name}={$previousProcess}");
    }
})->with([
    'main default' => [null, false],
    'main explicit' => ['false', false],
    'preview' => ['true', true],
    'preview shell value' => ['yes', true],
]);

it('requires a successful simulated TLS handshake before transmitting authentication or message data', function (array $responses, bool $startTlsSucceeds, bool $canSend): void {
    $stream = new class($responses, $startTlsSucceeds) extends AbstractStream
    {
        public array $writes = [];

        public array $unencryptedWrites = [];

        private bool $tlsActive = false;

        public function __construct(private array $responses, private bool $startTlsSucceeds) {}

        public function initialize(): void {}

        public function write(string $bytes, bool $debug = true): void
        {
            $this->writes[] = $bytes;
            if (! $this->tlsActive) {
                $this->unencryptedWrites[] = $bytes;
            }
        }

        public function flush(): void {}

        public function readLine(): string
        {
            return array_shift($this->responses) ?? '';
        }

        public function isTLS(): bool
        {
            return false;
        }

        public function startTLS(): bool
        {
            return $this->tlsActive = $this->startTlsSucceeds;
        }

        protected function getReadConnectionDescription(): string
        {
            return 'simulated preview SMTP';
        }
    };
    $transport = new class($stream) extends FeaturePreviewSmtpTransport
    {
        public function __construct(AbstractStream $stream)
        {
            SmtpTransport::__construct($stream);
            $this->setRequireTls(true);
            $this->setAuthenticators([new PlainAuthenticator]);
            $this->setUsername('synthetic-preview-user');
            $this->setPassword('synthetic-preview-password');
        }
    };
    $message = (new Email)->from('sender@example.test')->to('recipient@example.test')->text('synthetic-private-code');

    if ($canSend) {
        expect($transport->send($message))->not->toBeNull()
            ->and(implode('', $stream->writes))->toContain('AUTH PLAIN ', 'synthetic-private-code');
        $transport->stop();
    } else {
        expect(fn () => $transport->send($message))->toThrow(TransportException::class)
            ->and(implode('', $stream->writes))->not->toContain('synthetic-private-code');
    }

    foreach ($stream->unencryptedWrites as $write) {
        expect($write)->not->toMatch('/^(?:AUTH |MAIL FROM:|RCPT TO:|DATA)/')
            ->not->toContain('synthetic-private-code', 'recipient@example.test');
    }
})->with([
    'rejected EHLO followed by accepted legacy HELO' => [["220 Ready\r\n", "500 EHLO unsupported\r\n", "250 Hello\r\n"], true, false],
    'no STARTTLS advertised' => [["220 Ready\r\n", "250 Hello\r\n"], true, false],
    'STARTTLS handshake fails' => [["220 Ready\r\n", "250-Hello\r\n", "250 STARTTLS\r\n", "220 Start TLS\r\n"], false, false],
    'successful STARTTLS with authenticated delivery' => [[
        "220 Ready\r\n", "250-Hello\r\n", "250 STARTTLS\r\n", "220 Start TLS\r\n", "250-Hello\r\n", "250 AUTH PLAIN\r\n",
        "235 Authenticated\r\n", "250 Sender accepted\r\n", "250 Recipient accepted\r\n", "354 Send data\r\n", "250 Queued\r\n", "221 Bye\r\n",
    ], true, true],
]);
