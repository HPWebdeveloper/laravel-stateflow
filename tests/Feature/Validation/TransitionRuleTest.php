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

    it('fails for null value', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => null],
            ['state' => [TransitionRule::for($post)]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toContain('required');
    });

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
    it('fails when transitioning to same state by default', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'draft'],
            ['state' => [TransitionRule::for($post)]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toContain('already');
    });

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
