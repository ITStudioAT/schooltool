<?php

use Illuminate\Support\Facades\Artisan;

test('removed tutoring test data commands cannot seed user accounts', function (): void {
    $commands = array_keys(Artisan::all());

    expect(array_values(array_filter($commands, fn (string $command): bool => str_contains(strtolower($command), 'tutoring'))))->toBeEmpty();
});
