<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Actions;

use Hpwebdeveloper\LaravelStateflow\Actions\Concerns\AsAction;
use Hpwebdeveloper\LaravelStateflow\Actions\Concerns\HasTransitionHooks;
use Hpwebdeveloper\LaravelStateflow\Contracts\TransitionActionContract;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionContext;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Hpwebdeveloper\LaravelStateflow\Events\StateTransitioned;
use Hpwebdeveloper\LaravelStateflow\Events\StateTransitioning;
use Hpwebdeveloper\LaravelStateflow\Events\TransitionFailed;
use Hpwebdeveloper\LaravelStateflow\Exceptions\TransitionException;
use Hpwebdeveloper\LaravelStateflow\Models\StateHistory;
use Hpwebdeveloper\LaravelStateflow\StateFlow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Main action for executing state transitions.
 *
 * Following Invitex pattern using action traits.
 * Can be called as:
 * - Action: ExecuteTransition::run($transitionData)
 * - Instance: ExecuteTransition::make()->handle($transitionData)
 *
 * @example
 * // Direct call
 * $result = ExecuteTransition::run($transitionData);
 *
 * // Via make
 * $result = ExecuteTransition::make()->handle($transitionData);
 */
class ExecuteTransition implements TransitionActionContract
{
    use AsAction;
    use HasTransitionHooks;

    /**
     * Transition context for tracking lifecycle.
     */
    protected ?TransitionContext $context = null;

    /**
     * Custom transition action if specified.
     */
    protected ?TransitionActionContract $customAction = null;

    /**
     * Handle the transition.
     */
    public function handle(TransitionData $data): TransitionResult
    {
        // Create context for lifecycle tracking
        $this->context = TransitionContext::fromTransitionData($data);

        // Resolve custom transition action if defined
        $this->customAction = $this->resolveCustomAction($data);

        try {
            // Run within transaction if enabled
            if (config('laravel-stateflow.use_transactions', true)) {
                return DB::transaction(fn () => $this->executeTransition($data));
            }

            return $this->executeTransition($data);
        } catch (TransitionException $e) {
            $result = TransitionResult::failure($e->getMessage());
            $this->handleFailure($data, $result);

            throw $e;
        } catch (Throwable $e) {
            $result = TransitionResult::failure($e->getMessage());
            $this->handleFailure($data, $result);

            throw TransitionException::actionFailed(
                static::class,
                $e->getMessage()
            )->withContext($this->context);
        }
    }

    /**
     * Execute the transition with all hooks.
     */
    protected function executeTransition(TransitionData $data): TransitionResult
    {
        // 1. Validate
        $this->runValidation($data);
        $this->context->recordHook('validation');

        // 2. Authorize
        if (! $this->authorize($data)) {
            $role = $this->extractUserRole($data);
            throw TransitionException::unauthorized(
                $this->resolveStateName($data->toState),
                $role
            );
        }
        $this->context->recordHook('authorization');

        // 3. Fire StateTransitioning event (cancelable)
        if (StateFlow::hasFeature('events')) {
            $transitioningEvent = StateTransitioning::fromTransitionData($data);
            event($transitioningEvent);

            if ($transitioningEvent->isCancelled()) {
                throw TransitionException::cancelledByEvent(
                    $transitioningEvent->cancellationReason ?? 'Cancelled by listener'
                );
            }
            $this->context->recordHook('stateTransitioningEvent');
        }

        // 4. Before hook (can abort)
        if (! $this->runBeforeHook($data)) {
            throw TransitionException::abortedByHook('beforeTransition');
        }
        $this->context->recordHook('beforeTransition');

        // 5. Execute the actual transition
        if ($this->customAction) {
            $result = $this->customAction->handle($data);
        } else {
            $result = $this->performTransition($data);
        }

        // 6. After hook
        $this->runAfterHook($data, $result);
        $this->context->recordHook('afterTransition');

        // 7. Success/failure hooks
        if ($result->succeeded()) {
            $this->handleSuccess($data, $result);
        } else {
            $this->handleFailure($data, $result);
        }

        return $result;
    }

    /**
     * Perform the actual state change.
     */
    protected function performTransition(TransitionData $data): TransitionResult
    {
        $model = $data->model;
        $fromStateName = $this->resolveStateName($data->fromState);
        $toStateName = $this->resolveStateName($data->toState);

        $model->setAttribute($data->field, $toStateName);
        $model->save();

        return TransitionResult::success(
            model: $model,
            fromState: $fromStateName,
            toState: $toStateName,
            metadata: array_merge($data->metadata, [
                'performer_id' => $data->performer?->getAuthIdentifier(),
                'reason' => $data->reason,
                'context' => $this->context?->toArray() ?? [],
            ])
        );
    }

    /**
     * Authorize the transition.
     */
    public function authorize(TransitionData $data): bool
    {
        // Custom action authorization
        if ($this->customAction && method_exists($this->customAction, 'authorize')) {
            if (! $this->customAction->authorize($data)) {
                return false;
            }
        }

        // Check state permission (Phase 5 will enhance this)
        if (StateFlow::hasFeature('permissions') && $data->performer) {
            $toStateClass = $data->toState;

            if (class_exists($toStateClass) && method_exists($toStateClass, 'permittedRoles')) {
                $permittedRoles = $toStateClass::permittedRoles();

                if (! empty($permittedRoles)) {
                    $userRole = $this->extractUserRole($data);

                    if ($userRole === 'unknown') {
                        return false;
                    }

                    return in_array($userRole, $permittedRoles, true);
                }
            }
        }

        return true;
    }

    /**
     * Get validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Run validation.
     */
    protected function runValidation(TransitionData $data): void
    {
        // Get rules from custom action or self
        $rules = $this->customAction
            ? array_merge($this->customAction->rules(), $this->rules())
            : $this->rules();

        // Add validation rules from validationRules method
        if (method_exists($this, 'validationRules')) {
            $rules = array_merge($rules, $this->validationRules($data));
        }

        if ($this->customAction && method_exists($this->customAction, 'validationRules')) {
            $rules = array_merge($rules, $this->customAction->validationRules($data));
        }

        if (empty($rules)) {
            return;
        }

        $validator = Validator::make($data->metadata, $rules);

        if ($validator->fails()) {
            throw TransitionException::validationFailed($validator->errors()->toArray());
        }
    }

    /**
     * Run before hook.
     */
    protected function runBeforeHook(TransitionData $data): bool
    {
        // Custom action hook
        if ($this->customAction && method_exists($this->customAction, 'beforeTransition')) {
            if (! $this->customAction->beforeTransition($data)) {
                return false;
            }
        }

        // Self hook
        return $this->beforeTransition($data);
    }

    /**
     * Run after hook.
     */
    protected function runAfterHook(TransitionData $data, TransitionResult $result): void
    {
        // Custom action hook
        if ($this->customAction && method_exists($this->customAction, 'afterTransition')) {
            $this->customAction->afterTransition($data, $result);
        }

        // Self hook
        $this->afterTransition($data, $result);
    }

    /**
     * Handle successful transition.
     */
    protected function handleSuccess(TransitionData $data, TransitionResult $result): void
    {
        $this->context->recordHook('onSuccess');

        // Record history if enabled (Phase 7)
        if ($this->shouldRecordHistory()) {
            $this->recordHistory($data, $result);
            $this->context->recordHook('historyRecorded');
        }

        // Custom action hook
        if ($this->customAction && method_exists($this->customAction, 'onSuccess')) {
            $this->customAction->onSuccess($data, $result);
        }

        // Self hook
        $this->onSuccess($data, $result);

        // Fire StateTransitioned event if enabled
        if (StateFlow::hasFeature('events')) {
            event(StateTransitioned::fromTransitionData($data));
            $this->context->recordHook('stateTransitionedEvent');
        }
    }

    /**
     * Handle failed transition.
     */
    protected function handleFailure(TransitionData $data, TransitionResult $result): void
    {
        $this->context?->recordHook('onFailure');

        // Custom action hook
        if ($this->customAction && method_exists($this->customAction, 'onFailure')) {
            $this->customAction->onFailure($data, $result);
        }

        // Self hook
        $this->onFailure($data, $result);

        // Fire TransitionFailed event if enabled
        if (StateFlow::hasFeature('events')) {
            event(TransitionFailed::fromTransitionData(
                $data,
                $result->error ?? 'Unknown error',
                null,
                null
            ));
            $this->context?->recordHook('transitionFailedEvent');
        }
    }

    /**
     * Resolve custom transition action.
     */
    protected function resolveCustomAction(TransitionData $data): ?TransitionActionContract
    {
        // Get from global StateFlow registration
        $transitionClass = StateFlow::getTransitionClass($data->fromState, $data->toState);

        if ($transitionClass && class_exists($transitionClass)) {
            $action = app($transitionClass);

            if ($action instanceof TransitionActionContract) {
                return $action;
            }
        }

        // Get from model's state config
        $model = $data->model;

        if (method_exists($model, 'getStateConfig')) {
            $config = $model::getStateConfig($data->field);

            if ($config) {
                $configTransitionClass = $config->getTransitionClass(
                    $data->fromState,
                    $data->toState
                );

                if ($configTransitionClass && class_exists($configTransitionClass)) {
                    $action = app($configTransitionClass);

                    if ($action instanceof TransitionActionContract) {
                        return $action;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get the transition context.
     */
    public function getContext(): ?TransitionContext
    {
        return $this->context;
    }

    /**
     * Resolve state name from class or string.
     */
    protected function resolveStateName(string $state): string
    {
        if (class_exists($state) && method_exists($state, 'name')) {
            return $state::name();
        }

        return $state;
    }

    /**
     * Extract user role from transition data.
     */
    protected function extractUserRole(TransitionData $data): string
    {
        if (! $data->performer) {
            return 'unknown';
        }

        $role = null;

        // Try role property
        if (property_exists($data->performer, 'role')) {
            $role = $data->performer->role;
        }

        // Try getRole method
        if ($role === null && method_exists($data->performer, 'getRole')) {
            $role = $data->performer->getRole();
        }

        // Try getAttribute (Eloquent)
        if ($role === null && method_exists($data->performer, 'getAttribute')) {
            $role = $data->performer->getAttribute('role');
        }

        if ($role === null) {
            return 'unknown';
        }

        // Handle enum roles
        if (is_object($role) && enum_exists(get_class($role))) {
            return $role->value;
        }

        return (string) $role;
    }

    // -------------------------------------------------------------------------
    // History Recording (Phase 7)
    // -------------------------------------------------------------------------

    /**
     * Check if history recording should occur.
     */
    protected function shouldRecordHistory(): bool
    {
        return config('laravel-stateflow.history.enabled', true)
            && config('laravel-stateflow.features.history', true);
    }

    /**
     * Record the state transition to history.
     */
    protected function recordHistory(TransitionData $data, TransitionResult $result): ?StateHistory
    {
        return RecordStateTransition::make()->fromTransitionData($data, $result);
    }
}
