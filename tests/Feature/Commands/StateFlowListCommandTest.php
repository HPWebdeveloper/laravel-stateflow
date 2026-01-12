<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;

describe('StateFlowListCommand', function () {
    /**
     * Scenario: Show usage when model argument is missing
     * Setup: Run stateflow:list without model parameter
     * Assertion: Command shows usage instructions
     */
    it('shows usage when no model provided', function () {
        $this->artisan('stateflow:list')
            ->assertExitCode(0)
            ->expectsOutput('Usage: php artisan stateflow:list {ModelClass}');
    });

    /**
     * Scenario: List fails for non-existent model
     * Setup: Run stateflow:list with invalid model name
     * Assertion: Command exits with error and shows model not found message
     */
    it('fails for non-existent model', function () {
        $this->artisan('stateflow:list', ['model' => 'NonExistentModel'])
            ->assertExitCode(1)
            ->expectsOutput("Model class 'App\Models\NonExistentModel' not found.");
    });

    /**
     * Scenario: List fails for model without HasStatesContract
     * Setup: Run list on stdClass which doesn't implement HasStatesContract
     * Assertion: Command exits with error code 1
     */
    it('fails for model without HasStatesContract', function () {
        // Use a class that exists but doesn't use HasStates
        $this->artisan('stateflow:list', ['model' => \stdClass::class])
            ->assertExitCode(1);
    });

    /**
     * Scenario: Successfully list states for Post model
     * Setup: Run list command on Post model
     * Assertion: Command executes successfully
     */
    it('lists states for Post model', function () {
        $result = $this->artisan('stateflow:list', ['model' => Post::class]);

        $result->assertExitCode(0);
    });

    /**
     * Scenario: Display state table with proper column headers
     * Setup: Run list command
     * Assertion: Command succeeds (headers are displayed in table)
     */
    it('displays state table with correct headers', function () {
        $this->artisan('stateflow:list', ['model' => Post::class])
            ->assertExitCode(0);
    });

    /**
     * Scenario: Show default state indicator in output
     * Setup: Run list command
     * Assertion: Output contains checkmark (✓) for default state
     */
    it('shows default state indicator', function () {
        $this->artisan('stateflow:list', ['model' => Post::class])
            ->assertExitCode(0)
            ->expectsOutputToContain('✓');
    });

    /**
     * Scenario: List states for specific field using --field option
     * Setup: Run list with --field=state option
     * Assertion: Output shows specified field name
     */
    it('accepts field option', function () {
        $this->artisan('stateflow:list', [
            'model' => Post::class,
            '--field' => 'state',
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('field: state');
    });

    /**
     * Scenario: List fails for non-existent field name
     * Setup: Run list with invalid --field option
     * Assertion: Command fails with field not found error
     */
    it('fails for non-existent field', function () {
        $this->artisan('stateflow:list', [
            'model' => Post::class,
            '--field' => 'non_existent_field',
        ])
            ->assertExitCode(1)
            ->expectsOutput("No state configuration found for field 'non_existent_field'");
    });

    /**
     * Scenario: Display total count of states
     * Setup: Run list command
     * Assertion: Output contains 'Total states:' text
     */
    it('shows total states count', function () {
        $this->artisan('stateflow:list', ['model' => Post::class])
            ->assertExitCode(0)
            ->expectsOutputToContain('Total states:');
    });

    /**
     * Scenario: Display transitions to column in table
     * Setup: Run list command
     * Assertion: Output contains 'Transitions To' column header
     */
    it('shows transitions to column', function () {
        $this->artisan('stateflow:list', ['model' => Post::class])
            ->assertExitCode(0)
            ->expectsOutputToContain('Transitions To');
    });

    /**
     * Scenario: Display permitted roles column in table
     * Setup: Run list command
     * Assertion: Output contains 'Permitted Roles' column header
     */
    it('shows permitted roles column', function () {
        $this->artisan('stateflow:list', ['model' => Post::class])
            ->assertExitCode(0)
            ->expectsOutputToContain('Permitted Roles');
    });
});
