<?php

namespace Itstudioat\Spa\Commands;

use Illuminate\Console\Command;

class SpaPackages extends Command
{
    protected $signature = 'spa:packages';

    protected $description = 'Deprecated legacy SPA package command';

    public function handle(): int
    {
        $this->error('❌ spa:packages is deprecated; use php artisan app:update instead.');

        return self::FAILURE;
    }
}
