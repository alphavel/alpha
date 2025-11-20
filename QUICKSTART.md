# Alphavel Alpha - Quick Start Guide

## Installation

### 1. Install as Project Dependency (Recommended)

```bash
cd your-alphavel-project
composer require alphavel/alpha --dev
```

### 2. Install Globally (Optional)

```bash
composer global require alphavel/alpha
```

## First Steps

### 1. List Available Commands

```bash
php alpha list
```

Output:
```
alpha version 1.0.0

Usage:
  command [options] [arguments]

Available commands:

  inspect:schema       Inspect database schema and show table structure
  make:controller      Create a new controller class
  make:model          Create a new model class
  make:resource       Generate Model, Controller and Routes for a resource
```

### 2. Inspect Your Database

```bash
# List all tables
php alpha inspect:schema --list

# Inspect specific table
php alpha inspect:schema users
```

Output:
```
Inspecting table: users

Columns:
| Name       | Type         | Nullable | Default | Extra          |
|------------|--------------|----------|---------|----------------|
| id         | int(11)      | No       | NULL    | auto_increment |
| name       | varchar(100) | No       | NULL    | -              |
| email      | varchar(255) | No       | NULL    | -              |
| created_at | timestamp    | No       | CURRENT | -              |

Primary Key: id
```

### 3. Generate Your First Resource

The fastest way to create a complete CRUD resource:

```bash
php alpha make:resource User
```

This generates:
1. ✅ `app/Models/User.php` - Model with relationships
2. ✅ `app/Controllers/UserController.php` - Controller with CRUD
3. ✅ Routes suggestion (ready to copy)

### 4. Add Routes

Copy the suggested routes to `routes/api.php`:

```php
// User Resource
$router->get('/users', 'UserController@index');
$router->get('/users/{id}', 'UserController@show');
$router->post('/users', 'UserController@store');
$router->put('/users/{id}', 'UserController@update');
$router->delete('/users/{id}', 'UserController@destroy');
```

### 5. Test Your API

```bash
# Start server
php alphavel serve

# Test in another terminal
curl http://localhost:8080/users
```

## Common Workflows

### Workflow 1: Create API Resource from Existing Table

**Scenario:** You have a `products` table and want to create an API for it.

```bash
# Step 1: Inspect the table
php alpha inspect:schema products --validation --relationships

# Step 2: Generate everything
php alpha make:resource Product

# Step 3: Add routes (copy from output)
# Edit routes/api.php and add the suggested routes

# Step 4: Test
curl -X POST http://localhost:8080/products \
  -H "Content-Type: application/json" \
  -d '{"name":"Widget","price":19.99}'
```

### Workflow 2: Create Controller with Custom Validation

**Scenario:** You want fine control over the controller but want validation generated from schema.

```bash
# Generate with intelligent validation
php alpha make:controller ProductController --table=products
```

Generated controller will have:
```php
public function store(Request $request): Response
{
    $data = $request->only(['name', 'price', 'description', 'stock']);
    
    // ✨ Validation rules auto-generated from schema
    $errors = $request->validate($data, [
        'name' => 'required|string|max:100',
        'price' => 'required|numeric|min:0',
        'description' => 'nullable|string',
        'stock' => 'required|integer|min:0'
    ]);
    
    if ($errors) {
        return Response::error('Validation failed', 422, $errors);
    }
    
    $item = Product::create($data);
    return Response::success($item, 201);
}
```

### Workflow 3: Create Model with Relationships

**Scenario:** You want a model that automatically includes all relationships.

```bash
# Generate with relationships
php alpha make:model Post --table=posts
```

If `posts` table has:
```sql
FOREIGN KEY (user_id) REFERENCES users(id)
FOREIGN KEY (category_id) REFERENCES categories(id)
```

Generated model will have:
```php
class Post extends Model
{
    // ... fillable, etc.
    
    // Relationships automatically detected!
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }
}
```

And in `User` model:
```php
public function posts()
{
    return $this->hasMany(Post::class, 'user_id', 'id');
}
```

### Workflow 4: Explore Schema Before Development

**Scenario:** You're joining an existing project and want to understand the database.

```bash
# List all tables
php alpha inspect:schema --list

# Inspect each table with relationships
php alpha inspect:schema users --relationships
php alpha inspect:schema posts --relationships
php alpha inspect:schema categories --relationships

# See validation rules for a complex table
php alpha inspect:schema orders --validation
```

## Real-World Example

Let's build a simple blog API from scratch:

### 1. Database Schema

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    status ENUM('draft', 'published') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 2. Generate Resources

```bash
# Generate all resources
php alpha make:resource User
php alpha make:resource Post
php alpha make:resource Comment
```

### 3. Check Generated Models

`app/Models/User.php`:
```php
class User extends Model
{
    protected array $fillable = ['name', 'email'];
    
    // ✨ Auto-detected relationships
    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }
    
    public function comments()
    {
        return $this->hasMany(Comment::class, 'user_id', 'id');
    }
}
```

`app/Models/Post.php`:
```php
class Post extends Model
{
    protected array $fillable = ['user_id', 'title', 'content', 'status'];
    
    // ✨ Auto-detected relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    
    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id', 'id');
    }
}
```

### 4. Check Generated Controller

`app/Controllers/PostController.php`:
```php
class PostController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->only(['user_id', 'title', 'content', 'status']);
        
        // ✨ Validation auto-generated from schema
        $errors = $request->validate($data, [
            'user_id' => 'required|integer|min:0',
            'title' => 'required|string|max:200',
            'content' => 'nullable|string',
            'status' => 'in:draft,published'
        ]);
        
        if ($errors) {
            return Response::error('Validation failed', 422, $errors);
        }
        
        $item = Post::create($data);
        return Response::success($item, 201);
    }
    
    // ... other CRUD methods
}
```

### 5. Add Routes

`routes/api.php`:
```php
// User Resource
$router->get('/users', 'UserController@index');
$router->post('/users', 'UserController@store');

// Post Resource
$router->get('/posts', 'PostController@index');
$router->get('/posts/{id}', 'PostController@show');
$router->post('/posts', 'PostController@store');
$router->put('/posts/{id}', 'PostController@update');
$router->delete('/posts/{id}', 'PostController@destroy');

// Comment Resource
$router->get('/posts/{post_id}/comments', 'CommentController@index');
$router->post('/posts/{post_id}/comments', 'CommentController@store');
```

### 6. Test the API

```bash
# Create user
curl -X POST http://localhost:8080/users \
  -H "Content-Type: application/json" \
  -d '{"name":"John Doe","email":"john@example.com"}'

# Create post
curl -X POST http://localhost:8080/posts \
  -H "Content-Type: application/json" \
  -d '{"user_id":1,"title":"My First Post","content":"Hello World","status":"published"}'

# Get all posts
curl http://localhost:8080/posts

# Get post by ID
curl http://localhost:8080/posts/1

# Update post
curl -X PUT http://localhost:8080/posts/1 \
  -H "Content-Type: application/json" \
  -d '{"title":"Updated Title","status":"draft"}'

# Delete post
curl -X DELETE http://localhost:8080/posts/1
```

## Tips & Tricks

### 1. Preview Before Overwrite

Alpha will always ask before overwriting existing files:
```
Controller already exists. Overwrite? (yes/no) [no]:
```

### 2. Use Interactive Mode

Just run the command without arguments:
```bash
php alpha make:controller
# Will prompt for all needed info
```

### 3. Inspect Schema with All Options

```bash
php alpha inspect:schema users --validation --relationships
```

### 4. Generate from Table Automatically

```bash
# Will list tables and let you choose
php alpha make:model User
Generate from database table? (yes/no) [no]: yes
Select table
  [0] users
  [1] posts
  [2] comments
Choice: 0
```

### 5. Batch Generate

Use a simple script:
```bash
#!/bin/bash
for table in users posts comments categories tags
do
  php alpha make:resource ${table^}  # Capitalize first letter
done
```

## Common Errors

### Error: Could not find autoloader

**Solution:** Run `composer install` first.

### Error: Failed to connect to database

**Solution:** Check `config/app.php` database settings:
```php
'database' => [
    'host' => 'localhost',
    'name' => 'your_database',
    'user' => 'your_user',
    'password' => 'your_password'
]
```

### Error: Command not found

**Solution:** Use full path or add to PATH:
```bash
# Full path
php vendor/bin/alpha

# Or add alias
alias alpha="php vendor/bin/alpha"

# Or install globally
composer global require alphavel/alpha
```

## Next Steps

- Read the full [Architecture Guide](ARCHITECTURE.md)
- Check out [Examples](examples/)
- Join the [Discord Community](https://discord.gg/alphavel)
- Report bugs on [GitHub Issues](https://github.com/alphavel/alpha/issues)

## Getting Help

```bash
# General help
php alpha --help

# Command-specific help
php alpha make:controller --help

# List all commands
php alpha list
```

Happy coding! 🚀
