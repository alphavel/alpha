<?php

namespace Alphavel\Alpha\Generators;

use PDO;

/**
 * Schema Inspector
 * 
 * Reads MySQL database schema using INFORMATION_SCHEMA
 */
class SchemaInspector
{
    private PDO $pdo;
    
    private string $database;

    public function __construct(PDO $pdo, string $database)
    {
        $this->pdo = $pdo;
        $this->database = $database;
    }

    /**
     * Get all tables in database
     */
    public function getTables(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = :database
            AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME
        ");

        $stmt->execute(['database' => $this->database]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get table columns with full metadata
     */
    public function getColumns(string $table): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COLUMN_NAME as name,
                DATA_TYPE as type,
                COLUMN_TYPE as full_type,
                IS_NULLABLE as nullable,
                COLUMN_DEFAULT as default_value,
                CHARACTER_MAXIMUM_LENGTH as max_length,
                NUMERIC_PRECISION as numeric_precision,
                NUMERIC_SCALE as numeric_scale,
                COLUMN_KEY as key,
                EXTRA as extra,
                COLUMN_COMMENT as comment
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :database
            AND TABLE_NAME = :table
            ORDER BY ORDINAL_POSITION
        ");

        $stmt->execute([
            'database' => $this->database,
            'table' => $table
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get primary key column(s)
     */
    public function getPrimaryKey(string $table): array
    {
        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = :database
            AND TABLE_NAME = :table
            AND CONSTRAINT_NAME = 'PRIMARY'
            ORDER BY ORDINAL_POSITION
        ");

        $stmt->execute([
            'database' => $this->database,
            'table' => $table
        ]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get foreign keys for table
     */
    public function getForeignKeys(string $table): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                kcu.COLUMN_NAME as column_name,
                kcu.REFERENCED_TABLE_NAME as referenced_table,
                kcu.REFERENCED_COLUMN_NAME as referenced_column,
                rc.UPDATE_RULE as on_update,
                rc.DELETE_RULE as on_delete
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                AND kcu.TABLE_SCHEMA = rc.CONSTRAINT_SCHEMA
            WHERE kcu.TABLE_SCHEMA = :database
            AND kcu.TABLE_NAME = :table
            AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY kcu.ORDINAL_POSITION
        ");

        $stmt->execute([
            'database' => $this->database,
            'table' => $table
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get indexes for table
     */
    public function getIndexes(string $table): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                INDEX_NAME as name,
                COLUMN_NAME as column,
                NON_UNIQUE as non_unique,
                SEQ_IN_INDEX as sequence,
                INDEX_TYPE as type
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = :database
            AND TABLE_NAME = :table
            AND INDEX_NAME != 'PRIMARY'
            ORDER BY INDEX_NAME, SEQ_IN_INDEX
        ");

        $stmt->execute([
            'database' => $this->database,
            'table' => $table
        ]);

        $indexes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = $row['name'];
            
            if (!isset($indexes[$name])) {
                $indexes[$name] = [
                    'name' => $name,
                    'columns' => [],
                    'unique' => $row['non_unique'] == 0,
                    'type' => $row['type']
                ];
            }
            
            $indexes[$name]['columns'][] = $row['column'];
        }

        return array_values($indexes);
    }

    /**
     * Get enum values from column type
     */
    public function getEnumValues(string $fullType): array
    {
        if (!str_starts_with($fullType, 'enum(')) {
            return [];
        }

        // Extract values from enum('value1','value2',...)
        preg_match("/^enum\((.*)\)$/", $fullType, $matches);
        
        if (!isset($matches[1])) {
            return [];
        }

        $values = explode(',', $matches[1]);
        
        return array_map(function($value) {
            return trim($value, "'\"");
        }, $values);
    }

    /**
     * Check if column is auto increment
     */
    public function isAutoIncrement(array $column): bool
    {
        return str_contains(strtolower($column['extra']), 'auto_increment');
    }

    /**
     * Check if column is nullable
     */
    public function isNullable(array $column): bool
    {
        return strtoupper($column['nullable']) === 'YES';
    }

    /**
     * Check if column is unsigned
     */
    public function isUnsigned(string $fullType): bool
    {
        return str_contains(strtolower($fullType), 'unsigned');
    }

    /**
     * Get PHP type from SQL type
     */
    public function getPhpType(array $column): string
    {
        $type = strtolower($column['type']);

        return match($type) {
            'int', 'tinyint', 'smallint', 'mediumint', 'bigint' => 'int',
            'float', 'double', 'decimal' => 'float',
            'bool', 'boolean' => 'bool',
            'date', 'datetime', 'timestamp', 'time', 'year' => 'string',
            'json' => 'array',
            default => 'string'
        };
    }

    /**
     * Get table metadata summary
     */
    public function getTableMetadata(string $table): array
    {
        return [
            'name' => $table,
            'columns' => $this->getColumns($table),
            'primary_key' => $this->getPrimaryKey($table),
            'foreign_keys' => $this->getForeignKeys($table),
            'indexes' => $this->getIndexes($table)
        ];
    }
}
