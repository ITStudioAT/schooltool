<?php

use App\Models\User;
use App\Services\Matura\MaturaWorkflowService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = config('database.connections.mysql');
$database = (string) $connection['database'];
$ownedLocalDatabase = preg_match('/\Apest_test_test_[0-9]{24}\z/', $database) === 1;
$hostedCiDatabase = getenv('GITHUB_ACTIONS') === 'true'
    && getenv('RUNNER_ENVIRONMENT') === 'github-hosted'
    && $database === 'pest_test';
if (! app()->environment('testing')
    || config('database.default') !== 'mysql'
    || ! in_array($connection['host'], ['127.0.0.1', 'localhost', '::1'], true)
    || ! empty($connection['url'])
    || ! ($ownedLocalDatabase || $hostedCiDatabase)) {
    fwrite(STDERR, "Concurrent Matura tests require an owned disposable local database or the hosted CI database.\n");
    exit(1);
}

$request = Request::create('/');
$request->setUserResolver(fn (): User => User::findOrFail((int) $argv[1]));
$data = json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR);
while (microtime(true) < (float) $argv[4]) {
    usleep(1000);
}
try {
    app(MaturaWorkflowService::class)->execute($request, (int) $argv[2], $data);
    echo '200';
} catch (HttpExceptionInterface $exception) {
    echo $exception->getStatusCode();
}
