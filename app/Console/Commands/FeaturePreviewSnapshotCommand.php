<?php

namespace App\Console\Commands;

use App\Services\FeaturePreviewSnapshotService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

#[Signature('preview:snapshot {action : status, key:generate, export, delete, receive, import, restore, checkpoint, assert-current, assert-plan or activate} {--feature=} {--recipient=} {--artifact=} {--sha256=} {--source=} {--state-token=} {--path-only : Print only the verified received path} {--replace : Explicitly confirmed replacement of preview data}')]
#[Description('Prepare encrypted live-data snapshots and safely install them in the isolated preview')]
class FeaturePreviewSnapshotCommand extends Command
{
    public function handle(FeaturePreviewSnapshotService $snapshots): int
    {
        try {
            $feature = (string) $this->option('feature');
            $result = match ($this->argument('action')) {
                'status' => $snapshots->status($feature),
                'key:generate' => ['public_key' => $snapshots->generateKey()],
                'export' => $snapshots->export($feature, (string) $this->option('recipient'), (string) $this->option('artifact')),
                'receive' => $snapshots->receive((string) $this->option('artifact'), (string) $this->option('sha256')),
                'import' => $snapshots->import($feature, (string) $this->option('artifact'), (string) $this->option('sha256'), (bool) $this->option('replace')),
                'delete' => $this->completeOperation(fn () => $snapshots->deleteExport((string) $this->option('artifact'))),
                'assert-current' => $this->completeOperation(fn () => $snapshots->assertCurrent($feature)),
                'assert-plan' => $this->completeOperation(fn () => $snapshots->assertPlan($feature, (string) $this->option('state-token'))),
                'activate' => $this->completeOperation(fn () => $snapshots->activate($feature, (string) $this->option('source'))),
                'restore' => $this->completeOperation(fn () => $snapshots->restore((bool) $this->option('replace'))),
                'checkpoint' => $this->completeOperation(fn () => $snapshots->checkpoint($feature)),
                default => throw new RuntimeException('Unknown preview snapshot action.'),
            };
            $this->line($this->argument('action') === 'receive' && $this->option('path-only')
                ? $result['path']
                : json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $message = get_class($exception) === RuntimeException::class
                ? $exception->getMessage()
                : 'Preview snapshot operation failed safely. Check the private database, key and filesystem configuration.';
            $this->getOutput()->getErrorStyle()->writeln($message);

            return self::FAILURE;
        }
    }

    /** @return array{ok: bool} */
    private function completeOperation(callable $operation): array
    {
        $operation();

        return ['ok' => true];
    }
}
