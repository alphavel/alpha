<?php

namespace Alphavel\Alpha\Console\Commands;

use Alphavel\Alpha\Console\Command;

/**
 * Make Test Command
 */
class MakeTestCommand extends Command
{
    protected string $signature = 'make:test {name?}';
    protected string $description = 'Create test class';

    public function handle(): int
    {
        if (!$this->isTestingInstalled()) {
            $this->warn('⚠️  Testing package is not installed.');
            
            if ($this->confirm('Install alphavel/testing now?', true)) {
                return $this->installPackage();
            }
            
            return self::FAILURE;
        }

        $name = $this->argument('name');
        if ($name) {
            return $this->createTest($name);
        }

        return $this->runWizard();
    }

    private function isTestingInstalled(): bool
    {
        return class_exists('Alphavel\Testing\TestCase') ||
               file_exists(getcwd() . '/vendor/alphavel/testing');
    }

    private function installPackage(): int
    {
        $this->info('Installing alphavel/testing...');
        exec('composer require --dev alphavel/testing 2>&1', $output, $code);
        return $code === 0 ? $this->runWizard() : self::FAILURE;
    }

    private function runWizard(): int
    {
        $type = $this->choice('Test type', [
            'unit' => 'Unit Test',
            'feature' => 'Feature Test',
        ]);

        $name = $this->ask('Test name (e.g., UserTest)');
        return $this->createTest($name, $type);
    }

    private function createTest(string $name, string $type = 'unit'): int
    {
        if (!str_ends_with($name, 'Test')) {
            $name .= 'Test';
        }

        $dir = $type === 'feature' ? 'Feature' : 'Unit';
        $path = getcwd() . "/tests/{$dir}/{$name}.php";

        $stub = <<<PHP
<?php

namespace Tests\\{$dir};

use Alphavel\Testing\TestCase;

class {$name} extends TestCase
{
    public function testExample(): void
    {
        \$result = 1 + 1;
        
        \$this->assertEquals(2, \$result);
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $stub);
        $this->success("✓ Created: tests/{$dir}/{$name}.php");
        
        $this->line('');
        $this->comment('Run tests:');
        $this->comment('  ./vendor/bin/phpunit');
        
        return self::SUCCESS;
    }
}
