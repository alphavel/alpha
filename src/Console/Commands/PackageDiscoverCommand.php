<?php

namespace Alphavel\Alpha\Console\Commands;

use Alphavel\Alpha\Console\Command;
use Alphavel\Alpha\PackageManifest;

/**
 * Package Discover Command
 * 
 * Regenerates the package manifest cache
 */
class PackageDiscoverCommand extends Command
{
    protected string $signature = 'package:discover';

    protected string $description = 'Rebuild the cached package manifest';

    public function handle(): int
    {
        $this->info('Discovering packages...');

        $manifest = new PackageManifest($this->basePath());

        // Delete existing manifest
        $manifest->delete();

        // Rebuild manifest
        $packages = $manifest->build();

        $count = count($packages);

        if ($count === 0) {
            $this->comment('No Alphavel packages discovered.');
            return self::SUCCESS;
        }

        $this->line('');
        $this->success("Discovered {$count} package(s):");

        foreach ($packages as $package) {
            $this->line("  - {$package['name']} ({$package['version']})");
            
            if (!empty($package['providers'])) {
                $providerCount = count($package['providers']);
                $this->comment("    {$providerCount} provider(s)");
            }
            
            if (!empty($package['commands'])) {
                $commandCount = count($package['commands']);
                $this->comment("    {$commandCount} command(s)");
            }
        }

        $this->line('');
        $this->comment('Manifest cached successfully!');

        return self::SUCCESS;
    }
}
