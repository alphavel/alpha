<?php

namespace Alphavel\Alpha\Console\Commands;

use Alphavel\Alpha\Console\Command;
use Alphavel\Alpha\PackageManifest;
use Alphavel\Alpha\PackageRecipe;

/**
 * Package Add Command
 * 
 * Install and configure Alphavel packages with zero configuration
 */
class PackageAddCommand extends Command
{
    protected string $signature = 'add';

    protected string $description = 'Install an Alphavel package (e.g., alpha add queue)';

    /**
     * Package aliases map
     */
    private array $aliases = [
        'queue' => 'alphavel/queue',
        'auth' => 'alphavel/auth',
        'cache' => 'alphavel/cache',
        'database' => 'alphavel/database',
        'events' => 'alphavel/events',
        'logging' => 'alphavel/logging',
        'validation' => 'alphavel/validation',
        'support' => 'alphavel/support',
    ];

    public function handle(): int
    {
        $packageName = $this->argument(0);

        if (!$packageName) {
            $this->error('Package name is required.');
            $this->line('');
            $this->line('Usage: php alpha add <package>');
            $this->line('');
            $this->comment('Examples:');
            $this->line('  php alpha add queue');
            $this->line('  php alpha add alphavel/queue');
            $this->line('');
            $this->comment('Available aliases:');
            foreach ($this->aliases as $alias => $package) {
                $this->line("  {$alias} → {$package}");
            }
            
            return self::FAILURE;
        }

        // Resolve alias
        $composerPackage = $this->resolvePackage($packageName);
        
        $this->info("Installing {$composerPackage}...");
        $this->line('');

        // Step 1: Run composer require
        if (!$this->installViaComposer($composerPackage)) {
            $this->error('Failed to install package via Composer.');
            return self::FAILURE;
        }

        $this->line('');
        $this->success('Package installed successfully!');
        $this->line('');

        // Step 2: Regenerate package manifest
        $this->info('Discovering package configuration...');
        $this->callCommand('package:discover');

        $this->line('');

        // Step 3: Run post-install recipes
        $manifest = new PackageManifest($this->basePath());
        $packageInfo = $manifest->getPackage($composerPackage);

        if ($packageInfo) {
            $this->runRecipes($composerPackage, $packageInfo);
        }

        $this->line('');
        $this->success('All done! Package is ready to use. 🚀');

        return self::SUCCESS;
    }

    /**
     * Resolve package name (handle aliases)
     */
    private function resolvePackage(string $name): string
    {
        // If it's already a full package name (vendor/package)
        if (str_contains($name, '/')) {
            return $name;
        }

        // Check if it's an alias
        if (isset($this->aliases[$name])) {
            return $this->aliases[$name];
        }

        // Assume it's alphavel/package
        return "alphavel/{$name}";
    }

    /**
     * Install package via Composer
     */
    private function installViaComposer(string $package): bool
    {
        $composerBinary = $this->findComposer();
        $command = "{$composerBinary} require {$package}";

        $this->comment("Running: {$command}");
        $this->line('');

        // Execute and stream output
        $process = popen($command, 'r');

        if ($process === false) {
            return false;
        }

        while (!feof($process)) {
            $output = fgets($process);
            if ($output !== false) {
                echo $output;
            }
        }

        $exitCode = pclose($process);

        return $exitCode === 0;
    }

    /**
     * Run post-install recipes
     */
    private function runRecipes(string $package, array $packageInfo): void
    {
        $recipe = new PackageRecipe($this, $package, $packageInfo);
        $recipe->run();
    }

    /**
     * Call another command
     */
    private function callCommand(string $command): void
    {
        $commandClass = match($command) {
            'package:discover' => PackageDiscoverCommand::class,
            default => null
        };

        if ($commandClass) {
            $cmd = new $commandClass();
            $cmd->initialize(['alpha', $command]);
            $cmd->handle();
        }
    }

    /**
     * Find composer binary
     */
    private function findComposer(): string
    {
        // Try common locations
        $paths = [
            'composer',
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            $this->basePath('composer.phar'),
        ];

        foreach ($paths as $path) {
            if ($this->commandExists($path)) {
                return $path;
            }
        }

        return 'composer';
    }

    /**
     * Check if command exists
     */
    private function commandExists(string $command): bool
    {
        $result = shell_exec("which {$command}");
        return !empty($result);
    }
}
