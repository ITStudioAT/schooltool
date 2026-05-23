<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('spa:complete')]
#[Description('Deprecated legacy SPA package command')]
class SpaCompleteCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->error('spa:complete is deprecated; use php artisan app:update instead.');

        return self::FAILURE;
    }
}
