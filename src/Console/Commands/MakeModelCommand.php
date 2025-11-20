<?php

namespace Alphavel\Alpha\Console\Commands;

use Alphavel\Alpha\Console\Command;
use Alphavel\Alpha\Generators\SchemaInspector;
use Alphavel\Alpha\Generators\RelationshipDetector;

/**
 * Make Model Command - Intelligent generation from database
 */
class MakeModelCommand extends Command
{
    protected string $signature = 'make:model';

    protected string $description = 'Create a new model class (with optional intelligent generation from database)';

    public function handle(): int
    {
        $name = $this->argument(0) ?? $this->ask('Model name');

        if (!$name) {
            $this->error('Model name is required.');
            return self::FAILURE;
        }

        // Check if should use intelligent generation
        $useDatabase = $this->hasOption('table');
        
        if (!$useDatabase && $this->confirm('Generate from database table?', false)) {
            $useDatabase = true;
        }

        if ($useDatabase) {
            return $this->handleIntelligentGeneration($name);
        }

        return $this->handleBasicGeneration($name);
    }

    /**
     * Handle basic model generation
     */
    private function handleBasicGeneration(string $name): int
    {
        $stub = $this->getStub('model.basic');

        $content = $this->replaceInStub($stub, [
            'CLASS' => $name,
            'table' => $this->getTableName($name)
        ]);

        return $this->writeModel($name, $content);
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
            $detector = new RelationshipDetector($inspector);

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
            
            // Get fillable columns
            $fillable = [];
            foreach ($columns as $column) {
                if (!$inspector->isAutoIncrement($column) && 
                    !in_array($column['name'], ['created_at', 'updated_at'])) {
                    $fillable[] = "        '{$column['name']}'";
                }
            }
            $fillableCode = implode(",\n", $fillable);

            // Generate relationships
            $relationshipsCode = $detector->generateCode($table);

            // Generate model
            $stub = $this->getStub('model.intelligent');

            $content = $this->replaceInStub($stub, [
                'CLASS' => $name,
                'table' => $table,
                'primary_key' => $primaryKey[0] ?? 'id',
                'fillable' => $fillableCode,
                'relationships' => $relationshipsCode
            ]);

            $this->success("Model generated with relationships!");
            $this->comment("Based on table structure: {$table}");
            
            return $this->writeModel($name, $content);

        } catch (\Exception $e) {
            $this->error("Failed to connect to database: {$e->getMessage()}");
            $this->warn("Falling back to basic generation...");
            return $this->handleBasicGeneration($name);
        }
    }

    /**
     * Write model file
     */
    private function writeModel(string $name, string $content): int
    {
        $path = $this->appPath("Models/{$name}.php");
        $this->ensureDirectoryExists(dirname($path));

        if (file_exists($path)) {
            if (!$this->confirm('Model already exists. Overwrite?', false)) {
                $this->warn('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        file_put_contents($path, $content);
        
        $this->success("Model created: {$name}");
        $this->comment("Location: app/Models/{$name}.php");

        return self::SUCCESS;
    }

    /**
     * Get table name from model name
     */
    private function getTableName(string $model): string
    {
        // Convert PascalCase to snake_case and pluralize
        $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $model));
        
        return $snake . 's';
    }
}
