<?php

namespace Tests\Feature\Console;

dataset('deprecated_spa_commands', [
    'spa:complete' => [
        'spa:complete',
        'spa:complete is deprecated; use php artisan app:update instead.',
    ],
    'spa:update' => [
        'spa:update',
        'spa:update is deprecated; use php artisan app:update instead.',
    ],
]);

it('refuses to run deprecated spa workflow commands', function (string $command, string $message): void {
    $this->artisan($command)
        ->expectsOutputToContain($message)
        ->assertExitCode(1);
})->with('deprecated_spa_commands');
