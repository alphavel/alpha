# Changelog

All notable changes to Alphavel Alpha will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2024-11-20

### Added
- Initial release of Alphavel Alpha CLI
- Schema Inspector for reading MySQL database structure
- Validation Generator for converting SQL types to validation rules
- Relationship Detector for analyzing Foreign Keys
- Intelligent code generation commands:
  - `make:controller` - Generate controllers with schema-aware validation
  - `make:model` - Generate models with relationships
  - `make:resource` - Generate complete resource (Model + Controller + Routes)
  - `inspect:schema` - Analyze database schema
- Command base class with I/O helpers (info, error, warn, success, etc.)
- Console application with auto-discovery
- Stub-based code generation system
- Support for BelongsTo, HasMany, and HasOne relationships
- Automatic detection of:
  - Column types and constraints
  - Primary keys
  - Foreign keys
  - Indexes (unique and non-unique)
  - Enum values
  - Auto-increment columns
  - Nullable fields

### Features
- **Zero Configuration**: Works out of the box with Alphavel Framework
- **Intelligent Generation**: Reads actual database schema
- **Context-Aware**: Generates validation rules based on column types
- **Relationship Detection**: Analyzes Foreign Keys to generate Model methods
- **Interactive CLI**: Asks questions when needed, accepts arguments/options
- **Colored Output**: Beautiful terminal output with colors
- **Progress Tracking**: Progress bars for long operations
- **Table Display**: Formatted table output for data
- **Extensible**: Easy to add custom commands

### Technical Details
- PHP 8.2+ required
- PSR-4 autoloading
- Dependency on alphavel/alphavel and alphavel/database
- Uses PDO for database inspection
- INFORMATION_SCHEMA queries for schema reading
- Stub-based template system

[Unreleased]: https://github.com/alphavel/alpha/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/alphavel/alpha/releases/tag/v1.0.0
