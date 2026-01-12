<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Testing;

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;

/**
 * Fake implementation for testing state transitions.
 *
 * Usage:
 *   StateFlow::fake();
 *   // ... perform actions ...
 *   StateFlow::assertTransitioned($post, 'draft', 'review');
 */
class StateFlowFake
{
    /**
     * Recorded transitions.
     *
     * @var Collection<int, array<string, mixed>>
     */
    protected Collection $recordedTransitions;

    /**
     * Transitions to prevent.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    protected array $preventedTransitions = [];

    /**
     * Force specific transition results.
     *
     * @var array<string, TransitionResult>
     */
    protected array $forcedResults = [];

    /**
     * Whether all transitions should be prevented.
     */
    protected bool $preventAllTransitions = false;

    public function __construct()
    {
        $this->recordedTransitions = collect();
    }

    /**
     * Record a transition.
     */
    public function recordTransition(
        Model $model,
        string $field,
        string $fromState,
        string $toState,
        bool $success = true,
        ?string $error = null
    ): void {
        $this->recordedTransitions->push([
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'field' => $field,
            'from' => $fromState,
            'to' => $toState,
            'success' => $success,
            'error' => $error,
            'timestamp' => now(),
        ]);
    }

    /**
     * Assert that a transition occurred.
     */
    public function assertTransitioned(
        Model $model,
        string $fromState,
        string $toState,
        ?string $field = null
    ): self {
        $found = $this->findTransition($model, $fromState, $toState, $field);

        Assert::assertNotNull(
            $found,
            "Expected transition from '{$fromState}' to '{$toState}' was not recorded."
        );

        return $this;
    }

    /**
     * Assert that a transition did NOT occur.
     */
    public function assertNotTransitioned(
        Model $model,
        ?string $fromState = null,
        ?string $toState = null,
        ?string $field = null
    ): self {
        $found = $this->recordedTransitions->first(function ($t) use ($model, $fromState, $toState, $field) {
            $matches = $t['model_type'] === get_class($model)
                && $t['model_id'] === $model->getKey();

            if ($fromState !== null) {
                $matches = $matches && $t['from'] === $fromState;
            }
            if ($toState !== null) {
                $matches = $matches && $t['to'] === $toState;
            }
            if ($field !== null) {
                $matches = $matches && $t['field'] === $field;
            }

            return $matches;
        });

        Assert::assertNull(
            $found,
            'Unexpected transition was recorded.'
        );

        return $this;
    }

    /**
     * Assert a specific number of transitions occurred.
     */
    public function assertTransitionCount(int $count, ?Model $model = null): self
    {
        $transitions = $this->recordedTransitions;

        if ($model !== null) {
            $transitions = $transitions->filter(function ($t) use ($model) {
                return $t['model_type'] === get_class($model)
                    && $t['model_id'] === $model->getKey();
            });
        }

        Assert::assertCount(
            $count,
            $transitions,
            "Expected {$count} transitions, got {$transitions->count()}."
        );

        return $this;
    }

    /**
     * Assert no transitions occurred.
     */
    public function assertNoTransitions(?Model $model = null): self
    {
        return $this->assertTransitionCount(0, $model);
    }

    /**
     * Assert a transition was successful.
     */
    public function assertTransitionSucceeded(
        Model $model,
        string $fromState,
        string $toState,
        ?string $field = null
    ): self {
        $found = $this->findTransition($model, $fromState, $toState, $field);

        Assert::assertNotNull(
            $found,
            "Transition from '{$fromState}' to '{$toState}' was not recorded."
        );

        Assert::assertTrue(
            $found['success'],
            "Expected transition from '{$fromState}' to '{$toState}' to succeed, but it failed."
        );

        return $this;
    }

    /**
     * Assert a transition failed.
     */
    public function assertTransitionFailed(
        Model $model,
        string $fromState,
        string $toState,
        ?string $field = null,
        ?string $expectedError = null
    ): self {
        $found = $this->findTransition($model, $fromState, $toState, $field);

        Assert::assertNotNull(
            $found,
            "Transition from '{$fromState}' to '{$toState}' was not recorded."
        );

        Assert::assertFalse(
            $found['success'],
            "Expected transition from '{$fromState}' to '{$toState}' to fail, but it succeeded."
        );

        if ($expectedError !== null) {
            Assert::assertStringContainsString(
                $expectedError,
                $found['error'] ?? '',
                "Expected error to contain '{$expectedError}'."
            );
        }

        return $this;
    }

    /**
     * Prevent a specific transition (will fail if attempted).
     */
    public function preventTransition(string $fromState, string $toState): self
    {
        $this->preventedTransitions[] = [$fromState, $toState];

        return $this;
    }

    /**
     * Prevent all transitions.
     */
    public function preventAllTransitions(): self
    {
        $this->preventAllTransitions = true;

        return $this;
    }

    /**
     * Force a transition to return a specific result.
     */
    public function forceTransitionResult(string $toState, TransitionResult $result): self
    {
        $this->forcedResults[$toState] = $result;

        return $this;
    }

    /**
     * Check if transition is prevented.
     */
    public function isTransitionPrevented(string $fromState, string $toState): bool
    {
        if ($this->preventAllTransitions) {
            return true;
        }

        return in_array([$fromState, $toState], $this->preventedTransitions, true);
    }

    /**
     * Get forced result for a transition.
     */
    public function getForcedResult(string $toState): ?TransitionResult
    {
        return $this->forcedResults[$toState] ?? null;
    }

    /**
     * Get all recorded transitions.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getRecordedTransitions(): Collection
    {
        return $this->recordedTransitions;
    }

    /**
     * Get transitions for a specific model.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getTransitionsFor(Model $model): Collection
    {
        return $this->recordedTransitions->filter(function ($t) use ($model) {
            return $t['model_type'] === get_class($model)
                && $t['model_id'] === $model->getKey();
        });
    }

    /**
     * Clear recorded transitions.
     */
    public function clear(): self
    {
        $this->recordedTransitions = collect();
        $this->preventedTransitions = [];
        $this->forcedResults = [];
        $this->preventAllTransitions = false;

        return $this;
    }

    /**
     * Find a specific transition in recorded transitions.
     *
     * @return array<string, mixed>|null
     */
    protected function findTransition(
        Model $model,
        string $fromState,
        string $toState,
        ?string $field = null
    ): ?array {
        return $this->recordedTransitions->first(function ($t) use ($model, $fromState, $toState, $field) {
            return $t['model_type'] === get_class($model)
                && $t['model_id'] === $model->getKey()
                && $t['from'] === $fromState
                && $t['to'] === $toState
                && ($field === null || $t['field'] === $field);
        });
    }
}
