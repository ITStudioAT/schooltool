<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('spa:update')]
#[Description('Deprecated legacy SPA package command')]
class SpaUpdateCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->error('spa:update is deprecated; use php artisan app:update instead.');

        return self::FAILURE;
    }
}
