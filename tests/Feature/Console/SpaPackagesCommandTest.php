<?php

namespace Tests\Feature\Console;

it('refuses to run the deprecated spa packages workflow', function (): void {
    $this->artisan('spa:packages')
        ->expectsOutputToContain('spa:packages is deprecated; use php artisan app:update instead.')
        ->assertExitCode(1);
});
