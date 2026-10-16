<?php

namespace App\Services\NumberSystems;

use App\Models\NumberSystemGameRun;
use App\Models\NumberSystemGameSession;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NumberSystemGameService
{
    public function __construct(private NumberSystemEngine $engine) {}

    public function create(User $teacher, TeachingCourse $course, array $data): NumberSystemGameSession
    {
        abort_unless((int) $course->user_id === (int) $teacher->id && (int) $course->school_id === (int) $teacher->school_id, 404);
        $this->engine->count((int) $data['planned_minutes']);

        return NumberSystemGameSession::query()->create([
            'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'teaching_course_id' => $course->id,
            'title' => trim($data['title']), 'join_code' => strtoupper(bin2hex(random_bytes(5))),
            'seed' => bin2hex(random_bytes(32)), 'engine_version' => NumberSystemEngine::VERSION,
            'planned_minutes' => $data['planned_minutes'], 'difficulty' => $data['difficulty'], 'mode' => $data['mode'], 'status' => 'waiting',
        ]);
    }

    public function control(NumberSystemGameSession $session, string $action): NumberSystemGameSession
    {
        return DB::transaction(function () use ($session, $action): NumberSystemGameSession {
            $locked = NumberSystemGameSession::query()->lockForUpdate()->findOrFail($session->id);
            $allowed = ['start' => ['waiting'], 'pause' => ['running'], 'resume' => ['paused'], 'end' => ['waiting', 'running', 'paused']];
            if (! in_array($locked->status, $allowed[$action] ?? [], true)) {
                throw ValidationException::withMessages(['action' => ['Diese Aktion passt nicht zum aktuellen Rundenstatus. Bitte aktualisiere die Übersicht.']]);
            }
            $locked->status = match ($action) {
                'start', 'resume' => 'running', 'pause' => 'paused', 'end' => 'ended'
            };
            if ($action === 'start') {
                $locked->started_at = now();
            }
            if ($action === 'end') {
                $locked->ended_at = now();
            }
            $locked->save();

            return $locked;
        }, 3);
    }

    public function assertEnrolled(User $student, NumberSystemGameSession $session): void
    {
        abort_unless((int) $student->school_id === (int) $session->school_id
            && TeachingCourseStudent::query()->where('teaching_course_id', $session->teaching_course_id)
                ->where('user_id', $student->id)->whereNull('canceled_at')->exists(), 404, 'Diese Runde gehört nicht zu deinem aktiven Kurs.');
    }

    public function join(User $student, string $code, ?string $teamName, ?string $teamCode): NumberSystemGameRun
    {
        return DB::transaction(function () use ($student, $code, $teamName, $teamCode): NumberSystemGameRun {
            $session = NumberSystemGameSession::query()->where('join_code', strtoupper(trim($code)))->lockForUpdate()->firstOrFail();
            $this->assertEnrolled($student, $session);
            $invitedRun = null;
            if (filled($teamCode)) {
                abort_unless($session->mode === 'teams', 422, 'Diese Runde verwendet keine privaten Teams. Verwende den Kurscode ohne Teamcode.');
                $invitedRun = NumberSystemGameRun::query()->where('session_id', $session->id)->where('team_code', strtoupper(trim($teamCode)))->lockForUpdate()->firstOrFail();
            }
            $member = DB::table('number_system_game_members')->where('session_id', $session->id)->where('user_id', $student->id)->first();
            if ($member) {
                abort_if($invitedRun && (int) $invitedRun->id !== (int) $member->run_id, 409, 'Du gehörst in dieser Runde bereits zu einem anderen Team. Öffne deinen gespeicherten Spielstand.');

                return NumberSystemGameRun::query()->findOrFail($member->run_id);
            }
            abort_if($session->status === 'ended', 422, 'Diese Runde ist bereits beendet. Deine Lehrkraft kann ein neues Abenteuer öffnen.');
            if ($invitedRun) {
                $run = $invitedRun;
                abort_if($run->members()->count() >= 4, 422, 'Dieses Team ist vollständig (maximal vier Personen).');
                abort_if($run->completed_at, 422, 'Dieses Team hat das Abenteuer bereits abgeschlossen.');
            } else {
                if ($session->mode === 'teams') {
                    if (! filled($teamName)) {
                        throw ValidationException::withMessages(['team_name' => ['Gib deinem neuen Team einen Namen oder verwende den Teamcode deiner Gruppe.']]);
                    }
                    abort_if(NumberSystemGameRun::query()->where('session_id', $session->id)->where('team_name', trim($teamName))->exists(), 422, 'Dieser Teamname ist schon vergeben. Frage nach dem Teamcode oder wähle einen anderen Namen.');
                }
                abort_unless($session->engine_version === NumberSystemEngine::VERSION, 409, 'Diese Spielversion kann keine neuen Einstiege mehr erstellen.');
                $puzzles = [];
                foreach (range(0, $this->engine->count($session->planned_minutes) - 1) as $position) {
                    $puzzles[] = $this->engine->mission($session->seed, (int) $student->id, $position, $session->planned_minutes, $session->difficulty);
                }
                $run = NumberSystemGameRun::query()->create(['session_id' => $session->id, 'user_id' => $student->id,
                    'team_name' => $session->mode === 'teams' ? trim($teamName) : null,
                    'team_code' => $session->mode === 'teams' ? strtoupper(bin2hex(random_bytes(4))) : null,
                    'puzzles' => $puzzles, 'draft' => $this->initialDraft($puzzles[0]), 'history' => []]);
            }
            $run->members()->attach($student->id, ['session_id' => $session->id]);

            return $run->refresh();
        }, 3);
    }

    public function authorizeRun(User $student, NumberSystemGameRun $run): void
    {
        $run->loadMissing('session');
        $this->assertEnrolled($student, $run->session);
        abort_unless($run->members()->where('users.id', $student->id)->exists(), 404);
    }

    /** @return array<string, mixed> */
    public function mutate(NumberSystemGameRun $run, User $student, string $action, array $data): array
    {
        return DB::transaction(function () use ($run, $student, $action, $data): array {
            $session = NumberSystemGameSession::query()->lockForUpdate()->findOrFail($run->session_id);
            $locked = NumberSystemGameRun::query()->lockForUpdate()->findOrFail($run->id);
            $locked->setRelation('session', $session);
            $this->authorizeRun($student, $locked);
            abort_if((int) $data['revision'] !== $locked->revision, 409, 'Der Spielstand wurde in einem anderen Fenster oder Gerät verändert. Lade den gespeicherten Stand, bevor du weiterarbeitest.');
            abort_if($locked->completed_at || $session->status === 'ended', 422, 'Dieses Abenteuer ist abgeschlossen. Der gespeicherte Fortschritt bleibt erhalten.');
            $mission = $locked->puzzles[$locked->position];
            abort_unless(hash_equals($mission['id'], $data['mission_id']), 409, 'Die angezeigte Mission ist nicht mehr aktuell. Bitte lade den gespeicherten Stand.');
            if ($action !== 'draft') {
                abort_unless($session->status === 'running', 422, 'Die Lehrkraft hat noch nicht gestartet oder die Runde ist pausiert. Dein Entwurf bleibt gespeichert.');
            }
            if ($action === 'draft' || $action === 'answer') {
                abort_if($locked->solved, 422, 'Dieser Datenweg ist bereits bestätigt. Öffne die nächste Mission.');
                $locked->draft = $this->cleanDraft($mission, $data['draft']);
            }
            if ($action === 'answer') {
                abort_if($session->mode === 'teams' && $mission['kind'] === 'vault' && $locked->members()->count() < 2, 422, 'Für die verteilten Hinweise brauchst du mindestens eine weitere Person im Team. Gib deinen Teamcode weiter.');
                $feedback = $this->engine->check($mission, $locked->draft);
                $locked->attempts = min(65535, $locked->attempts + 1);
                $locked->feedback = $feedback;
                $locked->solved = $feedback['correct'];
                if ($locked->solved) {
                    $history = $locked->history ?? [];
                    $history[] = ['id' => $mission['id'], 'position' => $locked->position, 'kind' => $mission['kind'],
                        'directions' => $mission['directions'], 'attempts' => $locked->attempts, 'hints' => $locked->hints];
                    $locked->history = $history;
                }
            } elseif ($action === 'hint') {
                $locked->hints = min(3, $locked->hints + 1);
            } elseif ($action === 'advance') {
                abort_unless($locked->solved, 422, 'Bestätige zuerst diesen Datenweg.');
                if ($locked->position === count($locked->puzzles) - 1) {
                    $locked->completed_at = now();
                } else {
                    $locked->position++;
                    $locked->solved = false;
                    $locked->hints = 0;
                    $locked->attempts = 0;
                    $locked->feedback = null;
                    $locked->draft = $this->initialDraft($locked->puzzles[$locked->position]);
                }
            }
            $locked->revision++;
            $locked->save();

            return $this->state($locked, $student);
        }, 3);
    }

    /** @return array<string, mixed> */
    public function state(NumberSystemGameRun $run, User $student): array
    {
        $run->loadMissing(['session', 'members:id,first_name,last_name']);
        $session = $run->session;
        $mission = $run->puzzles[$run->position];
        $public = $this->engine->publicMission($mission);
        $members = $run->members->sortBy('id')->values();
        if ($session->mode === 'teams' && $mission['kind'] === 'vault') {
            $memberIndex = $members->search(fn (User $member): bool => (int) $member->id === (int) $student->id);
            $public['role_cards'] = array_values(array_filter($mission['private_clues'] ?? [], fn (string $clue, int $index): bool => ($index + $run->position) % max(1, $members->count()) === $memberIndex, ARRAY_FILTER_USE_BOTH));
            $public['prompt'] = 'Vier benötigte Hinweise sind auf eure persönlichen Zugänge verteilt. Erklärt eure Karte mündlich und verbindet die Angaben. Wechselt beim nächsten Tresor die erklärende Person.';
            foreach ($public['fields'] as &$field) {
                $field['label'] = $field['public_label'];
            }
            unset($field);
            $public['cooperative'] = true;
        }

        return ['id' => $run->id, 'revision' => $run->revision, 'position' => $run->position, 'total' => count($run->puzzles),
            'solved' => $run->solved, 'completed' => $run->completed_at !== null, 'feedback' => $run->feedback,
            'draft' => (object) ($run->draft ?? []), 'hints' => $run->hints,
            'hint_texts' => array_slice($mission['hints'], 0, $run->hints),
            'history' => $run->history ?? [], 'mission' => $session->status === 'waiting' ? null : $public,
            'team_name' => $run->team_name, 'team_code' => $run->team_code,
            'invitation' => $run->team_code && ! $run->completed_at && $session->status !== 'ended'
                ? app(NumberSystemInvitation::class)->make($session->join_code, $run->team_code) : null,
            'members' => $members->map(fn (User $member): string => trim($member->first_name.' '.$member->last_name))->all(),
            'session' => ['id' => $session->id, 'title' => $session->title, 'status' => $session->status,
                'planned_minutes' => $session->planned_minutes, 'difficulty' => $session->difficulty, 'mode' => $session->mode]];
    }

    private function initialDraft(array $mission): array
    {
        $draft = [];
        foreach ($mission['fields'] as $field) {
            if (array_key_exists('initial', $field)) {
                $draft[$field['name']] = $field['initial'];
            } elseif ($field['widget'] === 'bits') {
                $draft[$field['name']] = str_repeat('0', $field['width']);
            } elseif ($field['widget'] === 'weights') {
                $draft[$field['name']] = [];
            }
        }

        return $draft;
    }

    private function cleanDraft(array $mission, array $draft): array
    {
        $clean = [];
        foreach ($mission['fields'] as $field) {
            $value = $draft[$field['name']] ?? null;
            if ($value === null) {
                continue;
            }
            abort_unless(is_string($value) && mb_strlen($value) <= 500 || $field['widget'] === 'weights' && is_array($value) && count($value) <= 12 && count(array_filter($value, fn ($item): bool => is_int($item) || is_string($item) && ctype_digit($item))) === count($value), 422, 'Der Eingabewert hat kein gültiges Format.');
            $clean[$field['name']] = $value;
        }

        return $clean;
    }
}
