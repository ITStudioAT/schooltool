<?php

namespace Itstudioat\Spa\Commands;

use Illuminate\Console\Command;

class SpaComplete extends Command
{
    protected $signature = 'spa:complete';

    protected $description = 'Deprecated legacy SPA package command';

    public function handle(): int
    {
        $this->error('❌ spa:complete is deprecated; use php artisan app:update instead.');

        return self::FAILURE;
    }
}
