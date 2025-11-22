<?php

namespace Alphavel\Alpha\Console\Commands;

use Alphavel\Alpha\Console\Command;

/**
 * Make Auth Configuration Command
 * 
 * Helps configure JWT authentication (optional package)
 */
class MakeAuthCommand extends Command
{
    protected string $signature = 'make:auth';

    protected string $description = 'Configure JWT authentication (requires alphavel/auth)';

    public function handle(): int
    {
        // Check if auth package is installed
        if (!$this->isAuthInstalled()) {
            $this->warn('⚠️  Auth package is not installed.');
            $this->line('');
            $this->comment('The alphavel/auth package is optional.');
            $this->line('');
            
            if ($this->confirm('Install alphavel/auth now?', true)) {
                return $this->installPackage();
            }
            
            $this->line('');
            $this->comment('To install manually:');
            $this->comment('  composer require alphavel/auth');
            $this->line('');
            
            return self::FAILURE;
        }

        $this->info('✓ Auth package is installed');
        $this->line('');

        return $this->runConfigurationWizard();
    }

    private function isAuthInstalled(): bool
    {
        // Check if class exists
        if (class_exists('Alphavel\Auth\AuthServiceProvider')) {
            return true;
        }

        // Check composer.json
        $composerPath = getcwd() . '/composer.json';
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            if (isset($composer['require']['alphavel/auth'])) {
                return true;
            }
        }

        // Check vendor
        return file_exists(getcwd() . '/vendor/alphavel/auth');
    }

    private function installPackage(): int
    {
        $this->info('Installing alphavel/auth...');
        $this->line('');

        $output = [];
        $returnCode = 0;

        exec('composer require alphavel/auth 2>&1', $output, $returnCode);

        if ($returnCode === 0) {
            $this->success('✓ Package installed successfully!');
            $this->line('');
            
            // Publish config
            $this->info('Publishing configuration...');
            exec('php alphavel vendor:publish --tag=auth-config', $output);
            
            $this->line('');
            return $this->runConfigurationWizard();
        }

        $this->error('✗ Failed to install package');
        foreach ($output as $line) {
            $this->line($line);
        }

        return self::FAILURE;
    }

    private function runConfigurationWizard(): int
    {
        $this->comment('📋 JWT Authentication Configuration Wizard');
        $this->line('');

        $action = $this->choice(
            'What would you like to do?',
            [
                'setup' => 'Setup JWT configuration (.env)',
                'user' => 'Create User model with Authenticatable',
                'routes' => 'Add authentication routes',
                'example' => 'Show usage examples',
                'exit' => 'Exit'
            ]
        );

        return match ($action) {
            'setup' => $this->setupConfiguration(),
            'user' => $this->createUserModel(),
            'routes' => $this->addAuthRoutes(),
            'example' => $this->showExamples(),
            default => self::SUCCESS
        };
    }

    private function setupConfiguration(): int
    {
        $this->info('⚙️  Configuring JWT authentication');
        $this->line('');

        // Generate secret if not exists
        $envPath = getcwd() . '/.env';
        $hasSecret = false;

        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            $hasSecret = str_contains($envContent, 'JWT_SECRET=');
        }

        if (!$hasSecret) {
            $secret = bin2hex(random_bytes(32));
            $this->line('Generated JWT_SECRET:');
            $this->line('');
            $this->line("JWT_SECRET={$secret}");
            $this->line('');
        }

        $ttl = $this->ask('Token TTL in minutes', '60');
        $refreshTtl = $this->ask('Refresh token TTL in minutes', '20160');

        $this->line('');
        $this->comment('Add these to your .env file:');
        $this->line('');
        
        if (!$hasSecret) {
            $this->line("JWT_SECRET={$secret}");
        }
        $this->line("JWT_TTL={$ttl}");
        $this->line("JWT_REFRESH_TTL={$refreshTtl}");
        $this->line("JWT_ALGO=HS256");
        $this->line("JWT_BLACKLIST_ENABLED=true");
        $this->line("JWT_BLACKLIST_SIZE=100000");
        $this->line('');

        $this->success('✓ Configuration ready!');
        $this->line('');
        $this->comment('Next steps:');
        $this->comment('1. Add the values above to your .env');
        $this->comment('2. Run: php alpha make:auth (choose "user" option)');
        $this->comment('3. Add authentication routes');
        $this->line('');

        return self::SUCCESS;
    }

    private function createUserModel(): int
    {
        $this->info('👤 Creating User model with Authenticatable');
        $this->line('');

        $modelPath = getcwd() . '/app/Models/User.php';

        if (file_exists($modelPath)) {
            if (!$this->confirm('User model already exists. Overwrite?', false)) {
                $this->warn('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        $stub = <<<'PHP'
<?php

namespace App\Models;

use Alphavel\Database\Model;
use Alphavel\Auth\Contracts\Authenticatable;

class User extends Model implements Authenticatable
{
    protected string $table = 'users';
    
    protected array $fillable = [
        'name',
        'email',
        'password',
    ];
    
    protected array $hidden = [
        'password',
    ];
    
    /**
     * Get unique identifier for authentication
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }
    
    /**
     * Get hashed password for authentication
     */
    public function getAuthPassword(): string
    {
        return $this->password;
    }
}
PHP;

        if (!is_dir(dirname($modelPath))) {
            mkdir(dirname($modelPath), 0755, true);
        }

        file_put_contents($modelPath, $stub);

        $this->success('✓ User model created!');
        $this->comment('  Location: app/Models/User.php');
        $this->line('');
        $this->comment('Create users table migration:');
        $this->comment('  php alphavel make:migration create_users_table');
        $this->line('');

        return self::SUCCESS;
    }

    private function addAuthRoutes(): int
    {
        $this->info('🛣️  Adding authentication routes');
        $this->line('');

        $this->comment('Add these routes to routes/api.php:');
        $this->line('');
        $this->line('// Authentication routes');
        $this->line('$router->post(\'/auth/register\', [AuthController::class, \'register\']);');
        $this->line('$router->post(\'/auth/login\', [AuthController::class, \'login\']);');
        $this->line('$router->post(\'/auth/logout\', [AuthController::class, \'logout\'])');
        $this->line('    ->middleware(\'auth\');');
        $this->line('$router->get(\'/auth/me\', [AuthController::class, \'me\'])');
        $this->line('    ->middleware(\'auth\');');
        $this->line('');

        if ($this->confirm('Create AuthController?', true)) {
            return $this->createAuthController();
        }

        return self::SUCCESS;
    }

    private function createAuthController(): int
    {
        $controllerPath = getcwd() . '/app/Controllers/AuthController.php';

        if (file_exists($controllerPath)) {
            if (!$this->confirm('AuthController already exists. Overwrite?', false)) {
                return self::SUCCESS;
            }
        }

        $stub = <<<'PHP'
<?php

namespace App\Controllers;

use App\Models\User;
use Alphavel\Auth\Facades\Auth;

class AuthController extends Controller
{
    public function register($request)
    {
        // Validate
        $data = $request->json();
        
        // Create user
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_ARGON2ID),
        ]);
        
        // Login
        Auth::login($user);
        
        return response()->json([
            'user' => $user,
            'token' => Auth::token(),
        ], 201);
    }
    
    public function login($request)
    {
        $credentials = $request->json();
        
        if (!Auth::attempt($credentials)) {
            return response()->json([
                'error' => 'Invalid credentials'
            ], 401);
        }
        
        return response()->json([
            'user' => Auth::user(),
            'token' => Auth::token(),
        ]);
    }
    
    public function logout()
    {
        Auth::logout();
        
        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
    
    public function me()
    {
        return response()->json([
            'user' => Auth::user()
        ]);
    }
}
PHP;

        if (!is_dir(dirname($controllerPath))) {
            mkdir(dirname($controllerPath), 0755, true);
        }

        file_put_contents($controllerPath, $stub);

        $this->success('✓ AuthController created!');
        $this->comment('  Location: app/Controllers/AuthController.php');
        $this->line('');

        return self::SUCCESS;
    }

    private function showExamples(): int
    {
        $this->info('📚 JWT Authentication Examples');
        $this->line('');

        $examples = [
            [
                'title' => '1. Register new user',
                'code' => 'POST /auth/register
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secret123"
}

Response:
{
    "user": {...},
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}'
            ],
            [
                'title' => '2. Login',
                'code' => 'POST /auth/login
{
    "email": "john@example.com",
    "password": "secret123"
}

Response:
{
    "user": {...},
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}'
            ],
            [
                'title' => '3. Access protected route',
                'code' => 'GET /auth/me
Headers:
    Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...

Response:
{
    "user": {...}
}'
            ],
            [
                'title' => '4. Protect routes with middleware',
                'code' => '$router->middleware(\'auth\')->group(function ($router) {
    $router->get(\'/profile\', [ProfileController::class, \'show\']);
    $router->put(\'/profile\', [ProfileController::class, \'update\']);
});'
            ],
            [
                'title' => '5. Use in controller',
                'code' => 'use Alphavel\Auth\Facades\Auth;

public function show()
{
    $user = Auth::user();
    $userId = Auth::id();
    $isAuth = Auth::check();
    
    return response()->json([\'user\' => $user]);
}'
            ],
        ];

        foreach ($examples as $example) {
            $this->comment($example['title']);
            $this->line('');
            $this->line($example['code']);
            $this->line('');
            $this->line(str_repeat('-', 60));
            $this->line('');
        }

        $this->comment('Performance:');
        $this->line('- Token validation: < 0.001ms');
        $this->line('- Auth check overhead: < 0.1%');
        $this->line('- Swoole Table blacklist: 150x faster than Redis');
        $this->line('');

        $this->comment('Documentation:');
        $this->line('https://github.com/alphavel/auth');
        $this->line('');

        return self::SUCCESS;
    }
}
