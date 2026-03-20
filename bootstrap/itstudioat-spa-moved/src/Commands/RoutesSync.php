<?php

namespace Itstudioat\Spa\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class RoutesSync extends Command
{
    protected $signature = 'routes:sync';

    protected $description = 'Sync route metadata into meta files grouped by base path';

    public function handle()
    {
        $this->info('🚀 Starting route sync...');

        $excludePrefixes = ['/api', '/sanctum', '/storage', '/up'];

        $metaPath = base_path('routes/meta/web');
        if (! File::exists($metaPath)) {
            File::makeDirectory($metaPath, 0755, true);
            $this->info("📁 Created folder: $metaPath");
        }

        $routes = Route::getRoutes();
        $grouped = [];

        foreach ($routes as $route) {
            $uri = '/'.ltrim($route->uri(), '/');

            if (collect($excludePrefixes)->contains(fn ($prefix) => str_starts_with($uri, $prefix))) {
                continue;
            }

            // ➤ Ersetze Platzhalter wie {any} oder {id?} mit *
            $uri = preg_replace('/\{[^}]+\??\}/', '*', $uri);

            // ➤ Gruppierung nach erster URI-Komponente
            if ($uri === '/' || $uri === '/*') {
                $key = '/homepage';
            } else {
                $segments = explode('/', trim($uri, '/'));
                $first = $segments[0] ?? '';
                $key = ($first === '' || $first === '*') ? '/homepage' : '/'.preg_replace('/[^a-zA-Z0-9_\-]/', '_', $first);
            }

            if (! isset($grouped[$key])) {
                $grouped[$key] = [];
            }

            $grouped[$key][$uri] = $this->guessRoles($uri);
        }

        foreach ($grouped as $basePath => $newRoutes) {
            ksort($newRoutes);
            $fileName = $metaPath.'/'.ltrim($basePath, '/').'.php';

            if (File::exists($fileName)) {
                $existingRoutes = include $fileName;
                $existing = $existingRoutes['roles'] ?? [];

                $merged = $this->mergeWithExistingRoutes($existing, $newRoutes);

                if ($merged !== $existing) {
                    $content = $this->exportRoutesFormatted($merged, $existing);
                    File::put($fileName, $content);
                    $this->info("✏️  Updated file with new routes: $fileName");
                } else {
                    $this->line("⏭️  No new routes in: $fileName");
                }
            } else {
                $content = $this->exportRoutesFormatted($newRoutes);
                File::put($fileName, $content);
                $this->info("✅ Created metadata file: $fileName");
            }
        }

        $this->info('🎉 Route sync completed.');
    }

    protected function guessRoles(string $uri): array
    {
        /*
        if (str_starts_with($uri, '/admin')) {
            return ['admin'];
        }
            */
        return [];
    }

    protected function normalizeUri(string $uri): string
    {
        return preg_replace('/\{[^}]+\}/', '*', $uri);
    }

    protected function mergeWithExistingRoutes(array $existing, array $new): array
    {
        return $existing + array_diff_key($new, $existing);
    }

    protected function exportRoutesFormatted(array $routes, array $original = []): string
    {
        $lines = ['<?php', '', 'return [', "    'roles' => ["];

        // Bestehende Einträge
        foreach ($original as $path => $roles) {
            $rolesArray = empty($roles) ? '[]' : "['".implode("', '", $roles)."']";
            $lines[] = "        '$path' => $rolesArray,";
        }

        // Neue Einträge (falls vorhanden)
        $newOnly = array_diff_key($routes, $original);
        if (! empty($newOnly)) {
            if (! empty($original)) {
                $lines[] = ''; // Leerzeile zur Trennung
            }
            $lines[] = '        // new entries detected';
            foreach ($newOnly as $path => $roles) {
                $rolesArray = empty($roles) ? '[]' : "['".implode("', '", $roles)."']";
                $lines[] = "        '$path' => $rolesArray,";
            }
        }

        $lines[] = '    ]';
        $lines[] = '];';

        return implode("\n", $lines)."\n";
    }
}
