<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'stateflow:sync-enum')]
class SyncEnumCommand extends Command
{
    /**
     * The console command signature.
     */
    protected $signature = 'stateflow:sync-enum
                            {state : The base state class (e.g., App\\States\\Order\\OrderState)}
                            {--enum= : The enum class to create/update (e.g., App\\Enums\\OrderStatus)}
                            {--force : Overwrite existing enum file without preserving custom methods}';

    /**
     * The console command description.
     */
    protected $description = 'Create or synchronize an enum with state classes';

    /**
     * The filesystem instance.
     */
    protected Filesystem $files;

    /**
     * Create a new command instance.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $baseStateClass = $this->argument('state');

        // Convert class name to file path and validate it exists
        $baseStatePath = app_path(str_replace('\\', '/', Str::after($baseStateClass, 'App\\') ?: $baseStateClass).'.php');

        if (! $this->files->exists($baseStatePath)) {
            $this->error("Base state class file does not exist at '{$baseStatePath}'.");

            return self::FAILURE;
        }

        // Derive or use provided enum class
        $enumClass = $this->option('enum') ?? $this->deriveEnumClass($baseStateClass);

        // Find all state classes extending the base
        $stateClasses = $this->findStateClasses($baseStateClass);

        if (empty($stateClasses)) {
            $this->warn("No state classes found extending '{$baseStateClass}'.");

            return self::FAILURE;
        }

        // Extract NAME constants from state classes
        $cases = $this->extractCasesFromStates($stateClasses);

        if (empty($cases)) {
            $this->warn('No state classes with NAME constants found.');

            return self::FAILURE;
        }

        // Write to file
        $enumPath = $this->getEnumPath($enumClass);
        $this->ensureDirectoryExists(dirname($enumPath));

        $isNewFile = ! $this->files->exists($enumPath);

        // Extract existing canTransitionTo() method if file exists and --force not used
        $existingTransitions = null;
        if (! $isNewFile && ! $this->option('force')) {
            $existingTransitions = $this->extractCanTransitionToMethod($enumPath);
        }

        // Generate enum content
        $enumContent = $this->generateEnumContent($enumClass, $cases, $baseStateClass, $existingTransitions);

        $this->files->put($enumPath, $enumContent);

        // Display success message
        $action = $isNewFile ? 'created' : 'synchronized';
        $this->newLine();
        $this->info("✅ Enum {$action} with ".count($cases).' state classes.');
        $this->newLine();

        $this->table(['Case', 'Value'], collect($cases)->map(fn ($v, $k) => [$k, $v])->all());

        $this->newLine();
        $this->line("Enum file: <comment>{$enumPath}</comment>");

        if ($isNewFile) {
            $this->newLine();
            $this->line('<fg=yellow>Next steps:</>');
            $this->line('  1. Edit the enum file');
            $this->line('  2. Define your transitions in the <comment>canTransitionTo()</comment> method');
            $this->line('  3. Use the enum in your model\'s <comment>registerStates()</comment> method');
        }

        return self::SUCCESS;
    }

    /**
     * Derive enum class from base state class.
     *
     * App\States\Order\OrderState → App\Enums\OrderStateStatus
     */
    protected function deriveEnumClass(string $baseStateClass): string
    {
        $baseClassName = class_basename($baseStateClass);
        $enumName = $baseClassName.'Status';

        return 'App\\Enums\\'.$enumName;
    }

    /**
     * Find all state classes extending the base state class.
     *
     * @return array<class-string>
     */
    protected function findStateClasses(string $baseStateClass): array
    {
        // Convert base state class to file path and get directory
        $baseStatePath = app_path(str_replace('\\', '/', Str::after($baseStateClass, 'App\\') ?: $baseStateClass).'.php');
        $directory = dirname($baseStatePath);

        if (! is_dir($directory)) {
            return [];
        }

        $finder = new Finder;
        $finder->files()->in($directory)->name('*.php');

        $stateClasses = [];
        $baseClassName = class_basename($baseStateClass);
        $namespace = Str::beforeLast($baseStateClass, '\\');

        foreach ($finder as $file) {
            $filename = $file->getBasename('.php');

            // Skip the base state class itself
            if ($filename === $baseClassName) {
                continue;
            }

            // Check if file content extends the base class
            $content = $this->files->get($file->getRealPath());
            if (preg_match('/extends\s+'.preg_quote($baseClassName, '/').'\s*/s', $content)) {
                $stateClasses[] = $namespace.'\\'.$filename;
            }
        }

        return $stateClasses;
    }

    /**
     * Get the fully qualified class name from a file.
     */
    protected function getClassFromFile(string $filePath, string $baseStateClass): ?string
    {
        $namespace = Str::beforeLast($baseStateClass, '\\');
        $filename = pathinfo($filePath, PATHINFO_FILENAME);

        return $namespace.'\\'.$filename;
    }

    /**
     * Extract case names and values from state classes.
     *
     * @param  array<class-string>  $stateClasses
     * @return array<string, string>
     */
    protected function extractCasesFromStates(array $stateClasses): array
    {
        $cases = [];

        foreach ($stateClasses as $stateClass) {
            // Convert class name to file path
            $filePath = app_path(str_replace('\\', '/', Str::after($stateClass, 'App\\') ?: $stateClass).'.php');

            if (! $this->files->exists($filePath)) {
                continue;
            }

            $content = $this->files->get($filePath);
            $className = class_basename($stateClass);

            // Extract NAME constant from file content
            if (preg_match('/public\s+const\s+NAME\s*=\s*[\'"]([^\'"]+)[\'"]/s', $content, $matches)) {
                $cases[$className] = $matches[1];
            } else {
                // If no NAME constant, derive from class name
                $cases[$className] = Str::snake($className);
            }
        }

        // Sort cases alphabetically for consistency
        ksort($cases);

        return $cases;
    }

    /**
     * Generate the enum file content.
     *
     * @param  array<string, string>  $cases
     */
    protected function generateEnumContent(string $enumClass, array $cases, string $baseStateClass, ?string $existingTransitions = null): string
    {
        $namespace = Str::beforeLast($enumClass, '\\');
        $enumName = Str::afterLast($enumClass, '\\');

        $casesCode = collect($cases)
            ->map(fn ($value, $name) => "    case {$name} = '{$value}';")
            ->implode("\n");

        $stubPath = $this->resolveStubPath('/stubs/state-enum.stub');

        if ($this->files->exists($stubPath)) {
            $stub = $this->files->get($stubPath);
        } else {
            $stub = $this->getDefaultStub();
        }

        $defaultTransitions = <<<'METHOD'
    public function canTransitionTo(): array
    {
        return match ($this) {
            // TODO: Define your transitions here
            default => [],
        };
    }
METHOD;

        $transitionsMethod = $existingTransitions ?? $defaultTransitions;

        return str_replace(
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
                "php artisan stateflow:sync-enum {$baseStateClass} --enum={$enumClass}",
                $transitionsMethod,
            ],
            $stub
        );
    }

    /**
     * Extract the canTransitionTo() method from an existing enum file.
     */
    protected function extractCanTransitionToMethod(string $enumPath): ?string
    {
        $content = $this->files->get($enumPath);

        // Match the canTransitionTo method with its docblock
        // We need to find the method and its closing brace carefully
        // The method body contains a match expression, so we count braces

        // First, find if the method exists
        if (! preg_match('/public\s+function\s+canTransitionTo\s*\(\s*\)\s*:\s*array/', $content)) {
            return null;
        }

        // Find the position of canTransitionTo method signature
        $methodSignaturePattern = '/public\s+function\s+canTransitionTo\s*\(\s*\)\s*:\s*array\s*\{/';
        if (! preg_match($methodSignaturePattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $signatureStart = $matches[0][1];
        $signatureEnd = $signatureStart + strlen($matches[0][0]);

        // Look backwards from the method signature to find a docblock (if any)
        // We need to find the CLOSEST docblock, not just any docblock
        $beforeSignature = substr($content, 0, $signatureStart);
        $methodStart = $signatureStart;

        // Find the last docblock before the method by looking backwards
        // A docblock must end with */ followed by only whitespace before the method
        if (preg_match_all('/\/\*\*[\s\S]*?\*\//', $beforeSignature, $docMatches, PREG_OFFSET_CAPTURE)) {
            // Get the last match
            $lastDocblock = end($docMatches[0]);
            $docblockStart = $lastDocblock[1];
            $docblockEnd = $docblockStart + strlen($lastDocblock[0]);

            // Check if there's only whitespace between this docblock and the method
            $between = substr($beforeSignature, $docblockEnd);
            if (trim($between) === '') {
                $methodStart = $docblockStart;
            }
        }

        // Find the opening brace position
        $braceStart = $signatureEnd - 1;

        // Count braces to find the matching closing brace
        $braceCount = 1;
        $pos = $braceStart + 1;
        $len = strlen($content);

        while ($pos < $len && $braceCount > 0) {
            $char = $content[$pos];
            if ($char === '{') {
                $braceCount++;
            } elseif ($char === '}') {
                $braceCount--;
            }
            $pos++;
        }

        if ($braceCount !== 0) {
            return null;
        }

        // Extract the full method including docblock
        return substr($content, $methodStart, $pos - $methodStart);
    }

    /**
     * Resolve the stub path.
     */
    protected function resolveStubPath(string $stub): string
    {
        $customPath = base_path(trim($stub, '/'));

        if (file_exists($customPath)) {
            return $customPath;
        }

        return __DIR__.'/../../'.ltrim($stub, '/');
    }

    /**
     * Get the default stub content.
     */
    protected function getDefaultStub(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace {{ namespace }};

use {{ baseStateClass }};
use Illuminate\Support\Str;

/**
 * Auto-generated enum for {{ baseStateClass }}.
 *
 * ⚠️ IMPORTANT: After creating new state classes, run:
 *    {{ syncCommand }}
 *
 * This ensures all state classes are reflected in this enum.
 * Your custom canTransitionTo() logic will be preserved during sync.
 *
 * 👉 TODO: Define your transition logic in canTransitionTo()
 */
enum {{ enumName }}: string
{
{{ cases }}

    /**
     * Get the State class for this enum case.
     *
     * Convention: Enum case name = State class name.
     */
    public function stateClass(): string
    {
        $namespace = Str::beforeLast(\{{ baseStateClass }}::class, '\\');

        return $namespace . '\\' . $this->name;
    }

{{ canTransitionTo }}

    /**
     * Check if transition to target state is allowed.
     */
    public function canTransitionToState(self $target): bool
    {
        return in_array($target, $this->canTransitionTo(), true);
    }

    /**
     * Get all state classes for registration.
     */
    public static function stateClasses(): array
    {
        return array_map(fn (self $s) => $s->stateClass(), self::cases());
    }

    /**
     * Get all transitions for StateConfig.
     *
     * @return array<array{from: string, to: string}>
     */
    public static function transitions(): array
    {
        return collect(self::cases())
            ->flatMap(fn (self $from) =>
                collect($from->canTransitionTo())
                    ->map(fn (self $to) => [
                        'from' => $from->stateClass(),
                        'to' => $to->stateClass(),
                    ])
            )
            ->all();
    }

    /**
     * Find enum case from state name.
     */
    public static function fromStateName(string $name): ?self
    {
        return self::tryFrom($name);
    }
}
STUB;
    }

    /**
     * Get the path for the enum file.
     */
    protected function getEnumPath(string $enumClass): string
    {
        $relativePath = str_replace('\\', '/', Str::after($enumClass, 'App\\') ?: $enumClass);

        return app_path($relativePath.'.php');
    }

    /**
     * Ensure the directory exists.
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }
    }
}
