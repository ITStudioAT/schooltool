<?php

namespace App\Console\Commands {
    function exec(string $command, ?array &$output = null): int
    {
        $output = $GLOBALS['queue_health_exec_output'] ?? [];
        return 0;
    }

    function sleep(int $seconds): int
    {
        return 0;
    }
}

namespace Tests\Feature\Console {
    use App\Console\Commands\QueueHealthCheck;
    use Carbon\Carbon;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Log;
    use Mockery;
    use Tests\TestCase;

    it('reports running worker and logs stuck jobs', function () {
        $GLOBALS['queue_health_exec_output'] = ['queue:work'];

        $query = Mockery::mock();
        DB::shouldReceive('table')->with('jobs')->andReturn($query);
        $query->shouldReceive('whereNotNull')->with('reserved_at')->andReturnSelf();
        $query->shouldReceive('where')
            ->with('reserved_at', '<', Mockery::type(Carbon::class))
            ->andReturnSelf();
        $query->shouldReceive('count')->andReturn(2);

        Log::shouldReceive('warning')->once();

        $this->artisan('queue:health-check')->assertExitCode(0);
    });

    it('fails when worker is not running and restart is not requested', function () {
        $GLOBALS['queue_health_exec_output'] = [];

        Log::shouldReceive('error')->once();

        $this->artisan('queue:health-check')->assertExitCode(1);
    });
}
