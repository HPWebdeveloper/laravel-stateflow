<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:transition')]
class MakeTransitionCommand extends GeneratorCommand
{
    /**
     * The console command name.
     */
    protected $name = 'make:transition';

    /**
     * The console command description.
     */
    protected $description = 'Create a new state transition action class';

    /**
     * The type of class being generated.
     */
    protected $type = 'Transition';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/transition.stub');
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
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Actions\\Transitions';
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        // If from/to options provided, we could enhance the stub
        // For now, just return the base stub
        return $stub;
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['from', 'f', InputOption::VALUE_REQUIRED, 'The source state class'],
            ['to', 't', InputOption::VALUE_REQUIRED, 'The target state class'],
        ];
    }

    /**
     * Get the desired class name from the input.
     */
    protected function getNameInput(): string
    {
        $name = parent::getNameInput();

        // If no name given but from/to options are provided, generate a name
        if (empty($name) && $this->option('from') && $this->option('to')) {
            $from = class_basename($this->option('from'));
            $to = class_basename($this->option('to'));

            return "{$from}To{$to}Transition";
        }

        // Ensure the name ends with "Transition"
        if (! Str::endsWith($name, 'Transition')) {
            $name .= 'Transition';
        }

        return $name;
    }
}
