<?php

it('does not depend on the legacy local spa package', function (): void {
    $projectRoot = dirname(__DIR__, 2);

    $composerJson = json_decode(file_get_contents($projectRoot.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composerJson['require'])->not->toHaveKey('itstudioat/spa')
        ->and($composerJson)->not->toHaveKey('repositories')
        ->and(file_exists($projectRoot.'/bootstrap/itstudioat-spa-moved'))->toBeFalse();
});
