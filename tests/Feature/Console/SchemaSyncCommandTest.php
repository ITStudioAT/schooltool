<?php

namespace App\Console\Commands {
    use Doctrine\DBAL\Schema\Comparator;

    function class_exists(string $class): bool
    {
        if ($class === Comparator::class) {
            return $GLOBALS['schema_sync_dbal_available'] ?? false;
        }

        return \class_exists($class);
    }
}

namespace Tests\Feature\Console {

    it('fails when doctrine dbal is missing', function () {
        $GLOBALS['schema_sync_dbal_available'] = false;

        $this->artisan('schema:sync')
            ->expectsOutputToContain('Missing dependency: doctrine/dbal')
            ->assertExitCode(1);
    });

    it('fails when target connection is unknown', function () {
        $GLOBALS['schema_sync_dbal_available'] = true;

        $this->artisan('schema:sync --connection=unknown_connection')
            ->expectsOutputToContain('Unknown connection')
            ->assertExitCode(1);
    });
}
