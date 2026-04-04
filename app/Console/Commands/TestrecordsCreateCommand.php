<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestrecordsCreateCommand extends Command
{
    protected $signature = 'testrecords:create {--model=} {--count=1000}';

    protected $description = 'Create test records for a given Eloquent model using its factory';

    public function handle(): int
    {
        $modelOption = $this->option('model');
        $count = (int) $this->option('count');

        if (! $modelOption) {
            $this->error('❌  You must specify a model using --model=ModelName');

            return self::FAILURE;
        }

        // Support both "App\Models\School" and "School"
        $modelClass = Str::startsWith($modelOption, 'App\\')
            ? $modelOption
            : "App\\Models\\{$modelOption}";

        if (! class_exists($modelClass)) {
            $this->error("❌  Model class [$modelClass] does not exist.");

            return self::FAILURE;
        }

        if (! method_exists($modelClass, 'factory')) {
            $this->error("❌  Model [$modelClass] does not have a factory defined.");

            return self::FAILURE;
        }

        $this->info("Creating {$count} fake {$modelOption} records...");

        $modelClass::factory()->count($count)->create();

        $this->info("✅  Successfully created {$count} {$modelOption} records!");

        return self::SUCCESS;
    }
}
