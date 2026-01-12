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

describe('MakeStateCommand', function () {
    /**
     * Scenario: Generate a basic state class
     * Setup: Run make:state command with name 'Active'
     * Assertion: File is created with correct class name, name() and title() methods
     */
    it('creates a basic state class', function () {
        $this->artisan('make:state', ['name' => 'Active'])
            ->assertExitCode(0);

        expect(File::exists(app_path('States/Active.php')))->toBeTrue();

        $content = File::get(app_path('States/Active.php'));
        expect($content)
            ->toContain('class Active extends State')
            ->toContain("return 'active';")
            ->toContain("return 'Active';");
    });

    /**
     * Scenario: Generate state class with custom color
     * Setup: Run make:state with --color option
     * Assertion: Generated file contains color attribute with specified value
     */
    it('creates a state class with custom color', function () {
        $this->artisan('make:state', ['name' => 'Pending', '--color' => 'yellow'])
            ->assertExitCode(0);

        $content = File::get(app_path('States/Pending.php'));
        expect($content)->toContain("color: 'yellow'");
    });

    /**
     * Scenario: Generate state class with custom icon
     * Setup: Run make:state with --icon option
     * Assertion: Generated file contains icon attribute with specified value
     */
    it('creates a state class with custom icon', function () {
        $this->artisan('make:state', ['name' => 'Complete', '--icon' => 'check'])
            ->assertExitCode(0);

        $content = File::get(app_path('States/Complete.php'));
        expect($content)->toContain("icon: 'check'");
    });

    /**
     * Scenario: Generate default state with DefaultState attribute
     * Setup: Run make:state with --default flag
     * Assertion: File contains #[DefaultState] attribute and use statement
     */
    it('creates a default state with attribute', function () {
        $this->artisan('make:state', ['name' => 'Draft', '--default' => true])
            ->assertExitCode(0);

        $content = File::get(app_path('States/Draft.php'));
        expect($content)
            ->toContain('#[DefaultState]')
            ->toContain('use Hpwebdeveloper\LaravelStateflow\Attributes\DefaultState;');
    });

    /**
     * Scenario: Generate abstract base state class
     * Setup: Run make:state with --base flag
     * Assertion: File contains abstract class extending State
     */
    it('creates a base state class', function () {
        $this->artisan('make:state', ['name' => 'OrderState', '--base' => true])
            ->assertExitCode(0);

        $content = File::get(app_path('States/OrderState.php'));
        expect($content)
            ->toContain('abstract class OrderState extends State')
            ->toContain('use Hpwebdeveloper\LaravelStateflow\State;');
    });

    /**
     * Scenario: Generate state extending custom base class
     * Setup: Run make:state with --extends option
     * Assertion: Generated class extends specified base class
     */
    it('creates a state class extending a custom base', function () {
        $this->artisan('make:state', ['name' => 'Pending', '--extends' => 'OrderState'])
            ->assertExitCode(0);

        $content = File::get(app_path('States/Pending.php'));
        expect($content)->toContain('class Pending extends OrderState');
    });

    /**
     * Scenario: Generate state with full namespace in --extends option
     * Setup: Run make:state with --extends containing full namespace path
     * Assertion: File is created in the same directory as the base class and extends correctly
     */
    it('creates state in same directory when extends has full namespace', function () {
        // First create the base class in a nested directory
        File::ensureDirectoryExists(app_path('States/Booking'));
        File::put(app_path('States/Booking/BookingState.php'), '<?php namespace App\States\Booking; abstract class BookingState {}');

        $this->artisan('make:state', ['name' => 'Processing', '--extends' => 'App\\States\\Booking\\BookingState'])
            ->assertExitCode(0);

        // Should be created in the same directory as BookingState
        expect(File::exists(app_path('States/Booking/Processing.php')))->toBeTrue();

        $content = File::get(app_path('States/Booking/Processing.php'));
        expect($content)
            ->toContain('namespace App\States\Booking;')
            ->toContain('class Processing extends BookingState');
    });

    /**
     * Scenario: Handle CamelCase names with correct snake_case conversion
     * Setup: Run make:state with CamelCase name 'InProgress'
     * Assertion: name() returns 'in_progress', title() returns 'In Progress'
     */
    it('handles CamelCase names correctly', function () {
        $this->artisan('make:state', ['name' => 'InProgress'])
            ->assertExitCode(0);

        $content = File::get(app_path('States/InProgress.php'));
        expect($content)
            ->toContain("return 'in_progress';")
            ->toContain("return 'In Progress';");
    });

    /**
     * Scenario: Create state in nested namespace
     * Setup: Run make:state with path separator in name
     * Assertion: File is created in nested directory with correct namespace
     */
    it('creates state in nested namespace', function () {
        $this->artisan('make:state', ['name' => 'Orders/Pending'])
            ->assertExitCode(0);

        expect(File::exists(app_path('States/Orders/Pending.php')))->toBeTrue();
    });

    /**
     * Scenario: Generate state with all options combined
     * Setup: Run make:state with --default, --color, and --icon options
     * Assertion: File contains all attributes: DefaultState, color, and icon
     */
    it('creates all options combined', function () {
        $this->artisan('make:state', [
            'name' => 'Ready',
            '--default' => true,
            '--color' => 'green',
            '--icon' => 'thumbs-up',
        ])
            ->assertExitCode(0);

        $content = File::get(app_path('States/Ready.php'));
        expect($content)
            ->toContain('#[DefaultState]')
            ->toContain("color: 'green'")
            ->toContain("icon: 'thumbs-up'");
    });

    /**
     * Scenario: Generate base class and multiple extending states at once
     * Setup: Run make:state with --states option containing comma-separated state names
     * Assertion: Base abstract class and all extending state classes are created
     */
    it('creates base and multiple state classes with --states option', function () {
        $this->artisan('make:state', [
            'name' => 'OrderState',
            '--states' => 'Pending,Processing,Shipped',
        ])
            ->assertExitCode(0);

        // Verify base class was created
        expect(File::exists(app_path('States/OrderState.php')))->toBeTrue();
        $baseContent = File::get(app_path('States/OrderState.php'));
        expect($baseContent)->toContain('abstract class OrderState extends State');

        // Verify all extending state classes were created
        expect(File::exists(app_path('States/Pending.php')))->toBeTrue();
        expect(File::exists(app_path('States/Processing.php')))->toBeTrue();
        expect(File::exists(app_path('States/Shipped.php')))->toBeTrue();

        // Verify they extend the base class
        $pendingContent = File::get(app_path('States/Pending.php'));
        expect($pendingContent)->toContain('class Pending extends OrderState');

        $processingContent = File::get(app_path('States/Processing.php'));
        expect($processingContent)->toContain('class Processing extends OrderState');

        $shippedContent = File::get(app_path('States/Shipped.php'));
        expect($shippedContent)->toContain('class Shipped extends OrderState');
    });

    /**
     * Scenario: Handle spaces in --states option values
     * Setup: Run make:state with --states containing spaces after commas
     * Assertion: All state classes are created correctly despite spacing
     */
    it('handles spaces in --states option', function () {
        $this->artisan('make:state', [
            'name' => 'TaskState',
            '--states' => 'Todo, InProgress, Done',
        ])
            ->assertExitCode(0);

        expect(File::exists(app_path('States/TaskState.php')))->toBeTrue();
        expect(File::exists(app_path('States/Todo.php')))->toBeTrue();
        expect(File::exists(app_path('States/InProgress.php')))->toBeTrue();
        expect(File::exists(app_path('States/Done.php')))->toBeTrue();
    });

    /**
     * Scenario: Generate states with enum for workflow topology
     * Setup: Run make:state with --states and --transitions=enum options
     * Assertion: Base class, state classes, and enum file are all created
     */
    it('creates states and enum with --transitions=enum option', function () {
        $this->artisan('make:state', [
            'name' => 'OrderState',
            '--states' => 'Pending,Processing,Shipped',
            '--transitions' => 'enum',
        ])
            ->assertExitCode(0);

        // Verify state classes were created
        expect(File::exists(app_path('States/OrderState.php')))->toBeTrue();
        expect(File::exists(app_path('States/Pending.php')))->toBeTrue();
        expect(File::exists(app_path('States/Processing.php')))->toBeTrue();
        expect(File::exists(app_path('States/Shipped.php')))->toBeTrue();

        // Verify enum was created
        expect(File::exists(app_path('Enums/OrderStateStatus.php')))->toBeTrue();

        // Verify enum content
        $enumContent = File::get(app_path('Enums/OrderStateStatus.php'));
        expect($enumContent)
            ->toContain('enum OrderStateStatus: string')
            ->toContain("case Pending = 'pending';")
            ->toContain("case Processing = 'processing';")
            ->toContain("case Shipped = 'shipped';")
            ->toContain('public function stateClass(): string')
            ->toContain('public function canTransitionTo(): array')
            ->toContain('public static function stateClasses(): array')
            ->toContain('public static function transitions(): array');
    });

    /**
     * Scenario: Generate states with custom enum location
     * Setup: Run make:state with --transitions=enum and --enum options
     * Assertion: Enum is created at the custom specified location
     */
    it('creates enum at custom location with --enum option', function () {
        $this->artisan('make:state', [
            'name' => 'PaymentState',
            '--states' => 'Pending,Completed,Failed',
            '--transitions' => 'enum',
            '--enum' => 'App\\Enums\\PaymentWorkflow',
        ])
            ->assertExitCode(0);

        // Verify state classes were created
        expect(File::exists(app_path('States/PaymentState.php')))->toBeTrue();
        expect(File::exists(app_path('States/Pending.php')))->toBeTrue();

        // Verify enum was created at custom location
        expect(File::exists(app_path('Enums/PaymentWorkflow.php')))->toBeTrue();

        // Verify enum has correct name
        $enumContent = File::get(app_path('Enums/PaymentWorkflow.php'));
        expect($enumContent)->toContain('enum PaymentWorkflow: string');
    });

    /**
     * Scenario: Enum contains sync command reminder in docblock
     * Setup: Run make:state with --transitions=enum
     * Assertion: Generated enum contains sync command reminder comment
     */
    it('includes sync command reminder in generated enum', function () {
        $this->artisan('make:state', [
            'name' => 'ArticleState',
            '--states' => 'Draft,Published',
            '--transitions' => 'enum',
        ])
            ->assertExitCode(0);

        $enumContent = File::get(app_path('Enums/ArticleStateStatus.php'));
        expect($enumContent)
            ->toContain('IMPORTANT: After creating new state classes, run:')
            ->toContain('stateflow:sync-enum');
    });
});
