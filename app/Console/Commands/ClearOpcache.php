<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearOpcache extends Command
{
    protected $signature = 'opcache:clear';
    protected $description = 'Clear OPcache';

    public function handle()
    {
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $this->info('✓ OPcache cleared successfully!');

            $status = opcache_get_status();
            $this->info("Cached scripts: {$status['opcache_statistics']['num_cached_scripts']}");

            return 0;
        }

        $this->error('OPcache is not enabled');
        return 1;
    }
}
