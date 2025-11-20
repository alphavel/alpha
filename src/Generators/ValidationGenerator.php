<?php

namespace Alphavel\Alpha\Generators;

/**
 * Validation Generator
 * 
 * Converts SQL column types to validation rules
 */
class ValidationGenerator
{
    private SchemaInspector $inspector;

    public function __construct(SchemaInspector $inspector)
    {
        $this->inspector = $inspector;
    }

    /**
     * Generate validation rules for table
     */
    public function generateRules(string $table, bool $forUpdate = false): array
    {
        $columns = $this->inspector->getColumns($table);
        $primaryKey = $this->inspector->getPrimaryKey($table);
        $rules = [];

        foreach ($columns as $column) {
            // Skip auto-increment and primary keys
            if ($this->inspector->isAutoIncrement($column)) {
                continue;
            }

            // Skip primary key on create, but allow on update
            if (!$forUpdate && in_array($column['name'], $primaryKey)) {
                continue;
            }

            $columnRules = $this->generateColumnRules($column, $table, $forUpdate);
            
            if (!empty($columnRules)) {
                $rules[$column['name']] = implode('|', $columnRules);
            }
        }

        return $rules;
    }

    /**
     * Generate rules for single column
     */
    private function generateColumnRules(array $column, string $table, bool $forUpdate): array
    {
        $rules = [];
        $type = strtolower($column['type']);
        $fullType = strtolower($column['full_type']);

        // Required rule (skip on update if nullable)
        if (!$this->inspector->isNullable($column)) {
            if (!$forUpdate || !$this->inspector->isNullable($column)) {
                $rules[] = 'required';
            }
        } else {
            $rules[] = 'nullable';
        }

        // Type-specific rules
        $rules = array_merge($rules, $this->getTypeRules($column));

        // Unique constraint
        if ($column['key'] === 'UNI') {
            $rules[] = "unique:{$table},{$column['name']}";
        }

        return $rules;
    }

    /**
     * Get validation rules based on column type
     */
    private function getTypeRules(array $column): array
    {
        $type = strtolower($column['type']);
        $fullType = strtolower($column['full_type']);
        $rules = [];

        switch ($type) {
            case 'int':
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'bigint':
                $rules[] = 'integer';
                
                if ($this->inspector->isUnsigned($fullType)) {
                    $rules[] = 'min:0';
                }
                
                if ($type === 'tinyint' && !str_contains($fullType, 'unsigned')) {
                    $rules[] = 'min:-128';
                    $rules[] = 'max:127';
                } elseif ($type === 'tinyint') {
                    $rules[] = 'max:255';
                }
                break;

            case 'float':
            case 'double':
            case 'decimal':
                $rules[] = 'numeric';
                
                if ($this->inspector->isUnsigned($fullType)) {
                    $rules[] = 'min:0';
                }
                break;

            case 'varchar':
            case 'char':
                $rules[] = 'string';
                
                if ($column['max_length']) {
                    $rules[] = "max:{$column['max_length']}";
                }
                break;

            case 'text':
            case 'mediumtext':
            case 'longtext':
                $rules[] = 'string';
                break;

            case 'date':
                $rules[] = 'date';
                break;

            case 'datetime':
            case 'timestamp':
                $rules[] = 'date';
                break;

            case 'time':
                $rules[] = 'date_format:H:i:s';
                break;

            case 'year':
                $rules[] = 'integer';
                $rules[] = 'min:1901';
                $rules[] = 'max:2155';
                break;

            case 'bool':
            case 'boolean':
                $rules[] = 'boolean';
                break;

            case 'enum':
                $values = $this->inspector->getEnumValues($column['full_type']);
                if (!empty($values)) {
                    $rules[] = 'in:' . implode(',', $values);
                }
                break;

            case 'json':
                $rules[] = 'array';
                break;

            case 'email':
                $rules[] = 'email';
                break;

            case 'url':
                $rules[] = 'url';
                break;
        }

        return $rules;
    }

    /**
     * Generate validation array as PHP code
     */
    public function generateValidationCode(string $table, bool $forUpdate = false, int $indent = 2): string
    {
        $rules = $this->generateRules($table, $forUpdate);
        $lines = [];
        $spaces = str_repeat(' ', $indent);

        foreach ($rules as $field => $rule) {
            $lines[] = "{$spaces}'{$field}' => '{$rule}',";
        }

        return implode("\n", $lines);
    }

    /**
     * Generate separate create and update rules
     */
    public function generateCreateAndUpdateRules(string $table): array
    {
        return [
            'create' => $this->generateRules($table, false),
            'update' => $this->generateRules($table, true)
        ];
    }

    /**
     * Generate validation rules as array
     */
    public function toArray(string $table): array
    {
        return $this->generateRules($table);
    }

    /**
     * Generate validation rules as JSON
     */
    public function toJson(string $table): string
    {
        return json_encode($this->generateRules($table), JSON_PRETTY_PRINT);
    }
}
