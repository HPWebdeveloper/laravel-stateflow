<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\StateFlow;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\User;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\PostState;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Rejected;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;
use Hpwebdeveloper\LaravelStateflow\Validation\Rules\ValidTransition;
use Hpwebdeveloper\LaravelStateflow\Validation\TransitionRule;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->createPostsTable();
    $this->createUsersTable();
    Post::resetStateRegistration();

    // Register states for PostState base class
    StateFlow::registerStates(PostState::class, [
        Draft::class,
        Review::class,
        Published::class,
        Rejected::class,
    ]);

    // Enable permissions feature
    config()->set('laravel-stateflow.features.permissions', true);
});

describe('TransitionRule - Basic Validation', function () {
    /**
     * Scenario: TransitionRule validates state change is allowed per state machine
     * Setup: Draft post attempting transition to review (allowed path)
     * Assertions: Validation passes for permitted transition in state graph
     */
    it('passes for valid transition', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'review'],
            ['state' => [TransitionRule::for($post)]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails for invalid transition', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        // Cannot go directly from draft to published
        $validator = Validator::make(
            ['state' => 'published'],
            ['state' => [TransitionRule::for($post)]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toContain('Cannot transition');
    });

    /**
     * Scenario: Null target state values are rejected (transition requires destination)
     * Setup: Pass null as new state value
     * Assertions: Validation fails with 'required' error
     */
    it('fails for null value', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => null],
            ['state' => [TransitionRule::for($post)]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toContain('required');
    });

    /**
     * Scenario: TransitionRule enforces string type for state names
     * Setup: Pass integer 123 instead of state name string
     * Assertions: Validation fails with 'string' type error
     */
    it('fails for non-string value', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 123],
            ['state' => [TransitionRule::for($post)]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toContain('string');
    });
});

describe('TransitionRule - Same State Handling', function () {
    /**
     * Scenario: By default, transitioning to current state is rejected (no-op prevention)
     * Setup: Draft post attempting transition to 'draft' (already current)
     * Assertions: Validation fails with 'already' message (state unchanged)
     */
    it('fails when transitioning to same state by default', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'draft'],
            ['state' => [TransitionRule::for($post)]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toContain('already');
    });

    /**
     * Scenario: allowSameState() permits idempotent state transitions (re-apply allowed)
     * Setup: Call allowSameState(), attempt transition to current state
     * Assertions: Validation passes (useful for re-triggering hooks/events)
     */
    it('passes when transitioning to same state with allowSameState', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'draft'],
            ['state' => [TransitionRule::for($post)->allowSameState()]]
        );

        expect($validator->passes())->toBeTrue();
    });
});

describe('TransitionRule - Field Specification', function () {
    /**
     * Scenario: field() method specifies which model field to validate against
     * Setup: Explicitly set field('state') for clarity or multi-state models
     * Assertions: Validation works with specified field (defaults to 'state')
     */
    it('can specify a custom field', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'review'],
            ['state' => [TransitionRule::for($post)->field('state')]]
        );

        expect($validator->passes())->toBeTrue();
    });
});

describe('TransitionRule - Permission Checking', function () {
    it('validates with permission checking enabled', function () {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'admin',
        ]);
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'review'],
            ['state' => [TransitionRule::for($post)->checkPermissions($user)]]
        );

        // Admin should have permission
        expect($validator->passes())->toBeTrue();
    });

    it('fails when user lacks permission', function () {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer',
        ]);
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'review'],
            ['state' => [TransitionRule::for($post)->checkPermissions($user)]]
        );

        // Viewer doesn't have permission to transition draft -> review
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toContain("don't have permission");
    });

    it('fails when no user is authenticated and checking permissions', function () {
        // Ensure no authenticated user
        auth()->logout();

        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'review'],
            ['state' => [TransitionRule::for($post)->checkPermissions()]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toContain('Authentication required');
    });
});

describe('TransitionRule - Custom Message', function () {
    it('can use custom error message', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'published'],
            ['state' => [TransitionRule::for($post)->withMessage('Custom transition error')]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toBe('Custom transition error');
    });
});

describe('TransitionRule - Static Factory', function () {
    it('can create rule using static for method', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $rule = TransitionRule::for($post);

        expect($rule)->toBeInstanceOf(TransitionRule::class);
    });

    it('can chain methods fluently', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $rule = TransitionRule::for($post)
            ->field('state')
            ->allowSameState()
            ->withMessage('Custom message');

        expect($rule)->toBeInstanceOf(TransitionRule::class);
    });
});

describe('TransitionRule - Multiple Transition Paths', function () {
    it('validates multi-step transition path', function () {
        // Start at draft
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        // draft -> review should pass
        $validator = Validator::make(
            ['state' => 'review'],
            ['state' => [TransitionRule::for($post)]]
        );
        expect($validator->passes())->toBeTrue();

        // Update post state
        $post->update(['state' => 'review']);
        $post->refresh();

        // review -> published should pass
        $validator = Validator::make(
            ['state' => 'published'],
            ['state' => [TransitionRule::for($post)]]
        );
        expect($validator->passes())->toBeTrue();

        // review -> rejected should also pass
        $validator = Validator::make(
            ['state' => 'rejected'],
            ['state' => [TransitionRule::for($post)]]
        );
        expect($validator->passes())->toBeTrue();
    });
});

describe('ValidTransition Rule - Basic Validation', function () {
    it('passes for valid transition', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'review'],
            ['state' => [new ValidTransition($post)]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails for invalid transition', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'published'],
            ['state' => [new ValidTransition($post)]]
        );

        expect($validator->fails())->toBeTrue();
    });

    it('can allow same state', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'draft'],
            ['state' => [(new ValidTransition($post))->allowSameState()]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('can check permissions', function () {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'review'],
            ['state' => [(new ValidTransition($post))->withPermissions($user)]]
        );

        expect($validator->passes())->toBeTrue();
    });
});
