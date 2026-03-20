<?php

namespace Itstudioat\Spa\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncRoutes extends Command
{
    protected $signature = 'routes:sync';

    protected $description = 'Syncs all frontend JS routes with backend PHP metadata.';

    public function handle()
    {

        $jsDir = resource_path('routes');
        $phpDir = base_path('routes/meta/web');

        $jsFiles = File::files($jsDir);

        foreach ($jsFiles as $jsFile) {
            if ($jsFile->getExtension() !== 'js') {
                continue;
            }

            $baseName = $jsFile->getFilenameWithoutExtension(); // e.g., "admin"
            $phpFile = $phpDir.'/'.$baseName.'.php';

            $this->info("🔄 Syncing: {$baseName}.js → {$baseName}.php");

            $jsRoutes = [];

            $lines = explode("\n", File::get($jsFile));

            foreach ($lines as $line) {
                $line = trim($line);

                if (str_contains($line, 'path:')) {
                    [$before, $pathPart] = explode('path:', $line, 2);
                    $pathPart = trim($pathPart);

                    if ($pathPart[0] === "'" || $pathPart[0] === '"') {
                        $quote = $pathPart[0];
                        $endPos = strpos($pathPart, $quote, 1);
                        if ($endPos !== false) {
                            $route = substr($pathPart, 1, $endPos - 1);
                            if (str_starts_with($route, '/')) {
                                $jsRoutes[] = $route;
                            }
                        }
                    }
                }
            }

            $jsRoutes = array_unique($jsRoutes);

            // Load or initialize metadata
            if (File::exists($phpFile)) {
                $meta = include $phpFile;
                $existingRoles = $meta['roles'] ?? [];
            } else {
                $this->warn("⚠️  PHP metadata not found. Creating new file: $phpFile");
                $existingRoles = [];
            }

            // Add missing routes
            foreach ($jsRoutes as $route) {
                if (! array_key_exists($route, $existingRoles)) {
                    $this->info("  ➕ Adding missing route: $route");
                    $existingRoles[$route] = [];
                }
            }

            // Remove obsolete routes
            foreach (array_keys($existingRoles) as $route) {
                if (! in_array($route, $jsRoutes)) {
                    $this->warn("  ❌ Removing obsolete route: $route");
                    unset($existingRoles[$route]);
                }
            }

            // Save updated PHP file
            $phpOutput = "<?php\n\nreturn [\n    'roles' => [\n";
            foreach ($existingRoles as $path => $roles) {
                $rolesArray = '['.implode(', ', array_map(fn ($r) => "'$r'", $roles)).']';
                $phpOutput .= "        '$path' => $rolesArray,\n";
            }
            $phpOutput .= "    ]\n];\n";

            File::ensureDirectoryExists($phpDir);
            File::put($phpFile, $phpOutput);

            $this->info("✅ {$baseName}.php synced.\n");
        }

        $this->info('🎉 All route metadata files synced successfully.');

        return 0;
    }
}
