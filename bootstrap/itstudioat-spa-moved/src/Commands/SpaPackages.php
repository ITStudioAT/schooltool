<?php

namespace Itstudioat\Spa\Commands;

use Illuminate\Console\Command;

class SpaPackages extends Command
{
    protected $signature = 'spa:packages';

    protected $description = 'Installiere JS-Abhängigkeiten für spa';

    public function handle()
    {
        $deps = [
            '@vitejs/plugin-vue',
            'pinia',
            'sass-embedded',
            'vite-plugin-vuetify',
            'vue',
            'vue-router',
            'vuetify',
        ];

        $devDeps = [
            'axios',
            'laravel-vite-plugin@^1.3.0',
            'vite@^6.0.0',
        ];

        foreach ($deps as $pkg) {
            if (strpos($pkg, '@') !== false && ! str_ends_with($pkg, '@latest')) {
                $this->info("📦 Installing fixed {$pkg} ...");
                exec("npm install {$pkg}");
            } else {
                $this->info("📦 Installing latest {$pkg} ...");
                exec("npm install {$pkg}@latest");
            }
        }

        foreach ($devDeps as $pkg) {
            if (strpos($pkg, '@') !== false && ! str_ends_with($pkg, '@latest')) {
                $this->info("📦 Installing fixed dev version {$pkg} ...");
                exec("npm install --save-dev {$pkg}");
            } else {
                $this->info("📦 Installing latest dev {$pkg} ...");
                exec("npm install --save-dev {$pkg}@latest");
            }
        }

        $this->info('✅ Alle Pakete installiert.');
    }
}
