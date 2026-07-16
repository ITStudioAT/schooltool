<?php

use App\Support\RemoteUrlGuard;

it('accepts a public HTTP address', function () {
    expect(app(RemoteUrlGuard::class)->resolve('https://93.184.216.34/image.png'))
        ->toMatchArray([
            'host' => '93.184.216.34',
            'port' => 443,
            'ip' => '93.184.216.34',
        ]);
});

it('rejects private reserved and local addresses', function (string $url) {
    app(RemoteUrlGuard::class)->resolve($url);
})->with([
    'loopback' => 'http://127.0.0.1/image.png',
    'private network' => 'http://10.0.0.2/image.png',
    'link local metadata' => 'http://169.254.169.254/latest/meta-data',
    'IPv6 loopback' => 'http://[::1]/image.png',
    'localhost' => 'http://localhost/image.png',
])->throws(InvalidArgumentException::class);

it('rejects credentials and nonstandard ports', function (string $url) {
    app(RemoteUrlGuard::class)->resolve($url);
})->with([
    'credentials' => 'https://user:secret@93.184.216.34/image.png',
    'nonstandard port' => 'https://93.184.216.34:8443/image.png',
])->throws(InvalidArgumentException::class);
