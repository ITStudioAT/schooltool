<?php

namespace App\Services\MultipleChoice;

use App\Models\DropboxConnection;
use App\Models\McCatalog;
use App\Models\User;
use App\Services\DropboxModuleLinks;
use App\Services\DropboxWorkImport;
use Closure;
use Illuminate\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class McCatalogTemplateSync
{
    private ?Lock $lock = null;

    public function __construct(private DropboxWorkImport $dropbox, private DropboxModuleLinks $modules,
        private McTemplateDocument $documents, private McQuestionValidator $questions) {}

    public function version(McCatalog $catalog): string
    {
        return app(McTestExchange::class)->hash([$catalog->school_id, $catalog->owner_id, $catalog->title,
            $catalog->questions, $catalog->module_sources]);
    }

    public function payload(McCatalog $catalog): array
    {
        return $catalog->withoutRelations()->toArray() + ['template_version' => $this->version($catalog),
            'template_connection' => $this->status($catalog)];
    }

    public function status(McCatalog $catalog): ?array
    {
        $state = $this->state($catalog);
        $source = $state['binding']['source'] ?? $this->source($catalog);
        if ($source === null) {
            return null;
        }

        return ['source_path' => $source['source_path'] ?? '', 'source_file_id' => $source['source_file_id'] ?? null,
            'source_revision' => $state['binding']['revision'] ?? $source['source_revision'] ?? null,
            'status' => isset($source['ambiguous']) ? 'ambiguous' : (isset($state['binding']) ? 'connected' : 'needs_confirmation'),
            'message' => isset($state['pending']) ? 'Ein unterbrochener Abgleich kann über Vorlage synchronisieren fortgesetzt werden.' : null];
    }

    /** @return array{account_id: string, source: array<string, mixed>} */
    public function confirmedBinding(McCatalog $catalog): array
    {
        $state = $this->state($catalog);
        abort_if(isset($state['pending']), 409, 'Bitte zuerst den unterbrochenen Vorlagenabgleich abschließen.');
        $binding = $state['binding'] ?? null;
        abort_unless(is_array($binding) && is_string($binding['account_id'] ?? null)
            && is_array($binding['source'] ?? null), 409,
            'Bitte zuerst die Fragenvorlage fest mit diesem Katalog verbinden.');

        return ['account_id' => $binding['account_id'], 'source' => $binding['source']];
    }

    public function serialize(McCatalog $catalog, Closure $action): mixed
    {
        if (! $catalog->exists) {
            return $action();
        }

        $this->lock = Cache::store('file')->lock('mc-template:'.$catalog->school_id.':'.$catalog->id, 180);
        try {
            return $this->lock->block(3, $action);
        } catch (LockTimeoutException) {
            abort(409, 'Dieser Katalog wird gerade bearbeitet. Bitte kurz warten und erneut versuchen.');
        } finally {
            $this->lock = null;
        }
    }

    public function assertEditable(McCatalog $catalog, ?string $expectedVersion): void
    {
        $state = $this->state($catalog);
        abort_if(isset($state['pending']), 409, 'Bitte zuerst den unterbrochenen Vorlagenabgleich abschließen.');
        if (isset($state['binding']) || $expectedVersion !== null) {
            abort_unless(is_string($expectedVersion) && hash_equals($this->version($catalog), $expectedVersion),
                409, 'Der Katalog wurde inzwischen geändert. Bitte neu laden und Ihre Änderungen vergleichen.');
        }
    }

    public function assertCanImport(McCatalog $catalog): void
    {
        abort_if(isset($this->state($catalog)['binding']), 409,
            'Bitte zuerst die vorhandene Vorlagenverbindung lösen, bevor Sie eine andere Vorlage importieren.');
    }

    public function preview(User $actor, McCatalog $catalog, array $draft): array
    {
        $local = $draft['questions'] ?? $catalog->questions;
        $title = $draft['title'] ?? $catalog->title;
        $this->validate($local, $title);
        if (array_key_exists('title', $draft) || array_key_exists('questions', $draft) || isset($draft['expected_version'])) {
            abort_unless(is_string($draft['expected_version'] ?? null)
                && hash_equals($this->version($catalog), $draft['expected_version']),
                409, 'Der Katalog wurde inzwischen geändert. Bitte neu laden und Ihren Entwurf vergleichen.');
        }
        $state = $this->state($catalog);
        $this->prunePlans($catalog, $state['pending'] ?? null);
        if (isset($state['pending'])) {
            $plan = $this->read($this->planPath($catalog, $state['pending']));
            abort_unless($plan && $plan['actor_id'] === $actor->id, 409, 'Der vorherige Abgleich muss zuerst geprüft werden.');
            $hasDraft = array_key_exists('title', $draft) || array_key_exists('questions', $draft);
            abort_if($hasDraft && ($title !== ($plan['draft_title'] ?? $plan['title'])
                || app(McTestExchange::class)->hash($local) !== app(McTestExchange::class)->hash($plan['draft_questions'] ?? $plan['questions'])),
                409, 'Ihr Entwurf unterscheidet sich vom unterbrochenen Abgleich. Bitte sichern Sie die neuen Änderungen separat und stellen Sie den bestätigten Entwurf wieder her, bevor Sie fortsetzen.');
            $connection = $this->dropbox->connection($actor);
            abort_unless($connection->account_id === $plan['account_id'] && $this->dropbox->canWrite($connection),
                409, 'Bitte die ursprüngliche Dropbox-Verbindung mit Schreibfreigabe wiederherstellen.');
            $remote = $this->remote($connection, $plan['source']);
            $sameBefore = $remote['metadata']['rev'] === $plan['before_revision']
                && hash_equals($plan['before_contents'], $remote['document']['contents']);
            $sameAfter = hash_equals($plan['contents'], $remote['document']['contents']);
            if (! $sameBefore && ! $sameAfter) {
                return array_replace($plan['preview'], ['token' => null, 'can_apply' => false, 'resuming' => true,
                    'can_disconnect' => true, 'recovery_token' => $this->token($catalog, $state['pending']),
                    'conflicts' => ['Die Vorlage wurde nach dem unterbrochenen Schreiben verändert. Der gespeicherte Abgleich bleibt zur Prüfung erhalten; es wird nichts überschrieben. Sie können den Abgleich ausdrücklich beenden und die Verbindung lösen.']]);
            }

            return $plan['preview'] + ['token' => $this->token($catalog, $state['pending']), 'resuming' => true];
        }
        $source = $state['binding']['source'] ?? $this->source($catalog);
        abort_unless($source && ! isset($source['ambiguous']), 409, 'Bitte zuerst genau eine Fragenvorlage über ihren Modul-Handshake verbinden.');
        $connection = $this->dropbox->connection($actor);
        abort_unless($this->dropbox->canWrite($connection), 422, 'Für die Synchronisierung fehlt die Dropbox-Schreibfreigabe.');
        if (isset($state['binding'])) {
            abort_unless($state['binding']['account_id'] === $connection->account_id, 409, 'Das Dropbox-Konto der Vorlage wurde geändert. Bitte die Verbindung neu bestätigen.');
        }
        $remote = $this->remote($connection, $source);
        $base = $state['binding']['questions'] ?? null;
        $conflicts = [];
        $incoming = $remote['document']['questions'];
        if ($base === null) {
            if ($remote['metadata']['rev'] !== $source['source_revision']) {
                $conflicts[] = 'Die Vorlage wurde seit dem ursprünglichen Import geändert. Ohne gemeinsamen Ausgangsstand ist kein sicherer Erstabgleich möglich.';
            }
            $base = [];
            $used = [];
            foreach ($incoming as &$question) {
                $matches = array_values(array_filter($catalog->questions ?? [], fn (array $candidate): bool => $this->documents->semantic($candidate) === $this->documents->semantic($question)));
                if (count($matches) !== 1 || isset($used[$matches[0]['id'] ?? ''])) {
                    $conflicts[] = 'Die ursprüngliche Frage „'.mb_substr($question['prompt'], 0, 100).'“ ist im gespeicherten Katalog nicht eindeutig unverändert zugeordnet.';

                    continue;
                }
                $match = $matches[0];
                $used[$match['id']] = true;
                $question = $this->documents->identify($question, $match['id'], array_column($match['options'], 'id'));
                $base[] = $match;
            }
            unset($question);
        } else {
            $baseIds = array_column($base, null, 'id');
            foreach ($incoming as &$question) {
                if ($remote['document']['format'] === 'markdown' && ! ($question['_identified'] ?? false)) {
                    $matches = array_values(array_filter($base, fn (array $candidate): bool => $this->documents->semantic($candidate) === $this->documents->semantic($question)));
                    if (count($matches) === 1) {
                        $question = $this->documents->identify($question, $matches[0]['id'], array_column($matches[0]['options'], 'id'));
                    } elseif (isset($baseIds[$question['id']])) {
                        $conflicts[] = 'Eine bekannte Frage wurde ohne ihre Synchronisationskennung geändert. Bitte die Kennung in der Vorlage wiederherstellen.';
                    } else {
                        $question['id'] = 'source-'.hash('sha256', json_encode($this->documents->semantic($question), JSON_THROW_ON_ERROR));
                    }
                }
            }
            unset($question);
        }
        $incoming = array_map(fn (array $question): array => array_diff_key($question, ['_identified' => true]), $incoming);
        if (count(array_unique(array_column($incoming, 'id'))) !== count($incoming)) {
            $conflicts[] = 'Die Vorlage enthält doppelte oder nicht eindeutig zugeordnete Fragenkennungen.';
        }
        $baseOrder = array_column($base, 'id');
        $remoteOrder = array_column($incoming, 'id');
        $expectedRemoteOrder = [...array_values(array_intersect($baseOrder, $remoteOrder)),
            ...array_values(array_diff($remoteOrder, $baseOrder))];
        if ($remoteOrder !== $expectedRemoteOrder) {
            $conflicts[] = 'Die Reihenfolge der Fragen wurde in der Vorlage geändert oder neue Fragen wurden dazwischen eingefügt. Diese Reihenfolge wird nicht automatisch überschrieben. Bitte die Reihenfolge vor dem Abgleich angleichen.';
        }
        [$merged, $mergeConflicts] = $this->merge($base, $local, $incoming);
        $conflicts = array_values(array_unique([...$conflicts, ...$mergeConflicts]));
        $preview = ['source_path' => $source['source_path'], 'catalog_changes' => $this->changes($local, $merged),
            'source_changes' => $this->changes($incoming, $merged), 'conflicts' => $conflicts,
            'can_apply' => $conflicts === [], 'initial_connection' => ! isset($state['binding']),
            'warnings' => $remote['document']['format'] === 'markdown'
                ? ['Synchronisiert wird die bearbeitbare Fragenvorlage. Eine vorhandene PDF wird nicht aktualisiert und muss anschließend neu erstellt werden.'] : []];
        $retainedOrder = array_values(array_intersect($remoteOrder, array_column($merged, 'id')));
        $mergedRetainedOrder = array_values(array_intersect(array_column($merged, 'id'), $remoteOrder));
        if ($retainedOrder !== $mergedRetainedOrder) {
            $preview['warnings'][] = 'Die Reihenfolge bestehender Fragen in der Vorlage wird an die im Katalog angezeigte Reihenfolge angepasst.';
        }
        if ($conflicts !== []) {
            return $preview + ['token' => null];
        }
        $this->validate($merged, $title);
        $contents = $this->documents->write($remote['document'], $merged);
        $id = (string) Str::uuid();
        $plan = ['actor_id' => $actor->id, 'account_id' => $connection->account_id, 'catalog_version' => $this->version($catalog),
            'source' => $source, 'before_revision' => $remote['metadata']['rev'], 'before_contents' => $remote['document']['contents'],
            'contents' => $contents, 'questions' => $merged, 'title' => $title, 'draft_title' => $title,
            'draft_questions' => $local, 'preview' => $preview, 'created_at' => now()->timestamp];
        $this->write($this->planPath($catalog, $id), $plan);

        return $preview + ['token' => $this->token($catalog, $id)];
    }

    public function apply(User $actor, McCatalog $catalog, string $token): array
    {
        return $this->serialize($catalog, function () use ($actor, $catalog, $token): array {
            try {
                $identity = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                abort(409, 'Der Vergleich ist nicht mehr gültig. Bitte erneut vergleichen.');
            }
            abort_unless(($identity['catalog'] ?? null) === $catalog->id && ($identity['actor'] ?? null) === $actor->id
                && Str::isUuid($identity['id'] ?? ''), 404);
            $id = $identity['id'];
            $plan = $this->read($this->planPath($catalog, $id));
            abort_unless($plan && $plan['actor_id'] === $actor->id, 409, 'Der Vergleich ist nicht mehr verfügbar.');
            abort_if(($plan['rejected'] ?? false) || ($plan['abandoned'] ?? false),
                409, 'Dieser Abgleich wurde beendet. Bitte erneut vergleichen.');
            $catalog->refresh();
            $state = $this->state($catalog);
            abort_unless(! isset($state['pending']) || $state['pending'] === $id, 409, 'Ein anderer Abgleich muss zuerst abgeschlossen werden.');
            if (($plan['completed'] ?? false) === true) {
                return ['catalog' => $this->payload($catalog), 'message' => 'Dieser Vorlagenabgleich wurde bereits abgeschlossen.'];
            }
            abort_unless(isset($state['pending']) || $plan['created_at'] >= now()->subMinutes(30)->timestamp,
                409, 'Die Vorschau ist abgelaufen. Bitte erneut vergleichen.');
            $alreadySaved = $catalog->title === $plan['title'] && app(McTestExchange::class)->hash($catalog->questions) === app(McTestExchange::class)->hash($plan['questions']);
            abort_unless(hash_equals($plan['catalog_version'], $this->version($catalog)) || (isset($state['pending']) && $alreadySaved),
                409, 'Der Katalog wurde nach der Vorschau geändert. Bitte erneut vergleichen.');
            $connection = $this->dropbox->connection($actor);
            abort_unless($connection->account_id === $plan['account_id'], 409, 'Das Dropbox-Konto wurde nach der Vorschau geändert.');
            abort_unless($this->dropbox->canWrite($connection), 422, 'Die Dropbox-Schreibfreigabe fehlt.');
            $remote = $this->remote($connection, $plan['source']);
            $sameBefore = $remote['metadata']['rev'] === $plan['before_revision']
                && hash_equals($plan['before_contents'], $remote['document']['contents']);
            $sameAfter = hash_equals($plan['contents'], $remote['document']['contents']);
            abort_unless($sameBefore || (isset($state['pending']) && $sameAfter), 409, 'Die Vorlage wurde nach der Vorschau geändert. Bitte erneut vergleichen.');
            $state['pending'] = $id;
            $this->write($this->statePath($catalog), $state);
            $metadata = $remote['metadata'];
            if (! $sameAfter) {
                try {
                    $metadata = $this->dropbox->replaceModuleTemplate($connection, $plan['source']['source_file_id'],
                        $plan['before_revision'], $plan['contents'], $plan['source']['source_path']);
                } catch (McTemplateWriteRejected $exception) {
                    // Only a confirmed rejection proves that this request did not write.
                    $this->write($this->planPath($catalog, $id), $plan + ['rejected' => true]);
                    unset($state['pending']);
                    $this->write($this->statePath($catalog), $state);

                    throw $exception;
                }
            }
            $this->assertLock();
            DB::transaction(function () use ($catalog, $plan): void {
                $locked = McCatalog::query()->lockForUpdate()->findOrFail($catalog->id);
                abort_unless(hash_equals($plan['catalog_version'], $this->version($locked))
                    || ($locked->title === $plan['title'] && app(McTestExchange::class)->hash($locked->questions) === app(McTestExchange::class)->hash($plan['questions'])),
                    409, 'Der Katalog wurde während der Synchronisierung geändert. Der Vorgang bleibt zur Prüfung erhalten.');
                $locked->fill(['title' => $plan['title'], 'questions' => $plan['questions']])->save();
            });
            $state = ['binding' => ['source' => $plan['source'], 'account_id' => $plan['account_id'],
                'revision' => $metadata['rev'], 'questions' => $plan['questions'], 'synced_at' => now()->toISOString()]];
            $this->write($this->statePath($catalog), $state);
            $this->write($this->planPath($catalog, $id), $plan + ['completed' => true]);

            return ['catalog' => $this->payload($catalog->fresh()), 'warnings' => $plan['preview']['warnings'] ?? [],
                'message' => 'Fragenvorlage und Katalog wurden synchronisiert. Bestehende Tests bleiben unverändert.'];
        });
    }

    public function disconnect(User $actor, McCatalog $catalog, ?string $recoveryToken = null): array
    {
        return $this->serialize($catalog, function () use ($actor, $catalog, $recoveryToken): array {
            $catalog->refresh();
            $state = $this->state($catalog);
            $pendingPlan = null;
            abort_if($recoveryToken !== null && ! isset($state['pending']), 409,
                'Es liegt kein unterbrochener Abgleich zum Beenden vor. Bitte die Verbindungsanzeige aktualisieren.');
            if (isset($state['pending'])) {
                abort_unless(is_string($recoveryToken), 409, 'Bitte zuerst den unterbrochenen Abgleich prüfen und das Lösen ausdrücklich bestätigen.');
                try {
                    $identity = json_decode(Crypt::decryptString($recoveryToken), true, flags: JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    abort(409, 'Die Bestätigung zum Lösen ist ungültig. Bitte erneut vergleichen.');
                }
                abort_unless(($identity['catalog'] ?? null) === $catalog->id && ($identity['actor'] ?? null) === $actor->id
                    && ($identity['id'] ?? null) === $state['pending'], 409, 'Die Bestätigung gehört nicht zu diesem unterbrochenen Abgleich.');
                $pendingPlan = $this->read($this->planPath($catalog, $state['pending']));
                abort_unless($pendingPlan && $pendingPlan['actor_id'] === $actor->id, 409, 'Der gespeicherte Abgleich ist nicht verfügbar.');
                $connection = $this->dropbox->connection($actor);
                abort_unless($connection->account_id === $pendingPlan['account_id'], 409, 'Bitte das ursprüngliche Dropbox-Konto wiederherstellen.');
                $remote = $this->remote($connection, $pendingPlan['source']);
                $sameBefore = $remote['metadata']['rev'] === $pendingPlan['before_revision']
                    && hash_equals($pendingPlan['before_contents'], $remote['document']['contents']);
                $sameAfter = hash_equals($pendingPlan['contents'], $remote['document']['contents']);
                abort_if($sameBefore || $sameAfter, 409, 'Der Abgleich kann sicher fortgesetzt werden. Bitte die Vorschau erneut prüfen.');
            }
            $this->write($this->planPath($catalog, (string) Str::uuid()), ['disconnected_at' => now()->toISOString(),
                'created_at' => now()->timestamp, 'state' => $state, 'module_sources' => $catalog->module_sources,
                'pending_plan' => $pendingPlan]);
            if ($pendingPlan !== null) {
                $this->write($this->planPath($catalog, $state['pending']), $pendingPlan + ['abandoned' => true]);
            }
            $catalog->update(['module_sources' => []]);
            $this->write($this->statePath($catalog), []);

            return ['catalog' => $this->payload($catalog->fresh())];
        });
    }

    private function remote(DropboxConnection $connection, array $source): array
    {
        $located = $this->modules->locate($connection, json_encode(['schema_version' => 1, 'module_id' => $source['module_id']], JSON_THROW_ON_ERROR));
        $module = $located['candidate'] ?? null;
        abort_unless(! ($located['conflict'] ?? false) && $module && $module['folder_id'] === $source['module_folder_id'],
            409, 'Die ursprüngliche Modulzuordnung konnte nicht bestätigt werden.');
        $metadata = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $source['source_file_id']])->json();
        $expectedDirectory = rtrim(mb_strtolower($module['folder_path']), '/').'/multiple choice fragen';
        abort_unless(($metadata['.tag'] ?? null) === 'file' && ($metadata['id'] ?? null) === $source['source_file_id']
            && is_string($metadata['path_lower'] ?? null) && dirname($metadata['path_lower']) === $expectedDirectory
            && ($metadata['path_display'] ?? $metadata['path_lower']) === $source['source_path']
            && is_string($metadata['rev'] ?? null) && is_int($metadata['size'] ?? null) && $metadata['size'] <= 1024 * 1024,
            409, 'Die Vorlage wurde verschoben, entfernt oder passt nicht mehr zur Verbindung.');
        $contents = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$metadata['rev']])->body();
        $after = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $source['source_file_id']])->json();
        abort_unless(($after['rev'] ?? null) === $metadata['rev'] && ($after['path_lower'] ?? null) === $metadata['path_lower']
            && strlen($contents) === $metadata['size'], 409, 'Die Vorlage wurde während des Lesens geändert.');
        $format = strtolower(pathinfo($metadata['name'], PATHINFO_EXTENSION)) === 'json' ? 'json' : 'markdown';

        return ['metadata' => $metadata, 'document' => $this->documents->read($contents, $format)];
    }

    private function merge(array $base, array $local, array $remote): array
    {
        $base = array_column($base, null, 'id');
        $local = array_column($local, null, 'id');
        $remote = array_column($remote, null, 'id');
        $merged = [];
        $conflicts = [];
        foreach (array_unique([...array_keys($local), ...array_keys($remote), ...array_keys($base)]) as $id) {
            $before = $base[$id] ?? null;
            $left = $local[$id] ?? null;
            $right = $remote[$id] ?? null;
            $leftChanged = $this->documents->semantic($left) !== $this->documents->semantic($before);
            $rightChanged = $this->documents->semantic($right) !== $this->documents->semantic($before);
            if ($leftChanged && $rightChanged && $this->documents->semantic($left) !== $this->documents->semantic($right)) {
                $conflicts[] = 'Beide Seiten haben die Frage „'.mb_substr(($left ?? $right ?? $before)['prompt'], 0, 100).'“ unterschiedlich geändert oder entfernt.';
                $selected = $left;
            } else {
                $selected = $rightChanged ? $right : $left;
            }
            if ($selected !== null) {
                $selected['active'] = $left['active'] ?? true;
                $selected['reviewed'] = $rightChanged && $this->documents->semantic($left) !== $this->documents->semantic($right) ? false : ($left['reviewed'] ?? false);
                $merged[] = $selected;
            }
        }

        return [$merged, $conflicts];
    }

    private function changes(array $before, array $after): array
    {
        $before = array_column($before, null, 'id');
        $after = array_column($after, null, 'id');
        $changes = ['added' => [], 'modified' => [], 'removed' => []];
        foreach ($after as $id => $question) {
            if (! isset($before[$id])) {
                $changes['added'][] = ['id' => $id, 'prompt' => $question['prompt'], 'before' => null, 'after' => $question];
            } elseif ($this->documents->semantic($before[$id]) !== $this->documents->semantic($question)) {
                $changes['modified'][] = ['id' => $id, 'prompt' => $question['prompt'], 'before_prompt' => $before[$id]['prompt'],
                    'before' => $before[$id], 'after' => $question];
            }
        }
        foreach (array_diff_key($before, $after) as $id => $question) {
            $changes['removed'][] = ['id' => $id, 'prompt' => $question['prompt'], 'before' => $question, 'after' => null];
        }

        return $changes;
    }

    private function validate(array $questions, string $title): void
    {
        Validator::make(['title' => $title, 'questions' => $questions], ['title' => ['required', 'string', 'max:255'],
            'questions' => ['required', 'array', 'max:2000'], 'questions.*' => ['array:id,prompt,type,options,correct_option_ids,active,reviewed'],
            'questions.*.id' => ['required', 'string', 'max:100', 'distinct'], 'questions.*.prompt' => ['required', 'string', 'max:20000'],
            'questions.*.options' => ['required', 'array', 'max:30'], 'questions.*.options.*' => ['array:id,text'],
            'questions.*.options.*.id' => ['required', 'string', 'max:100'], 'questions.*.options.*.text' => ['required', 'string', 'max:10000'],
            'questions.*.correct_option_ids' => ['required', 'array', 'max:30'], 'questions.*.correct_option_ids.*' => ['string', 'max:100'],
            'questions.*.active' => ['required', 'boolean'], 'questions.*.reviewed' => ['required', 'boolean']])->validate();
        $errors = $this->questions->errors($questions);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function source(McCatalog $catalog): ?array
    {
        $sources = collect($catalog->module_sources ?? [])->keyBy('source_file_id')->values();

        return $sources->count() > 1 ? ['ambiguous' => true] : $sources->first();
    }

    private function token(McCatalog $catalog, string $id): string
    {
        return Crypt::encryptString(json_encode(['id' => $id, 'catalog' => $catalog->id, 'actor' => $catalog->owner_id], JSON_THROW_ON_ERROR));
    }

    private function state(McCatalog $catalog): array
    {
        return $this->read($this->statePath($catalog)) ?? [];
    }

    private function statePath(McCatalog $catalog): string
    {
        return 'multiple-choice/template-sync/'.$catalog->school_id.'/'.$catalog->owner_id.'/'.$catalog->id.'/connection.json.enc';
    }

    private function planPath(McCatalog $catalog, string $id): string
    {
        return dirname($this->statePath($catalog)).'/'.$id.'.json.enc';
    }

    private function read(string $path): ?array
    {
        $contents = Storage::disk('local')->get($path);

        return $contents === null ? null : json_decode(Crypt::decryptString($contents), true, flags: JSON_THROW_ON_ERROR);
    }

    private function prunePlans(McCatalog $catalog, ?string $pending): void
    {
        $storage = Storage::disk('local');
        foreach ($storage->files(dirname($this->statePath($catalog))) as $path) {
            $id = basename($path, '.json.enc');
            if (! Str::isUuid($id) || $id === $pending) {
                continue;
            }
            $plan = $this->read($path);
            $retention = isset($plan['completed']) || isset($plan['disconnected_at']) || isset($plan['rejected']) || isset($plan['abandoned']) ? 7 * 86400 : 1800;
            if (($plan['created_at'] ?? $storage->lastModified($path)) < now()->timestamp - $retention) {
                $storage->delete($path);
            }
        }
    }

    private function write(string $path, array $value): void
    {
        $this->assertLock();
        $contents = Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR));
        abort_unless(Storage::disk('local')->put($path, $contents) && hash_equals($contents, Storage::disk('local')->get($path) ?? ''),
            409, 'Der Synchronisationsstand konnte nicht sicher gespeichert werden.');
    }

    private function assertLock(): void
    {
        abort_unless($this->lock?->refresh(180), 409,
            'Die Bearbeitung hat zu lange gedauert. Bitte den gespeicherten Abgleich erneut prüfen; es wird nichts ungeprüft übernommen.');
    }
}
