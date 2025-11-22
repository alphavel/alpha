<?php

namespace Alphavel\Alpha\Console\Commands;

use Alphavel\Alpha\Console\Command;

/**
 * Make Queue Configuration Command
 * 
 * Helps configure async job queue (optional package)
 */
class MakeQueueCommand extends Command
{
    protected string $signature = 'make:queue';
    protected string $description = 'Configure async job queue (requires alphavel/queue)';

    public function handle(): int
    {
        if (!$this->isQueueInstalled()) {
            $this->warn('⚠️  Queue package is not installed.');
            $this->line('');
            $this->comment('The alphavel/queue package is optional.');
            $this->line('');
            
            if ($this->confirm('Install alphavel/queue now?', true)) {
                return $this->installPackage();
            }
            
            $this->line('');
            $this->comment('To install manually:');
            $this->comment('  composer require alphavel/queue');
            return self::FAILURE;
        }

        $this->info('✓ Queue package is installed');
        $this->line('');
        return $this->runWizard();
    }

    private function isQueueInstalled(): bool
    {
        return class_exists('Alphavel\Queue\QueueServiceProvider') ||
               file_exists(getcwd() . '/vendor/alphavel/queue');
    }

    private function installPackage(): int
    {
        $this->info('Installing alphavel/queue...');
        exec('composer require alphavel/queue 2>&1', $output, $code);
        
        if ($code === 0) {
            $this->success('✓ Installed!');
            return $this->runWizard();
        }
        
        $this->error('✗ Installation failed');
        return self::FAILURE;
    }

    private function runWizard(): int
    {
        $action = $this->choice('What would you like to do?', [
            'job' => 'Create a new job class',
            'config' => 'Configure queue driver',
            'example' => 'Show usage examples',
            'exit' => 'Exit'
        ]);

        return match ($action) {
            'job' => $this->createJob(),
            'config' => $this->configureQueue(),
            'example' => $this->showExamples(),
            default => self::SUCCESS
        };
    }

    private function createJob(): int
    {
        $name = $this->ask('Job name (e.g., SendEmailJob)');
        
        if (!str_ends_with($name, 'Job')) {
            $name .= 'Job';
        }

        $path = getcwd() . "/app/Jobs/{$name}.php";

        $stub = <<<PHP
<?php

namespace App\Jobs;

class {$name}
{
    public function __construct(
        private array \$data
    ) {}
    
    /**
     * Execute the job
     */
    public function handle(): void
    {
        // Your job logic here
        // Example: Send email, process data, etc.
        
        logger()->info('Job executed', \$this->data);
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $stub);

        $this->success("✓ Job created: app/Jobs/{$name}.php");
        $this->line('');
        $this->comment('Dispatch job:');
        $this->comment("  dispatch(new {$name}(['key' => 'value']));");
        $this->line('');

        return self::SUCCESS;
    }

    private function configureQueue(): int
    {
        $this->comment('Add to .env:');
        $this->line('');
        $this->line('QUEUE_DRIVER=memory  # or redis, database');
        $this->line('');
        
        $this->success('✓ Configuration ready!');
        return self::SUCCESS;
    }

    private function showExamples(): int
    {
        $this->info('📚 Queue Examples');
        $this->line('');
        
        $this->comment('1. Create and dispatch job:');
        $this->line('dispatch(new SendEmailJob([\'to\' => \'user@example.com\']));');
        $this->line('');
        
        $this->comment('2. Delayed job:');
        $this->line('dispatch(new SendEmailJob($data))->delay(60); // 60 seconds');
        $this->line('');
        
        $this->comment('3. Job with priority:');
        $this->line('Queue::push(SendEmailJob::class, $data, \'high\');');
        $this->line('');

        return self::SUCCESS;
    }
}
