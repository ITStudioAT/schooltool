<?php

namespace App\Services;

use App\Ai\Agents\TeachingExerciseDraftAgent;
use App\Models\DropboxConnection;
use App\Models\McTest;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Support\SchooltoolAssessmentJson;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Files\Document;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

class TeachingWorkExerciseGeneration
{
    public function __construct(private DropboxWorkImport $dropbox, private DropboxModuleLinks $modules, private TeachingWorkAiExport $exports) {}

    public function requireConfigured(): void
    {
        abort_unless(trim((string) config('ai.providers.openai.key')) !== '', 422,
            'Für die direkte Ausführung fehlt der konfigurierte OpenAI-API-Schlüssel. Der Übergabetext kann weiterhin kopiert werden.');
    }

    /** @param array<string, mixed> $export @return array<string, mixed> */
    public function preview(TeachingCourseWork $work, User $actor, array $export, string $formPolicy, string $instructions): array
    {
        $this->requireConfigured();
        $context = $export['context'];
        abort_if($context['folder_binding'] !== null, 409, 'Für diese Arbeit ist bereits ein konkreter Arbeitsordner gespeichert. Es wird kein zweiter Ordner angelegt.');
        $connection = $this->dropbox->connection($actor);
        $module = $this->module($work, $connection);
        $this->assertNew($work, $connection, $context['work']['scheduled_date']);
        abort_unless($this->dropbox->canWrite($connection), 422, 'Für die vollständige Anlage fehlt die zusätzliche Dropbox-Schreibfreigabe.');
        abort_unless($context['work']['scheduled_date'] && $context['work']['maximum_points'] > 0, 422,
            'Durchführungstermin und positive LF-Gesamtpunktzahl müssen gespeichert sein.');
        $sources = $this->sources($connection, $module);
        $points = $context['multiple_choice_test']['points_plan'] ?? ['mc_points' => 0,
            'other_points' => (float) $context['work']['maximum_points'], 'maximum_points' => (float) $context['work']['maximum_points']];
        abort_unless(round((float) $points['maximum_points'], 2) === round((float) $context['work']['maximum_points'], 2), 422,
            'Die Aufgabenpunkte weichen von der Schooltool-Skala ab. Bitte die Punkteaufteilung vor der direkten Anlage abstimmen.');
        $input = ['work' => array_diff_key($context['work'], array_flip(['groups', 'technical_created_at'])),
            'course' => $context['course'], 'points_plan' => $points,
            'curriculum' => $context['curriculum'], 'sessions' => $context['sessions'],
            'multiple_choice_test' => $context['multiple_choice_test'] ?? null,
            'email_form_policy' => $formPolicy, 'teacher_instructions' => $instructions,
            'sources' => array_map(fn (array $source): array => $this->sourceInfo($source), $sources)];
        $snapshot = $this->snapshot($work, $actor, $context);
        try {
            $draft = (new TeachingExerciseDraftAgent)->prompt(
                explode('## Schooltool-Daten', $export['text'], 2)[0]
                    ."\nDie Anwendung übernimmt Bestandsprüfung, Ordneranlage, Teilnehmerliste, PDFs und Online-Belege. "
                    .'Bereiten Sie dafür die vollständigen fachlichen Dokumente aus den beigefügten Quellen vor. '
                    ."Die ausdrückliche Lehrerentscheidung zur E-Mail-Form steht unter email_form_policy; bereits beantwortete Fragen nicht erneut stellen.\n"
                    .$this->json($input),
                attachments: array_map(fn (array $source) => Document::fromString($source['bytes'], $source['mime'])->as($source['name']), $sources),
                provider: 'openai',
            )->toArray();
        } catch (Throwable) {
            throw ValidationException::withMessages(['generation' => 'OpenAI konnte das Materialpaket nicht erzeugen. Es wurde kein Leistungsfeststellungsordner angelegt. Bitte später erneut versuchen.']);
        }
        Validator::make($draft, [
            'assignment_markdown' => ['required', 'string', 'max:80000'], 'criteria_markdown' => ['required', 'string', 'max:80000'],
            'criteria' => ['present', 'array', 'max:100'], 'criteria.*.title' => ['required', 'string', 'max:300'],
            'criteria.*.description' => ['required', 'string', 'max:5000'], 'criteria.*.points' => ['required', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
            'questions' => ['present', 'array', 'max:30'], 'questions.*' => ['required', 'string', 'max:2000'],
            'warnings' => ['present', 'array', 'max:30'], 'warnings.*' => ['required', 'string', 'max:2000'],
        ])->validate();
        if ($draft['questions'] !== []) {
            return ['questions' => $draft['questions'], 'warnings' => $draft['warnings'], 'ready' => false];
        }
        abort_unless((int) round(array_sum(array_column($draft['criteria'], 'points')) * 100) === (int) round($points['other_points'] * 100),
            422, 'Die erzeugten Teilkriterien stimmen nicht mit dem vereinbarten Punkteplan überein. Es wurde nichts angelegt. Bitte die Vorgaben ergänzen.');
        $this->assertSnapshot($work, $actor, $context, $snapshot);
        $id = (string) Str::uuid();
        $plan = ['context' => $context, 'snapshot' => $snapshot,
            'draft' => $draft, 'sources' => $sources, 'form_policy' => $formPolicy, 'instructions' => $instructions,
            'module' => $module, 'points' => $points];
        $plan['documents'] = $this->documents($plan);
        $plan['prepared_at'] = now('Europe/Vienna')->toIso8601String();
        $this->preparation($work, $plan, $plan['documents']);
        Cache::put($this->key($actor, $work, $id), $plan, now()->addMinutes(30));

        return ['id' => $id, 'ready' => true, ...$draft,
            'pdfs' => collect($plan['documents'])->filter(fn (string $bytes, string $path): bool => str_ends_with($path, '.pdf')
                && ! str_contains($path, '/Quellunterlagen/'))->map(fn (string $bytes, string $path): array => ['name' => basename($path),
                    'base64' => base64_encode($bytes)])->values()->all(),
            'sources' => array_map(fn (array $source): array => $this->sourceInfo($source), $sources)];
    }

    /** @return array<string, mixed> */
    public function store(TeachingCourseWork $work, User $actor, string $id): array
    {
        $key = $this->key($actor, $work, $id);
        $lock = Cache::lock('teaching:exercise-create:'.$work->id, 300);
        abort_unless($lock->get(), 409, 'Für diese Arbeit läuft bereits eine Anlage. Bitte den Stand anschließend prüfen.');
        try {
            $plan = Cache::get($key);
            abort_unless(is_array($plan), 409, 'Die Vorschau ist abgelaufen. Bitte neu erzeugen und prüfen.');
            if (isset($plan['completed'])) {
                return $plan['completed'];
            }
            $connection = $this->dropbox->connection($actor);
            $this->assertSnapshot($work, $actor, $plan['context'], $plan['snapshot']);
            $module = $this->module($work, $connection);
            abort_unless($module === $plan['module'] && $this->dropbox->canWrite($connection), 409, 'Die Modulbindung oder Dropbox-Schreibfreigabe hat sich geändert.');
            $this->assertNew($work, $connection, $plan['context']['work']['scheduled_date']);
            foreach ($plan['sources'] as $source) {
                $current = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $source['id']])->json();
                abort_unless(($current['rev'] ?? null) === $source['revision'] && ($current['path_lower'] ?? null) === $source['path_lower'],
                    409, 'Eine Quellvorlage wurde seit der Vorschau geändert. Bitte neu erzeugen.');
            }
            $files = $plan['documents'];
            $rootPath = rtrim($module['path_lower'], '/').'/Leistungsfeststellung';
            $root = $this->dropbox->findModuleEntry($connection, $rootPath);
            if ($root === null) {
                $root = $this->dropbox->createExerciseChildFolder($connection, $module['id'], 'Leistungsfeststellung', $module['path_lower']);
            }
            abort_unless(($root['.tag'] ?? null) === 'folder', 409, 'Der Zielpfad ist kein Leistungsfeststellungsordner.');
            $name = $plan['context']['work']['scheduled_date'].'_'.Str::slug($plan['context']['course']['folder_group']
                ?: implode('-', $plan['context']['course']['classes']), '_').'_'.Str::slug($work->title, '_').'_Arbeit-'.$work->id;
            $folder = $this->dropbox->createExerciseChildFolder($connection, $root['id'], mb_substr($name, 0, 190), $root['path_lower']);
            $path = $folder['path_display'] ?? $folder['path_lower'];
            $history = ['schema_version' => 1, 'kind' => 'create-exercise.history', 'binding' => ['folder' => $path,
                'work_id' => $work->id, 'course_id' => $work->teaching_course_id, 'school_id' => $work->teachingCourse->school_id],
                'entries' => [$this->event('exercise_folder_created', '.', 'created')]];
            $folders = [];
            $historyRevision = null;
            try {
                foreach (['Transaktionen', 'Schülerlisten', 'Angaben', 'Abgaben', 'Auswertungen', 'Bewertungskriterien'] as $section) {
                    $folders[$section] = $this->dropbox->createExerciseChildFolder($connection, $folder['id'], $section, $folder['path_lower']);
                    $history['entries'][] = $this->event('subfolder_created', $section, 'created');
                    $historyRevision = $this->saveHistory($connection, $folders['Transaktionen']['id'], $history, $historyRevision);
                }
                $folders['Bewertungskriterien/Quellunterlagen'] = $this->dropbox->createExerciseChildFolder($connection,
                    $folders['Bewertungskriterien']['id'], 'Quellunterlagen', $folders['Bewertungskriterien']['path_lower']);
                $history['entries'][] = $this->event('subfolder_created', 'Bewertungskriterien/Quellunterlagen', 'created');
                $files['Schülerlisten/Schooltool-Quelle.json'] = $this->json($plan['context']);
                $files['Schülerlisten/Schuelerliste.json'] = $this->json(['schema_version' => 1, 'work_id' => $work->id,
                    'course_id' => $work->teaching_course_id, 'source' => 'Schooltool-Quelle.json',
                    'source_sha256' => hash('sha256', $files['Schülerlisten/Schooltool-Quelle.json']),
                    'participants' => $plan['context']['course_roster']['participants']]);
                $handshake = $this->json(['schema_version' => 1, 'kind' => 'schooltool.exercise_folder', 'exercise_folder_id' => (string) Str::uuid()]);
                $preparation = $this->json($this->preparation($work, $plan, $files));
                $files['Anlage_und_Quellen.json'] = $this->json($this->metadata($work, $plan, $path,
                    $files + ['handshake.json' => $handshake, 'Schooltool-Vorbereitung.json' => $preparation]));
                $files['handshake.json'] = $handshake;
                $files['Schooltool-Vorbereitung.json'] = $preparation;
                foreach ($files as $relative => $bytes) {
                    if ($relative === 'Schooltool-Vorbereitung.json') {
                        $this->assertSnapshot($work, $actor, $plan['context'], $plan['snapshot']);
                    }
                    $parent = dirname($relative) === '.' ? $folder : $folders[dirname($relative)];
                    $this->writeVerified($connection, $parent['id'], basename($relative), $bytes);
                    $history['entries'][] = $this->event('file_created', $relative, 'created') + ['sha256' => hash('sha256', $bytes)];
                    $historyRevision = $this->saveHistory($connection, $folders['Transaktionen']['id'], $history, $historyRevision);
                }
                $history['entries'][] = $this->event('material_package_completed', '.', 'completed');
                $this->saveHistory($connection, $folders['Transaktionen']['id'], $history, $historyRevision);
            } catch (Throwable) {
                if (isset($folders['Transaktionen'])) {
                    $history['entries'][] = $this->event('material_package_failed', '.', 'not_confirmed');
                    try {
                        $this->saveHistory($connection, $folders['Transaktionen']['id'], $history, $historyRevision);
                    } catch (Throwable) {
                        // Preserve the last confirmed history when Dropbox cannot confirm another write.
                    }
                }
                throw ValidationException::withMessages(['generation' => 'Die Anlage wurde nicht vollständig bestätigt. Bereits angelegte Dateien bleiben geschützt unter '.$path.'. Bitte „Erneut prüfen“ verwenden; keinen zweiten Ordner anlegen.']);
            }
            $confirmed = $this->modules->exercise($connection, $work->fresh('teachingCourse'), $this->modules->current($work),
                $plan['context']['work']['scheduled_date'], refresh: true);
            abort_unless(($confirmed['status'] ?? null) === 'found' && ($confirmed['assignment_status'] ?? null) === 'ready'
                && ($confirmed['folder']['id'] ?? null) === $folder['id'], 409,
                'Die Dateien wurden angelegt, der abschließende Online-Abgleich ist noch offen. Bitte „Erneut prüfen“ verwenden.');
            $result = ['folder' => $confirmed['folder'], 'files' => array_keys($files), 'status' => 'created'];
            $plan['completed'] = $result;
            Cache::put($key, $plan, now()->addMinutes(30));

            return $result;
        } finally {
            $lock->release();
        }
    }

    /** @return array<string, mixed> */
    private function module(TeachingCourseWork $work, DropboxConnection $connection): array
    {
        $link = $this->modules->current($work);
        abort_unless($link && $link->dropbox_connection_id === $connection->id && $link->account_id === $connection->account_id, 422,
            'Zuerst den Lernmaterialordner mit dieser Arbeit verknüpfen.');
        $folder = $this->dropbox->metadata($connection, $link->folder_id);
        abort_unless(strcasecmp($folder['path_lower'], $link->folder_path) === 0 && ! $this->dropbox->isTemporaryFolderPath($folder['path_lower']), 409,
            'Die gespeicherte Modulquelle wurde verschoben oder ist nur ein temporärer Ordner.');

        return $folder;
    }

    private function assertNew(TeachingCourseWork $work, DropboxConnection $connection, ?string $date): void
    {
        $status = $work->status ?? [];
        foreach (['assessment_json_imports', 'assessment_json_packages', 'evaluation_pdfs', 'dispatch_notifications', 'submission_checks', 'provision_events'] as $field) {
            abort_if(! empty($status[$field]), 409, 'Die Arbeit besitzt bereits Originalnachweise. Eine neue Initialanlage ist gesperrt.');
        }
        abort_if(! empty($status['student_distribution']['published_at']), 409, 'Die Arbeit wurde bereits im Schülerbereich bereitgestellt.');
        $state = $this->modules->exercise($connection, $work, $this->modules->current($work), $date, refresh: true);
        abort_unless(($state['status'] ?? null) === 'missing', 409,
            ($state['status'] ?? null) === 'found' ? 'Diese Leistungsfeststellung ist bereits angelegt. Vorhandene Dateien bleiben unverändert.'
                : ($state['message'] ?? 'Der tatsächliche Anlagezustand ist nicht eindeutig bestätigt.'));
        $module = $this->module($work, $connection);
        $root = $this->dropbox->findModuleEntry($connection, $module['path_lower'].'/Leistungsfeststellung');
        if ($root === null) {
            return;
        }
        foreach ($this->listing($connection, $root['id']) as $folder) {
            if (($folder['.tag'] ?? null) !== 'folder') {
                continue;
            }
            $record = $this->dropbox->readDocument($connection, $folder['id'], 'Anlage_und_Quellen.json');
            abort_unless($record, 409, 'Eine bestehende Leistungsfeststellung ist nicht eindeutig gekennzeichnet. Bitte den Bestand zuerst klären.');
            $identity = json_decode($record['contents'], true);
            abort_unless(is_array($identity) && is_int($identity['Schooltool_Arbeit_ID'] ?? null), 409, 'Eine bestehende Arbeitsidentität ist unklar.');
            abort_if($identity['Schooltool_Arbeit_ID'] === $work->id, 409,
                'Diese Arbeitskennung ist bereits vorhanden, auch wenn der Ordner unvollständig ist oder der Termin abweicht. Es wird nichts ergänzt.');
        }
    }

    /** @return list<array<string, mixed>> */
    private function listing(DropboxConnection $connection, string $id, bool $recursive = false): array
    {
        $data = $this->dropbox->readModuleApi($connection, 'files/list_folder', ['path' => $id, 'recursive' => $recursive, 'limit' => 100])->json();
        abort_unless(is_array($data['entries'] ?? null) && ($data['has_more'] ?? true) === false && count($data['entries']) <= 100, 422,
            'Der Ordnerinhalt kann nicht vollständig innerhalb der Prüfgrenze gelesen werden. Bitte die Quelle eingrenzen.');

        return $data['entries'];
    }

    /** @param array<string, mixed> $module @return list<array<string, mixed>> */
    private function sources(DropboxConnection $connection, array $module): array
    {
        $entries = $this->listing($connection, $module['id']);
        $templates = array_values(array_filter($entries, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
            && strcasecmp($entry['name'] ?? '', 'Assessment Template') === 0));
        abort_unless(count($templates) === 1, 422, 'Die bestätigte Modulquelle benötigt einen eindeutigen Ordner „Assessment Template“.');
        $templateFiles = $this->listing($connection, $templates[0]['id'], true);
        $files = array_values(array_filter([...$entries, ...$templateFiles], fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file'
            && in_array(strtolower(pathinfo($entry['name'] ?? '', PATHINFO_EXTENSION)), ['md', 'txt', 'pdf', 'docx'], true)));
        abort_unless($files !== [] && count($files) <= 16 && collect($templateFiles)->contains(fn (array $entry): bool => in_array($entry, $files, true)),
            422, 'Bitte 1 bis 16 lesbare Markdown-, Text-, Word- oder PDF-Quellen mit mindestens einer Aufgaben-/Kriterienvorlage bereitstellen.');
        $sources = [];
        $total = 0;
        foreach ($files as $file) {
            $size = $file['size'] ?? null;
            abort_unless(is_int($size) && $size > 0 && $size <= 6 * 1024 * 1024 && is_string($file['rev'] ?? null)
                && is_string($file['id'] ?? null) && str_starts_with($file['path_lower'] ?? '', $module['path_lower'].'/'), 422,
                'Eine Quellvorlage ist ungültig oder größer als 6 MB.');
            $total += $size;
            abort_if($total > 12 * 1024 * 1024, 422, 'Die ausgewählten Quellen überschreiten insgesamt 12 MB.');
            $bytes = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$file['rev']])->body();
            abort_unless(strlen($bytes) === $size, 409, 'Eine Quellvorlage wurde nicht vollständig gelesen.');
            $mime = match (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION))) {
                'pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', default => 'text/plain',
            };
            $sources[] = ['id' => $file['id'], 'name' => $file['name'], 'revision' => $file['rev'],
                'path_lower' => $file['path_lower'], 'sha256' => hash('sha256', $bytes), 'bytes' => $bytes, 'mime' => $mime];
        }

        return $sources;
    }

    /** @param array<string, mixed> $context */
    private function snapshot(TeachingCourseWork $work, User $actor, array $context): string
    {
        $fresh = $this->exports->buildExercise($work->fresh('teachingCourse'), $actor, $context['include_multiple_choice'],
            $context['work']['scheduled_date'], $context['multiple_choice_test'] ?? null)['context'];
        unset($fresh['exported_at']);
        $test = isset($context['multiple_choice_test']['test_id']) ? McTest::query()->find($context['multiple_choice_test']['test_id'])?->getAttributes() : null;

        return SchooltoolAssessmentJson::digest([$fresh, $test, $this->modules->current($work)?->getAttributes()]);
    }

    /** @param array<string, mixed> $context */
    private function assertSnapshot(TeachingCourseWork $work, User $actor, array $context, string $snapshot): void
    {
        abort_unless(hash_equals($snapshot, $this->snapshot($work, $actor, $context)), 409,
            'Arbeitsdaten, Teilnehmer, MC-Test oder Modulbindung wurden geändert. Bitte die Vorschau neu erzeugen.');
    }

    /** @param array<string, mixed> $plan @return array<string, string> */
    private function documents(array $plan): array
    {
        $topic = Str::slug($plan['context']['work']['title'], '_') ?: 'Leistungsfeststellung';
        $files = [];
        foreach (['assignment_markdown' => ['Angaben', 'Aufgabenstellung_'.$topic, 'Aufgabenstellung'],
            'criteria_markdown' => ['Bewertungskriterien', 'Beurteilungskriterien_'.$topic, 'Bewertungskriterien']] as $field => [$section, $name, $label]) {
            $markdown = trim($plan['draft'][$field])."\n";
            $files[$section.'/'.$name.'.md'] = $markdown;
            $html = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
            abort_if(preg_match('/<img\b/i', $html), 422, 'Externe Bilder sind im erzeugten Dokument nicht zulässig. Bitte die Vorschau neu erzeugen.');
            $pdf = base64_decode(Pdf::view('pdfs.teachingExercise', ['title' => $plan['context']['work']['title'],
                'label' => $label, 'course' => $plan['context']['course']['title'], 'html' => $html])->format('a4')->base64(), true);
            abort_unless(is_string($pdf) && str_starts_with($pdf, '%PDF-') && strlen($pdf) <= 6 * 1024 * 1024, 422,
                'Die vollständige PDF-Erzeugung konnte nicht bestätigt werden. Es wurde noch kein Ordner angelegt.');
            $files[$section.'/'.$name.'.pdf'] = $pdf;
        }
        $files['Bewertungskriterien/Beurteilungskriterien_'.$topic.'.json'] = $this->json(['schema_version' => 1,
            'criteria' => $plan['draft']['criteria'], 'email_form_policy' => $plan['form_policy'], 'points_plan' => $plan['points'],
            'sources' => array_map(fn (array $source): array => $this->sourceInfo($source), $plan['sources'])]);
        foreach ($plan['sources'] as $index => $source) {
            $files['Bewertungskriterien/Quellunterlagen/'.($index + 1).'_'.$source['name']] = $source['bytes'];
            if (dirname($source['path_lower']) === $plan['module']['path_lower']) {
                $files['Angaben/Material_'.($index + 1).'_'.$source['name']] = $source['bytes'];
            }
        }
        if (isset($plan['context']['multiple_choice_test'])) {
            $files['Transaktionen/MC-Test.json'] = $this->json($plan['context']['multiple_choice_test']);
        }

        return $files;
    }

    /** @param array<string, mixed> $plan @param array<string, string> $files @return array<string, mixed> */
    private function metadata(TeachingCourseWork $work, array $plan, string $path, array $files): array
    {
        return ['Schooltool_Arbeit_ID' => $work->id, 'Schooltool_Kurs_ID' => $work->teaching_course_id,
            'Schooltool_Schule_ID' => $work->teachingCourse->school_id, 'Titel' => $work->title,
            'Leistungsfeststellungsordner' => $path, 'Durchfuehrungsdatum' => $plan['context']['work']['scheduled_date'],
            'Zeitzone' => 'Europe/Vienna', 'Abgabeschluss' => $this->deadline($plan['context']['work']['main_deadline']),
            'MitgeteilteAbgabefrist' => $plan['context']['work']['communicated_deadline']['at'] ?? null,
            'Modulbindung' => $plan['context']['module_binding'], 'Kurs' => $plan['context']['course'],
            'Teilnehmerliste' => 'Schülerlisten/Schuelerliste.json', 'Erstellt' => $plan['prepared_at'],
            'MC_Test_ID' => $plan['context']['multiple_choice_test']['test_id'] ?? null,
            'Bewertungskriterien' => ['Status' => 'verbindlich', 'Lehrerentscheidung' => $plan['form_policy'],
                'Abweichungen' => $plan['draft']['warnings'], 'Bestaetigung' => 'Konkrete Vorschau in Schooltool bestätigt'],
            'Dateien' => collect($files)->map(fn (string $bytes, string $file): array => ['Pfad' => $file, 'SHA256' => hash('sha256', $bytes)])->values()->all()];
    }

    /** @param array<string, mixed> $plan @param array<string, string> $files @return array<string, mixed> */
    private function preparation(TeachingCourseWork $work, array $plan, array $files): array
    {
        $pdf = collect(array_keys($files))->first(fn (string $path): bool => str_starts_with($path, 'Angaben/Aufgabenstellung_') && str_ends_with($path, '.pdf'));
        $package = ['schema_version' => 2, 'kind' => 'schooltool.preparation', 'stage' => 'assignment_created',
            'work_id' => $work->id, 'course_id' => $work->teaching_course_id, 'prepared_at' => $plan['prepared_at'], 'timezone' => 'Europe/Vienna',
            'exercise' => ['title' => $work->title, 'course_title' => $plan['context']['course']['title'], 'scheduled_date' => $plan['context']['work']['scheduled_date']],
            'participants' => array_map(fn (array $person): array => array_intersect_key($person,
                array_flip(['participant_id', 'schooltool_person_id', 'first_name', 'last_name', 'class_name'])), $plan['context']['course_roster']['participants']),
            'assignment' => ['filename' => basename($pdf), 'sha256' => hash('sha256', $files[$pdf])]];
        $package['preparation_checksum'] = SchooltoolAssessmentJson::digest($package);
        app(TeachingWorkDispatchImport::class)->parse($this->json($package));

        return $package;
    }

    private function writeVerified(DropboxConnection $connection, string $folderId, string $name, string $bytes): void
    {
        $revision = $this->dropbox->writeDocument($connection, $folderId, $name, $bytes, null);
        $verified = $this->dropbox->readDocument($connection, $folderId, $name);
        abort_unless($verified && $verified['rev'] === $revision && hash_equals(hash('sha256', $bytes), hash('sha256', $verified['contents'])),
            409, 'Dropbox hat die unveränderte Datei nicht bestätigt.');
    }

    /** @param array<string, mixed> $history */
    private function saveHistory(DropboxConnection $connection, string $folderId, array $history, ?string $revision): string
    {
        $bytes = $this->json($history);
        $saved = $this->dropbox->writeDocument($connection, $folderId, 'History.json', $bytes, $revision);
        $verified = $this->dropbox->readDocument($connection, $folderId, 'History.json');
        abort_unless($verified && $verified['rev'] === $saved && $verified['contents'] === $bytes, 409, 'Die tatsächliche History wurde nicht bestätigt.');

        return $saved;
    }

    /** @return array<string, string> */
    private function event(string $action, string $path, string $result): array
    {
        return ['at' => now('Europe/Vienna')->toIso8601String(), 'action' => $action, 'path' => $path, 'result' => $result];
    }

    /** @param array<string, mixed> $source @return array<string, string> */
    private function sourceInfo(array $source): array
    {
        return array_intersect_key($source, array_flip(['id', 'name', 'revision', 'path_lower', 'sha256']));
    }

    private function key(User $actor, TeachingCourseWork $work, string $id): string
    {
        return 'teaching:exercise-plan:'.$actor->id.':'.$work->id.':'.$id;
    }

    /** @param array{date: ?string, time: ?string} $deadline */
    private function deadline(array $deadline): ?string
    {
        return $deadline['date'] && $deadline['time']
            ? CarbonImmutable::parse($deadline['date'].' '.$deadline['time'], 'Europe/Vienna')->toIso8601String() : null;
    }

    /** @param array<mixed> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }
}
