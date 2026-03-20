<?php

namespace Itstudioat\Spa;

use App\Models\User;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Itstudioat\Spa\Commands\CreateUser;
use Itstudioat\Spa\Commands\Fake\UserFake;
use Itstudioat\Spa\Commands\RoutesSync;
use Itstudioat\Spa\Commands\SpaComplete;
use Itstudioat\Spa\Commands\SpaPackages;
use Itstudioat\Spa\Commands\SpaUpdate;
use Itstudioat\Spa\Commands\SyncRoutes;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SpaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('spa')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('00001_update_users_table')
            ->hasCommands([
                CreateUser::class,
                SpaPackages::class,
                SpaComplete::class,
                SpaUpdate::class,
                SyncRoutes::class,
                UserFake::class,
                RoutesSync::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->startWith(function (InstallCommand $command) {
                        $command->info('Hello, and welcome to my great new package!');
                    })
                    ->publishMigrations()
                    ->publishConfigFile()
                    ->endWith(function (InstallCommand $command) {
                        $command->info('Have a great day!');
                    });
            });
    }

    public function packageRegistered()
    {

        // Publishing
        $this->publishes([
            __DIR__.'/../stubs/vite.config.js' => base_path('vite.config.js'),
            __DIR__.'/../stubs/images' => storage_path('/app/public/images'),
            __DIR__.'/../bootstrap/app.php' => base_path('/bootstrap/app.php'),
            __DIR__.'/Models' => app_path('/Models'),
            __DIR__.'/Enums' => app_path('/Enums'),
            __DIR__.'/Facades' => app_path('/Facades'),
            __DIR__.'/Enums' => app_path('/Enums'),
            __DIR__.'/Http' => app_path('/Http'),
            __DIR__.'/Notifications' => app_path('/Notifications'),
            __DIR__.'/Services' => app_path('/Services'),
            __DIR__.'/Traits' => app_path('/Traits'),
            __DIR__.'/Providers/AppServiceProvider.php' => app_path('/Providers/AppServiceProvider.php'),
            __DIR__.'/../resources' => resource_path(),
            __DIR__.'/../routes/web.php' => base_path('/routes/web.php'),
            __DIR__.'/../routes/api.php' => base_path('/routes/api.php'),
        ], 'spa-all');
    }

    public function bootingPackage()
    {
        $this->app['router']->pushMiddlewareToGroup('web', StartSession::class);

        // Routen werden beim Booten des Pakets geladen
        Route::model('user', User::class);
        $this->loadRoutes();
    }

    protected function loadRoutes()
    {
        // Check if published web.php exists in the app's routes directory
        $publishedWebRoutes = base_path('routes/web.php');
        $publishedApiRoutes = base_path('routes/api.php');

        if (file_exists($publishedWebRoutes)) {
            $this->loadRoutesFrom($publishedWebRoutes);
        } elseif (file_exists(__DIR__.'/../routes/web.php')) {
            // Only fallback to vendor route if no published version exists
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if (file_exists($publishedApiRoutes)) {
            Route::prefix('api')->group($publishedApiRoutes);
        } elseif (file_exists(__DIR__.'/../routes/api.php')) {
            Route::prefix('api')->group(__DIR__.'/../routes/api.php');
        }
    }

    protected function updateControllerNamespaces()
    {
        // Use glob with a recursive pattern
        $controllerFiles = glob(app_path('Http/Controllers/**/*.php'), GLOB_BRACE);

        foreach ($controllerFiles as $controllerFile) {
            $content = file_get_contents($controllerFile);

            // Check if this is one of your published controllers and modify its namespace
            if (strpos($content, 'namespace Itstudioat\\Spa\\Http\\Controllers') !== false) {
                $content = str_replace('namespace Itstudioat\\Spa\\Http\\Controllers', 'namespace App\\Http\\Controllers', $content);
                file_put_contents($controllerFile, $content);
            }
        }
    }
}
