<?php

it('injects public Echo settings into each admin shell at request time', function (string $view): void {
    config()->set('frontend.echo', [
        'VITE_BROADCAST_CONNECTION' => 'reverb',
        'VITE_REVERB_APP_KEY' => 'public-reverb-key',
        'VITE_REVERB_HOST' => 'socket.example.com',
        'VITE_REVERB_PORT' => '443',
        'VITE_REVERB_SCHEME' => 'https',
    ]);

    $html = view($view)->render();

    expect($html)
        ->toContain('window.schooltoolEchoEnvironment')
        ->toContain('VITE_BROADCAST_CONNECTION')
        ->toContain('public-reverb-key')
        ->toContain('socket.example.com');
})->with([
    'application admin shell' => 'admin',
    'package admin shell' => 'vendor.spa.admin',
]);

it('never publishes broadcasting secrets to the frontend configuration', function (): void {
    expect(config('frontend.echo'))
        ->not->toHaveKeys([
            'REVERB_APP_SECRET',
            'PUSHER_APP_SECRET',
        ]);
});
