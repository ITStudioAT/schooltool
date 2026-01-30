<?php

namespace App\Console\Commands {
    function file_exists(string $path): bool
    {
        return false;
    }
}

namespace Tests\Feature\Console {
    use App\Console\Commands\AppUpdateCommand;
    use App\Services\InstallUpdateService;
    use App\Services\RecordsCreateService;
    use Illuminate\Support\Facades\Artisan;
    use Mockery;
    use Illuminate\Console\OutputStyle;
    use Symfony\Component\Console\Input\ArrayInput;
    use Symfony\Component\Console\Output\BufferedOutput;
    use Tests\TestCase;

    it('runs update workflow without frontend build when package.json is absent', function () {
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
            'lunch_user',
            'teaching_admin',
            'student',
        ])->once();
        $install->shouldReceive('findOrCreateFolders')->once();
        $install->shouldReceive('clearDebugbar')->once();
        $records->shouldReceive('initRecords')->once();

        app()->instance(InstallUpdateService::class, $install);
        app()->instance(RecordsCreateService::class, $records);

        Artisan::shouldReceive('call')->with('migrate', ['--force' => true])->once()->andReturn(0);
        Artisan::shouldReceive('output')->twice()->andReturn('');
        Artisan::shouldReceive('call')->with('optimize:clear')->once()->andReturn(0);
        Artisan::shouldReceive('call')->with('queue:restart')->once()->andReturn(0);

        $command = new AppUpdateCommand();
        $command->setLaravel(app());
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $command->setOutput(new OutputStyle($input, $output));

        $exitCode = $command->handle($install, $records);

        expect($exitCode)->toBe(0);
    });
}
