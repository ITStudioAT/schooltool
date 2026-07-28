<?php

namespace App\Providers;

use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::post('broadcasting/auth', [BroadcastController::class, 'authenticate'])
            ->middleware('web');

        require base_path('routes/channels.php');
    }
}
