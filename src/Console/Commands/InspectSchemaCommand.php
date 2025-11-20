<?php

namespace Alphavel\Alpha\Console\Commands;

use Alphavel\Alpha\Console\Command;
use Alphavel\Alpha\Generators\SchemaInspector;
use Alphavel\Alpha\Generators\ValidationGenerator;
use Alphavel\Alpha\Generators\RelationshipDetector;

/**
 * Inspect Schema Command - Analyze database schema
 */
class InspectSchemaCommand extends Command
{
    protected string $signature = 'inspect:schema';

    protected string $description = 'Inspect database schema and show table structure';

    public function handle(): int
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
            $detector = new RelationshipDetector($inspector);

            // Get table name
            $table = $this->argument(0);
            
            if (!$table) {
                $tables = $inspector->getTables();
                
                if ($this->hasOption('list')) {
                    $this->info("Tables in database: {$config['database']['name']}");
                    foreach ($tables as $t) {
                        $this->line("  - {$t}");
                    }
                    return self::SUCCESS;
                }
                
                $table = $this->choice('Select table', $tables);
            }

            $this->info("Inspecting table: {$table}\n");

            // Display columns
            $this->displayColumns($inspector, $table);
            
            // Display primary key
            $this->displayPrimaryKey($inspector, $table);
            
            // Display foreign keys
            $this->displayForeignKeys($inspector, $table);
            
            // Display indexes
            $this->displayIndexes($inspector, $table);
            
            // Display validation rules if requested
            if ($this->hasOption('validation')) {
                $this->displayValidationRules($validator, $table);
            }
            
            // Display relationships if requested
            if ($this->hasOption('relationships')) {
                $this->displayRelationships($detector, $table);
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to inspect schema: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Display columns information
     */
    private function displayColumns(SchemaInspector $inspector, string $table): void
    {
        $columns = $inspector->getColumns($table);
        
        $this->line("Columns:");
        $headers = ['Name', 'Type', 'Nullable', 'Default', 'Extra'];
        $rows = [];
        
        foreach ($columns as $column) {
            $rows[] = [
                $column['name'],
                $column['full_type'],
                $column['nullable'] === 'YES' ? 'Yes' : 'No',
                $column['default_value'] ?? 'NULL',
                $column['extra'] ?: '-'
            ];
        }
        
        $this->table($headers, $rows);
    }

    /**
     * Display primary key
     */
    private function displayPrimaryKey(SchemaInspector $inspector, string $table): void
    {
        $primaryKey = $inspector->getPrimaryKey($table);
        
        if (!empty($primaryKey)) {
            $this->line("Primary Key: " . implode(', ', $primaryKey));
            $this->line('');
        }
    }

    /**
     * Display foreign keys
     */
    private function displayForeignKeys(SchemaInspector $inspector, string $table): void
    {
        $foreignKeys = $inspector->getForeignKeys($table);
        
        if (!empty($foreignKeys)) {
            $this->line("Foreign Keys:");
            $headers = ['Column', 'References', 'On Update', 'On Delete'];
            $rows = [];
            
            foreach ($foreignKeys as $fk) {
                $rows[] = [
                    $fk['column_name'],
                    "{$fk['referenced_table']}.{$fk['referenced_column']}",
                    $fk['on_update'],
                    $fk['on_delete']
                ];
            }
            
            $this->table($headers, $rows);
        }
    }

    /**
     * Display indexes
     */
    private function displayIndexes(SchemaInspector $inspector, string $table): void
    {
        $indexes = $inspector->getIndexes($table);
        
        if (!empty($indexes)) {
            $this->line("Indexes:");
            $headers = ['Name', 'Columns', 'Unique', 'Type'];
            $rows = [];
            
            foreach ($indexes as $index) {
                $rows[] = [
                    $index['name'],
                    implode(', ', $index['columns']),
                    $index['unique'] ? 'Yes' : 'No',
                    $index['type']
                ];
            }
            
            $this->table($headers, $rows);
        }
    }

    /**
     * Display validation rules
     */
    private function displayValidationRules(ValidationGenerator $validator, string $table): void
    {
        $this->line("\nValidation Rules (Create):");
        $rules = $validator->generateRules($table, false);
        
        foreach ($rules as $field => $rule) {
            $this->line("  {$field}: {$rule}");
        }
        
        $this->line("\nValidation Rules (Update):");
        $rules = $validator->generateRules($table, true);
        
        foreach ($rules as $field => $rule) {
            $this->line("  {$field}: {$rule}");
        }
    }

    /**
     * Display relationships
     */
    private function displayRelationships(RelationshipDetector $detector, string $table): void
    {
        $relationships = $detector->detectRelationships($table);
        
        $this->line("\nRelationships:");
        
        if (!empty($relationships['belongs_to'])) {
            $this->line("\nBelongsTo:");
            foreach ($relationships['belongs_to'] as $rel) {
                $this->line("  {$rel['method']}() -> {$rel['model']}");
            }
        }
        
        if (!empty($relationships['has_many'])) {
            $this->line("\nHasMany:");
            foreach ($relationships['has_many'] as $rel) {
                $this->line("  {$rel['method']}() -> {$rel['model']}[]");
            }
        }
        
        if (!empty($relationships['has_one'])) {
            $this->line("\nHasOne:");
            foreach ($relationships['has_one'] as $rel) {
                $this->line("  {$rel['method']}() -> {$rel['model']}");
            }
        }
    }
}
