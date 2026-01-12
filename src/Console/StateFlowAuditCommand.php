<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Console;

use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;
use Hpwebdeveloper\LaravelStateflow\HasStatesContract;
use Hpwebdeveloper\LaravelStateflow\StateConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'stateflow:audit')]
class StateFlowAuditCommand extends Command
{
    /**
     * The console command signature.
     */
    protected $signature = 'stateflow:audit
        {model : The model class to audit}
        {--field= : The state field name to audit}';

    /**
     * The console command description.
     */
    protected $description = 'Audit state configuration and find potential issues';

    /**
     * Issues found during audit.
     *
     * @var array<string>
     */
    protected array $issues = [];

    /**
     * Warnings found during audit.
     *
     * @var array<string>
     */
    protected array $warnings = [];

    /**
     * Info messages.
     *
     * @var array<string>
     */
    protected array $infos = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modelClass = $this->argument('model');

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

        $this->info("Auditing state configuration for {$modelClass}...");
        $this->newLine();

        $configs = $modelClass::getAllStateConfigs();

        if (empty($configs)) {
            $this->error('No state configurations found.');

            return self::FAILURE;
        }

        $field = $this->option('field');

        if ($field) {
            if (! isset($configs[$field])) {
                $this->error("No state configuration found for field '{$field}'");

                return self::FAILURE;
            }
            $configs = [$field => $configs[$field]];
        }

        foreach ($configs as $fieldName => $config) {
            $this->auditConfig($fieldName, $config);
        }

        // Output results
        $this->outputResults();

        return empty($this->issues) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Audit a single state configuration.
     */
    protected function auditConfig(string $field, StateConfig $config): void
    {
        $this->line("Checking field: {$field}");
        $this->newLine();

        // Check 1: Default state exists
        $this->checkDefaultState($config);

        // Check 2: All states have required attributes
        $this->checkStateAttributes($config);

        // Check 3: Orphan states (no transitions to/from)
        $this->checkOrphanStates($config);

        // Check 4: Unreachable states from default
        $this->checkReachability($config);

        // Check 5: Circular transitions (could be intentional)
        $this->checkCircularTransitions($config);

        // Check 6: Permission consistency
        $this->checkPermissions($config);
    }

    /**
     * Check if default state is defined.
     */
    protected function checkDefaultState(StateConfig $config): void
    {
        $defaultState = $config->getDefaultStateClass();

        if (! $defaultState) {
            $this->issues[] = 'No default state defined';
        } else {
            $this->infos[] = "Default state: {$defaultState::name()}";
        }
    }

    /**
     * Check state attributes.
     */
    protected function checkStateAttributes(StateConfig $config): void
    {
        foreach ($config->getStates() as $stateClass) {
            // Check for empty title
            if (empty($stateClass::title())) {
                $this->warnings[] = "State {$stateClass::name()} has no title";
            }

            // Check for default color
            if ($stateClass::color() === 'gray') {
                $this->warnings[] = "State {$stateClass::name()} uses default color (gray)";
            }
        }
    }

    /**
     * Check for orphan states.
     */
    protected function checkOrphanStates(StateConfig $config): void
    {
        $defaultState = $config->getDefaultStateClass();

        foreach ($config->getStates() as $stateClass) {
            // Skip default state - it's the entry point
            if ($stateClass === $defaultState) {
                continue;
            }

            $transitionsFrom = $config->getAllowedTransitions($stateClass);
            $transitionsTo = $this->findTransitionsTo($config, $stateClass);

            // A state with no incoming and no outgoing transitions is orphaned
            // (unless it's the default or final state)
            if (empty($transitionsFrom) && empty($transitionsTo)) {
                $this->warnings[] = "State {$stateClass::name()} has no transitions (orphaned)";
            }
        }
    }

    /**
     * Check state reachability from default.
     */
    protected function checkReachability(StateConfig $config): void
    {
        $defaultState = $config->getDefaultStateClass();

        if (! $defaultState) {
            return;
        }

        $visited = [];
        $reachable = $this->findReachableStates($config, $defaultState, $visited);

        foreach ($config->getStates() as $stateClass) {
            if ($stateClass === $defaultState) {
                continue;
            }

            if (! in_array($stateClass, $reachable, true)) {
                $this->warnings[] = "State {$stateClass::name()} is unreachable from default state";
            }
        }
    }

    /**
     * Check for circular transitions.
     */
    protected function checkCircularTransitions(StateConfig $config): void
    {
        foreach ($config->getStates() as $stateClass) {
            $transitions = $config->getAllowedTransitions($stateClass);

            if (in_array($stateClass, $transitions, true)) {
                $this->infos[] = "State {$stateClass::name()} allows self-transition";
            }
        }
    }

    /**
     * Check permission configuration.
     */
    protected function checkPermissions(StateConfig $config): void
    {
        $statesWithRoles = 0;
        $statesWithoutRoles = 0;

        foreach ($config->getStates() as $stateClass) {
            $roles = $stateClass::permittedRoles();

            if (empty($roles)) {
                $statesWithoutRoles++;
            } else {
                $statesWithRoles++;
            }
        }

        if ($statesWithRoles > 0 && $statesWithoutRoles > 0) {
            $this->warnings[] = 'Mixed permission configuration: some states have roles, others do not';
        }
    }

    /**
     * Find all states that can transition TO a target state.
     *
     * @return array<class-string<StateContract>>
     */
    protected function findTransitionsTo(StateConfig $config, string $targetState): array
    {
        $transitions = [];

        foreach ($config->getStates() as $stateClass) {
            $allowedTransitions = $config->getAllowedTransitions($stateClass);
            if (in_array($targetState, $allowedTransitions, true)) {
                $transitions[] = $stateClass;
            }
        }

        return $transitions;
    }

    /**
     * Find all reachable states from a starting state.
     *
     * @param  array<class-string<StateContract>>  $visited
     * @return array<class-string<StateContract>>
     */
    protected function findReachableStates(StateConfig $config, string $startState, array &$visited = []): array
    {
        if (in_array($startState, $visited, true)) {
            return $visited;
        }

        $visited[] = $startState;
        $transitions = $config->getAllowedTransitions($startState);

        foreach ($transitions as $nextState) {
            $this->findReachableStates($config, $nextState, $visited);
        }

        return $visited;
    }

    /**
     * Output audit results.
     */
    protected function outputResults(): void
    {
        // Info messages
        if (! empty($this->infos)) {
            foreach ($this->infos as $info) {
                $this->line("  ✓ {$info}");
            }
            $this->newLine();
        }

        // Warnings
        if (! empty($this->warnings)) {
            $this->warn('Warnings:');
            foreach ($this->warnings as $warning) {
                $this->line("  ⚠️  {$warning}");
            }
            $this->newLine();
        }

        // Issues (errors)
        if (! empty($this->issues)) {
            $this->error('Issues:');
            foreach ($this->issues as $issue) {
                $this->line("  ❌ {$issue}");
            }
            $this->newLine();
        }

        // Summary
        $this->newLine();
        if (empty($this->issues) && empty($this->warnings)) {
            $this->info('✓ Audit passed with no issues!');
        } elseif (empty($this->issues)) {
            $this->info('✓ Audit passed with '.count($this->warnings).' warning(s)');
        } else {
            $this->error('✗ Audit failed with '.count($this->issues).' issue(s) and '.count($this->warnings).' warning(s)');
        }
    }
}
