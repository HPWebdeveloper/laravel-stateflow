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
use Hpwebdeveloper\LaravelStateflow\Validation\StateRule;
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

    config()->set('laravel-stateflow.features.permissions', true);
});

describe('Form Request Integration - Combined Rules', function () {
    /**
     * Scenario: StateRule and TransitionRule work together in validation chain
     * Setup: Apply both rules plus required/string - validates format, registry, and transition
     * Assertions: All rules pass for valid state change (comprehensive validation)
     */
    it('validates state is valid and transition is allowed', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'review'],
            [
                'state' => [
                    'required',
                    'string',
                    StateRule::for(PostState::class),
                    TransitionRule::for($post),
                ],
            ]
        );

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Scenario: StateRule fails first before TransitionRule (optimization shortcut)
     * Setup: Pass non-existent state through both validators
     * Assertions: Error is 'valid state' not 'Cannot transition' (stops at first failure)
     */
    it('fails first on state validation if invalid state', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'nonexistent'],
            [
                'state' => [
                    'required',
                    'string',
                    StateRule::for(PostState::class),
                    TransitionRule::for($post),
                ],
            ]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toContain('valid state');
    });

    /**
     * Scenario: TransitionRule runs only after StateRule passes (layered validation)
     * Setup: Pass 'published' which exists but isn't allowed from draft
     * Assertions: Error is 'Cannot transition' not 'valid state' (state valid, path invalid)
     */
    it('fails on transition if state is valid but transition is not', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'published'],
            [
                'state' => [
                    'required',
                    'string',
                    StateRule::for(PostState::class),
                    TransitionRule::for($post),
                ],
            ]
        );

        expect($validator->fails())->toBeTrue();
        // State is valid, but transition is not
        expect($validator->errors()->first('state'))->toContain('Cannot transition');
    });
});

describe('Form Request Integration - With Additional Fields', function () {
    /**
     * Scenario: State validation integrates with standard Laravel validation rules
     * Setup: Validate both state (StateRule+TransitionRule) and reason (string max)
     * Assertions: All fields validate together (state machine + form data)
     */
    it('validates state along with other fields', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            [
                'state' => 'review',
                'reason' => 'Ready for review',
            ],
            [
                'state' => [
                    'required',
                    StateRule::for(PostState::class),
                    TransitionRule::for($post),
                ],
                'reason' => ['nullable', 'string', 'max:500'],
            ]
        );

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Scenario: Multiple validation errors accumulate across all fields
     * Setup: Invalid transition + overly long reason string (2 violations)
     * Assertions: Errors for both state and reason fields (complete feedback)
     */
    it('validates both state and reason with errors', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            [
                'state' => 'published',
                'reason' => str_repeat('x', 600), // Too long
            ],
            [
                'state' => [
                    'required',
                    StateRule::for(PostState::class),
                    TransitionRule::for($post),
                ],
                'reason' => ['nullable', 'string', 'max:500'],
            ]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('state'))->toBeTrue();
        expect($validator->errors()->has('reason'))->toBeTrue();
    });
});

describe('Form Request Integration - With Permissions', function () {
    it('validates with user permissions', function () {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'review'],
            [
                'state' => [
                    'required',
                    StateRule::for(PostState::class),
                    TransitionRule::for($post)->checkPermissions($user),
                ],
            ]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails when user lacks permission', function () {
        $user = User::create([
            'name' => 'Viewer User',
            'email' => 'viewer@example.com',
            'role' => 'viewer',
        ]);
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'review'],
            [
                'state' => [
                    'required',
                    StateRule::for(PostState::class),
                    TransitionRule::for($post)->checkPermissions($user),
                ],
            ]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toContain("don't have permission");
    });
});

describe('Form Request Integration - Bail on First Failure', function () {
    it('stops validation on first failure with bail', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'nonexistent'],
            [
                'state' => [
                    'bail',
                    'required',
                    'string',
                    StateRule::for(PostState::class),
                    TransitionRule::for($post),
                ],
            ]
        );

        expect($validator->fails())->toBeTrue();
        // Only one error should be present due to bail
        expect($validator->errors()->get('state'))->toHaveCount(1);
    });
});

describe('Form Request Integration - Custom Messages', function () {
    it('can use custom validation messages', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'published'],
            [
                'state' => [
                    'required',
                    StateRule::for(PostState::class),
                    TransitionRule::for($post)->withMessage('This state change is not allowed.'),
                ],
            ],
            [
                'state.required' => 'Please select a state.',
            ]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toBe('This state change is not allowed.');
    });
});

describe('Form Request Integration - Filtered States', function () {
    it('validates with only allowed states', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'review'],
            [
                'state' => [
                    'required',
                    StateRule::for(PostState::class)->only(['draft', 'review']),
                    TransitionRule::for($post),
                ],
            ]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails for valid state that is excluded', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'review']);

        $validator = Validator::make(
            ['state' => 'rejected'],
            [
                'state' => [
                    'required',
                    StateRule::for(PostState::class)->except(['rejected']),
                    TransitionRule::for($post),
                ],
            ]
        );

        expect($validator->fails())->toBeTrue();
    });
});

describe('Form Request Integration - Same State Handling', function () {
    it('fails by default when transitioning to same state', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'draft'],
            [
                'state' => [
                    'required',
                    StateRule::for(PostState::class),
                    TransitionRule::for($post),
                ],
            ]
        );

        expect($validator->fails())->toBeTrue();
    });

    it('passes when same state is allowed', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $validator = Validator::make(
            ['state' => 'draft'],
            [
                'state' => [
                    'required',
                    StateRule::for(PostState::class),
                    TransitionRule::for($post)->allowSameState(),
                ],
            ]
        );

        expect($validator->passes())->toBeTrue();
    });
});
