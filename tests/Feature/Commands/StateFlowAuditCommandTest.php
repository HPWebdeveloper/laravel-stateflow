<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;

describe('StateFlowAuditCommand', function () {
    /**
     * Scenario: Audit fails for non-existent model class
     * Setup: Run stateflow:audit with invalid model name
     * Assertion: Command exits with error and shows model not found message
     */
    it('fails for non-existent model', function () {
        $this->artisan('stateflow:audit', ['model' => 'NonExistentModel'])
            ->assertExitCode(1)
            ->expectsOutput("Model class 'App\Models\NonExistentModel' not found.");
    });

    /**
     * Scenario: Audit fails for model without HasStatesContract
     * Setup: Run audit on stdClass which doesn't implement HasStatesContract
     * Assertion: Command exits with error code 1
     */
    it('fails for model without HasStatesContract', function () {
        $this->artisan('stateflow:audit', ['model' => \stdClass::class])
            ->assertExitCode(1);
    });

    /**
     * Scenario: Successfully audit Post model configuration
     * Setup: Run audit on Post model with valid state configuration
     * Assertion: Command succeeds and outputs audit message
     */
    it('audits Post model successfully', function () {
        $this->artisan('stateflow:audit', ['model' => Post::class])
            ->assertExitCode(0)
            ->expectsOutputToContain('Auditing state configuration');
    });

    /**
     * Scenario: Audit output includes default state information
     * Setup: Run audit on Post model
     * Assertion: Output contains 'Default state: draft'
     */
    it('shows default state in audit results', function () {
        $this->artisan('stateflow:audit', ['model' => Post::class])
            ->assertExitCode(0)
            ->expectsOutputToContain('Default state: draft');
    });

    /**
     * Scenario: Audit specific field using --field option
     * Setup: Run audit with --field=state option
     * Assertion: Output shows checking specified field
     */
    it('accepts field option', function () {
        $this->artisan('stateflow:audit', [
            'model' => Post::class,
            '--field' => 'state',
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Checking field: state');
    });

    /**
     * Scenario: Audit fails for non-existent field name
     * Setup: Run audit with invalid --field option
     * Assertion: Command fails with field not found error message
     */
    it('fails for non-existent field', function () {
        $this->artisan('stateflow:audit', [
            'model' => Post::class,
            '--field' => 'non_existent_field',
        ])
            ->assertExitCode(1)
            ->expectsOutput("No state configuration found for field 'non_existent_field'");
    });

    /**
     * Scenario: Show success message when audit passes
     * Setup: Run audit on valid model
     * Assertion: Output contains 'Audit passed'
     */
    it('shows audit passed message on success', function () {
        $this->artisan('stateflow:audit', ['model' => Post::class])
            ->assertExitCode(0)
            ->expectsOutputToContain('Audit passed');
    });
});

describe('StateFlowAuditCommand with issues', function () {
    /**
     * Scenario: Detect and report state configuration
     * Setup: Run audit command on Post model
     * Assertion: Command runs and completes successfully
     */
    it('detects states configuration', function () {
        // The audit command runs and completes
        $this->artisan('stateflow:audit', ['model' => Post::class])
            ->assertExitCode(0);
    });
});

describe('StateFlowAuditCommand handles model name shortcuts', function () {
    /**
     * Scenario: Expand short model names with App\Models prefix
     * Setup: Run audit with short name 'SomeNonExistentModel'
     * Assertion: Error message shows expanded path with App\Models prefix
     */
    it('expands short model names with App\\Models prefix', function () {
        // This should fail because the model doesn't exist, but with correct path
        $this->artisan('stateflow:audit', ['model' => 'SomeNonExistentModel'])
            ->assertExitCode(1)
            ->expectsOutput("Model class 'App\Models\SomeNonExistentModel' not found.");
    });

    /**
     * Scenario: Accept fully qualified class names
     * Setup: Run audit with full class name including namespace
     * Assertion: Command runs successfully
     */
    it('accepts fully qualified class names', function () {
        $this->artisan('stateflow:audit', ['model' => Post::class])
            ->assertExitCode(0);
    });
});
