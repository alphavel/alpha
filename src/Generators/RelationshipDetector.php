<?php

namespace Alphavel\Alpha\Generators;

/**
 * Relationship Detector
 * 
 * Analyzes Foreign Keys to generate Model relationships
 */
class RelationshipDetector
{
    private SchemaInspector $inspector;

    public function __construct(SchemaInspector $inspector)
    {
        $this->inspector = $inspector;
    }

    /**
     * Detect all relationships for a table
     */
    public function detectRelationships(string $table): array
    {
        return [
            'belongs_to' => $this->detectBelongsTo($table),
            'has_many' => $this->detectHasMany($table),
            'has_one' => $this->detectHasOne($table)
        ];
    }

    /**
     * Detect BelongsTo relationships (this table has foreign keys)
     */
    public function detectBelongsTo(string $table): array
    {
        $foreignKeys = $this->inspector->getForeignKeys($table);
        $relationships = [];

        foreach ($foreignKeys as $fk) {
            $relationships[] = [
                'type' => 'belongsTo',
                'method' => $this->getMethodName($fk['referenced_table']),
                'model' => $this->getModelName($fk['referenced_table']),
                'foreign_key' => $fk['column_name'],
                'owner_key' => $fk['referenced_column'],
                'table' => $fk['referenced_table']
            ];
        }

        return $relationships;
    }

    /**
     * Detect HasMany relationships (other tables reference this table)
     */
    public function detectHasMany(string $table): array
    {
        $allTables = $this->inspector->getTables();
        $relationships = [];

        foreach ($allTables as $otherTable) {
            if ($otherTable === $table) {
                continue;
            }

            $foreignKeys = $this->inspector->getForeignKeys($otherTable);

            foreach ($foreignKeys as $fk) {
                if ($fk['referenced_table'] === $table) {
                    $relationships[] = [
                        'type' => 'hasMany',
                        'method' => $this->pluralize($this->getMethodName($otherTable)),
                        'model' => $this->getModelName($otherTable),
                        'foreign_key' => $fk['column_name'],
                        'local_key' => $fk['referenced_column'],
                        'table' => $otherTable
                    ];
                }
            }
        }

        return $relationships;
    }

    /**
     * Detect HasOne relationships
     * Similar to HasMany but for 1:1 relationships
     */
    public function detectHasOne(string $table): array
    {
        $hasMany = $this->detectHasMany($table);
        $relationships = [];

        // Check if foreign key is unique (indicates 1:1)
        foreach ($hasMany as $relation) {
            $indexes = $this->inspector->getIndexes($relation['table']);
            
            foreach ($indexes as $index) {
                if ($index['unique'] && in_array($relation['foreign_key'], $index['columns'])) {
                    $relationships[] = [
                        'type' => 'hasOne',
                        'method' => $this->singularize($relation['method']),
                        'model' => $relation['model'],
                        'foreign_key' => $relation['foreign_key'],
                        'local_key' => $relation['local_key'],
                        'table' => $relation['table']
                    ];
                    break;
                }
            }
        }

        return $relationships;
    }

    /**
     * Generate relationship method code
     */
    public function generateRelationshipMethods(string $table): array
    {
        $relationships = $this->detectRelationships($table);
        $methods = [];

        foreach ($relationships['belongs_to'] as $relation) {
            $methods[] = $this->generateBelongsToMethod($relation);
        }

        foreach ($relationships['has_many'] as $relation) {
            $methods[] = $this->generateHasManyMethod($relation);
        }

        foreach ($relationships['has_one'] as $relation) {
            $methods[] = $this->generateHasOneMethod($relation);
        }

        return $methods;
    }

    /**
     * Generate BelongsTo method code
     */
    private function generateBelongsToMethod(array $relation): string
    {
        return <<<PHP
    /**
     * Get the {$relation['method']} that owns this record
     */
    public function {$relation['method']}()
    {
        return \$this->belongsTo({$relation['model']}::class, '{$relation['foreign_key']}', '{$relation['owner_key']}');
    }
PHP;
    }

    /**
     * Generate HasMany method code
     */
    private function generateHasManyMethod(array $relation): string
    {
        return <<<PHP
    /**
     * Get all {$relation['method']} for this record
     */
    public function {$relation['method']}()
    {
        return \$this->hasMany({$relation['model']}::class, '{$relation['foreign_key']}', '{$relation['local_key']}');
    }
PHP;
    }

    /**
     * Generate HasOne method code
     */
    private function generateHasOneMethod(array $relation): string
    {
        return <<<PHP
    /**
     * Get the {$relation['method']} for this record
     */
    public function {$relation['method']}()
    {
        return \$this->hasOne({$relation['model']}::class, '{$relation['foreign_key']}', '{$relation['local_key']}');
    }
PHP;
    }

    /**
     * Get model name from table name
     */
    private function getModelName(string $table): string
    {
        // Convert table name to singular PascalCase
        // users -> User
        // user_profiles -> UserProfile
        
        $singular = $this->singularize($table);
        
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $singular)));
    }

    /**
     * Get method name from table name
     */
    private function getMethodName(string $table): string
    {
        // Convert table name to singular camelCase
        // users -> user
        // user_profiles -> userProfile
        
        $singular = $this->singularize($table);
        $words = explode('_', $singular);
        
        $camelCase = array_shift($words);
        foreach ($words as $word) {
            $camelCase .= ucfirst($word);
        }
        
        return $camelCase;
    }

    /**
     * Simple singularize (remove trailing 's')
     */
    private function singularize(string $word): string
    {
        // Basic rules
        $irregulars = [
            'people' => 'person',
            'children' => 'child',
            'oxen' => 'ox',
            'feet' => 'foot',
            'teeth' => 'tooth',
            'geese' => 'goose'
        ];

        if (isset($irregulars[$word])) {
            return $irregulars[$word];
        }

        // Remove trailing 's' if present
        if (str_ends_with($word, 'ies')) {
            return substr($word, 0, -3) . 'y';
        }

        if (str_ends_with($word, 'es')) {
            return substr($word, 0, -2);
        }

        if (str_ends_with($word, 's')) {
            return substr($word, 0, -1);
        }

        return $word;
    }

    /**
     * Simple pluralize (add 's')
     */
    private function pluralize(string $word): string
    {
        // Basic rules
        $irregulars = [
            'person' => 'people',
            'child' => 'children',
            'ox' => 'oxen',
            'foot' => 'feet',
            'tooth' => 'teeth',
            'goose' => 'geese'
        ];

        if (isset($irregulars[$word])) {
            return $irregulars[$word];
        }

        // Add 's' or 'es'
        if (str_ends_with($word, 'y')) {
            return substr($word, 0, -1) . 'ies';
        }

        if (str_ends_with($word, 's') || str_ends_with($word, 'x') || 
            str_ends_with($word, 'ch') || str_ends_with($word, 'sh')) {
            return $word . 'es';
        }

        return $word . 's';
    }

    /**
     * Generate relationship code as string
     */
    public function generateCode(string $table, int $indent = 4): string
    {
        $methods = $this->generateRelationshipMethods($table);
        
        if (empty($methods)) {
            return '';
        }

        $spaces = str_repeat(' ', $indent);
        $code = "\n" . $spaces . "// Relationships\n\n";
        $code .= implode("\n\n", $methods);

        return $code;
    }

    /**
     * Get relationships as array
     */
    public function toArray(string $table): array
    {
        return $this->detectRelationships($table);
    }

    /**
     * Get relationships as JSON
     */
    public function toJson(string $table): string
    {
        return json_encode($this->detectRelationships($table), JSON_PRETTY_PRINT);
    }
}
