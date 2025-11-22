<?php

namespace Alphavel\Alpha\Console\Commands;

use Alphavel\Alpha\Console\Command;

/**
 * Make Session Configuration Command
 */
class MakeSessionCommand extends Command
{
    protected string $signature = 'make:session';
    protected string $description = 'Configure session management';

    public function handle(): int
    {
        if (!$this->isSessionInstalled()) {
            $this->warn('⚠️  Session package is not installed.');
            
            if ($this->confirm('Install alphavel/session now?', true)) {
                return $this->installPackage();
            }
            
            return self::FAILURE;
        }

        return $this->runWizard();
    }

    private function isSessionInstalled(): bool
    {
        return class_exists('Alphavel\Session\SessionServiceProvider') ||
               file_exists(getcwd() . '/vendor/alphavel/session');
    }

    private function installPackage(): int
    {
        $this->info('Installing alphavel/session...');
        exec('composer require alphavel/session 2>&1', $output, $code);
        return $code === 0 ? $this->runWizard() : self::FAILURE;
    }

    private function runWizard(): int
    {
        $driver = $this->choice('Session driver', [
            'file' => 'File (default)',
            'redis' => 'Redis (fast)',
            'memory' => 'Memory (Swoole Table)',
        ]);

        $this->comment('Add to .env:');
        $this->line('');
        $this->line("SESSION_DRIVER={$driver}");
        $this->line('SESSION_LIFETIME=120 # minutes');
        
        if ($driver === 'redis') {
            $this->line('REDIS_HOST=127.0.0.1');
            $this->line('REDIS_PORT=6379');
        }
        
        $this->line('');
        $this->success('✓ Session configured!');
        
        return self::SUCCESS;
    }
}
