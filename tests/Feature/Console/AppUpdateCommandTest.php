<?php

namespace Tests\Feature\Console;

use App\Console\Commands\AppUpdateCommand;
use App\Services\InstallUpdateService;
use App\Services\RecordsCreateService;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Mockery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function fakeAppUpdateFiles(
    array $missingPaths = [],
    bool $nodeModulesExists = false,
    bool $nodeModulesDeleteSucceeds = true,
): void {
    File::shouldReceive('exists')
        ->andReturnUsing(function (string $path) use ($missingPaths): bool {
            return ! in_array($path, $missingPaths, true);
        });

    File::shouldReceive('ensureDirectoryExists')
        ->zeroOrMoreTimes()
        ->andReturnNull();
    File::shouldReceive('isDirectory')
        ->zeroOrMoreTimes()
        ->with(base_path('node_modules'))
        ->andReturn($nodeModulesExists);
    File::shouldReceive('deleteDirectory')
        ->zeroOrMoreTimes()
        ->with(base_path('node_modules'))
        ->andReturn($nodeModulesDeleteSucceeds);
    File::shouldReceive('delete')
        ->zeroOrMoreTimes()
        ->andReturnTrue();
}

function fakeAppUpdateProcesses(string|array|null $npmCiError = null): void
{
    $npmCiErrors = match (true) {
        is_array($npmCiError) => array_values($npmCiError),
        $npmCiError !== null => [$npmCiError],
        default => [],
    };
    $npmCiAttempt = 0;

    Process::fake(function ($process) use ($npmCiErrors, &$npmCiAttempt) {
        $command = implode(' ', $process->command);

        if (str_contains($command, 'node --version')) {
            return Process::result('v22.18.0');
        }

        if (str_contains($command, 'npm --version')) {
            return Process::result('10.8.2');
        }

        if (str_contains($command, 'check_node_version.cjs')) {
            return Process::result('Node v22.18.0 OK');
        }

        if (str_contains($command, 'npm ci')) {
            $currentError = $npmCiErrors[$npmCiAttempt] ?? null;
            $npmCiAttempt++;

            if ($currentError !== null) {
                return Process::describe()
                    ->errorOutput($currentError)
                    ->exitCode(1)
                    ->iterations(2);
            }

            return Process::describe()
                ->output('npm ci complete')
                ->iterations(2);
        }

        if (str_contains($command, 'powershell -NoProfile -ExecutionPolicy Bypass -Command')) {
            return Process::result('');
        }

        if (str_contains($command, 'npm run build')) {
            return Process::describe()
                ->output('frontend build complete')
                ->iterations(2);
        }

        if (str_contains($command, 'Get-CimInstance Win32_Process')) {
            return Process::result('Stopped project-local Node/esbuild processes');
        }

        return Process::result('', 'Unexpected process: '.$command, 1);
    });
}

/**
 * @return array{exit_code: int, output: string}
 */
function runAppUpdateCommand(
    InstallUpdateService $install,
    RecordsCreateService $records,
    ?AppUpdateCommand $command = null,
): array {
    $command ??= app()->make(AppUpdateCommand::class);
    $input = new ArrayInput([]);
    $output = new BufferedOutput;

    $command->setLaravel(app());
    $command->setOutput(new OutputStyle($input, $output));

    $exitCode = $command->handle($install, $records);

    return [
        'exit_code' => $exitCode,
        'output' => $output->fetch(),
    ];
}

function appUpdateCommandWithNodeModulesCleanup(): AppUpdateCommand
{
    return new class extends AppUpdateCommand
    {
        protected function shouldCleanNodeModulesBeforeNpmCi(): bool
        {
            return true;
        }
    };
}

it('runs the full update workflow end to end', function (): void {
    fakeAppUpdateFiles();
    fakeAppUpdateProcesses();

    $install = Mockery::mock(InstallUpdateService::class);
    $records = Mockery::mock(RecordsCreateService::class);

    $install->shouldReceive('clearModels')->once();
    $install->shouldReceive('createRoles')->with([
        'super_admin',
        'admin',
        'register_admin',
        'register_user',
        'tutoring_user',
        'tutoring_admin',
        'teacher',
        'lunch_admin',
        'lunch_candidate',
        'lunch_user',
        'teaching_admin',
        'materials_admin',
        'student',
        'materials_moderator',
        'aba_teacher',
        'studentstimetables_admin',
        'studentstimetables_moderator',
        'studentstimetables_user',
    ])->once();
    $install->shouldReceive('findOrCreateFolders')->once();
    $install->shouldReceive('pruneOrphanPrivateSchoolFolders')
        ->once()
        ->andReturn(['deleted' => [], 'failed' => []]);
    $install->shouldReceive('clearDebugbar')->once();
    $install->shouldReceive('normalizeRestaurantUserRoles')
        ->once()
        ->andReturn([
            'restaurant_confirmed_backfilled' => 0,
            'lunch_user_roles_assigned' => 0,
            'lunch_candidate_roles_removed' => 0,
        ]);
    $records->shouldReceive('initRecords')->once();

    app()->instance(InstallUpdateService::class, $install);
    app()->instance(RecordsCreateService::class, $records);

    Artisan::shouldReceive('call')->with('config:clear', [])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('migrate', ['--force' => true])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('schooltool:backfill-school-user-licences', [])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('schooltool:backfill-teaching-course-work-group-students', [])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('optimize:clear', [])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('horizon:terminate', [])->once()->andReturn(0);
    Artisan::shouldReceive('output')->times(6)->andReturn('');

    $result = runAppUpdateCommand($install, $records);

    expect($result['exit_code'])->toBe(0);
    expect($result['output'])->toContain('▶ PREFLIGHT VALIDATION');
    expect($result['output'])->toContain('Checking the toolbox before we start turning screws.');
    expect($result['output'])->toContain('Using npm cache: '.base_path('storage/framework/npm-cache'));
    expect($result['output'])->toContain('▶ INSTALLING FRONTEND DEPENDENCIES');
    expect($result['output'])->toContain('npm is arranging a very large drawer of tiny packages.');
    expect($result['output'])->toContain('Still installing dependencies. npm is sorting versions, scripts, and small opinions.');
    expect($result['output'])->toContain('▶ BUILDING FRONTEND');
    expect($result['output'])->toContain('Vite is baking the frontend. Please enjoy the smell of compiled assets.');
    expect($result['output'])->toContain('Still building. Vite is transforming modules and keeping count.');
    expect($result['output'])->toContain('▶ CLEARING CONFIG CACHE');
    expect($result['output'])->toContain('Dusting off cached config so Laravel reads the fresh notes.');
    expect($result['output'])->toContain('▶ MIGRATIONS');
    expect($result['output'])->toContain('Checking the database floorboards before anyone steps on them.');
    expect($result['output'])->toContain('▶ LICENCE BACKFILL');
    expect($result['output'])->toContain('Matching licences with their users. Tiny paperwork parade.');
    expect($result['output'])->toContain('▶ TEACHING WORK GROUP INDEX BACKFILL');
    expect($result['output'])->toContain('Tidying teaching work group indexes so future searches feel snappy.');

    Process::assertRan(fn ($process) => str_contains(implode(' ', $process->command), 'node --version'));
    Process::assertRan(fn ($process) => str_contains(implode(' ', $process->command), 'npm --version'));
    Process::assertRan(fn ($process) => str_contains(implode(' ', $process->command), 'check_node_version.cjs'));
    Process::assertRan(fn ($process) => str_contains(implode(' ', $process->command), 'npm ci')
        && ($process->environment['NPM_CONFIG_CACHE'] ?? null) === base_path('storage/framework/npm-cache')
        && ($process->environment['npm_config_cache'] ?? null) === base_path('storage/framework/npm-cache'));
    Process::assertRanTimes(fn ($process) => str_contains(implode(' ', $process->command), 'npm ci'), 1);
    Process::assertRanTimes(fn ($process) => str_contains(implode(' ', $process->command), 'npm run build'), 1);
});

it('fails fast when a required frontend file is missing', function (): void {
    fakeAppUpdateFiles([base_path('package-lock.json')]);
    Process::fake();

    Artisan::spy();

    $install = Mockery::spy(InstallUpdateService::class);
    $records = Mockery::spy(RecordsCreateService::class);

    $result = runAppUpdateCommand($install, $records);

    expect($result['exit_code'])->toBe(1);
    expect($result['output'])->toContain('package-lock.json is missing; npm ci requires a lockfile.');

    Process::assertNothingRan();
    Artisan::shouldNotHaveReceived('call');
    $install->shouldNotHaveReceived('clearModels');
    $install->shouldNotHaveReceived('createRoles');
    $install->shouldNotHaveReceived('findOrCreateFolders');
    $install->shouldNotHaveReceived('pruneOrphanPrivateSchoolFolders');
    $install->shouldNotHaveReceived('clearDebugbar');
    $install->shouldNotHaveReceived('normalizeRestaurantUserRoles');
    $records->shouldNotHaveReceived('initRecords');
});

it('stops before backend work when npm ci fails', function (): void {
    fakeAppUpdateFiles();
    fakeAppUpdateProcesses([
        'EBUSY: resource busy or locked, unlink node_modules\\esbuild\\bin.js',
        'EBUSY: resource busy or locked, unlink node_modules\\esbuild\\bin.js',
        'EBUSY: resource busy or locked, unlink node_modules\\esbuild\\bin.js',
    ]);

    Artisan::spy();

    $install = Mockery::spy(InstallUpdateService::class);
    $records = Mockery::spy(RecordsCreateService::class);

    app()->instance(InstallUpdateService::class, $install);
    app()->instance(RecordsCreateService::class, $records);

    $result = runAppUpdateCommand($install, $records);

    expect($result['exit_code'])->toBe(1);
    expect($result['output'])->toContain('▶ INSTALLING FRONTEND DEPENDENCIES');
    expect($result['output'])->toContain('npm ci failed — aborting update.');

    Process::assertRanTimes(fn ($process) => str_contains(implode(' ', $process->command), 'npm ci'), PHP_OS_FAMILY === 'Windows' ? 3 : 1);
    Process::assertNotRan(fn ($process) => str_contains(implode(' ', $process->command), 'npm run build'));
    Artisan::shouldNotHaveReceived('call');
    $install->shouldNotHaveReceived('clearModels');
    $install->shouldNotHaveReceived('createRoles');
    $install->shouldNotHaveReceived('findOrCreateFolders');
    $install->shouldNotHaveReceived('pruneOrphanPrivateSchoolFolders');
    $install->shouldNotHaveReceived('clearDebugbar');
    $install->shouldNotHaveReceived('normalizeRestaurantUserRoles');
    $records->shouldNotHaveReceived('initRecords');
});

it('removes existing node modules before npm ci on non windows hosts', function (): void {
    fakeAppUpdateFiles(nodeModulesExists: true);
    fakeAppUpdateProcesses();

    $install = Mockery::mock(InstallUpdateService::class);
    $records = Mockery::mock(RecordsCreateService::class);

    $install->shouldReceive('clearModels')->once();
    $install->shouldReceive('createRoles')->once();
    $install->shouldReceive('findOrCreateFolders')->once();
    $install->shouldReceive('pruneOrphanPrivateSchoolFolders')
        ->once()
        ->andReturn(['deleted' => [], 'failed' => []]);
    $install->shouldReceive('clearDebugbar')->once();
    $install->shouldReceive('normalizeRestaurantUserRoles')
        ->once()
        ->andReturn([
            'restaurant_confirmed_backfilled' => 0,
            'lunch_user_roles_assigned' => 0,
            'lunch_candidate_roles_removed' => 0,
        ]);
    $records->shouldReceive('initRecords')->once();

    app()->instance(InstallUpdateService::class, $install);
    app()->instance(RecordsCreateService::class, $records);

    Artisan::shouldReceive('call')->with('config:clear', [])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('migrate', ['--force' => true])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('schooltool:backfill-school-user-licences', [])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('schooltool:backfill-teaching-course-work-group-students', [])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('optimize:clear', [])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('horizon:terminate', [])->once()->andReturn(0);
    Artisan::shouldReceive('output')->times(6)->andReturn('');

    $result = runAppUpdateCommand($install, $records, appUpdateCommandWithNodeModulesCleanup());

    expect($result['exit_code'])->toBe(0);
    expect($result['output'])->toContain('Removing existing node_modules before npm ci for a clean install.');

    File::shouldHaveReceived('deleteDirectory')
        ->once()
        ->with(base_path('node_modules'));
    Process::assertRanTimes(fn ($process) => str_contains(implode(' ', $process->command), 'npm ci'), 1);
});

it('stops before npm ci when node modules cleanup fails on non windows hosts', function (): void {
    fakeAppUpdateFiles(nodeModulesExists: true, nodeModulesDeleteSucceeds: false);
    fakeAppUpdateProcesses();

    Artisan::spy();

    $install = Mockery::spy(InstallUpdateService::class);
    $records = Mockery::spy(RecordsCreateService::class);

    $result = runAppUpdateCommand($install, $records, appUpdateCommandWithNodeModulesCleanup());

    expect($result['exit_code'])->toBe(1);
    expect($result['output'])->toContain('Could not remove existing node_modules');

    Process::assertNotRan(fn ($process) => str_contains(implode(' ', $process->command), 'npm ci'));
    Artisan::shouldNotHaveReceived('call');
});

it('stops when the school user licence backfill fails', function (): void {
    fakeAppUpdateFiles();
    fakeAppUpdateProcesses();

    $install = Mockery::spy(InstallUpdateService::class);
    $records = Mockery::spy(RecordsCreateService::class);

    app()->instance(InstallUpdateService::class, $install);
    app()->instance(RecordsCreateService::class, $records);

    Artisan::shouldReceive('call')->with('config:clear', [])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('migrate', ['--force' => true])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('schooltool:backfill-school-user-licences', [])->once()->andReturn(1);
    Artisan::shouldReceive('output')->times(3)->andReturn('', '', 'school_user_licences.role_name fehlt.');

    $result = runAppUpdateCommand($install, $records);

    expect($result['exit_code'])->toBe(1);
    expect($result['output'])->toContain('▶ LICENCE BACKFILL');
    expect($result['output'])->toContain('School user licence backfill failed — aborting update.');
    expect($result['output'])->toContain('school_user_licences.role_name fehlt.');

    Artisan::shouldNotHaveReceived('call', ['optimize:clear', []]);
    Artisan::shouldNotHaveReceived('call', ['horizon:terminate', []]);
    $install->shouldNotHaveReceived('clearModels');
    $install->shouldNotHaveReceived('createRoles');
    $install->shouldNotHaveReceived('findOrCreateFolders');
    $install->shouldNotHaveReceived('pruneOrphanPrivateSchoolFolders');
    $install->shouldNotHaveReceived('clearDebugbar');
    $install->shouldNotHaveReceived('normalizeRestaurantUserRoles');
    $records->shouldNotHaveReceived('initRecords');
});

it('retries npm ci when a windows lock error is transient', function (): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('Windows-specific retry logic.');
    }

    fakeAppUpdateFiles();
    fakeAppUpdateProcesses([
        'EPERM: operation not permitted, unlink C:\\laravel\\schooltool\\node_modules\\@esbuild\\win32-x64\\esbuild.exe',
        'EBUSY: resource busy or locked, unlink C:\\laravel\\schooltool\\node_modules\\@esbuild\\win32-x64\\esbuild.exe',
    ]);

    $install = Mockery::mock(InstallUpdateService::class);
    $records = Mockery::mock(RecordsCreateService::class);

    $install->shouldReceive('clearModels')->once();
    $install->shouldReceive('createRoles')->once();
    $install->shouldReceive('findOrCreateFolders')->once();
    $install->shouldReceive('pruneOrphanPrivateSchoolFolders')
        ->once()
        ->andReturn(['deleted' => [], 'failed' => []]);
    $install->shouldReceive('clearDebugbar')->once();
    $install->shouldReceive('normalizeRestaurantUserRoles')
        ->once()
        ->andReturn([
            'restaurant_confirmed_backfilled' => 0,
            'lunch_user_roles_assigned' => 0,
            'lunch_candidate_roles_removed' => 0,
        ]);
    $records->shouldReceive('initRecords')->once();

    app()->instance(InstallUpdateService::class, $install);
    app()->instance(RecordsCreateService::class, $records);

    Artisan::shouldReceive('call')->with('config:clear', [])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('migrate', ['--force' => true])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('schooltool:backfill-school-user-licences', [])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('schooltool:backfill-teaching-course-work-group-students', [])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('optimize:clear', [])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('horizon:terminate', [])->once()->andReturn(0);
    Artisan::shouldReceive('output')->times(6)->andReturn('');

    $result = runAppUpdateCommand($install, $records);

    expect($result['exit_code'])->toBe(0);
    expect($result['output'])->toContain('npm ci hit a Windows file lock on attempt 1 of 3');
    expect($result['output'])->toContain('npm ci hit a Windows file lock on attempt 2 of 3');
    expect($result['output'])->toContain('Detected active Vite hot mode; removed public/hot before npm ci.');
    expect($result['output'])->toContain('Attempting to stop project-local node/esbuild processes before retrying npm ci...');
    expect($result['output'])->toContain('▶ BUILDING FRONTEND');

    Process::assertRanTimes(fn ($process) => str_contains(implode(' ', $process->command), 'npm ci'), 3);
    Process::assertRanTimes(fn ($process) => str_contains(implode(' ', $process->command), 'Get-CimInstance Win32_Process'), 3);
    Process::assertRanTimes(fn ($process) => str_contains(implode(' ', $process->command), 'npm run build'), 1);
    Process::assertRanTimes(fn ($process) => str_contains(implode(' ', $process->command), 'powershell -NoProfile -ExecutionPolicy Bypass -Command'), 3);
});
