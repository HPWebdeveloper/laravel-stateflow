<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(app_path('Actions'));
});

afterEach(function () {
    File::deleteDirectory(app_path('Actions'));
});

describe('MakeTransitionCommand', function () {
    /**
     * Scenario: Generate a transition action class
     * Setup: Run make:transition command with name 'PublishPost'
     * Assertion: File created with TransitionActionContract implementation and handle method
     */
    it('creates a transition class', function () {
        $this->artisan('make:transition', ['name' => 'PublishPost'])
            ->assertExitCode(0);

        expect(File::exists(app_path('Actions/Transitions/PublishPostTransition.php')))->toBeTrue();

        $content = File::get(app_path('Actions/Transitions/PublishPostTransition.php'));
        expect($content)
            ->toContain('class PublishPostTransition')
            ->toContain('implements TransitionActionContract')
            ->toContain('public function handle(Model $model, TransitionContext $context): TransitionResult');
    });

    /**
     * Scenario: Automatically append 'Transition' suffix if missing
     * Setup: Run make:transition with name not ending in 'Transition'
     * Assertion: File name includes 'Transition' suffix
     */
    it('appends Transition suffix if not provided', function () {
        $this->artisan('make:transition', ['name' => 'ApproveOrder'])
            ->assertExitCode(0);

        expect(File::exists(app_path('Actions/Transitions/ApproveOrderTransition.php')))->toBeTrue();
    });

    /**
     * Scenario: Avoid duplicating 'Transition' suffix
     * Setup: Run make:transition with name already ending in 'Transition'
     * Assertion: File name contains suffix only once
     */
    it('does not duplicate Transition suffix', function () {
        $this->artisan('make:transition', ['name' => 'RejectTransition'])
            ->assertExitCode(0);

        expect(File::exists(app_path('Actions/Transitions/RejectTransition.php')))->toBeTrue();

        $content = File::get(app_path('Actions/Transitions/RejectTransition.php'));
        expect($content)->toContain('class RejectTransition');
    });

    /**
     * Scenario: Accept --from and --to state options
     * Setup: Run make:transition with --from and --to options
     * Assertion: Transition file is created successfully
     */
    it('accepts from and to options', function () {
        $this->artisan('make:transition', [
            'name' => 'DraftToReview',
            '--from' => 'Draft',
            '--to' => 'Review',
        ])
            ->assertExitCode(0);

        expect(File::exists(app_path('Actions/Transitions/DraftToReviewTransition.php')))->toBeTrue();
    });

    /**
     * Scenario: Create transition in nested namespace
     * Setup: Run make:transition with path separator in name
     * Assertion: File is created in nested directory structure
     */
    it('creates transition in nested namespace', function () {
        $this->artisan('make:transition', ['name' => 'Orders/ShipOrder'])
            ->assertExitCode(0);

        expect(File::exists(app_path('Actions/Transitions/Orders/ShipOrderTransition.php')))->toBeTrue();
    });

    /**
     * Scenario: Verify correct namespace in generated file
     * Setup: Run make:transition, read generated file
     * Assertion: File contains correct namespace declaration
     */
    it('contains correct namespace', function () {
        $this->artisan('make:transition', ['name' => 'ArchivePost'])
            ->assertExitCode(0);

        $content = File::get(app_path('Actions/Transitions/ArchivePostTransition.php'));
        expect($content)->toContain('namespace App\Actions\Transitions;');
    });

    /**
     * Scenario: Verify all required imports are included
     * Setup: Run make:transition, check file contents
     * Assertion: File contains use statements for TransitionActionContract, TransitionContext, TransitionResult, and Model
     */
    it('imports required classes', function () {
        $this->artisan('make:transition', ['name' => 'TestTransition'])
            ->assertExitCode(0);

        $content = File::get(app_path('Actions/Transitions/TestTransition.php'));
        expect($content)
            ->toContain('use Hpwebdeveloper\LaravelStateflow\Contracts\TransitionActionContract;')
            ->toContain('use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionContext;')
            ->toContain('use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;')
            ->toContain('use Illuminate\Database\Eloquent\Model;');
    });
});
