<?php

use Illuminate\Support\Facades\Storage;

it('isolates local filesystem writes from development private storage', function () {
    $localDiskRoot = str_replace('\\', '/', (string) config('filesystems.disks.local.root'));
    $testPrivateStorage = str_replace('\\', '/', storage_path('app/private'));
    $developmentPrivateStorage = str_replace('\\', '/', base_path('storage/app/private'));
    $probePath = 'testing-storage-isolation/probe.txt';

    expect($localDiskRoot)
        ->toBe($testPrivateStorage)
        ->toContain('/storage/framework/testing/process-')
        ->not->toBe($developmentPrivateStorage);

    try {
        Storage::disk('local')->put($probePath, 'test-only');

        Storage::disk('local')->assertExists($probePath);
        expect(is_file($developmentPrivateStorage.'/'.$probePath))->toBeFalse();
    } finally {
        Storage::disk('local')->deleteDirectory('testing-storage-isolation');
    }
});
