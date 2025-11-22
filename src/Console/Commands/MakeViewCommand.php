<?php

namespace Alphavel\Alpha\Console\Commands;

use Alphavel\Alpha\Console\Command;

/**
 * Make View/Template Command
 */
class MakeViewCommand extends Command
{
    protected string $signature = 'make:view {name?}';
    protected string $description = 'Create view template';

    public function handle(): int
    {
        if (!$this->isViewInstalled()) {
            $this->warn('⚠️  View package is not installed.');
            
            if ($this->confirm('Install alphavel/view now?', true)) {
                return $this->installPackage();
            }
            
            return self::FAILURE;
        }

        $name = $this->argument('name');
        if ($name) {
            return $this->createView($name);
        }

        return $this->runWizard();
    }

    private function isViewInstalled(): bool
    {
        return class_exists('Alphavel\View\ViewServiceProvider') ||
               file_exists(getcwd() . '/vendor/alphavel/view');
    }

    private function installPackage(): int
    {
        $this->info('Installing alphavel/view...');
        exec('composer require alphavel/view 2>&1', $output, $code);
        return $code === 0 ? $this->runWizard() : self::FAILURE;
    }

    private function runWizard(): int
    {
        $action = $this->choice('What would you like to do?', [
            'create' => 'Create new view',
            'example' => 'Show examples',
            'exit' => 'Exit'
        ]);

        return match ($action) {
            'create' => $this->createView(),
            'example' => $this->showExamples(),
            default => self::SUCCESS
        };
    }

    private function createView(?string $name = null): int
    {
        $name = $name ?: $this->ask('View name (e.g., home)');
        $path = getcwd() . "/resources/views/{$name}.blade.php";

        $stub = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>{{ $title ?? 'Page' }}</title>
</head>
<body>
    <h1>{{ $title ?? 'Welcome' }}</h1>
    
    @if($user)
        <p>Hello, {{ $user->name }}!</p>
    @endif
    
    <div>
        {{ $content }}
    </div>
</body>
</html>
HTML;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $stub);
        $this->success("✓ Created: resources/views/{$name}.blade.php");
        
        return self::SUCCESS;
    }

    private function showExamples(): int
    {
        $this->info('📄 View Examples');
        $this->line('');
        $this->comment('Render view:');
        $this->line('return view(\'home\', [\'title\' => \'Welcome\']);');
        
        return self::SUCCESS;
    }
}
