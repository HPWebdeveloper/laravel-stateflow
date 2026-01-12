<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:state')]
class MakeStateCommand extends GeneratorCommand
{
    /**
     * The console command name.
     */
    protected $name = 'make:state';

    /**
     * The console command description.
     */
    protected $description = 'Create a new state class';

    /**
     * The type of class being generated.
     */
    protected $type = 'State';

    /**
     * Execute the console command.
     */
    public function handle(): ?bool
    {
        // If --states option is provided, generate base + multiple state classes
        if ($states = $this->option('states')) {
            return $this->generateMultipleStates($states) ? true : false;
        }

        return parent::handle();
    }

    /**
     * Generate a base state class and multiple extending state classes.
     */
    protected function generateMultipleStates(string $states): int
    {
        $baseName = $this->argument('name');
        $stateNames = array_map('trim', explode(',', $states));

        // Capture options before nested calls (nested $this->call() overrides the option context)
        $transitionsOption = $this->option('transitions');
        $enumOption = $this->option('enum');

        // Generate the base state class first
        $this->info("Creating base state class: {$baseName}");
        $exitCode = $this->call('make:state', [
            'name' => $baseName,
            '--base' => true,
        ]);

        if ($exitCode !== 0) {
            return $exitCode;
        }

        // Generate each extending state class
        foreach ($stateNames as $stateName) {
            if (empty($stateName)) {
                continue;
            }

            $this->info("Creating state class: {$stateName}");
            $exitCode = $this->call('make:state', [
                'name' => $stateName,
                '--extends' => $baseName,
            ]);

            if ($exitCode !== 0) {
                return $exitCode;
            }
        }

        // Generate enum if --transitions=enum is specified
        $enumGenerated = false;
        if ($transitionsOption === 'enum') {
            $enumGenerated = $this->generateEnumWithOptions($baseName, $stateNames, $enumOption);
        }

        $this->newLine();
        $this->info('✅ All state classes created successfully!');
        $this->newLine();
        $this->line('Directory structure:');
        $this->line("  app/States/{$baseName}.php (base class)");
        foreach ($stateNames as $stateName) {
            if (! empty($stateName)) {
                $this->line("  app/States/{$stateName}.php");
            }
        }

        if ($enumGenerated) {
            $enumClass = $enumOption ?? 'App\\Enums\\'.$baseName.'Status';
            $afterApp = Str::after($enumClass, 'App\\');
            $enumPath = is_string($afterApp) ? str_replace('\\', '/', $afterApp) : $enumClass;
            $this->line("  app/{$enumPath}.php (enum)");
        }

        // Show next steps for enum approach
        if ($enumGenerated) {
            $this->newLine();
            $this->line('<fg=yellow>Next steps:</>');
            $this->line('  1. Edit the enum file');
            $this->line('  2. Define your transitions in the <comment>canTransitionTo()</comment> method');
            $this->line('  3. Use the enum in your model\'s <comment>registerStates()</comment> method');
        }

        return 0;
    }

    /**
     * Generate an enum for the state workflow.
     */
    protected function generateEnumWithOptions(string $baseName, array $stateNames, ?string $enumOption): bool
    {
        // Build the base state class fully qualified name
        $baseStateClass = $this->laravel->getNamespace().'States\\'.$baseName;

        // Build the enum class name
        $enumClass = $enumOption ?? 'App\\Enums\\'.$baseName.'Status';

        $this->info("Creating enum: {$enumClass}");

        // Call the sync-enum command to generate the enum
        // But we need to wait until the state classes exist
        // Since they're just created, we can generate the enum manually

        $cases = [];
        foreach ($stateNames as $stateName) {
            if (! empty($stateName)) {
                // Convert class name to snake_case for the value
                $cases[$stateName] = Str::snake($stateName);
            }
        }

        if (empty($cases)) {
            return false;
        }

        return $this->createEnumFile($enumClass, $cases, $baseStateClass);
    }

    /**
     * Create the enum file.
     *
     * @param  array<string, string>  $cases
     */
    protected function createEnumFile(string $enumClass, array $cases, string $baseStateClass): bool
    {
        $filesystem = $this->laravel->make('files');

        $namespace = Str::beforeLast($enumClass, '\\');
        $enumName = Str::afterLast($enumClass, '\\');

        $casesCode = collect($cases)
            ->map(fn ($value, $name) => "    case {$name} = '{$value}';")
            ->implode("\n");

        $syncCommand = "php artisan stateflow:sync-enum {$baseStateClass} --enum={$enumClass}";

        $defaultTransitions = <<<'METHOD'
    public function canTransitionTo(): array
    {
        return match ($this) {
            // TODO: Define your transitions here
            default => [],
        };
    }
METHOD;

        $stub = $this->resolveStubPath('/stubs/state-enum.stub');
        $content = file_get_contents($stub);

        if ($content === false) {
            return false;
        }

        $content = str_replace(
            [
                '{{ namespace }}',
                '{{ baseStateClass }}',
                '{{ enumName }}',
                '{{ cases }}',
                '{{ syncCommand }}',
                '{{ canTransitionTo }}',
            ],
            [
                $namespace,
                $baseStateClass,
                $enumName,
                $casesCode,
                $syncCommand,
                $defaultTransitions,
            ],
            $content
        );

        $relativePath = Str::after($enumClass, 'App\\');
        if (! is_string($relativePath)) {
            $relativePath = $enumClass;
        }
        $path = app_path(str_replace('\\', '/', $relativePath).'.php');

        // Ensure directory exists
        $directory = dirname($path);
        if (! $filesystem->isDirectory($directory)) {
            $filesystem->makeDirectory($directory, 0755, true);
        }

        $filesystem->put($path, $content);

        return true;
    }

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        if ($this->option('base')) {
            return $this->resolveStubPath('/stubs/base-state.stub');
        }

        return $this->resolveStubPath('/stubs/state.stub');
    }

    /**
     * Resolve the stub path.
     */
    protected function resolveStubPath(string $stub): string
    {
        $customPath = $this->laravel->basePath(trim($stub, '/'));

        return file_exists($customPath)
            ? $customPath
            : __DIR__.'/../../'.ltrim($stub, '/');
    }

    /**
     * Get the default namespace for the class.
     *
     * When --extends option contains a full namespace path (e.g., App\States\Booking\BookingState),
     * the new state should be placed in the same namespace/directory as the base state.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        $extends = $this->option('extends');

        // If --extends contains a namespace (has backslash), extract the namespace
        if ($extends && str_contains($extends, '\\')) {
            // Get the namespace part (everything before the last segment)
            return Str::beforeLast($extends, '\\');
        }

        return $rootNamespace.'\\States';
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        return $this->replaceStateDetails($stub);
    }

    /**
     * Replace state-specific placeholders.
     */
    protected function replaceStateDetails(string $stub): string
    {
        $className = class_basename($this->argument('name'));
        $stateName = Str::snake($className);
        $stateTitle = Str::title(str_replace('_', ' ', $stateName));

        // Handle --extends option: extract just the class name for the extends clause
        $extends = $this->option('extends');
        $baseState = $extends ? class_basename($extends) : 'State';

        $replacements = [
            '{{ stateName }}' => $stateName,
            '{{ stateTitle }}' => $stateTitle,
            '{{ baseState }}' => $baseState,
            '{{ color }}' => $this->option('color') ?? 'gray',
            '{{ icon }}' => $this->option('icon') ?? 'circle',
        ];

        // Handle default state attribute
        if ($this->option('default')) {
            $stub = str_replace(
                '{{ defaultImport }}',
                'use Hpwebdeveloper\LaravelStateflow\Attributes\DefaultState;',
                $stub
            );
            $stub = str_replace('{{ defaultAttribute }}', '#[DefaultState]', $stub);
        } else {
            $stub = str_replace("{{ defaultImport }}\n", '', $stub);
            $stub = str_replace("{{ defaultAttribute }}\n", '', $stub);
        }

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['base', 'b', InputOption::VALUE_NONE, 'Create a base state class'],
            ['extends', 'e', InputOption::VALUE_REQUIRED, 'The base state class to extend'],
            ['states', 's', InputOption::VALUE_REQUIRED, 'Comma-separated list of state classes to generate (creates base + extending states)'],
            ['default', 'd', InputOption::VALUE_NONE, 'Mark as the default state'],
            ['color', 'c', InputOption::VALUE_REQUIRED, 'The state color for UI'],
            ['icon', 'i', InputOption::VALUE_REQUIRED, 'The state icon for UI'],
            ['transitions', 't', InputOption::VALUE_REQUIRED, 'Transition definition style: "enum" to scaffold an enum for workflow topology'],
            ['enum', null, InputOption::VALUE_REQUIRED, 'Custom enum class name (e.g., App\\Enums\\OrderWorkflow)'],
        ];
    }
}
