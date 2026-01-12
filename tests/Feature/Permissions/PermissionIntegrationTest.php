<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Actions\CheckTransitionPermission;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\Facades\StateFlow;
use Hpwebdeveloper\LaravelStateflow\Permissions\CompositeChecker;
use Hpwebdeveloper\LaravelStateflow\Permissions\PermissionDenied;
use Hpwebdeveloper\LaravelStateflow\Permissions\PolicyBasedChecker;
use Hpwebdeveloper\LaravelStateflow\Permissions\RoleBasedChecker;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\User;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->createPostsTable();
    Post::resetStateRegistration();
    StateFlow::reset();
    Gate::policy(Post::class, null);
});

afterEach(function (): void {
    StateFlow::reset();
});

// ============================================================================
// CHECK TRANSITION PERMISSION ACTION TESTS
// ============================================================================

describe('CheckTransitionPermission Action', function (): void {

    it('returns allowed result when permission check passes', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        StateFlow::usePermissionChecker(new RoleBasedChecker);
        StateFlow::enableFeature('permissions');

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Review::class,
            toState: Published::class,
            performer: $user
        );

        $result = CheckTransitionPermission::run($data);

        expect($result->allowed)->toBeTrue();
        expect($result->denial)->toBeNull();
    });

    it('returns denial result when permission check fails', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        StateFlow::usePermissionChecker(new RoleBasedChecker);
        StateFlow::enableFeature('permissions');

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Review::class,
            toState: Published::class,
            performer: $user
        );

        $result = CheckTransitionPermission::run($data);

        expect($result->allowed)->toBeFalse();
        expect($result->denial)->toBeInstanceOf(PermissionDenied::class);
    });

    it('allows transition when no performer (system transition)', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        StateFlow::usePermissionChecker(new RoleBasedChecker);
        StateFlow::enableFeature('permissions');

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Review::class,
            toState: Published::class,
            performer: null
        );

        $result = CheckTransitionPermission::run($data);

        expect($result->allowed)->toBeTrue();
    });

    it('allows transition when permissions feature is disabled', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        StateFlow::usePermissionChecker(new RoleBasedChecker);
        // Explicitly disable permissions
        StateFlow::disableFeature('permissions');

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Review::class,
            toState: Published::class,
            performer: $user
        );

        $result = CheckTransitionPermission::run($data);

        expect($result->allowed)->toBeTrue();
    });

    it('uses static check helper', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        StateFlow::usePermissionChecker(new RoleBasedChecker);
        StateFlow::enableFeature('permissions');

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Review::class,
            toState: Published::class,
            performer: $user
        );

        $allowed = CheckTransitionPermission::check($data);

        expect($allowed)->toBeTrue();
    });

    it('uses static getDenial helper', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        StateFlow::usePermissionChecker(new RoleBasedChecker);
        StateFlow::enableFeature('permissions');

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Review::class,
            toState: Published::class,
            performer: $user
        );

        $denial = CheckTransitionPermission::getDenial($data);

        expect($denial)->toBeInstanceOf(PermissionDenied::class);
    });
});

// ============================================================================
// PERMISSION DENIED VALUE OBJECT TESTS
// ============================================================================

describe('PermissionDenied Value Object', function (): void {

    it('creates permission denied with all properties', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $denial = PermissionDenied::make(
            user: $user,
            model: $post,
            field: 'state',
            fromState: Review::class,
            toState: Published::class,
            reason: 'User not permitted',
            checkerClass: RoleBasedChecker::class
        );

        expect($denial->user)->toBe($user);
        expect($denial->model)->toBe($post);
        expect($denial->field)->toBe('state');
        // PermissionDenied resolves to state name, not class
        expect($denial->fromState)->toBe('review');
        expect($denial->toState)->toBe('published');
        expect($denial->reason)->toBe('User not permitted');
        expect($denial->checkerClass)->toBe(RoleBasedChecker::class);
    });

    it('converts to array for logging', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $denial = PermissionDenied::make(
            user: $user,
            model: $post,
            field: 'state',
            fromState: Review::class,
            toState: Published::class,
            reason: 'User not permitted',
            checkerClass: RoleBasedChecker::class
        );

        $array = $denial->toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveKey('from_state');
        expect($array)->toHaveKey('to_state');
        expect($array)->toHaveKey('reason');
        expect($array)->toHaveKey('checker');
    });

    it('provides human readable description', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $denial = PermissionDenied::make(
            user: $user,
            model: $post,
            field: 'state',
            fromState: Review::class,
            toState: Published::class,
            reason: 'User not permitted',
            checkerClass: RoleBasedChecker::class
        );

        $description = $denial->getDescription();

        expect($description)->toBeString();
        expect($description)->toContain('User not permitted');
    });
});

// ============================================================================
// STATEFLOW PERMISSION METHODS TESTS
// ============================================================================

describe('StateFlow Permission Methods', function (): void {

    it('can set permission checker', function (): void {
        $checker = new RoleBasedChecker;

        StateFlow::usePermissionChecker($checker);

        expect(StateFlow::getPermissionChecker())->toBe($checker);
    });

    it('can check if user can transition via StateFlow facade', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        StateFlow::usePermissionChecker(new RoleBasedChecker);
        StateFlow::enableFeature('permissions');

        $canTransition = StateFlow::userCanTransition(
            $user,
            $post,
            'state',
            Review::class,
            Published::class
        );

        expect($canTransition)->toBeTrue();
    });

    it('returns false when user cannot transition via facade', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        StateFlow::usePermissionChecker(new RoleBasedChecker);
        StateFlow::enableFeature('permissions');

        $canTransition = StateFlow::userCanTransition(
            $user,
            $post,
            'state',
            Review::class,
            Published::class
        );

        expect($canTransition)->toBeFalse();
    });
});

// ============================================================================
// FEATURE MANAGEMENT TESTS
// ============================================================================

describe('Feature Management', function (): void {

    it('can enable feature at runtime', function (): void {
        // Disable first to test enabling
        config(['laravel-stateflow.features.permissions' => false]);
        StateFlow::reset();

        StateFlow::enableFeature('permissions');

        expect(StateFlow::hasFeature('permissions'))->toBeTrue();
    });

    it('can disable feature at runtime', function (): void {
        StateFlow::enableFeature('permissions');
        StateFlow::disableFeature('permissions');

        expect(StateFlow::hasFeature('permissions'))->toBeFalse();
    });

    it('reset clears feature overrides', function (): void {
        config(['laravel-stateflow.features.permissions' => false]);
        StateFlow::enableFeature('permissions');
        StateFlow::reset();

        // Should revert to config value (false)
        expect(StateFlow::hasFeature('permissions'))->toBeFalse();
    });

    it('checks if permissions feature is enabled', function (): void {
        config(['laravel-stateflow.features.permissions' => false]);
        StateFlow::reset();

        expect(StateFlow::checksPermissions())->toBeFalse();

        StateFlow::enableFeature('permissions');

        expect(StateFlow::checksPermissions())->toBeTrue();
    });
});

// ============================================================================
// HAS STATES TRAIT PERMISSION TESTS
// ============================================================================

describe('HasStates Trait Permission Methods', function (): void {

    it('can check if user can transition to state', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        StateFlow::usePermissionChecker(new RoleBasedChecker);
        StateFlow::enableFeature('permissions');

        $canTransition = $post->userCanTransitionTo($user, Published::class, 'state');

        expect($canTransition)->toBeTrue();
    });

    it('denies when user cannot transition to state', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        StateFlow::usePermissionChecker(new RoleBasedChecker);
        StateFlow::enableFeature('permissions');

        $canTransition = $post->userCanTransitionTo($user, Published::class, 'state');

        expect($canTransition)->toBeFalse();
    });

    it('gets next states filtered by user permission', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        StateFlow::usePermissionChecker(new RoleBasedChecker);
        StateFlow::enableFeature('permissions');

        $nextStates = $post->getNextStatesForUser($user, 'state');

        expect($nextStates)->toContain(Review::class);
    });

    it('filters out states user cannot access', function (): void {
        $user = User::author();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        StateFlow::usePermissionChecker(new RoleBasedChecker);
        StateFlow::enableFeature('permissions');

        // Author cannot transition review -> published (only admin)
        $nextStates = $post->getNextStatesForUser($user, 'state');

        expect($nextStates)->not->toContain(Published::class);
    });

    it('gets next states data for user', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        StateFlow::usePermissionChecker(new RoleBasedChecker);
        StateFlow::enableFeature('permissions');

        $statesData = $post->getNextStatesDataForUser($user, 'state');

        expect($statesData)->toBeArray();
        expect($statesData)->not->toBeEmpty();
        expect($statesData[0]->name)->toBe('review');
        expect($statesData[0]->title)->toBe('Under Review');
        expect($statesData[0]->color)->toBeString();
    });

    it('returns all next states when permissions disabled', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        // Explicitly disable permissions feature
        StateFlow::disableFeature('permissions');

        // Permissions disabled - guest should get all next states
        $nextStates = $post->getNextStatesForUser($user, 'state');

        expect($nextStates)->toContain(Review::class);
    });
});

// ============================================================================
// CURRENT USER HELPERS TESTS
// ============================================================================

describe('Current User Helpers', function (): void {

    it('returns false when no authenticated user', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        StateFlow::usePermissionChecker(new RoleBasedChecker);
        StateFlow::enableFeature('permissions');

        $canTransition = $post->currentUserCanTransitionTo(Published::class, 'state');

        expect($canTransition)->toBeFalse();
    });

    it('returns empty array when no authenticated user', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        StateFlow::usePermissionChecker(new RoleBasedChecker);
        StateFlow::enableFeature('permissions');

        $nextStates = $post->getNextStatesForCurrentUser('state');

        expect($nextStates)->toBeEmpty();
    });
});

// ============================================================================
// COMPOSITE WITH INTEGRATION TESTS
// ============================================================================

describe('Composite Checker Integration', function (): void {

    it('works with all() composite in model context', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        Gate::define('transitionToPublished', fn ($user) => $user->role === 'admin');

        StateFlow::usePermissionChecker(CompositeChecker::all([
            new RoleBasedChecker,
            new PolicyBasedChecker,
        ]));
        StateFlow::enableFeature('permissions');

        $canTransition = $post->userCanTransitionTo($user, Published::class, 'state');

        expect($canTransition)->toBeTrue();
    });

    it('works with any() composite in model context', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        // Gate denies but role allows
        Gate::define('transitionToPublished', fn () => false);

        StateFlow::usePermissionChecker(CompositeChecker::any([
            new RoleBasedChecker,
            new PolicyBasedChecker,
        ]));
        StateFlow::enableFeature('permissions');

        $canTransition = $post->userCanTransitionTo($user, Published::class, 'state');

        expect($canTransition)->toBeTrue();
    });
});

// ============================================================================
// EDGE CASES
// ============================================================================

describe('Edge Cases', function (): void {

    it('allows transition when no permission checker configured', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        // Explicitly disable permissions feature
        StateFlow::disableFeature('permissions');

        $canTransition = $post->userCanTransitionTo($user, Published::class, 'state');

        // Should allow since transition is valid and permissions disabled
        expect($canTransition)->toBeTrue();
    });

    it('handles empty state transitions', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'published']);

        StateFlow::usePermissionChecker(new RoleBasedChecker);
        StateFlow::enableFeature('permissions');

        // Published has no next states
        $nextStates = $post->getNextStatesForUser($user, 'state');

        expect($nextStates)->toBeEmpty();
    });
});
