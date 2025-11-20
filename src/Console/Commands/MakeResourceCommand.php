<?php

namespace Alphavel\Alpha\Console\Commands;

use Alphavel\Alpha\Console\Command;

/**
 * Make Resource Command - Generate Model + Controller + Routes
 */
class MakeResourceCommand extends Command
{
    protected string $signature = 'make:resource';

    protected string $description = 'Generate Model, Controller and Routes for a resource (all-in-one)';

    public function handle(): int
    {
        $name = $this->argument(0) ?? $this->ask('Resource name (singular, e.g., User)');

        if (!$name) {
            $this->error('Resource name is required.');
            return self::FAILURE;
        }

        $this->info("Generating resource: {$name}\n");

        // Generate Model
        $this->line('Creating Model...');
        $modelCommand = new MakeModelCommand();
        $modelCommand->initialize(['alpha', 'make:model', $name, '--table']);
        $modelResult = $modelCommand->handle();

        if ($modelResult !== self::SUCCESS) {
            $this->error('Failed to create Model');
            return self::FAILURE;
        }

        // Generate Controller
        $this->line('Creating Controller...');
        $controllerName = "{$name}Controller";
        $controllerCommand = new MakeControllerCommand();
        $controllerCommand->initialize(['alpha', 'make:controller', $controllerName, '--model=' . $name]);
        $controllerResult = $controllerCommand->handle();

        if ($controllerResult !== self::SUCCESS) {
            $this->error('Failed to create Controller');
            return self::FAILURE;
        }

        // Generate routes suggestion
        $this->line('');
        $this->success('Resource generated successfully!');
        $this->line('');
        $this->comment('Add these routes to routes/api.php:');
        $this->line('');
        
        $tableName = $this->getTableName($name);
        $this->info("// {$name} Resource");
        $this->line("\$router->get('/{$tableName}', '{$controllerName}@index');");
        $this->line("\$router->get('/{$tableName}/{id}', '{$controllerName}@show');");
        $this->line("\$router->post('/{$tableName}', '{$controllerName}@store');");
        $this->line("\$router->put('/{$tableName}/{id}', '{$controllerName}@update');");
        $this->line("\$router->delete('/{$tableName}/{id}', '{$controllerName}@destroy');");
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Get table name from model name
     */
    private function getTableName(string $model): string
    {
        // Convert PascalCase to snake_case and pluralize
        $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $model));
        
        return $snake . 's';
    }
}
