<?php

namespace App\Jobs;

use App\Http\Controllers\Admin\Materials\MaterialShareController;
use App\Services\Materials\MaterialInboxImportStatusStore;
use App\Services\Materials\MaterialKeywordService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMaterialInboxInsertJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $authUserId,
        public string $operationId,
        public array $payload,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        MaterialShareController $controller,
        MaterialInboxImportStatusStore $statusStore,
        MaterialKeywordService $keywordService,
    ): void {
        $statusStore->markRunning($this->authUserId, $this->operationId);

        try {
            $kind = (string) ($this->payload['kind'] ?? '');

            $result = match ($kind) {
                'workspace_tree' => $controller->runQueuedInboxWorkspaceTreeInsert(
                    $this->authUserId,
                    (int) ($this->payload['rule_id'] ?? 0),
                    $keywordService,
                ),
                'subject_tree' => $controller->runQueuedInboxSubjectTreeInsert(
                    $this->authUserId,
                    (int) ($this->payload['rule_id'] ?? 0),
                    (int) ($this->payload['subject_id'] ?? 0),
                    $keywordService,
                ),
                'topic_tree' => $controller->runQueuedInboxTopicTreeInsert(
                    $this->authUserId,
                    (int) ($this->payload['rule_id'] ?? 0),
                    (int) ($this->payload['topic_id'] ?? 0),
                    (int) ($this->payload['target_subject_id'] ?? 0),
                    $keywordService,
                ),
                'unit_tree' => $controller->runQueuedInboxUnitTreeInsert(
                    $this->authUserId,
                    (int) ($this->payload['rule_id'] ?? 0),
                    (int) ($this->payload['unit_id'] ?? 0),
                    (int) ($this->payload['target_topic_id'] ?? 0),
                    $keywordService,
                ),
                default => throw new \InvalidArgumentException('Unbekannter Einordnen-Auftrag.'),
            };

            $statusStore->markCompleted(
                $this->authUserId,
                $this->operationId,
                (string) ($result['message'] ?? 'Einordnen abgeschlossen.'),
                $result,
            );
        } catch (\Throwable $throwable) {
            app(MaterialInboxImportStatusStore::class)->markFailed(
                $this->authUserId,
                $this->operationId,
                $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Einordnen konnte nicht abgeschlossen werden.',
            );

            throw $throwable;
        }
    }
}
