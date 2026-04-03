<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

if (! isset($_ENV['APP_ENV']) || $_ENV['APP_ENV'] === '') {
    $_ENV['APP_ENV'] = 'e2e';
    $_SERVER['APP_ENV'] = 'e2e';
    putenv('APP_ENV=e2e');
}

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$email = $argv[1] ?? null;
$field = $argv[2] ?? 'token_2fa';

if (! $email) {
    fwrite(STDERR, 'Missing required argument: email'.PHP_EOL);
    exit(1);
}

if (! in_array($field, ['token_2fa', 'token_2fa_2'], true)) {
    fwrite(STDERR, "Unsupported token field: {$field}".PHP_EOL);
    exit(1);
}

$user = User::query()->where('email', $email)->first();
if (! $user) {
    fwrite(STDERR, "No user found for email: {$email}".PHP_EOL);
    exit(2);
}

$token = (string) ($user->{$field} ?? '');
if ($token === '') {
    fwrite(STDERR, "No token present for {$email} ({$field})".PHP_EOL);
    exit(3);
}

echo $token;
