<?php

namespace Alphavel\Alpha\Console\Commands;

use Alphavel\Alpha\Console\Command;

/**
 * Make Mail Configuration Command
 * 
 * Configure email sending (optional package)
 */
class MakeMailCommand extends Command
{
    protected string $signature = 'make:mail {name?}';
    protected string $description = 'Configure email or create mailable class';

    public function handle(): int
    {
        if (!$this->isMailInstalled()) {
            $this->warn('⚠️  Mail package is not installed.');
            
            if ($this->confirm('Install alphavel/mail now?', true)) {
                return $this->installPackage();
            }
            
            $this->comment('To install: composer require alphavel/mail');
            return self::FAILURE;
        }

        $name = $this->argument('name');
        if ($name) {
            return $this->createMailable($name);
        }

        return $this->runWizard();
    }

    private function isMailInstalled(): bool
    {
        return class_exists('Alphavel\Mail\MailServiceProvider') ||
               file_exists(getcwd() . '/vendor/alphavel/mail');
    }

    private function installPackage(): int
    {
        $this->info('Installing alphavel/mail...');
        exec('composer require alphavel/mail 2>&1', $output, $code);
        
        if ($code === 0) {
            $this->success('✓ Installed!');
            return $this->runWizard();
        }
        
        return self::FAILURE;
    }

    private function runWizard(): int
    {
        $action = $this->choice('What would you like to do?', [
            'mailable' => 'Create mailable class',
            'config' => 'Configure SMTP',
            'example' => 'Show examples',
            'exit' => 'Exit'
        ]);

        return match ($action) {
            'mailable' => $this->createMailable(),
            'config' => $this->configureSmtp(),
            'example' => $this->showExamples(),
            default => self::SUCCESS
        };
    }

    private function createMailable(?string $name = null): int
    {
        $name = $name ?: $this->ask('Mailable name (e.g., WelcomeEmail)');
        
        $path = getcwd() . "/app/Mail/{$name}.php";
        $stub = <<<PHP
<?php

namespace App\Mail;

class {$name}
{
    public function __construct(
        private array \$data
    ) {}
    
    public function build(): array
    {
        return [
            'to' => \$this->data['email'],
            'subject' => 'Welcome!',
            'html' => \$this->render(),
        ];
    }
    
    private function render(): string
    {
        return <<<HTML
        <h1>Welcome!</h1>
        <p>Hello {\$this->data['name']},</p>
        HTML;
    }
}
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $stub);
        $this->success("✓ Created: app/Mail/{$name}.php");
        
        return self::SUCCESS;
    }

    private function configureSmtp(): int
    {
        $this->comment('Add to .env:');
        $this->line('');
        $this->line('MAIL_DRIVER=smtp');
        $this->line('MAIL_HOST=smtp.mailtrap.io');
        $this->line('MAIL_PORT=2525');
        $this->line('MAIL_USERNAME=your-username');
        $this->line('MAIL_PASSWORD=your-password');
        $this->line('MAIL_FROM_ADDRESS=noreply@example.com');
        $this->line('MAIL_FROM_NAME="My App"');
        
        return self::SUCCESS;
    }

    private function showExamples(): int
    {
        $this->info('📧 Mail Examples');
        $this->line('');
        $this->comment('Send email:');
        $this->line('Mail::send(new WelcomeEmail([\'name\' => \'John\']));');
        
        return self::SUCCESS;
    }
}
