<?php

namespace {
    require_once __DIR__.'/../../../bootstrap/itstudioat-spa-moved/src/Commands/SpaPackages.php';
}

namespace Tests\Feature\Console {
    use Illuminate\Console\OutputStyle;
    use Itstudioat\Spa\Commands\SpaPackages;
    use Symfony\Component\Console\Input\ArrayInput;
    use Symfony\Component\Console\Output\BufferedOutput;

    it('refuses to run the deprecated spa packages workflow', function (): void {
        $command = new SpaPackages;
        $command->setLaravel(app());
        $input = new ArrayInput([]);
        $output = new BufferedOutput;
        $command->setOutput(new OutputStyle($input, $output));

        $exitCode = $command->handle();

        expect($exitCode)->toBe(1);
        expect($output->fetch())->toContain('spa:packages is deprecated; use php artisan app:update instead.');
    });
}
