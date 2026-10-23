<?php

namespace App\Services\NumberSystems;

use App\Models\NumberSystemGameRun;
use App\Models\NumberSystemGameSession;
use App\Models\TeachingCourse;
use App\Models\User;
use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class NumberSystemLocalProbe
{
    public function __construct(private NumberSystemGameService $games, private NumberSystemInvitation $invitations) {}

    public function available(Request $request): bool
    {
        return app()->environment('local') && ! config('schooltool.preview.instance')
            && in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true);
    }

    public function listing(int $owner): array
    {
        $items = [];
        foreach (glob($this->root($owner).DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'probe.json') ?: [] as $file) {
            $receipt = json_decode((string) @file_get_contents($file), true);
            if (($receipt['format'] ?? '') === 'number-system-local-probe-v1' && ($receipt['owner'] ?? null) === $owner && ($receipt['state'] ?? '') === 'ready') {
                $items[] = ['id' => $receipt['id'], 'title' => $receipt['title'] ?? 'Gespeicherte Proberunde',
                    'created_at' => $receipt['created_at'], 'url' => $this->url($receipt['id'])];
            }
        }
        usort($items, fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return $items;
    }

    public function create(int $owner, array $data): array
    {
        $selfPlay = (bool) ($data['self_play'] ?? false);
        if ($selfPlay) {
            $data = [...$data, 'mode' => 'solo', 'short' => false, 'ranking_enabled' => false];
        }
        $title = trim($data['title'] ?? '') ?: ($selfPlay ? 'Mein Abenteuer' : 'Lokaler Probelauf');
        $id = (string) Str::uuid();
        $directory = $this->root($owner).DIRECTORY_SEPARATOR.$id;
        if (! mkdir($directory, 0700, true)) {
            throw new RuntimeException('Der lokale Probelauf konnte nicht angelegt werden.');
        }
        $receipt = ['format' => 'number-system-local-probe-v1', 'owner' => $owner, 'id' => $id,
            'created_at' => now()->toIso8601String(), 'title' => $title, 'self_play' => $selfPlay, 'short' => $data['short'], 'state' => 'creating'];
        $this->writeReceipt($directory, $receipt);
        touch($directory.DIRECTORY_SEPARATOR.'game.sqlite');
        touch($directory.DIRECTORY_SEPARATOR.'probe.lock');
        try {
            $this->connection($directory, function () use ($data, $selfPlay, $title): void {
                Schema::create('schools', fn (Blueprint $table) => $table->id());
                Schema::create('users', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('school_id');
                    $table->string('first_name');
                    $table->string('last_name');
                    $table->timestamps();
                });
                Schema::create('teaching_courses', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('school_id');
                    $table->unsignedBigInteger('user_id');
                    $table->string('title');
                    $table->timestamps();
                    $table->softDeletes();
                });
                Schema::create('teaching_course_students', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('teaching_course_id');
                    $table->unsignedBigInteger('user_id');
                    $table->timestamp('canceled_at')->nullable();
                    $table->softDeletes();
                });
                (require database_path('migrations/2026_10_16_225024_create_number_system_game_tables.php'))->up();
                $this->upgradeTiming();
                DB::table('schools')->insert(['id' => 1]);
                $people = $selfPlay ? [1 => 'Test-Lehrkraft', 2 => 'Du'] : [1 => 'Test-Lehrkraft', 2 => 'Testperson A', 3 => 'Testperson B'];
                foreach ($people as $id => $name) {
                    DB::table('users')->insert(['id' => $id, 'school_id' => 1, 'first_name' => $name, 'last_name' => '']);
                }
                DB::table('teaching_courses')->insert(['id' => 1, 'school_id' => 1, 'user_id' => 1, 'title' => 'Lokaler Probekurs']);
                foreach ($selfPlay ? [2] : [2, 3] as $student) {
                    DB::table('teaching_course_students')->insert(['teaching_course_id' => 1, 'user_id' => $student]);
                }
                $session = $this->games->create(User::findOrFail(1), TeachingCourse::findOrFail(1), [
                    ...$data, 'title' => $title]);
                $session->update(['join_code' => 'P-'.strtoupper(bin2hex(random_bytes(4)))]);
                if ($selfPlay) {
                    $student = User::findOrFail(2);
                    $run = $this->games->join($student, $session->join_code, null, null);
                    $this->games->control($session, 'start');
                    app(NumberSystemPresence::class)->record($run, $student);
                }
            });
            $receipt['state'] = 'ready';
            $this->writeReceipt($directory, $receipt);
        } catch (Throwable $failure) {
            $this->removeFiles($directory);
            throw $failure;
        }

        return ['id' => $id, 'url' => $this->url($id)];
    }

    public function show(int $owner, string $probe): array
    {
        return $this->within($owner, $probe, function (array $receipt) use ($probe): array {
            $session = NumberSystemGameSession::firstOrFail();
            $runs = NumberSystemGameRun::with('members')->get();
            $teamCode = $runs->first()?->team_code;
            $results = app(NumberSystemGameResults::class);
            $presence = app(NumberSystemPresence::class);
            $seen = $presence->snapshot($session);

            return ['id' => $probe, 'short' => $receipt['short'], 'session' => $session->toArray(),
                'clock' => $results->clock($session), 'ranking' => $results->ranking($session),
                'players' => array_map(fn (int $participant): array => ['participant' => $participant, 'label' => ($receipt['self_play'] ?? false) ? 'Du' : ($participant === 1 ? 'Testperson A' : 'Testperson B'),
                    'url' => ($receipt['self_play'] ?? false) ? $this->url($probe) : $this->url($probe, ['participant' => $participant, 'code' => $session->join_code, ...($participant === 2 && $teamCode ? ['team_code' => $teamCode] : [])])], ($receipt['self_play'] ?? false) ? [1] : [1, 2]),
                'runs' => $runs->map(fn ($run): array => ['name' => $run->team_name ?: $run->members->first()?->first_name,
                    'members' => $run->members->pluck('first_name')->all(), 'solved' => count($run->history ?? []), 'total' => count($run->puzzles), 'completed' => $run->completed_at !== null, 'clock' => $results->clock($session, $run), 'presence' => $presence->members($run, $seen)])->all()];
        });
    }

    public function control(int $owner, string $probe, string $action): array
    {
        $this->within($owner, $probe, fn () => $this->games->control(NumberSystemGameSession::firstOrFail(), $action));

        return $this->show($owner, $probe);
    }

    public function player(int $owner, string $probe, int $participant): array
    {
        return $this->within($owner, $probe, function (array $receipt) use ($probe, $participant): array {
            $student = $this->student($participant);
            $membership = DB::table('number_system_game_members')->where('user_id', $student->id)->first();
            if ($membership) {
                app(NumberSystemPresence::class)->record(NumberSystemGameRun::findOrFail($membership->run_id), $student);
            }

            return ['id' => $probe, 'participant' => $participant, 'label' => $student->first_name, 'short' => $receipt['short'],
                'session' => NumberSystemGameSession::firstOrFail()->toArray(),
                'run' => $membership ? $this->state(NumberSystemGameRun::findOrFail($membership->run_id), $student, $probe, $participant) : null];
        });
    }

    public function join(int $owner, string $probe, int $participant, array $data): array
    {
        return $this->within($owner, $probe, function (array $receipt) use ($probe, $participant, $data): array {
            $student = $this->student($participant);
            $run = $this->games->join($student, $data['code'], $data['team_name'] ?? null, $data['team_code'] ?? null);
            app(NumberSystemPresence::class)->record($run, $student);
            if ($run->team_code && ctype_xdigit($run->team_code)) {
                $run->update(['team_code' => 'P'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 7))]);
            }
            if ($receipt['short'] && count($run->puzzles) > 3) {
                $puzzles = $run->puzzles;
                $vault = collect($puzzles)->firstWhere('kind', 'vault');
                $bits = collect($puzzles)->firstWhere('kind', 'bits');
                $run->update(['puzzles' => [$vault, $bits, $puzzles[array_key_last($puzzles)]], 'draft' => []]);
            }

            return $this->state($run->refresh(), $student, $probe, $participant);
        });
    }

    public function update(int $owner, string $probe, int $participant, array $data): array
    {
        return $this->within($owner, $probe, function () use ($probe, $participant, $data): array {
            $student = $this->student($participant);
            $member = DB::table('number_system_game_members')->where('user_id', $student->id)->first();
            abort_unless($member, 404);
            $run = NumberSystemGameRun::findOrFail($member->run_id);
            $this->games->mutate($run, $student, $data['action'], $data);

            return $this->state($run->fresh(), $student, $probe, $participant);
        });
    }

    public function presence(int $owner, string $probe, int $participant): array
    {
        return $this->within($owner, $probe, function () use ($participant): array {
            $student = $this->student($participant);
            $member = DB::table('number_system_game_members')->where('user_id', $student->id)->first();
            abort_unless($member, 404);

            return ['recorded' => app(NumberSystemPresence::class)->record(NumberSystemGameRun::findOrFail($member->run_id), $student)];
        });
    }

    public function remove(int $owner, string $probe): void
    {
        $directory = $this->directory($owner, $probe);
        $this->within($owner, $probe, function (array $receipt) use ($directory): void {
            $receipt['state'] = 'deleting';
            $this->writeReceipt($directory, $receipt);
        });
        $this->removeFiles($directory);
    }

    private function state(NumberSystemGameRun $run, User $student, string $probe, int $participant): array
    {
        $this->games->authorizeRun($student, $run);
        $state = $this->games->state($run, $student);
        if ($state['invitation']) {
            $state['invitation'] = $this->invitations->forUrl($this->url($probe, ['participant' => $participant === 1 ? 2 : 1,
                'code' => $run->session->join_code, 'team_code' => $run->team_code]));
        }

        return $state;
    }

    private function student(int $participant): User
    {
        abort_unless(in_array($participant, [1, 2], true), 404);

        return User::findOrFail($participant + 1);
    }

    private function within(int $owner, string $probe, Closure $callback): mixed
    {
        $directory = $this->directory($owner, $probe);
        $lock = fopen($directory.DIRECTORY_SEPARATOR.'probe.lock', 'r+');
        if (! $lock || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Der Probelauf ist gerade nicht verfügbar.');
        }
        try {
            $receipt = $this->receipt($directory, $owner, $probe);
            abort_unless($receipt['state'] === 'ready', 404);

            return $this->connection($directory, function () use ($callback, $receipt): mixed {
                $this->upgradeTiming();

                return $callback($receipt);
            });
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function connection(string $directory, Closure $callback): mixed
    {
        $previous = DB::getDefaultConnection();
        $previousSchema = Schema::getFacadeRoot();
        $oldConfig = config('database.connections.number_system_probe');
        config(['database.connections.number_system_probe' => ['driver' => 'sqlite', 'database' => $directory.DIRECTORY_SEPARATOR.'game.sqlite',
            'prefix' => '', 'foreign_key_constraints' => true, 'busy_timeout' => 5000]]);
        DB::purge('number_system_probe');
        DB::setDefaultConnection('number_system_probe');
        Schema::swap(DB::connection('number_system_probe')->getSchemaBuilder());
        try {
            return $callback();
        } finally {
            Schema::swap($previousSchema);
            DB::setDefaultConnection($previous);
            DB::purge('number_system_probe');
            config(['database.connections.number_system_probe' => $oldConfig]);
        }
    }

    private function root(int $owner): string
    {
        return storage_path('app/private/number-system-probes/'.$owner);
    }

    private function upgradeTiming(): void
    {
        if (! Schema::hasColumn('number_system_game_sessions', 'timer_mode')) {
            (require database_path('migrations/2026_10_17_143636_add_timing_to_number_system_games.php'))->up();
        }
        if (! Schema::hasColumn('number_system_game_members', 'last_seen_at')) {
            (require database_path('migrations/2026_10_17_144456_add_presence_to_number_system_game_members.php'))->up();
        }
    }

    private function directory(int $owner, string $probe): string
    {
        abort_unless(Str::isUuid($probe), 404);
        $path = $this->root($owner).DIRECTORY_SEPARATOR.$probe;
        $directory = realpath($path);
        abort_unless($directory && ! is_link($path) && dirname($directory) === realpath($this->root($owner)), 404);
        foreach (['game.sqlite', 'probe.json', 'probe.lock'] as $file) {
            abort_unless(is_file($directory.DIRECTORY_SEPARATOR.$file) && ! is_link($directory.DIRECTORY_SEPARATOR.$file), 404);
        }
        $this->receipt($directory, $owner, $probe);

        return $directory;
    }

    private function receipt(string $directory, int $owner, string $probe): array
    {
        $receipt = json_decode((string) @file_get_contents($directory.DIRECTORY_SEPARATOR.'probe.json'), true);
        abort_unless(is_array($receipt) && ($receipt['format'] ?? '') === 'number-system-local-probe-v1'
            && ($receipt['owner'] ?? null) === $owner && ($receipt['id'] ?? '') === $probe, 404);

        return $receipt;
    }

    private function writeReceipt(string $directory, array $receipt): void
    {
        if (file_put_contents($directory.DIRECTORY_SEPARATOR.'probe.json', json_encode($receipt, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('Der lokale Besitznachweis konnte nicht gespeichert werden.');
        }
    }

    private function removeFiles(string $directory): void
    {
        foreach (['game.sqlite', 'game.sqlite-journal', 'game.sqlite-wal', 'game.sqlite-shm', 'probe.json', 'probe.lock'] as $file) {
            $path = $directory.DIRECTORY_SEPARATOR.$file;
            if (is_file($path) && ! is_link($path) && ! unlink($path)) {
                throw new RuntimeException('Die eigenen Probedaten konnten nicht vollständig entfernt werden.');
            }
        }
        if (! rmdir($directory)) {
            throw new RuntimeException('Der eigene Probeordner ist nicht leer.');
        }
    }

    private function url(string $probe, array $query = []): string
    {
        return route('admin.teaching-tools', ['any' => 'games/number-systems/local/'.$probe, ...$query]);
    }
}
