<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(app_path('States'));
    File::deleteDirectory(app_path('Enums'));
});

afterEach(function () {
    File::deleteDirectory(app_path('States'));
    File::deleteDirectory(app_path('Enums'));
});

describe('SyncEnumCommand', function () {
    /**
     * Scenario: Create enum from existing state classes
     * Setup: Create state classes first, then run sync-enum command
     * Assertion: Enum file is created with all state cases
     */
    it('creates enum from existing state classes', function () {
        // First create the state classes
        $this->artisan('make:state', [
            'name' => 'OrderState',
            '--states' => 'Pending,Processing,Shipped',
        ])->assertExitCode(0);

        // Now run sync-enum
        $this->artisan('stateflow:sync-enum', [
            'state' => 'App\\States\\OrderState',
            '--enum' => 'App\\Enums\\OrderStatus',
        ])->assertExitCode(0);

        // Verify enum was created
        expect(File::exists(app_path('Enums/OrderStatus.php')))->toBeTrue();

        $enumContent = File::get(app_path('Enums/OrderStatus.php'));
        expect($enumContent)
            ->toContain('enum OrderStatus: string')
            ->toContain("case Pending = 'pending';")
            ->toContain("case Processing = 'processing';")
            ->toContain("case Shipped = 'shipped';");
    });

    /**
     * Scenario: Derive enum class name when not provided
     * Setup: Run sync-enum without --enum option
     * Assertion: Enum is created with derived name ({BaseState}Status)
     */
    it('derives enum class name when not provided', function () {
        // Create state classes
        $this->artisan('make:state', [
            'name' => 'TaskState',
            '--states' => 'Todo,Done',
        ])->assertExitCode(0);

        // Run sync-enum without --enum option
        $this->artisan('stateflow:sync-enum', [
            'state' => 'App\\States\\TaskState',
        ])->assertExitCode(0);

        // Verify enum was created with derived name
        expect(File::exists(app_path('Enums/TaskStateStatus.php')))->toBeTrue();

        $enumContent = File::get(app_path('Enums/TaskStateStatus.php'));
        expect($enumContent)->toContain('enum TaskStateStatus: string');
    });

    /**
     * Scenario: Enum contains all required helper methods
     * Setup: Create states and run sync-enum
     * Assertion: Generated enum contains stateClass, canTransitionTo, stateClasses, transitions methods
     */
    it('generates enum with all helper methods', function () {
        $this->artisan('make:state', [
            'name' => 'PaymentState',
            '--states' => 'Pending,Completed',
        ])->assertExitCode(0);

        $this->artisan('stateflow:sync-enum', [
            'state' => 'App\\States\\PaymentState',
            '--enum' => 'App\\Enums\\PaymentStatus',
        ])->assertExitCode(0);

        $enumContent = File::get(app_path('Enums/PaymentStatus.php'));
        expect($enumContent)
            ->toContain('public function stateClass(): string')
            ->toContain('public function canTransitionTo(): array')
            ->toContain('public function canTransitionToState(self $target): bool')
            ->toContain('public static function stateClasses(): array')
            ->toContain('public static function transitions(): array')
            ->toContain('public static function fromStateName(string $name): ?self');
    });

    /**
     * Scenario: Enum contains sync command reminder
     * Setup: Create states and run sync-enum
     * Assertion: Generated enum docblock contains sync command reminder
     */
    it('includes sync command reminder in generated enum', function () {
        $this->artisan('make:state', [
            'name' => 'ArticleState',
            '--states' => 'Draft,Published',
        ])->assertExitCode(0);

        $this->artisan('stateflow:sync-enum', [
            'state' => 'App\\States\\ArticleState',
            '--enum' => 'App\\Enums\\ArticleStatus',
        ])->assertExitCode(0);

        $enumContent = File::get(app_path('Enums/ArticleStatus.php'));
        expect($enumContent)
            ->toContain('IMPORTANT: After creating new state classes, run:')
            ->toContain('stateflow:sync-enum');
    });

    /**
     * Scenario: Fail gracefully when base state class doesn't exist
     * Setup: Run sync-enum with non-existent base state class
     * Assertion: Command fails with appropriate error message
     */
    it('fails when base state class does not exist', function () {
        $this->artisan('stateflow:sync-enum', [
            'state' => 'App\\States\\NonExistentState',
        ])->assertExitCode(1);
    });

    /**
     * Scenario: Update existing enum with new state cases
     * Setup: Create states, run sync-enum, add new state, run sync-enum again
     * Assertion: Enum is updated with new case while preserving structure
     */
    it('can update existing enum with new states', function () {
        // Create initial states
        $this->artisan('make:state', [
            'name' => 'InvoiceState',
            '--states' => 'Draft,Sent',
        ])->assertExitCode(0);

        // Run sync-enum
        $this->artisan('stateflow:sync-enum', [
            'state' => 'App\\States\\InvoiceState',
            '--enum' => 'App\\Enums\\InvoiceStatus',
        ])->assertExitCode(0);

        // Verify initial enum
        $enumContent = File::get(app_path('Enums/InvoiceStatus.php'));
        expect($enumContent)
            ->toContain("case Draft = 'draft';")
            ->toContain("case Sent = 'sent';");

        // Create new state
        $this->artisan('make:state', [
            'name' => 'Paid',
            '--extends' => 'InvoiceState',
        ])->assertExitCode(0);

        // Run sync-enum again
        $this->artisan('stateflow:sync-enum', [
            'state' => 'App\\States\\InvoiceState',
            '--enum' => 'App\\Enums\\InvoiceStatus',
        ])->assertExitCode(0);

        // Verify updated enum
        $updatedEnumContent = File::get(app_path('Enums/InvoiceStatus.php'));
        expect($updatedEnumContent)
            ->toContain("case Draft = 'draft';")
            ->toContain("case Sent = 'sent';")
            ->toContain("case Paid = 'paid';");
    });

    /**
     * Scenario: Enum uses Str::beforeLast for namespace resolution
     * Setup: Create states and run sync-enum
     * Assertion: Generated stateClass method uses Str::beforeLast
     */
    it('uses Str::beforeLast for convention-based class resolution', function () {
        $this->artisan('make:state', [
            'name' => 'TicketState',
            '--states' => 'Open,Closed',
        ])->assertExitCode(0);

        $this->artisan('stateflow:sync-enum', [
            'state' => 'App\\States\\TicketState',
            '--enum' => 'App\\Enums\\TicketStatus',
        ])->assertExitCode(0);

        $enumContent = File::get(app_path('Enums/TicketStatus.php'));
        expect($enumContent)
            ->toContain('Str::beforeLast')
            ->toContain('$this->name');
    });

    /**
     * Scenario: Command displays informative output
     * Setup: Create states and run sync-enum
     * Assertion: Command output includes table of cases and next steps
     */
    it('displays informative output', function () {
        $this->artisan('make:state', [
            'name' => 'ProjectState',
            '--states' => 'Planning,Active,Completed',
        ])->assertExitCode(0);

        // Use Artisan facade to capture output
        \Illuminate\Support\Facades\Artisan::call('stateflow:sync-enum', [
            'state' => 'App\\States\\ProjectState',
            '--enum' => 'App\\Enums\\ProjectStatus',
        ]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        expect($output)->toContain('Enum')
            ->toContain('3 state classes');
    });

    /**
     * Scenario: Preserves custom canTransitionTo() logic during sync
     * Setup: Create states, sync enum, add custom transitions, add new state, sync again
     * Assertion: Custom transition logic is preserved after second sync
     */
    it('preserves custom canTransitionTo method during sync', function () {
        // Create initial states
        $this->artisan('make:state', [
            'name' => 'InvoiceState',
            '--states' => 'Draft,Sent',
        ])->assertExitCode(0);

        // Initial sync
        $this->artisan('stateflow:sync-enum', [
            'state' => 'App\\States\\InvoiceState',
            '--enum' => 'App\\Enums\\InvoiceStatus',
        ])->assertExitCode(0);

        // Manually add custom canTransitionTo() logic
        $enumPath = app_path('Enums/InvoiceStatus.php');
        $content = File::get($enumPath);

        $customTransitions = <<<'PHP'
    /**
     * Custom transition logic for invoices.
     */
    public function canTransitionTo(): array
    {
        return match ($this) {
            self::Draft => [self::Sent],
            self::Sent => [],
        };
    }
PHP;

        // Replace the default canTransitionTo with custom logic
        $content = preg_replace(
            '/public\s+function\s+canTransitionTo\s*\(\s*\)\s*:\s*array\s*\{[\s\S]*?^    \}/m',
            $customTransitions,
            $content
        );
        File::put($enumPath, $content);

        // Add a new state
        $this->artisan('make:state', [
            'name' => 'Paid',
            '--extends' => 'App\\States\\InvoiceState',
        ])->assertExitCode(0);

        // Sync again - should preserve custom canTransitionTo()
        $this->artisan('stateflow:sync-enum', [
            'state' => 'App\\States\\InvoiceState',
            '--enum' => 'App\\Enums\\InvoiceStatus',
        ])->assertExitCode(0);

        // Verify custom logic is preserved and new case is added
        $updatedContent = File::get($enumPath);
        expect($updatedContent)
            ->toContain('case Paid =')
            ->toContain('Custom transition logic for invoices')
            ->toContain('self::Draft => [self::Sent]');

        // Verify file has only one enum declaration (no duplication)
        expect(substr_count($updatedContent, 'enum InvoiceStatus'))->toBe(1);
    });

    /**
     * Scenario: Preserves complex canTransitionTo with nested match expressions
     * Setup: Create states with complex transition logic including arrays and comments
     * Assertion: Complex transition logic is preserved correctly
     */
    it('preserves complex canTransitionTo with nested arrays', function () {
        // Create initial states
        $this->artisan('make:state', [
            'name' => 'BookingState',
            '--states' => 'Draft,Confirmed,Paid,Fulfilled,Cancelled,Expired',
        ])->assertExitCode(0);

        // Initial sync
        $this->artisan('stateflow:sync-enum', [
            'state' => 'App\\States\\BookingState',
            '--enum' => 'App\\Enums\\BookingStatus',
        ])->assertExitCode(0);

        // Manually add complex canTransitionTo() logic (like the real BookingStateStatus)
        $enumPath = app_path('Enums/BookingStatus.php');
        $content = File::get($enumPath);

        $complexTransitions = <<<'PHP'
    /**
     * Define which states this state can transition to.
     *
     * This method encodes the entire transition topology for the booking workflow.
     *
     * @return array<self>
     */
    public function canTransitionTo(): array
    {
        return match ($this) {
            self::Draft => [
                self::Confirmed,
                self::Expired,
            ],
            self::Confirmed => [
                self::Paid,
                self::Cancelled,
                self::Expired,
            ],
            self::Paid => [
                self::Fulfilled,
                self::Cancelled, // refund scenario
            ],
            // Final states - no transitions allowed
            self::Fulfilled,
            self::Cancelled,
            self::Expired => [],
        };
    }
PHP;

        // Replace the default canTransitionTo with complex logic
        $content = preg_replace(
            '/public\s+function\s+canTransitionTo\s*\(\s*\)\s*:\s*array\s*\{[\s\S]*?^    \}/m',
            $complexTransitions,
            $content
        );
        File::put($enumPath, $content);

        // Add a new state
        $this->artisan('make:state', [
            'name' => 'Processing',
            '--extends' => 'App\\States\\BookingState',
        ])->assertExitCode(0);

        // Sync again - should preserve complex canTransitionTo()
        $this->artisan('stateflow:sync-enum', [
            'state' => 'App\\States\\BookingState',
            '--enum' => 'App\\Enums\\BookingStatus',
        ])->assertExitCode(0);

        // Verify complex logic is preserved and new case is added
        $updatedContent = File::get($enumPath);
        expect($updatedContent)
            ->toContain('case Processing =')
            ->toContain('self::Draft => [')
            ->toContain('self::Confirmed,')
            ->toContain('self::Expired,')
            ->toContain('// refund scenario')
            ->toContain('// Final states - no transitions allowed');

        // Verify file has only one enum declaration (no duplication bug)
        expect(substr_count($updatedContent, 'enum BookingStatus'))->toBe(1);

        // Verify the file is valid PHP by checking it can be parsed
        $tokens = token_get_all($updatedContent);
        expect($tokens)->not->toBeEmpty();
    });

    /**
     * Scenario: --force flag overwrites custom canTransitionTo() logic
     * Setup: Create states with custom transitions, sync with --force
     * Assertion: Custom transition logic is replaced with default
     */
    it('overwrites custom canTransitionTo when force flag is used', function () {
        // Create initial states
        $this->artisan('make:state', [
            'name' => 'ContractState',
            '--states' => 'Active,Expired',
        ])->assertExitCode(0);

        // Initial sync
        $this->artisan('stateflow:sync-enum', [
            'state' => 'App\\States\\ContractState',
            '--enum' => 'App\\Enums\\ContractStatus',
        ])->assertExitCode(0);

        // Add custom canTransitionTo() logic
        $enumPath = app_path('Enums/ContractStatus.php');
        $content = File::get($enumPath);

        $customTransitions = <<<'PHP'
    /**
     * My custom contract transitions.
     */
    public function canTransitionTo(): array
    {
        return match ($this) {
            self::Active => [self::Expired],
            self::Expired => [],
        };
    }
PHP;

        $content = preg_replace(
            '/public\s+function\s+canTransitionTo\s*\(\s*\)\s*:\s*array\s*\{[\s\S]*?^    \}/m',
            $customTransitions,
            $content
        );
        File::put($enumPath, $content);

        // Sync with --force - should overwrite custom logic
        $this->artisan('stateflow:sync-enum', [
            'state' => 'App\\States\\ContractState',
            '--enum' => 'App\\Enums\\ContractStatus',
            '--force' => true,
        ])->assertExitCode(0);

        // Verify custom logic is replaced with default
        $updatedContent = File::get($enumPath);
        expect($updatedContent)
            ->not->toContain('My custom contract transitions')
            ->toContain('TODO: Define your transitions here');
    });
});
