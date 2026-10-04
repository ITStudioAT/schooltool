<?php

use App\Models\User;
use App\Services\Matura\MaturaWorkflowService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('testing') || ! str_starts_with(config('database.connections.mysql.database'), 'pest_test_test_')) {
    throw new RuntimeException('Concurrent Matura tests require an owned disposable database.');
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
