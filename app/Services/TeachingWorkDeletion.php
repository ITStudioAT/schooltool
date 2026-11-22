<?php

namespace App\Services;

use App\Exceptions\TeachingWorkDeletionConflict;
use App\Models\McTest;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\MultipleChoice\McTestExchange;
use App\Services\MultipleChoice\McTestFolder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TeachingWorkDeletion
{
    /** @return array{tests: Collection<int, McTest>, bindings: array, history: array, targets: ?array} */
    public function prepare(TeachingCourseWork $work, User $actor, bool $createReceipt): array
    {
        $tests = $this->testsForWork($work);
        $folderService = app(McTestFolder::class);
        $bindings = [];
        $history = [];
        foreach ($tests as $test) {
            $binding = $folderService->bindingForDeletion($test, $actor);
            if ($binding !== null) {
                $bindings[] = $binding;
            }
            array_push($history, ...app(McTestExchange::class)->historyForDeletion($test, $actor));
        }
        $receipt = $this->receipt($work);
        $targets = app(DropboxModuleLinks::class)->deletionTargets($work, $actor, $receipt, preferInitializedFolder: true);
        if ($receipt !== null) {
            abort_unless($targets && (int) $targets['connection']->id === $receipt['connection_id']
                && hash_equals($receipt['account_hash'], hash('sha256', $targets['connection']->account_id)),
                409, 'Die Dropbox-Verbindung hat sich seit dem Löschversuch geändert. Bitte die Zuordnung prüfen.');
            $planned = array_column($receipt['folders'], 'path', 'id');
            $current = array_column($targets['folders'], 'path', 'id');
            foreach ($current as $id => $path) {
                abort_unless(isset($planned[$id]) && $planned[$id] === $path, 409,
                    'Die Prüfungsordner haben sich seit dem Löschversuch geändert. Bitte die Zuordnung prüfen.');
            }
            foreach (array_diff_key($planned, $current) as $id => $path) {
                try {
                    $folder = app(DropboxWorkImport::class)->findFolderById($targets['connection'], $id);
                } catch (ValidationException $exception) {
                    if (! isset($exception->errors()['dropbox'])) {
                        throw $exception;
                    }
                    throw new TeachingWorkDeletionConflict('Der zuvor bestätigte Prüfungsordner konnte nicht geprüft werden. '.collect($exception->errors()['dropbox'])->first());
                }
                abort_unless($folder === null, 409,
                    'Ein zuvor bestätigter Prüfungsordner wurde verändert oder verschoben. Bitte die Zuordnung prüfen.');
            }
        } elseif ($targets && $targets['folders'] !== []) {
            abort_unless($createReceipt, 409, 'Die Prüfungsordner haben sich während des Löschens geändert. Bitte erneut versuchen.');
            $payload = ['work_id' => (int) $work->id, 'fingerprint' => $this->fingerprint($work),
                'connection_id' => (int) $targets['connection']->id, 'account_hash' => hash('sha256', $targets['connection']->account_id),
                'folders' => $targets['folders']];
            if (isset($targets['initialized_folder'])) {
                $payload['initialized_folder'] = $targets['initialized_folder'];
            }
            $payload['signature'] = $this->sign($payload);
            $status = $work->status ?? [];
            $status['deletion_receipt'] = $payload;
            $work->update(['status' => $status]);
        }

        return ['tests' => $tests, 'bindings' => $bindings, 'history' => $history, 'targets' => $targets];
    }

    /** @return array<string, mixed>|null */
    private function receipt(TeachingCourseWork $work): ?array
    {
        $receipt = $work->status['deletion_receipt'] ?? null;
        if ($receipt === null) {
            return null;
        }
        abort_unless(is_array($receipt) && is_string($receipt['signature'] ?? null), 409, 'Der gespeicherte Löschstand ist ungültig.');
        $signature = $receipt['signature'];
        unset($receipt['signature']);
        abort_unless(hash_equals($this->sign($receipt), $signature)
            && ($receipt['work_id'] ?? null) === (int) $work->id
            && is_string($receipt['fingerprint'] ?? null) && hash_equals($receipt['fingerprint'], $this->fingerprint($work))
            && is_int($receipt['connection_id'] ?? null) && is_string($receipt['account_hash'] ?? null)
            && is_array($receipt['folders'] ?? null), 409,
            'Die Arbeit oder ihr gespeicherter Löschstand wurde seit dem letzten Versuch geändert. Bitte die Zuordnung prüfen.');

        return $receipt;
    }

    private function fingerprint(TeachingCourseWork $work): string
    {
        $attributes = $work->getAttributes();
        unset($attributes['status'], $attributes['updated_at']);
        $status = $work->status ?? [];
        unset($status['deletion_receipt']);

        return hash('sha256', json_encode($this->canonical([$attributes, $status]), JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $payload */
    private function sign(array $payload): string
    {
        return hash_hmac('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonical($item);
        }

        return $value;
    }

    /** @return Collection<int, McTest> */
    public function testsForWork(TeachingCourseWork $work): Collection
    {
        $bindings = $work->status['multiple_choice_tests'] ?? [];
        if (! is_array($bindings)) {
            throw new TeachingWorkDeletionConflict('Die MC-Zuordnung ist unvollständig.');
        }
        $ids = [];
        foreach ($bindings as $binding) {
            if (! is_array($binding)
                || filter_var($binding['work_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== (int) $work->id
                || filter_var($binding['test_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new TeachingWorkDeletionConflict('Die MC-Zuordnung ist nicht eindeutig.');
            }
            $ids[] = (int) $binding['test_id'];
        }

        $tests = McTest::query()->whereIn('id', array_unique($ids))->orderBy('id')->lockForUpdate()->get();
        foreach ($tests as $test) {
            if ((int) $test->school_id !== (int) $work->teachingCourse->school_id
                || (int) $test->schoolyear_id !== (int) $work->teachingCourse->schoolyear_id
                || (int) $test->owner_id !== (int) $work->teachingCourse->user_id) {
                throw new TeachingWorkDeletionConflict('Ein zugeordneter MC-Test gehört nicht eindeutig zu dieser Arbeit.');
            }
            $shared = TeachingCourseWork::query()->whereKeyNot($work->id)
                ->where(function ($query) use ($test): void {
                    $query->whereRaw('JSON_CONTAINS(JSON_EXTRACT(status, ?), ?)', ['$.multiple_choice_tests.*.test_id', json_encode((int) $test->id, JSON_THROW_ON_ERROR)])
                        ->orWhereRaw('JSON_CONTAINS(JSON_EXTRACT(status, ?), ?)', ['$.multiple_choice_tests.*.test_id', json_encode((string) $test->id, JSON_THROW_ON_ERROR)]);
                })->lockForUpdate()->exists();
            if ($shared) {
                throw new TeachingWorkDeletionConflict('Ein zugeordneter MC-Test wird auch von einer anderen Arbeit verwendet.');
            }
        }

        return $tests;
    }
}
