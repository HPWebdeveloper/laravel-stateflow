<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\StateFlow;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\PostState;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Rejected;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;
use Hpwebdeveloper\LaravelStateflow\Validation\Rules\ValidState;
use Hpwebdeveloper\LaravelStateflow\Validation\StateRule;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->createPostsTable();
    Post::resetStateRegistration();

    // Register states for PostState base class
    StateFlow::registerStates(PostState::class, [
        Draft::class,
        Review::class,
        Published::class,
        Rejected::class,
    ]);
});

describe('StateRule - Basic Validation', function () {
    /**
     * Scenario: StateRule validates that input matches registered state name
     * Setup: Validate 'draft' against PostState class registry
     * Assertions: Validation passes for state in registry (basic happy path)
     */
    it('passes for valid state', function () {
        $validator = Validator::make(
            ['state' => 'draft'],
            ['state' => [StateRule::for(PostState::class)]]
        );

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Scenario: All registered states pass validation (complete coverage test)
     * Setup: Iterate through draft, review, published, rejected
     * Assertions: Each state name validates successfully against registry
     */
    it('passes for all registered states', function () {
        $states = ['draft', 'review', 'published', 'rejected'];

        foreach ($states as $state) {
            $validator = Validator::make(
                ['state' => $state],
                ['state' => [StateRule::for(PostState::class)]]
            );

            expect($validator->passes())->toBeTrue("State '{$state}' should be valid");
        }
    });

    /**
     * Scenario: StateRule rejects input not in state registry (security check)
     * Setup: Pass 'invalid_state' not registered in PostState
     * Assertions: Validation fails with 'valid state' error message for user
     */
    it('fails for invalid state', function () {
        $validator = Validator::make(
            ['state' => 'invalid_state'],
            ['state' => [StateRule::for(PostState::class)]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toContain('valid state');
    });

    /**
     * Scenario: Empty string values are rejected (prevent default state bypass)
     * Setup: Validate empty string ''
     * Assertions: Validation fails (empty not treated as valid state)
     */
    it('fails for empty string', function () {
        $validator = Validator::make(
            ['state' => ''],
            ['state' => [StateRule::for(PostState::class)]]
        );

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Scenario: StateRule enforces string type requirement (type safety)
     * Setup: Pass integer 123 instead of string state name
     * Assertions: Validation fails with 'string' type error
     */
    it('fails for non-string value', function () {
        $validator = Validator::make(
            ['state' => 123],
            ['state' => [StateRule::for(PostState::class)]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toContain('string');
    });

    /**
     * Scenario: Error messages list valid options for user guidance (UX improvement)
     * Setup: Trigger validation error with invalid state
     * Assertions: Error includes draft, review, published names (shows valid choices)
     */
    it('includes valid states in error message', function () {
        $validator = Validator::make(
            ['state' => 'invalid'],
            ['state' => [StateRule::for(PostState::class)]]
        );

        $message = $validator->errors()->first('state');
        expect($message)->toContain('draft')
            ->and($message)->toContain('review')
            ->and($message)->toContain('published');
    });
});

describe('StateRule - Nullable', function () {
    /**
     * Scenario: By default, null values are rejected (state required unless opted-in)
     * Setup: Pass null value without nullable() modifier
     * Assertions: Validation fails with 'required' error
     */
    it('fails for null when not nullable', function () {
        $validator = Validator::make(
            ['state' => null],
            ['state' => [StateRule::for(PostState::class)]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toContain('required');
    });

    /**
     * Scenario: nullable() modifier allows null values (optional state fields)
     * Setup: Call StateRule::for()->nullable(), pass null value
     * Assertions: Validation passes (null explicitly permitted)
     */
    it('passes for null when nullable', function () {
        $validator = Validator::make(
            ['state' => null],
            ['state' => [StateRule::for(PostState::class)->nullable()]]
        );

        expect($validator->passes())->toBeTrue();
    });
});

describe('StateRule - Only Filter', function () {
    /**
     * Scenario: only() restricts validation to subset of registered states (permission filtering)
     * Setup: Allow only 'draft' and 'review', validate 'draft'
     * Assertions: Validation passes for whitelisted state
     */
    it('passes when state is in only list', function () {
        $validator = Validator::make(
            ['state' => 'draft'],
            ['state' => [StateRule::for(PostState::class)->only(['draft', 'review'])]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails when state is not in only list', function () {
        $validator = Validator::make(
            ['state' => 'published'],
            ['state' => [StateRule::for(PostState::class)->only(['draft', 'review'])]]
        );

        expect($validator->fails())->toBeTrue();
    });
});

describe('StateRule - Except Filter', function () {
    it('passes when state is not in except list', function () {
        $validator = Validator::make(
            ['state' => 'draft'],
            ['state' => [StateRule::for(PostState::class)->except(['rejected'])]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails when state is in except list', function () {
        $validator = Validator::make(
            ['state' => 'rejected'],
            ['state' => [StateRule::for(PostState::class)->except(['rejected'])]]
        );

        expect($validator->fails())->toBeTrue();
    });
});

describe('StateRule - Static Factory', function () {
    it('can create rule using static for method', function () {
        $rule = StateRule::for(PostState::class);

        expect($rule)->toBeInstanceOf(StateRule::class);
    });

    it('can chain methods fluently', function () {
        $rule = StateRule::for(PostState::class)
            ->nullable()
            ->only(['draft', 'review'])
            ->except(['rejected']);

        expect($rule)->toBeInstanceOf(StateRule::class);
    });
});

describe('ValidState Rule - Basic Validation', function () {
    it('passes for valid state', function () {
        $validator = Validator::make(
            ['state' => 'draft'],
            ['state' => [new ValidState(PostState::class)]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails for invalid state', function () {
        $validator = Validator::make(
            ['state' => 'invalid'],
            ['state' => [new ValidState(PostState::class)]]
        );

        expect($validator->fails())->toBeTrue();
    });
});

describe('ValidState Rule - Filters', function () {
    it('filters with only', function () {
        $validator = Validator::make(
            ['state' => 'published'],
            ['state' => [(new ValidState(PostState::class))->only(['draft', 'review'])]]
        );

        expect($validator->fails())->toBeTrue();
    });

    it('filters with except', function () {
        $validator = Validator::make(
            ['state' => 'rejected'],
            ['state' => [(new ValidState(PostState::class))->except(['rejected'])]]
        );

        expect($validator->fails())->toBeTrue();
    });

    it('can hide valid states from error message', function () {
        $validator = Validator::make(
            ['state' => 'invalid'],
            ['state' => [(new ValidState(PostState::class))->hideValidStates()]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toBe('The state is not a valid state.');
    });
});
