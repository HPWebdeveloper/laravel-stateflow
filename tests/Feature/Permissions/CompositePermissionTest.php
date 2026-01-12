<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Permissions\CompositeChecker;
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
    Gate::policy(Post::class, null);
});

// ============================================================================
// COMPOSITE CHECKER - ALL (AND) LOGIC TESTS
// ============================================================================

describe('CompositeChecker::all() - AND Logic', function (): void {

    it('allows when all checkers allow', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        // Gate allows admin
        Gate::define('transitionToPublished', fn ($user, $model) => $user->role === 'admin');

        $checker = CompositeChecker::all([
            new RoleBasedChecker,
            new PolicyBasedChecker,
        ]);

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeTrue();
    });

    it('denies when role checker denies', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        // Gate allows everyone
        Gate::define('transitionToPublished', fn () => true);

        $checker = CompositeChecker::all([
            new RoleBasedChecker,
            new PolicyBasedChecker,
        ]);

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeFalse();
    });

    it('denies when policy checker denies', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        // Gate denies everyone
        Gate::define('transitionToPublished', fn () => false);

        $checker = CompositeChecker::all([
            new RoleBasedChecker,
            new PolicyBasedChecker,
        ]);

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeFalse();
    });

    it('denies when both checkers deny', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        Gate::define('transitionToPublished', fn () => false);

        $checker = CompositeChecker::all([
            new RoleBasedChecker,
            new PolicyBasedChecker,
        ]);

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeFalse();
    });
});

// ============================================================================
// COMPOSITE CHECKER - ANY (OR) LOGIC TESTS
// ============================================================================

describe('CompositeChecker::any() - OR Logic', function (): void {

    it('allows when all checkers allow', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        Gate::define('transitionToPublished', fn ($user, $model) => $user->role === 'admin');

        $checker = CompositeChecker::any([
            new RoleBasedChecker,
            new PolicyBasedChecker,
        ]);

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeTrue();
    });

    it('allows when only role checker allows', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        // Gate denies everyone
        Gate::define('transitionToPublished', fn () => false);

        $checker = CompositeChecker::any([
            new RoleBasedChecker,
            new PolicyBasedChecker,
        ]);

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeTrue();
    });

    it('allows when only policy checker allows', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        // Gate allows everyone
        Gate::define('transitionToPublished', fn () => true);

        $checker = CompositeChecker::any([
            new RoleBasedChecker,
            new PolicyBasedChecker,
        ]);

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeTrue();
    });

    it('denies when both checkers deny', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        Gate::define('transitionToPublished', fn () => false);

        $checker = CompositeChecker::any([
            new RoleBasedChecker,
            new PolicyBasedChecker,
        ]);

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeFalse();
    });
});

// ============================================================================
// ADD CHECKER TESTS
// ============================================================================

describe('Adding Checkers', function (): void {

    it('can add checker after creation', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $checker = CompositeChecker::all([])
            ->add(new RoleBasedChecker);

        expect($checker->getCheckers())->toHaveCount(1);

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeTrue();
    });

    it('returns empty checkers array when none added', function (): void {
        $checker = CompositeChecker::all([]);

        expect($checker->getCheckers())->toBeEmpty();
    });

    it('allows transition when no checkers configured (vacuously true)', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $checker = CompositeChecker::all([]);

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeTrue();
    });
});

// ============================================================================
// DENIAL REASON TESTS
// ============================================================================

describe('Denial Reasons', function (): void {

    it('provides first denial reason from failing checker in ALL mode', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $checker = CompositeChecker::all([
            new RoleBasedChecker,
            new PolicyBasedChecker,
        ]);

        $reason = $checker->getDenialReason(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($reason)->not->toBeNull();
        expect($reason)->toContain('not permitted');
    });

    it('returns null when all checkers allow', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $checker = CompositeChecker::all([
            new RoleBasedChecker,
        ]);

        $reason = $checker->getDenialReason(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($reason)->toBeNull();
    });

    it('combines denial reasons in ANY mode', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        Gate::define('transitionToPublished', fn () => false);

        $checker = CompositeChecker::any([
            new RoleBasedChecker,
            new PolicyBasedChecker,
        ]);

        $reason = $checker->getDenialReason(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($reason)->not->toBeNull();
    });
});

// ============================================================================
// USER ROLE TESTS
// ============================================================================

describe('User Role', function (): void {

    it('returns role from first checker that has a role', function (): void {
        $user = User::admin();

        $checker = CompositeChecker::all([
            new RoleBasedChecker,
            new PolicyBasedChecker,
        ]);

        expect($checker->getUserRole($user))->toBe('admin');
    });

    it('returns null when no checkers have roles', function (): void {
        $user = User::admin();

        $checker = CompositeChecker::all([
            new PolicyBasedChecker,
        ]);

        expect($checker->getUserRole($user))->toBeNull();
    });

    it('returns null when no checkers configured', function (): void {
        $user = User::admin();

        $checker = CompositeChecker::all([]);

        expect($checker->getUserRole($user))->toBeNull();
    });
});

// ============================================================================
// NESTED COMPOSITE TESTS
// ============================================================================

describe('Nested Composites', function (): void {

    it('supports nested composite checkers', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        Gate::define('transitionToPublished', fn () => true);

        $nestedChecker = CompositeChecker::all([
            new RoleBasedChecker,
        ]);

        $parentChecker = CompositeChecker::any([
            $nestedChecker,
            new PolicyBasedChecker,
        ]);

        $canTransition = $parentChecker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeTrue();
    });

    it('returns checkers count including nested', function (): void {
        $checker = CompositeChecker::all([
            new RoleBasedChecker,
            CompositeChecker::any([
                new PolicyBasedChecker,
            ]),
        ]);

        expect($checker->getCheckers())->toHaveCount(2);
    });
});
