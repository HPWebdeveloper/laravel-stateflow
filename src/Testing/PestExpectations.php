<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Testing;

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Hpwebdeveloper\LaravelStateflow\HasStatesContract;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;

/**
 * Pest expectation extensions for StateFlow testing.
 *
 * These expectations extend Pest's expect() function to provide
 * fluent assertions for state-related tests.
 *
 * Usage:
 *   PestExpectations::register();
 *
 *   expect($post)->toBeInState('draft');
 *   expect($post)->toBeAbleToTransitionTo('review');
 *   expect($result)->toBeSuccessfulTransition();
 */
class PestExpectations
{
    /**
     * Whether expectations have been registered.
     */
    protected static bool $registered = false;

    /**
     * Register all expectations.
     */
    public static function register(): void
    {
        if (static::$registered) {
            return;
        }

        static::registerModelExpectations();
        static::registerTransitionResultExpectations();

        static::$registered = true;
    }

    /**
     * Register model state expectations.
     */
    protected static function registerModelExpectations(): void
    {
        // toBeInState
        expect()->extend('toBeInState', function (string $expectedState, ?string $field = null) {
            /** @var Model&HasStatesContract $model */
            $model = $this->value;
            $field = $field ?? 'state';
            $actualState = PestExpectations::extractStateValue($model, $field);

            expect($actualState)->toBe($expectedState);

            return $this;
        });

        // toNotBeInState
        expect()->extend('toNotBeInState', function (string $unexpectedState, ?string $field = null) {
            /** @var Model&HasStatesContract $model */
            $model = $this->value;
            $field = $field ?? 'state';
            $actualState = PestExpectations::extractStateValue($model, $field);

            expect($actualState)->not->toBe($unexpectedState);

            return $this;
        });

        // toBeAbleToTransitionTo
        expect()->extend('toBeAbleToTransitionTo', function (string $targetState, ?string $field = null) {
            /** @var Model&HasStatesContract $model */
            $model = $this->value;
            $field = $field ?? 'state';
            $allowedTransitions = PestExpectations::extractAllowedTransitionNames($model, $field);

            Assert::assertContains(
                $targetState,
                $allowedTransitions,
                "Model cannot transition to '{$targetState}'. Allowed transitions: ".implode(', ', $allowedTransitions)
            );

            return $this;
        });

        // toNotBeAbleToTransitionTo
        expect()->extend('toNotBeAbleToTransitionTo', function (string $targetState, ?string $field = null) {
            /** @var Model&HasStatesContract $model */
            $model = $this->value;
            $field = $field ?? 'state';
            $allowedTransitions = PestExpectations::extractAllowedTransitionNames($model, $field);

            Assert::assertNotContains(
                $targetState,
                $allowedTransitions,
                "Model should NOT be able to transition to '{$targetState}'."
            );

            return $this;
        });

        // toHaveAllowedTransitions
        expect()->extend('toHaveAllowedTransitions', function (array $expectedTransitions, ?string $field = null) {
            /** @var Model&HasStatesContract $model */
            $model = $this->value;
            $field = $field ?? 'state';
            $allowedTransitions = PestExpectations::extractAllowedTransitionNames($model, $field);

            foreach ($expectedTransitions as $transition) {
                Assert::assertContains(
                    $transition,
                    $allowedTransitions,
                    "Expected '{$transition}' to be an allowed transition."
                );
            }

            return $this;
        });

        // toHaveExactlyAllowedTransitions
        expect()->extend('toHaveExactlyAllowedTransitions', function (array $expectedTransitions, ?string $field = null) {
            /** @var Model&HasStatesContract $model */
            $model = $this->value;
            $field = $field ?? 'state';
            $allowedTransitions = PestExpectations::extractAllowedTransitionNames($model, $field);

            sort($expectedTransitions);
            sort($allowedTransitions);

            expect($allowedTransitions)->toBe($expectedTransitions);

            return $this;
        });

        // toBeInTerminalState
        expect()->extend('toBeInTerminalState', function (?string $field = null) {
            /** @var Model&HasStatesContract $model */
            $model = $this->value;
            $field = $field ?? 'state';
            $allowedTransitions = PestExpectations::extractAllowedTransitionNames($model, $field);

            expect($allowedTransitions)->toBeEmpty();

            return $this;
        });

        // toBeInInitialState
        expect()->extend('toBeInInitialState', function (?string $field = null) {
            /** @var Model&HasStatesContract $model */
            $model = $this->value;
            $field = $field ?? 'state';
            $stateConfig = $model::getStateConfig($field);
            $defaultStateClass = $stateConfig->getDefaultStateClass();
            $defaultStateName = $defaultStateClass ? $defaultStateClass::name() : null;
            $currentState = PestExpectations::extractStateValue($model, $field);

            expect($currentState)->toBe($defaultStateName);

            return $this;
        });
    }

    /**
     * Register TransitionResult expectations.
     */
    protected static function registerTransitionResultExpectations(): void
    {
        // toBeSuccessfulTransition
        expect()->extend('toBeSuccessfulTransition', function () {
            /** @var TransitionResult $result */
            $result = $this->value;

            Assert::assertTrue(
                $result->succeeded(),
                'Expected transition to succeed, but it failed'.($result->error ? ": {$result->error}" : '.')
            );

            return $this;
        });

        // toBeFailedTransition
        expect()->extend('toBeFailedTransition', function (?string $expectedError = null) {
            /** @var TransitionResult $result */
            $result = $this->value;

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

            return $this;
        });

        // toHaveTransitionedTo
        expect()->extend('toHaveTransitionedTo', function (string $expectedState) {
            /** @var TransitionResult $result */
            $result = $this->value;

            expect($result->toState)->toBe($expectedState);

            return $this;
        });

        // toHaveTransitionedFrom
        expect()->extend('toHaveTransitionedFrom', function (string $expectedState) {
            /** @var TransitionResult $result */
            $result = $this->value;

            expect($result->fromState)->toBe($expectedState);

            return $this;
        });
    }

    /**
     * Extract the state value (name) from a model.
     *
     * @param  Model&HasStatesContract  $model
     */
    public static function extractStateValue(Model $model, string $field): string
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
     * Extract allowed transition names from a model.
     *
     * @param  Model&HasStatesContract  $model
     * @return array<int, string>
     */
    public static function extractAllowedTransitionNames(Model $model, string $field): array
    {
        $nextStates = $model->getNextStates($field);

        return array_map(fn ($stateClass) => $stateClass::name(), $nextStates);
    }
}
