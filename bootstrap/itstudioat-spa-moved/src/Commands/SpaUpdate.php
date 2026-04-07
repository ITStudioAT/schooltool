<?php

namespace Itstudioat\Spa\Commands;

use Illuminate\Console\Command;

class SpaUpdate extends Command
{
    protected $signature = 'spa:update';

    protected $description = 'Deprecated legacy SPA package command';

    public function handle(): int
    {
        $this->error('❌ spa:update is deprecated; use php artisan app:update instead.');

        return self::FAILURE;
    }
}
