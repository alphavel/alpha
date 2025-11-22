<?php

namespace Alphavel\Alpha\Console\Commands;

use Alphavel\Alpha\Console\Command;

/**
 * Make Rate Limit Configuration Command
 * 
 * Helps configure rate limiting for routes (optional package)
 */
class MakeRateLimitCommand extends Command
{
    protected string $signature = 'make:rate-limit';

    protected string $description = 'Configure rate limiting for routes (requires alphavel/rate-limit)';

    public function handle(): int
    {
        // Check if rate-limit package is installed
        if (!$this->isRateLimitInstalled()) {
            $this->warn('⚠️  Rate limiting package is not installed.');
            $this->line('');
            $this->comment('The alphavel/rate-limit package is optional.');
            $this->line('');
            
            if ($this->confirm('Install alphavel/rate-limit now?', true)) {
                return $this->installPackage();
            }
            
            $this->line('');
            $this->comment('To install manually:');
            $this->comment('  composer require alphavel/rate-limit');
            $this->line('');
            
            return self::FAILURE;
        }

        $this->info('✓ Rate limiting package is installed');
        $this->line('');

        // Configuration wizard
        return $this->runConfigurationWizard();
    }

    /**
     * Check if rate-limit package is installed
     */
    private function isRateLimitInstalled(): bool
    {
        // Method 1: Check if class exists
        if (class_exists('Alphavel\RateLimit\RateLimitServiceProvider')) {
            return true;
        }

        // Method 2: Check composer.json
        $composerPath = getcwd() . '/composer.json';
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            
            if (isset($composer['require']['alphavel/rate-limit'])) {
                return true;
            }
        }

        // Method 3: Check if vendor package exists
        return file_exists(getcwd() . '/vendor/alphavel/rate-limit');
    }

    /**
     * Install rate-limit package
     */
    private function installPackage(): int
    {
        $this->info('Installing alphavel/rate-limit...');
        $this->line('');

        $output = [];
        $returnCode = 0;

        exec('composer require alphavel/rate-limit 2>&1', $output, $returnCode);

        if ($returnCode === 0) {
            $this->success('✓ Package installed successfully!');
            $this->line('');
            
            // Publish config
            $this->info('Publishing configuration...');
            exec('php alphavel vendor:publish --tag=rate-limit-config', $output);
            
            $this->line('');
            return $this->runConfigurationWizard();
        }

        $this->error('✗ Failed to install package');
        foreach ($output as $line) {
            $this->line($line);
        }

        return self::FAILURE;
    }

    /**
     * Run configuration wizard
     */
    private function runConfigurationWizard(): int
    {
        $this->comment('📋 Rate Limiting Configuration Wizard');
        $this->line('');

        // Choose what to configure
        $action = $this->choice(
            'What would you like to do?',
            [
                'configure' => 'Configure global settings (.env)',
                'route' => 'Add rate limiting to routes',
                'example' => 'Show usage examples',
                'exit' => 'Exit'
            ]
        );

        switch ($action) {
            case 'configure':
                return $this->configureGlobal();
            
            case 'route':
                return $this->configureRoutes();
            
            case 'example':
                return $this->showExamples();
            
            case 'exit':
                return self::SUCCESS;
        }

        return self::SUCCESS;
    }

    /**
     * Configure global settings
     */
    private function configureGlobal(): int
    {
        $this->info('⚙️  Configuring global rate limiting settings');
        $this->line('');

        $maxEntries = $this->ask('Max entries in Swoole Table', '100000');
        $defaultLimit = $this->ask('Default rate limit (requests per window)', '1000');
        $defaultWindow = $this->ask('Default time window (seconds)', '60');

        // Check if .env exists
        $envPath = getcwd() . '/.env';
        
        if (!file_exists($envPath)) {
            $this->warn('.env file not found');
            $this->line('');
            $this->comment('Add these to your .env file:');
        } else {
            $this->line('');
            $this->comment('Add these to your .env file:');
        }

        $this->line('');
        $this->line("RATE_LIMIT_MAX_ENTRIES={$maxEntries}");
        $this->line("RATE_LIMIT_DEFAULT_LIMIT={$defaultLimit}");
        $this->line("RATE_LIMIT_DEFAULT_WINDOW={$defaultWindow}");
        $this->line('');

        // Whitelist configuration
        if ($this->confirm('Configure whitelist?', false)) {
            $whitelist = $this->ask('Enter IPs to whitelist (comma-separated)', '127.0.0.1,::1');
            $this->line('');
            $this->comment('In config/rate_limit.php, add:');
            $this->line('');
            
            $ips = array_map('trim', explode(',', $whitelist));
            $this->line("'whitelist' => [");
            foreach ($ips as $ip) {
                $this->line("    '{$ip}',");
            }
            $this->line("],");
            $this->line('');
        }

        $this->success('✓ Configuration ready!');
        $this->line('');
        $this->comment('Next steps:');
        $this->comment('1. Update your .env file with the values above');
        $this->comment('2. Run: php alphavel make:rate-limit (choose "route" option)');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Configure routes
     */
    private function configureRoutes(): int
    {
        $this->info('🛣️  Adding rate limiting to routes');
        $this->line('');

        // Get route file
        $routeFile = $this->choice(
            'Which route file?',
            ['routes/api.php', 'routes/web.php', 'Custom path']
        );

        if ($routeFile === 'Custom path') {
            $routeFile = $this->ask('Enter custom route file path');
        }

        $fullPath = getcwd() . '/' . $routeFile;

        if (!file_exists($fullPath)) {
            $this->error("Route file not found: {$routeFile}");
            return self::FAILURE;
        }

        // Choose level
        $level = $this->choice(
            'Rate limiting level',
            [
                'ip' => 'IP-based (recommended for public APIs)',
                'user' => 'User-based (for authenticated users)',
                'api_key' => 'API Key-based',
                'endpoint' => 'Endpoint-based (IP + route)',
                'session' => 'Session-based',
            ]
        );

        $limit = $this->ask('Maximum requests', '100');
        $window = $this->ask('Time window (seconds)', '60');

        // Show example
        $this->line('');
        $this->comment('Add this middleware to your routes:');
        $this->line('');
        $this->line("->middleware('rate_limit:{$limit},{$window},{$level}')");
        $this->line('');
        $this->comment('Example:');
        $this->line('');
        $this->line("\$router->post('/api/data', [ApiController::class, 'store'])");
        $this->line("    ->middleware('rate_limit:{$limit},{$window},{$level}');");
        $this->line('');

        if ($this->confirm('Open route file in editor?', false)) {
            $editor = getenv('EDITOR') ?: 'nano';
            passthru("{$editor} {$fullPath}");
        }

        $this->success('✓ Configuration ready!');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Show usage examples
     */
    private function showExamples(): int
    {
        $this->info('📚 Rate Limiting Examples');
        $this->line('');

        $examples = [
            [
                'title' => '1. Basic IP-based rate limiting (100 req/min)',
                'code' => "\$router->post('/api/users', [UserController::class, 'store'])\n    ->middleware('rate_limit:100,60,ip');"
            ],
            [
                'title' => '2. User-based rate limiting (50 req/min for authenticated users)',
                'code' => "\$router->post('/api/posts', [PostController::class, 'create'])\n    ->middleware(['auth', 'rate_limit:50,60,user']);"
            ],
            [
                'title' => '3. API Key-based (1000 req/min)',
                'code' => "\$router->post('/api/webhook', [WebhookController::class, 'handle'])\n    ->middleware('rate_limit:1000,60,api_key');"
            ],
            [
                'title' => '4. Strict endpoint limiting (5 req/min for heavy operations)',
                'code' => "\$router->post('/api/heavy', [HeavyController::class, 'process'])\n    ->middleware('rate_limit:5,60,endpoint');"
            ],
            [
                'title' => '5. Login protection (5 attempts per 5 minutes)',
                'code' => "\$router->post('/auth/login', [AuthController::class, 'login'])\n    ->middleware('rate_limit:5,300,ip');"
            ],
            [
                'title' => '6. Group middleware (apply to all routes)',
                'code' => "\$router->middleware('rate_limit:100,60,ip')->group(function (\$router) {\n    \$router->get('/api/users', [UserController::class, 'index']);\n    \$router->post('/api/users', [UserController::class, 'store']);\n});"
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

        $this->comment('CLI Commands:');
        $this->line('');
        $this->line('php alphavel rate-limit:stats          # Show statistics');
        $this->line('php alphavel rate-limit:list           # List active limits');
        $this->line('php alphavel rate-limit:list --blocked # List blocked IPs');
        $this->line('php alphavel rate-limit:reset <key>    # Reset specific key');
        $this->line('php alphavel rate-limit:block <key>    # Block key manually');
        $this->line('');

        $this->comment('Documentation:');
        $this->line('https://github.com/alphavel/rate-limit');
        $this->line('');

        return self::SUCCESS;
    }
}
