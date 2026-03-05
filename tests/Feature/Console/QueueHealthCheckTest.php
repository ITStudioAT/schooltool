<?php

namespace App\Console\Commands {
    function exec(string $command, ?array &$output = null): int
    {
        $GLOBALS['queue_health_exec_commands'][] = $command;

        if (isset($GLOBALS['queue_health_exec_output_sequence_by_command']) && is_array($GLOBALS['queue_health_exec_output_sequence_by_command'])) {
            foreach ($GLOBALS['queue_health_exec_output_sequence_by_command'] as $needle => $sequence) {
                if ($needle !== '' && str_contains($command, $needle) && is_array($sequence) && count($sequence) > 0) {
                    $nextOutput = array_shift($sequence);
                    $GLOBALS['queue_health_exec_output_sequence_by_command'][$needle] = $sequence;
                    $output = is_array($nextOutput) ? $nextOutput : [];

                    return 0;
                }
            }
        }

        if (isset($GLOBALS['queue_health_exec_output_by_command']) && is_array($GLOBALS['queue_health_exec_output_by_command'])) {
            foreach ($GLOBALS['queue_health_exec_output_by_command'] as $needle => $commandOutput) {
                if ($needle !== '' && str_contains($command, $needle)) {
                    $output = $commandOutput;

                    return 0;
                }
            }
        }

        $output = $GLOBALS['queue_health_exec_output'] ?? [];

        return 0;
    }

    function sleep(int $seconds): int
    {
        return 0;
    }

    function popen(string $command, string $mode)
    {
        $GLOBALS['queue_health_popen_commands'][] = [$command, $mode];

        return fopen('php://temp', 'r');
    }

    function pclose($handle): int
    {
        if (is_resource($handle)) {
            fclose($handle);
        }

        return 0;
    }
}

namespace Tests\Feature\Console {
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Log;
    use Mockery;

    beforeEach(function () {
        $GLOBALS['queue_health_exec_commands'] = [];
        $GLOBALS['queue_health_exec_output_by_command'] = null;
        $GLOBALS['queue_health_exec_output_sequence_by_command'] = null;
        $GLOBALS['queue_health_popen_commands'] = [];
    });

    it('reports running worker and logs stuck jobs', function () {
        $GLOBALS['queue_health_exec_output'] = ['queue:work'];

        $query = Mockery::mock();
        DB::shouldReceive('table')->with('jobs')->andReturn($query);
        $query->shouldReceive('whereNotNull')->with('reserved_at')->andReturnSelf();
        $query->shouldReceive('where')
            ->with('reserved_at', '<', Mockery::type('int'))
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
