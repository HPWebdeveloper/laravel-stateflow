<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Testing;

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Hpwebdeveloper\LaravelStateflow\HasStatesContract;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;

/**
 * Trait for asserting state-related conditions in tests.
 *
 * Usage in test class:
 *   use AssertState;
 *
 *   $this->assertModelInState($post, 'draft');
 *   $this->assertCanTransitionTo($post, 'review');
 */
trait AssertState
{
    /**
     * Assert that a model is in a specific state.
     *
     * @param  Model&HasStatesContract  $model
     */
    protected function assertModelInState(Model $model, string $expectedState, ?string $field = null): void
    {
        $field = $field ?? 'state';
        $actualState = $this->getStateNameFromModel($model, $field);

        Assert::assertEquals(
            $expectedState,
            $actualState,
            "Expected model to be in state '{$expectedState}', but was in '{$actualState}'."
        );
    }

    /**
     * Assert that a model is NOT in a specific state.
     *
     * @param  Model&HasStatesContract  $model
     */
    protected function assertModelNotInState(Model $model, string $unexpectedState, ?string $field = null): void
    {
        $field = $field ?? 'state';
        $actualState = $this->getStateNameFromModel($model, $field);

        Assert::assertNotEquals(
            $unexpectedState,
            $actualState,
            "Model should NOT be in state '{$unexpectedState}'."
        );
    }

    /**
     * Assert that a model can transition to a specific state.
     *
     * @param  Model&HasStatesContract  $model
     */
    protected function assertCanTransitionTo(Model $model, string $targetState, ?string $field = null): void
    {
        $field = $field ?? 'state';

        Assert::assertTrue(
            $model->canTransitionTo($targetState, $field),
            "Model cannot transition to '{$targetState}'. Allowed transitions: ".implode(', ', $this->getAllowedTransitionNames($model, $field))
        );
    }

    /**
     * Assert that a model CANNOT transition to a specific state.
     *
     * @param  Model&HasStatesContract  $model
     */
    protected function assertCannotTransitionTo(Model $model, string $targetState, ?string $field = null): void
    {
        $field = $field ?? 'state';

        Assert::assertFalse(
            $model->canTransitionTo($targetState, $field),
            "Model should NOT be able to transition to '{$targetState}'."
        );
    }

    /**
     * Assert that a transition result was successful.
     */
    protected function assertTransitionSucceeded(TransitionResult $result): void
    {
        Assert::assertTrue(
            $result->succeeded(),
            'Expected transition to succeed, but it failed'.($result->error ? ": {$result->error}" : '.')
        );
    }

    /**
     * Assert that a transition result failed.
     */
    protected function assertTransitionFailed(TransitionResult $result, ?string $expectedError = null): void
    {
        Assert::assertTrue(
            $result->failed(),
            'Expected transition to fail, but it succeeded.'
        );

        if ($expectedError !== null) {
            Assert::assertStringContainsString(
                $expectedError,
                $result->error ?? '',
                "Expected error to contain '{$expectedError}'."
            );
        }
    }

    /**
     * Assert that a model has allowed transitions.
     *
     * @param  Model&HasStatesContract  $model
     * @param  array<int, string>  $expectedTransitions
     */
    protected function assertHasAllowedTransitions(Model $model, array $expectedTransitions, ?string $field = null): void
    {
        $field = $field ?? 'state';
        $allowedTransitions = $this->getAllowedTransitionNames($model, $field);

        foreach ($expectedTransitions as $transition) {
            Assert::assertContains(
                $transition,
                $allowedTransitions,
                "Expected '{$transition}' to be an allowed transition."
            );
        }
    }

    /**
     * Assert that allowed transitions match exactly.
     *
     * @param  Model&HasStatesContract  $model
     * @param  array<int, string>  $expectedTransitions
     */
    protected function assertAllowedTransitionsExactly(Model $model, array $expectedTransitions, ?string $field = null): void
    {
        $field = $field ?? 'state';
        $allowedTransitions = $this->getAllowedTransitionNames($model, $field);

        sort($expectedTransitions);
        sort($allowedTransitions);

        Assert::assertEquals(
            $expectedTransitions,
            $allowedTransitions,
            'Allowed transitions do not match expected.'
        );
    }

    /**
     * Assert a model has no allowed transitions (terminal state).
     *
     * @param  Model&HasStatesContract  $model
     */
    protected function assertNoAllowedTransitions(Model $model, ?string $field = null): void
    {
        $field = $field ?? 'state';
        $allowedTransitions = $this->getAllowedTransitionNames($model, $field);

        Assert::assertEmpty(
            $allowedTransitions,
            'Expected no allowed transitions (terminal state), but found: '.implode(', ', $allowedTransitions)
        );
    }

    /**
     * Assert model is in a terminal state.
     *
     * @param  Model&HasStatesContract  $model
     */
    protected function assertInTerminalState(Model $model, ?string $field = null): void
    {
        $this->assertNoAllowedTransitions($model, $field);
    }

    /**
     * Assert model is in an initial/default state.
     *
     * @param  Model&HasStatesContract  $model
     */
    protected function assertInInitialState(Model $model, ?string $field = null): void
    {
        $field = $field ?? 'state';
        $stateConfig = $model::getStateConfig($field);

        $defaultStateClass = $stateConfig->getDefaultStateClass();
        $defaultStateName = $defaultStateClass ? $defaultStateClass::name() : null;
        $currentState = $this->getStateNameFromModel($model, $field);

        Assert::assertEquals(
            $defaultStateName,
            $currentState,
            "Expected model to be in initial state '{$defaultStateName}', but was in '{$currentState}'."
        );
    }

    /**
     * Get the state name from a model.
     *
     * @param  Model&HasStatesContract  $model
     */
    protected function getStateNameFromModel(Model $model, string $field): string
    {
        $state = $model->{$field};

        if (is_object($state)) {
            if (method_exists($state, 'name')) {
                return $state::name();
            }
            if (method_exists($state, '__toString')) {
                return (string) $state;
            }
        }

        return (string) $state;
    }

    /**
     * Get allowed transition names from a model.
     *
     * @param  Model&HasStatesContract  $model
     * @return array<int, string>
     */
    protected function getAllowedTransitionNames(Model $model, string $field): array
    {
        $nextStates = $model->getNextStates($field);

        return array_map(fn ($stateClass) => $stateClass::name(), $nextStates);
    }
}
