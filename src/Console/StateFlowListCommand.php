<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Console;

use Hpwebdeveloper\LaravelStateflow\HasStatesContract;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'stateflow:list')]
class StateFlowListCommand extends Command
{
    /**
     * The console command signature.
     */
    protected $signature = 'stateflow:list
        {model? : The model class to list states for}
        {--field= : The state field name}';

    /**
     * The console command description.
     */
    protected $description = 'List all registered states for a model';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modelClass = $this->argument('model');

        if (! $modelClass) {
            $this->info('Usage: php artisan stateflow:list {ModelClass}');
            $this->newLine();
            $this->line('Example: php artisan stateflow:list "App\Models\Post"');

            return self::SUCCESS;
        }

        // Handle shortened class names
        if (! Str::contains($modelClass, '\\')) {
            $modelClass = 'App\\Models\\'.$modelClass;
        }

        if (! class_exists($modelClass)) {
            $this->error("Model class '{$modelClass}' not found.");

            return self::FAILURE;
        }

        if (! in_array(HasStatesContract::class, class_implements($modelClass), true)) {
            $this->error("Model '{$modelClass}' does not implement HasStatesContract.");

            return self::FAILURE;
        }

        return $this->listStatesForModel($modelClass);
    }

    /**
     * List states for a specific model.
     */
    protected function listStatesForModel(string $modelClass): int
    {
        // Get all state configs from the model
        $configs = $modelClass::getAllStateConfigs();

        if (empty($configs)) {
            $this->warn("No states configured for {$modelClass}");

            return self::FAILURE;
        }

        $field = $this->option('field');

        // If field is specified, only show that config
        if ($field) {
            if (! isset($configs[$field])) {
                $this->error("No state configuration found for field '{$field}'");

                return self::FAILURE;
            }
            $configs = [$field => $configs[$field]];
        }

        foreach ($configs as $fieldName => $config) {
            $this->displayStateConfig($modelClass, $fieldName, $config);
        }

        return self::SUCCESS;
    }

    /**
     * Display state configuration table.
     */
    protected function displayStateConfig(string $modelClass, string $field, $config): void
    {
        $this->newLine();
        $this->info("States for {$modelClass} (field: {$field}):");
        $this->newLine();

        $states = $config->getStates();
        $defaultState = $config->getDefaultStateClass();

        $headers = ['State', 'Title', 'Color', 'Default', 'Transitions To', 'Permitted Roles'];
        $rows = [];

        foreach ($states as $stateClass) {
            $allowedTransitions = $config->getAllowedTransitions($stateClass);
            $transitionNames = [];

            foreach ($allowedTransitions as $targetClass) {
                $transitionNames[] = $targetClass::name();
            }

            $permittedRoles = $stateClass::permittedRoles();

            $rows[] = [
                $stateClass::name(),
                $stateClass::title(),
                $stateClass::color(),
                $stateClass === $defaultState ? '✓' : '',
                implode(', ', $transitionNames) ?: '-',
                implode(', ', $permittedRoles) ?: 'any',
            ];
        }

        $this->table($headers, $rows);

        // Show transition summary
        $this->newLine();
        $this->line('  Total states: '.count($states));
        if ($defaultState) {
            $this->line('  Default: '.$defaultState::name());
        }
    }
}
