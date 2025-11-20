<?php

namespace Alphavel\Alpha\Console\Commands;

use Alphavel\Alpha\Console\Command;
use Alphavel\Alpha\Generators\SchemaInspector;
use Alphavel\Alpha\Generators\ValidationGenerator;

/**
 * Make Controller Command - Intelligent code generation
 */
class MakeControllerCommand extends Command
{
    protected string $signature = 'make:controller';

    protected string $description = 'Create a new controller class (with optional intelligent generation from database)';

    public function handle(): int
    {
        $name = $this->argument(0) ?? $this->ask('Controller name');

        if (!$name) {
            $this->error('Controller name is required.');
            return self::FAILURE;
        }

        // Add Controller suffix if not present
        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        // Check if should use intelligent generation
        $useDatabase = $this->hasOption('model') || $this->hasOption('table');
        
        if (!$useDatabase && $this->confirm('Generate from database table?', false)) {
            $useDatabase = true;
        }

        if ($useDatabase) {
            return $this->handleIntelligentGeneration($name);
        }

        return $this->handleBasicGeneration($name);
    }

    /**
     * Handle basic controller generation
     */
    private function handleBasicGeneration(string $name): int
    {
        $type = $this->choice(
            'Controller type',
            ['API (JSON responses)', 'Resource (CRUD)', 'Empty'],
            0
        );

        $stub = match((int) $type) {
            1 => $this->getStub('controller.resource'),
            2 => $this->getStub('controller.empty'),
            default => $this->getStub('controller.api')
        };

        $content = $this->replaceInStub($stub, [
            'CLASS' => $name,
            'class' => $name
        ]);

        return $this->writeController($name, $content);
    }

    /**
     * Handle intelligent generation from database
     */
    private function handleIntelligentGeneration(string $name): int
    {
        try {
            // Get database connection
            $config = require $this->configPath('app.php');
            $dsn = "mysql:host={$config['database']['host']};dbname={$config['database']['name']}";
            $pdo = new \PDO(
                $dsn,
                $config['database']['user'],
                $config['database']['password']
            );

            $inspector = new SchemaInspector($pdo, $config['database']['name']);
            $validator = new ValidationGenerator($inspector);

            // Get table name
            $table = $this->option('table');
            
            if (!$table) {
                $tables = $inspector->getTables();
                $table = $this->choice('Select table', $tables);
            }

            $this->info("Analyzing table: {$table}");

            // Get table metadata
            $columns = $inspector->getColumns($table);
            $primaryKey = $inspector->getPrimaryKey($table);
            
            // Generate validation rules
            $createRules = $validator->generateValidationCode($table, false, 12);
            $updateRules = $validator->generateValidationCode($table, true, 12);

            // Get fillable columns (exclude auto-increment and timestamps)
            $fillable = [];
            foreach ($columns as $column) {
                if (!$inspector->isAutoIncrement($column) && 
                    !in_array($column['name'], ['created_at', 'updated_at'])) {
                    $fillable[] = "'{$column['name']}'";
                }
            }
            $fillableCode = implode(', ', $fillable);

            // Generate controller
            $stub = $this->getStub('controller.intelligent');
            $modelName = $this->getModelName($table);

            $content = $this->replaceInStub($stub, [
                'CLASS' => $name,
                'class' => $name,
                'MODEL' => $modelName,
                'model' => lcfirst($modelName),
                'table' => $table,
                'TABLE' => $table,
                'primary_key' => $primaryKey[0] ?? 'id',
                'fillable' => $fillableCode,
                'create_rules' => $createRules,
                'update_rules' => $updateRules
            ]);

            $this->success("Controller generated with intelligent validation rules!");
            $this->comment("Based on table structure: {$table}");
            
            return $this->writeController($name, $content);

        } catch (\Exception $e) {
            $this->error("Failed to connect to database: {$e->getMessage()}");
            $this->warn("Falling back to basic generation...");
            return $this->handleBasicGeneration($name);
        }
    }

    /**
     * Write controller file
     */
    private function writeController(string $name, string $content): int
    {
        $path = $this->appPath("Controllers/{$name}.php");
        $this->ensureDirectoryExists(dirname($path));

        if (file_exists($path)) {
            if (!$this->confirm('Controller already exists. Overwrite?', false)) {
                $this->warn('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        file_put_contents($path, $content);
        
        $this->success("Controller created: {$name}");
        $this->comment("Location: app/Controllers/{$name}.php");

        return self::SUCCESS;
    }

    /**
     * Get model name from table name
     */
    private function getModelName(string $table): string
    {
        // Remove trailing 's' for simple pluralization
        $singular = rtrim($table, 's');
        
        // Convert snake_case to PascalCase
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $singular)));
    }
}
