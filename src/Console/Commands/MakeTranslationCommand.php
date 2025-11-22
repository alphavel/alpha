<?php

namespace Alphavel\Alpha\Console\Commands;

use Alphavel\Alpha\Console\Command;

/**
 * Make i18n/Translation Command
 */
class MakeTranslationCommand extends Command
{
    protected string $signature = 'make:translation {locale?}';
    protected string $description = 'Configure i18n translations';

    public function handle(): int
    {
        if (!$this->isI18nInstalled()) {
            $this->warn('⚠️  i18n package is not installed.');
            
            if ($this->confirm('Install alphavel/i18n now?', true)) {
                return $this->installPackage();
            }
            
            return self::FAILURE;
        }

        $locale = $this->argument('locale');
        if ($locale) {
            return $this->createTranslation($locale);
        }

        return $this->runWizard();
    }

    private function isI18nInstalled(): bool
    {
        return class_exists('Alphavel\I18n\I18nServiceProvider') ||
               file_exists(getcwd() . '/vendor/alphavel/i18n');
    }

    private function installPackage(): int
    {
        $this->info('Installing alphavel/i18n...');
        exec('composer require alphavel/i18n 2>&1', $output, $code);
        return $code === 0 ? $this->runWizard() : self::FAILURE;
    }

    private function runWizard(): int
    {
        $action = $this->choice('What would you like to do?', [
            'locale' => 'Add new locale',
            'config' => 'Configure default locale',
            'example' => 'Show examples',
            'exit' => 'Exit'
        ]);

        return match ($action) {
            'locale' => $this->createTranslation(),
            'config' => $this->configure(),
            'example' => $this->showExamples(),
            default => self::SUCCESS
        };
    }

    private function createTranslation(?string $locale = null): int
    {
        $locale = $locale ?: $this->ask('Locale code (e.g., pt_BR)');
        $path = getcwd() . "/resources/lang/{$locale}/messages.php";

        $stub = <<<'PHP'
<?php

return [
    'welcome' => 'Bem-vindo!',
    'hello' => 'Olá, :name!',
    'goodbye' => 'Até logo!',
];
PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $stub);
        $this->success("✓ Created: resources/lang/{$locale}/messages.php");
        
        return self::SUCCESS;
    }

    private function configure(): int
    {
        $locale = $this->ask('Default locale', 'en');
        
        $this->comment('Add to .env:');
        $this->line('');
        $this->line("APP_LOCALE={$locale}");
        $this->line('APP_FALLBACK_LOCALE=en');
        
        return self::SUCCESS;
    }

    private function showExamples(): int
    {
        $this->info('🌍 i18n Examples');
        $this->line('');
        $this->comment('Translate text:');
        $this->line('__(\'messages.welcome\');');
        $this->line('__(\'messages.hello\', [\'name\' => \'John\']);');
        $this->line('');
        $this->comment('Change locale:');
        $this->line('app()->setLocale(\'pt_BR\');');
        
        return self::SUCCESS;
    }
}
