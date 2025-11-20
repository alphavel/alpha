<?php

namespace Alphavel\Alpha\Console\Commands;

use Alphavel\Alpha\Console\Command;

class RouteClearCommand extends Command
{
    protected string $signature = 'route:clear';
    protected string $description = 'Remove the route cache file';

    public function handle(): int
    {
        $cwd = getcwd();
        $cacheFile = $cwd . '/bootstrap/cache/routes.php';

        if (!file_exists($cacheFile)) {
            $this->comment('Route cache does not exist.');
            return self::SUCCESS;
        }

        unlink($cacheFile);

        $this->success('Route cache cleared!');
        $this->info('Routes will be loaded from routes/api.php on next request.');

        return self::SUCCESS;
    }
}
