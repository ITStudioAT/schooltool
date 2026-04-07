<?php

namespace {
    require_once __DIR__.'/../../../bootstrap/itstudioat-spa-moved/src/Commands/SpaComplete.php';
    require_once __DIR__.'/../../../bootstrap/itstudioat-spa-moved/src/Commands/SpaUpdate.php';
}

namespace Tests\Feature\Console {
    use Illuminate\Console\OutputStyle;
    use Itstudioat\Spa\Commands\SpaComplete;
    use Itstudioat\Spa\Commands\SpaUpdate;
    use Symfony\Component\Console\Input\ArrayInput;
    use Symfony\Component\Console\Output\BufferedOutput;

    dataset('deprecated_spa_commands', [
        'spa:complete' => [
            SpaComplete::class,
            'spa:complete is deprecated; use php artisan app:update instead.',
        ],
        'spa:update' => [
            SpaUpdate::class,
            'spa:update is deprecated; use php artisan app:update instead.',
        ],
    ]);

    it('refuses to run deprecated spa workflow commands', function (string $commandClass, string $message): void {
        $command = new $commandClass;
        $command->setLaravel(app());
        $input = new ArrayInput([]);
        $output = new BufferedOutput;
        $command->setOutput(new OutputStyle($input, $output));

        $exitCode = $command->handle();

        expect($exitCode)->toBe(1);
        expect($output->fetch())->toContain($message);
    })->with('deprecated_spa_commands');
}
