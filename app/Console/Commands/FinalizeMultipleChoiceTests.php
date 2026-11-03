<?php

namespace App\Console\Commands;

use App\Services\MultipleChoice\McTestService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

#[Signature('multiple-choice:finalize-expired')]
#[Description('Close and score multiple-choice attempts at their fixed server deadline')]
class FinalizeMultipleChoiceTests extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(McTestService $tests): int
    {
        if (! Schema::hasTable('mc_tests')) {
            return self::SUCCESS;
        }

        $count = $tests->finalizeExpired();
        $this->info("{$count} Versuche abgeschlossen.");

        return self::SUCCESS;
    }
}
