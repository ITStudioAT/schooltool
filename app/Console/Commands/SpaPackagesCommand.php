<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('spa:packages')]
#[Description('Deprecated legacy SPA package command')]
class SpaPackagesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->error('spa:packages is deprecated; use php artisan app:update instead.');

        return self::FAILURE;
    }
}
