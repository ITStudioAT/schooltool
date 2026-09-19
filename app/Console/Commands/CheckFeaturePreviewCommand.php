<?php

namespace App\Console\Commands;

use App\Services\FeaturePreviewControlSignature;
use App\Services\FeaturePreviewDatabaseGuard;
use App\Services\FeaturePreviewMailService;
use App\Services\FeaturePreviewRuntimeService;
use App\Services\FeaturePreviewService;
use App\Services\FeaturePreviewSnapshotFiles;
use App\Services\FeaturePreviewSnapshotIdentityStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('preview:check {--configuration-only : Check isolation before importing an initial snapshot}')]
#[Description('Read-only verification of the preview instance and its isolated runtime configuration')]
class CheckFeaturePreviewCommand extends Command
{
    public function handle(FeaturePreviewService $preview, FeaturePreviewDatabaseGuard $databases, FeaturePreviewSnapshotFiles $files, FeaturePreviewSnapshotIdentityStore $identity, FeaturePreviewMailService $mail): int
    {
        if (! $preview->isPreview()) {
            $this->error('SCHOOLTOOL_PREVIEW_INSTANCE must be true. No changes were made.');

            return self::FAILURE;
        }

        $previewUrl = $preview->adminUrl('url');
        $liveUrl = $preview->adminUrl('live_url');
        $previewHost = $previewUrl ? parse_url($previewUrl, PHP_URL_HOST) : null;
        $liveHost = $liveUrl ? parse_url($liveUrl, PHP_URL_HOST) : null;
        $expectedHost = strtolower((string) config('schooltool.preview.expected_host'));
        $appUrl = rtrim((string) config('app.url'), '/');
        $cacheStore = (string) config('cache.default');
        $cacheDriver = config("cache.stores.{$cacheStore}.driver");
        $limiterStore = config('cache.limiter') ?: $cacheStore;
        $permissionStore = config('permission.cache.store', 'default');
        $queueConnection = (string) config('queue.default');
        $cookie = (string) config('session.cookie');

        $checks = [
            'Preview and live URLs must be valid HTTPS URLs on different hosts.' => $previewHost && $liveHost && strtolower($previewHost) !== strtolower($liveHost),
            'SCHOOLTOOL_PREVIEW_EXPECTED_HOST must match the preview URL.' => $expectedHost !== '' && $expectedHost === strtolower((string) $previewHost),
            'APP_URL must match the preview origin.' => $previewUrl !== null && "{$appUrl}/admin" === $previewUrl,
            'APP_DEBUG must be false.' => ! config('app.debug'),
            'Preview must not retain previous application encryption keys.' => config('app.previous_keys', []) === [],
            'Use file sessions within this application storage directory.' => config('session.driver') === 'file' && $this->ownedStoragePath(config('session.files')),
            'SESSION_COOKIE must have a dedicated preview name.' => preg_match('/^[A-Za-z0-9_-]*preview[A-Za-z0-9_-]*$/i', $cookie) === 1,
            'Session cookies must be host-only, secure and HTTP-only.' => ! config('session.domain') && config('session.secure') === true && config('session.http_only') === true,
            'Session SameSite must be lax or strict.' => in_array(config('session.same_site'), ['lax', 'strict'], true),
            'Use file cache and locks within this application storage directory.' => $cacheDriver === 'file' && $this->ownedStoragePath(config("cache.stores.{$cacheStore}.path")) && $this->ownedStoragePath(config("cache.stores.{$cacheStore}.lock_path") ?: config("cache.stores.{$cacheStore}.path")),
            'Rate limiting and permission cache must use the isolated default cache.' => $limiterStore === $cacheStore && in_array($permissionStore, ['default', $cacheStore], true),
            'Use the sync queue connection; preview must not enqueue jobs for live workers.' => config("queue.connections.{$queueConnection}.driver") === 'sync',
            'Broadcasting must be disabled or local logging only.' => in_array(config('broadcasting.default'), [null, 'null', 'log'], true),
            'Maintenance mode must use local files.' => config('app.maintenance.driver') === 'file',
            'Pulse, Telescope and Nightwatch must be disabled in preview.' => ! config('pulse.enabled') && ! config('telescope.enabled') && ! config('nightwatch.enabled'),
            'Remove additional Cloudways, legacy remote database and AWS credentials from preview configuration.' => app(FeaturePreviewRuntimeService::class)->integrationCredentialsAreAbsent(),
            'Configure the authenticated HTTPS main control bridge and its private dedicated key.' => app(FeaturePreviewControlSignature::class)->configurationIsSafe(),
            'Remove public/storage from the preview web root; Laravel cannot protect static files.' => ! file_exists(public_path('storage')) && ! is_link(public_path('storage')),
            'Authentication emails require safe preview URLs and a dedicated SMTP mailer with mandatory TLS and certificate verification.' => $mail->configurationIsSafe(),
        ];

        $failed = false;
        foreach ($checks as $message => $passed) {
            if (! $passed) {
                $this->error($message);
                $failed = true;
            }
        }

        if ($failed) {
            return self::FAILURE;
        }

        try {
            $databases->target();
            $files->assertConfigurationSafe();
            $identity->directory();
        } catch (Throwable) {
            $this->error('Preview database and private file isolation failed. Configure a separate target database, the authenticated main control bridge and private snapshot storage.');

            return self::FAILURE;
        }

        if (! $this->option('configuration-only') && ! $preview->schemaReady()) {
            $this->error('Central preview settings and user grants are missing or unavailable. Apply the approved main-application migration first.');

            return self::FAILURE;
        }

        $this->info('Preview configuration is ready. No data, files or permissions were changed.');

        return self::SUCCESS;
    }

    private function ownedStoragePath(mixed $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        $applicationRoot = realpath(base_path());
        $storageRoot = realpath(storage_path());
        $resolvedPath = realpath($path);
        if ($applicationRoot === false || $storageRoot === false || $resolvedPath === false) {
            return false;
        }

        $applicationRoot = str_replace('\\', '/', $applicationRoot).'/';
        $storageRoot = str_replace('\\', '/', $storageRoot).'/';
        $resolvedPath = str_replace('\\', '/', $resolvedPath).'/';

        return str_starts_with($storageRoot, $applicationRoot)
            && str_starts_with($resolvedPath, $storageRoot);
    }
}
