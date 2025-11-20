# Alphavel Alpha CLI

Alphavel Framework CLI - Intelligent code generation and scaffolding toolkit.

## Features

### 🧠 Intelligent Code Generation
- **Schema-Aware**: Reads your database schema to generate context-aware code
- **Validation Generation**: Automatically converts SQL types to validation rules
- **Relationship Detection**: Analyzes Foreign Keys to generate Model relationships
- **Smart Controllers**: Generates CRUD operations based on actual table structure

### 🚀 Core Commands

```bash
# Generate schema-aware controller
php alpha make:controller UserController --model=User

# Generate model from database table
php alpha make:model User --table=users

# Generate complete resource (Model + Controller + Routes)
php alpha make:resource User

# Docker utilities
php alpha make:docker

# IDE Helper generation
php alpha make:ide-helper

# Interactive REPL
php alpha tinker
```

### 📊 Schema Inspector

The Schema Inspector reads your MySQL database structure:

```php
// Automatically detects:
- Column types (varchar → string, int → integer)
- Nullable columns (nullable validation rule)
- Default values
- Indexes and keys
- Foreign key relationships
- Enum values
```

### ✅ Intelligent Validation

Converts SQL schema to validation rules:

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    age INT UNSIGNED,
    status ENUM('active', 'inactive') DEFAULT 'active'
);
```

Generates:

```php
$rules = [
    'name' => 'required|string|max:100',
    'email' => 'required|email|max:255|unique:users,email',
    'age' => 'integer|min:0',
    'status' => 'in:active,inactive'
];
```

### 🔗 Relationship Detection

Automatically detects relationships from Foreign Keys:

```sql
CREATE TABLE posts (
    id INT PRIMARY KEY,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

Generates in `Post` model:

```php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

And in `User` model:

```php
public function posts(): HasMany
{
    return $this->hasMany(Post::class);
}
```

## Installation

### As Project Dependency

```bash
composer require alphavel/alpha --dev
```

### Global Installation

```bash
composer global require alphavel/alpha
```

## Usage

### List Available Commands

```bash
php alpha list
```

### Get Help for Command

```bash
php alpha make:controller --help
```

## Requirements

- PHP 8.2 or higher
- MySQL 5.7+ or MariaDB 10.2+
- Composer

## Development

### Running Tests

```bash
composer test
```

### Code Style

```bash
composer cs-fix
```

## Architecture

```
alpha/
├── src/
│   ├── Console/
│   │   ├── Command.php           # Base Command class
│   │   ├── Console.php            # Console application
│   │   └── Commands/              # Built-in commands
│   ├── Generators/
│   │   ├── SchemaInspector.php    # Database schema reader
│   │   ├── ValidationGenerator.php # SQL → Validation rules
│   │   └── RelationshipDetector.php # FK → Model relationships
│   └── Stubs/                     # Code templates
├── bin/
│   └── alpha                      # CLI entry point
└── tests/
```

## License

MIT License - see LICENSE file for details
