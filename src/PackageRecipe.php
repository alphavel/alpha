<?php

namespace Alphavel\Alpha;

use Alphavel\Alpha\Console\Command;

/**
 * Package Recipe
 * 
 * Executes post-installation tasks for a package
 */
class PackageRecipe
{
    private Command $command;
    
    private string $package;
    
    private array $packageInfo;

    public function __construct(Command $command, string $package, array $packageInfo)
    {
        $this->command = $command;
        $this->package = $package;
        $this->packageInfo = $packageInfo;
    }

    /**
     * Run the recipe
     */
    public function run(): void
    {
        $this->command->output("Configuring {$this->package}...", 'info');
        $this->command->output('');

        // Publish configuration files
        if (!empty($this->packageInfo['config'])) {
            $this->publishConfig();
        }

        // Publish migrations
        if (!empty($this->packageInfo['migrations'])) {
            $this->publishMigrations();
        }

        // Publish views
        if (!empty($this->packageInfo['views'])) {
            $this->publishViews();
        }

        // Run custom setup commands
        if (!empty($this->packageInfo['setup_commands'])) {
            $this->runSetupCommands();
        }

        // Show instructions
        if (!empty($this->packageInfo['instructions'])) {
            $this->showInstructions();
        }
    }

    /**
     * Publish configuration files
     */
    private function publishConfig(): void
    {
        $configs = (array) $this->packageInfo['config'];

        foreach ($configs as $source => $target) {
            if (is_int($source)) {
                // Simple format: ['config/queue.php']
                $source = $target;
                $target = basename($source);
            }

            $sourcePath = $this->getVendorPath($source);
            $targetPath = $this->command->getConfigPath($target);

            if (file_exists($targetPath)) {
                if (!$this->command->askConfirmation("Config file {$target} already exists. Overwrite?", false)) {
                    continue;
                }
            }

            if (file_exists($sourcePath)) {
                $this->command->ensureDirectory(dirname($targetPath));
                copy($sourcePath, $targetPath);
                $this->command->output("Published: config/{$target}", 'success');
            }
        }

        $this->command->output('');
    }

    /**
     * Publish migrations
     */
    private function publishMigrations(): void
    {
        $migrationsPath = $this->packageInfo['migrations'];
        $sourcePath = $this->getVendorPath($migrationsPath);

        if (!is_dir($sourcePath)) {
            return;
        }

        $targetPath = $this->command->getBasePath('database/migrations');
        $this->command->ensureDirectory($targetPath);

        $files = glob($sourcePath . '/*.php');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $target = $targetPath . '/' . $filename;

            if (!file_exists($target)) {
                copy($file, $target);
                $this->command->output("Published migration: {$filename}", 'success');
            }
        }

        $this->command->output('');

        // Ask if user wants to run migrations
        if ($this->command->askConfirmation('Run migrations now?', true)) {
            $this->command->output('');
            $this->command->output('Running: php alpha migrate', 'comment');
            $this->command->output('');
            
            // TODO: Run migrate command when implemented
            $this->command->output('Migration command not yet implemented', 'warn');
        }

        $this->command->output('');
    }

    /**
     * Publish views
     */
    private function publishViews(): void
    {
        $viewsPath = $this->packageInfo['views'];
        $sourcePath = $this->getVendorPath($viewsPath);

        if (!is_dir($sourcePath)) {
            return;
        }

        $targetPath = $this->command->getBasePath('resources/views');
        $this->command->ensureDirectory($targetPath);

        $this->copyDirectory($sourcePath, $targetPath);
        $this->command->output('Published views', 'success');
        $this->command->output('');
    }

    /**
     * Run custom setup commands
     */
    private function runSetupCommands(): void
    {
        $commands = (array) $this->packageInfo['setup_commands'];

        foreach ($commands as $cmd) {
            $this->command->output('');
            $this->command->output("Running: {$cmd}", 'comment');
            $this->command->output('');

            $output = [];
            $exitCode = 0;

            exec($cmd, $output, $exitCode);

            foreach ($output as $line) {
                $this->command->output($line);
            }

            if ($exitCode !== 0) {
                $this->command->output("Command exited with code {$exitCode}", 'warn');
            }
        }

        $this->command->output('');
    }

    /**
     * Show post-installation instructions
     */
    private function showInstructions(): void
    {
        $instructions = $this->packageInfo['instructions'];

        $this->command->output('');
        $this->command->output('📋 Next Steps:', 'info');
        $this->command->output('');

        if (is_array($instructions)) {
            foreach ($instructions as $instruction) {
                $this->command->output("  • {$instruction}");
            }
        } else {
            $this->command->output("  {$instructions}");
        }

        $this->command->output('');
    }

    /**
     * Get vendor path for package file
     */
    private function getVendorPath(string $path): string
    {
        return $this->command->getBasePath("vendor/{$this->package}/{$path}");
    }

    /**
     * Copy directory recursively
     */
    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . '/' . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755);
                }
            } else {
                copy($item, $targetPath);
            }
        }
    }
}
