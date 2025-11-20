# Alphavel Alpha - Architecture & Usage Guide

## Overview

Alphavel Alpha is an intelligent CLI toolkit for the Alphavel Framework that revolutionizes code generation by reading your actual database schema and generating context-aware, production-ready code.

## Architecture

```
alpha/
├── bin/
│   └── alpha                    # CLI entry point
├── src/
│   ├── Console/
│   │   ├── Command.php          # Base command class
│   │   ├── Console.php          # Console application
│   │   └── Commands/            # Built-in commands
│   │       ├── MakeControllerCommand.php
│   │       ├── MakeModelCommand.php
│   │       ├── MakeResourceCommand.php
│   │       └── InspectSchemaCommand.php
│   ├── Generators/
│   │   ├── SchemaInspector.php      # Database schema reader
│   │   ├── ValidationGenerator.php   # SQL → Validation rules
│   │   └── RelationshipDetector.php  # FK → Model relationships
│   └── Stubs/                   # Code templates
│       ├── controller.*.stub
│       └── model.*.stub
└── tests/
```

## Core Components

### 1. Console Application (`Console.php`)

The console application manages command registration and execution.

**Features:**
- Auto-discovery of commands from directories
- Grouped command listing (by prefix: make:, inspect:, etc.)
- Built-in help and version commands
- Error handling with stack traces

**Usage:**
```php
$console = new Console();
$console->setName('alpha');
$console->setVersion('1.0.0');
$console->autodiscover(__DIR__ . '/Commands');
$console->run($argv);
```

### 2. Base Command (`Command.php`)

All commands extend this base class which provides:

**Argument/Option Parsing:**
```php
// Positional arguments
$name = $this->argument(0);
$name = $this->argument('name');

// Options
$table = $this->option('table');
$force = $this->hasOption('force');
```

**I/O Helpers:**
```php
$this->info('Info message');     // Green
$this->success('Success!');       // Bright green with ✓
$this->error('Error occurred');   // Red
$this->warn('Warning');           // Yellow
$this->comment('Comment');        // Gray
$this->line('Regular text');      // No color
```

**Interactive Input:**
```php
$name = $this->ask('What is your name?', 'default');
$confirmed = $this->confirm('Are you sure?', false);
$choice = $this->choice('Select type', ['API', 'Web', 'CLI'], 0);
```

**Progress & Tables:**
```php
$this->progressBar(100, function($i) {
    // Do work
});

$this->table(['Name', 'Type'], [
    ['id', 'integer'],
    ['name', 'string']
]);
```

**Path Helpers:**
```php
$this->basePath('config/app.php');
$this->appPath('Controllers/UserController.php');
$this->configPath('app.php');
$this->storagePath('cache/routes.php');
```

### 3. Schema Inspector (`SchemaInspector.php`)

Reads MySQL database structure using `INFORMATION_SCHEMA`.

**Methods:**

```php
$inspector = new SchemaInspector($pdo, $database);

// Get all tables
$tables = $inspector->getTables();

// Get columns with metadata
$columns = $inspector->getColumns('users');
// Returns: name, type, full_type, nullable, default_value, 
//          max_length, key, extra, comment

// Get primary key(s)
$primaryKey = $inspector->getPrimaryKey('users');

// Get foreign keys
$foreignKeys = $inspector->getForeignKeys('users');
// Returns: column_name, referenced_table, referenced_column,
//          on_update, on_delete

// Get indexes
$indexes = $inspector->getIndexes('users');
// Returns: name, columns[], unique, type

// Helper methods
$inspector->isAutoIncrement($column);
$inspector->isNullable($column);
$inspector->isUnsigned($fullType);
$inspector->getEnumValues($fullType);
$inspector->getPhpType($column);
```

**Example:**
```php
$columns = $inspector->getColumns('users');
foreach ($columns as $column) {
    echo "{$column['name']}: {$column['type']}\n";
    if ($inspector->isNullable($column)) {
        echo "  - Nullable\n";
    }
    if ($inspector->isAutoIncrement($column)) {
        echo "  - Auto Increment\n";
    }
}
```

### 4. Validation Generator (`ValidationGenerator.php`)

Converts SQL column types to validation rules.

**Methods:**

```php
$validator = new ValidationGenerator($inspector);

// Generate rules for create
$rules = $validator->generateRules('users', false);

// Generate rules for update
$rules = $validator->generateRules('users', true);

// Generate as PHP code
$code = $validator->generateValidationCode('users', false, 12);

// Get both create and update
$both = $validator->generateCreateAndUpdateRules('users');

// Export formats
$array = $validator->toArray('users');
$json = $validator->toJson('users');
```

**Type Conversion Examples:**

| SQL Type | Validation Rules |
|----------|-----------------|
| `INT UNSIGNED` | `integer\|min:0` |
| `VARCHAR(100) NOT NULL` | `required\|string\|max:100` |
| `VARCHAR(255) NULLABLE` | `nullable\|string\|max:255` |
| `ENUM('active','inactive')` | `in:active,inactive` |
| `DECIMAL(10,2) UNSIGNED` | `numeric\|min:0` |
| `DATE` | `date` |
| `DATETIME` | `date` |
| `BOOLEAN` | `boolean` |
| `JSON` | `array` |
| `UNIQUE` column | Adds `unique:table,column` |

### 5. Relationship Detector (`RelationshipDetector.php`)

Analyzes Foreign Keys to generate Model relationships.

**Methods:**

```php
$detector = new RelationshipDetector($inspector);

// Detect all relationships
$relationships = $detector->detectRelationships('posts');
// Returns: belongs_to[], has_many[], has_one[]

// Detect specific types
$belongsTo = $detector->detectBelongsTo('posts');
$hasMany = $detector->detectHasMany('users');
$hasOne = $detector->detectHasOne('users');

// Generate method code
$methods = $detector->generateRelationshipMethods('posts');

// Generate as string
$code = $detector->generateCode('posts', 4);

// Export formats
$array = $detector->toArray('posts');
$json = $detector->toJson('posts');
```

**Relationship Detection Logic:**

1. **BelongsTo**: Current table has foreign key to another table
   ```sql
   CREATE TABLE posts (
       user_id INT,
       FOREIGN KEY (user_id) REFERENCES users(id)
   );
   ```
   Generates in `Post` model:
   ```php
   public function user() {
       return $this->belongsTo(User::class, 'user_id', 'id');
   }
   ```

2. **HasMany**: Other tables reference current table
   ```sql
   -- posts table references users table
   ```
   Generates in `User` model:
   ```php
   public function posts() {
       return $this->hasMany(Post::class, 'user_id', 'id');
   }
   ```

3. **HasOne**: Like HasMany but foreign key has UNIQUE constraint
   ```sql
   CREATE TABLE profiles (
       user_id INT UNIQUE,
       FOREIGN KEY (user_id) REFERENCES users(id)
   );
   ```
   Generates in `User` model:
   ```php
   public function profile() {
       return $this->hasOne(Profile::class, 'user_id', 'id');
   }
   ```

## Commands

### make:controller

Generate controller with optional intelligent generation from database.

**Basic Usage:**
```bash
php alpha make:controller UserController
```

**Intelligent Generation:**
```bash
php alpha make:controller UserController --table=users
php alpha make:controller UserController --model=User
```

**Interactive:**
```bash
php alpha make:controller
# Will prompt for name and options
```

**Generated Code (Intelligent):**
```php
class UserController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->only(['name', 'email', 'age']);
        
        // Validation rules generated from schema
        $errors = $request->validate($data, [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'age' => 'integer|min:0'
        ]);
        
        if ($errors) {
            return Response::error('Validation failed', 422, $errors);
        }
        
        $item = User::create($data);
        return Response::success($item, 201);
    }
}
```

### make:model

Generate model with optional intelligent generation from database.

**Basic Usage:**
```bash
php alpha make:model User
```

**Intelligent Generation:**
```bash
php alpha make:model User --table=users
```

**Generated Code (Intelligent):**
```php
class User extends Model
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'name',
        'email',
        'age',
        'status'
    ];
    
    // Relationships (auto-detected from Foreign Keys)
    
    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }
    
    public function profile()
    {
        return $this->hasOne(Profile::class, 'user_id', 'id');
    }
}
```

### make:resource

Generate complete resource (Model + Controller + Routes suggestion).

**Usage:**
```bash
php alpha make:resource User
```

**What it generates:**
1. `User` model with relationships
2. `UserController` with CRUD operations
3. Routes suggestion (to copy to `routes/api.php`)

**Output:**
```
Creating Model...
✓ Model created: User

Creating Controller...
✓ Controller created: UserController

✓ Resource generated successfully!

Add these routes to routes/api.php:

// User Resource
$router->get('/users', 'UserController@index');
$router->get('/users/{id}', 'UserController@show');
$router->post('/users', 'UserController@store');
$router->put('/users/{id}', 'UserController@update');
$router->delete('/users/{id}', 'UserController@destroy');
```

### inspect:schema

Analyze database schema and display table structure.

**List all tables:**
```bash
php alpha inspect:schema --list
```

**Inspect specific table:**
```bash
php alpha inspect:schema users
```

**With validation rules:**
```bash
php alpha inspect:schema users --validation
```

**With relationships:**
```bash
php alpha inspect:schema users --relationships
```

**Example Output:**
```
Inspecting table: users

Columns:
| Name       | Type             | Nullable | Default | Extra          |
|------------|------------------|----------|---------|----------------|
| id         | int(11)          | No       | NULL    | auto_increment |
| name       | varchar(100)     | No       | NULL    | -              |
| email      | varchar(255)     | No       | NULL    | -              |
| age        | int(11) unsigned | Yes      | NULL    | -              |
| created_at | timestamp        | No       | CURRENT | -              |

Primary Key: id

Indexes:
| Name          | Columns | Unique | Type  |
|---------------|---------|--------|-------|
| idx_email     | email   | Yes    | BTREE |
| idx_name      | name    | No     | BTREE |

Validation Rules (Create):
  name: required|string|max:100
  email: required|email|max:255|unique:users,email
  age: integer|min:0

Relationships:

HasMany:
  posts() -> Post[]
  comments() -> Comment[]
```

## Stub System

Stubs are template files with placeholders that get replaced during code generation.

**Available Stubs:**
- `controller.api.stub` - Simple API controller
- `controller.resource.stub` - Resource controller with CRUD
- `controller.intelligent.stub` - Intelligent controller with validation
- `controller.empty.stub` - Empty controller with single `__invoke` method
- `model.basic.stub` - Basic model
- `model.intelligent.stub` - Model with relationships

**Placeholder Syntax:**
```
{{ CLASS }} - Class name
{{ class }} - lowercase class name
{{ MODEL }} - Model name
{{ model }} - lowercase model name
{{ table }} - Table name
{{ fillable }} - Fillable fields
{{ create_rules }} - Validation rules for create
{{ update_rules }} - Validation rules for update
{{ relationships }} - Relationship methods
```

**Using Stubs:**
```php
$stub = $this->getStub('controller.api');
$content = $this->replaceInStub($stub, [
    'CLASS' => 'UserController',
    'MODEL' => 'User'
]);
file_put_contents($path, $content);
```

## Creating Custom Commands

1. Create command class:

```php
namespace App\Console\Commands;

use Alphavel\Alpha\Console\Command;

class MyCommand extends Command
{
    protected string $signature = 'my:command';
    protected string $description = 'My custom command';
    
    public function handle(): int
    {
        $name = $this->argument(0) ?? $this->ask('Name?');
        
        $this->info("Processing {$name}...");
        
        // Do work
        
        $this->success('Done!');
        
        return self::SUCCESS;
    }
}
```

2. Commands are auto-discovered from `app/Console/Commands/`

3. Run: `php alpha my:command`

## Best Practices

### 1. Always Inspect Schema First
```bash
php alpha inspect:schema users --validation --relationships
```

### 2. Use Intelligent Generation
```bash
# Instead of basic generation
php alpha make:model User --table=users
```

### 3. Generate Complete Resources
```bash
# One command for everything
php alpha make:resource User
```

### 4. Validate Before Commit
```bash
# Check generated code
php alpha inspect:schema users
# Generate test
php alpha make:controller UserController --table=users
# Review generated validation rules
```

### 5. Keep Schema in Sync
```bash
# Re-generate after schema changes
php alpha make:model User --table=users
# Will prompt to overwrite
```

## Performance Considerations

- Schema inspection queries are optimized with proper indexes
- Results can be cached for repeated generation
- Auto-discovery scans only `*Command.php` files
- Reflection used only during initial load

## Limitations

- Currently supports MySQL/MariaDB only
- Relationship detection limited to Foreign Keys
- Many-to-Many requires junction table detection (planned)
- Polymorphic relationships not yet supported

## Roadmap

### v1.1.0
- [ ] PostgreSQL support
- [ ] SQLite support
- [ ] Many-to-Many relationship detection
- [ ] Migration generator from existing tables

### v1.2.0
- [ ] Polymorphic relationship support
- [ ] Custom validation rule templates
- [ ] API documentation generator
- [ ] Postman collection generator

### v2.0.0
- [ ] Visual schema designer
- [ ] Database seeder generator
- [ ] Factory generator from schema
- [ ] Complete test suite generator

## Contributing

See `CONTRIBUTING.md` for details on how to contribute to Alphavel Alpha.

## License

MIT License - see `LICENSE` file for details.
