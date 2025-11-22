<?php

namespace Alphavel\Alpha\Console\Commands;

use Alphavel\Alpha\Console\Command;

/**
 * Make ORM Relationship Command
 * 
 * Helps add relationships to models (optional package)
 */
class MakeRelationshipCommand extends Command
{
    protected string $signature = 'make:relationship';
    protected string $description = 'Add relationships to models (requires alphavel/orm)';

    public function handle(): int
    {
        if (!$this->isOrmInstalled()) {
            $this->warn('⚠️  ORM package is not installed.');
            $this->line('');
            
            if ($this->confirm('Install alphavel/orm now?', true)) {
                return $this->installPackage();
            }
            
            $this->comment('To install: composer require alphavel/orm');
            return self::FAILURE;
        }

        $this->info('✓ ORM package is installed');
        $this->line('');
        return $this->runWizard();
    }

    private function isOrmInstalled(): bool
    {
        return class_exists('Alphavel\ORM\ORMServiceProvider') ||
               file_exists(getcwd() . '/vendor/alphavel/orm');
    }

    private function installPackage(): int
    {
        $this->info('Installing alphavel/orm...');
        exec('composer require alphavel/orm 2>&1', $output, $code);
        
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
            'add' => 'Add relationship to model',
            'example' => 'Show relationship examples',
            'exit' => 'Exit'
        ]);

        return match ($action) {
            'add' => $this->addRelationship(),
            'example' => $this->showExamples(),
            default => self::SUCCESS
        };
    }

    private function addRelationship(): int
    {
        $model = $this->ask('Model name (e.g., User)');
        $type = $this->choice('Relationship type', [
            'hasMany' => 'Has Many (one-to-many)',
            'hasOne' => 'Has One (one-to-one)',
            'belongsTo' => 'Belongs To (inverse)',
            'belongsToMany' => 'Belongs To Many (many-to-many)',
        ]);
        
        $related = $this->ask('Related model (e.g., Post)');

        $this->line('');
        $this->comment("Add this to your {$model} model:");
        $this->line('');
        $this->line('use Alphavel\ORM\HasRelationships;');
        $this->line('');
        $this->line('class ' . $model . ' extends Model');
        $this->line('{');
        $this->line('    use HasRelationships;');
        $this->line('');
        
        match ($type) {
            'hasMany' => $this->line("    public function " . strtolower($related) . "s()\n    {\n        return \$this->hasMany({$related}::class);\n    }"),
            'hasOne' => $this->line("    public function " . strtolower($related) . "()\n    {\n        return \$this->hasOne({$related}::class);\n    }"),
            'belongsTo' => $this->line("    public function " . strtolower($related) . "()\n    {\n        return \$this->belongsTo({$related}::class);\n    }"),
            'belongsToMany' => $this->line("    public function " . strtolower($related) . "s()\n    {\n        return \$this->belongsToMany({$related}::class);\n    }"),
        };
        
        $this->line('}');
        $this->line('');

        return self::SUCCESS;
    }

    private function showExamples(): int
    {
        $this->info('📚 ORM Relationship Examples');
        $this->line('');
        
        $this->comment('1. Has Many:');
        $this->line('$user->posts; // Get all posts');
        $this->line('$user->posts()->where(\'published\', true)->get();');
        $this->line('');
        
        $this->comment('2. Belongs To:');
        $this->line('$post->user; // Get post author');
        $this->line('');
        
        $this->comment('3. Eager Loading (prevents N+1):');
        $this->line('$users = User::with(\'posts\')->get();');
        $this->line('$users = User::with(\'posts.comments\')->get();');
        $this->line('');

        return self::SUCCESS;
    }
}
