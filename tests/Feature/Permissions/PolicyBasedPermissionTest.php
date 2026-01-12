<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Permissions\PolicyBasedChecker;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\User;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->createPostsTable();
    Post::resetStateRegistration();
    Gate::policy(Post::class, null); // Reset any registered policies
});

// ============================================================================
// POLICY-BASED CHECKER TESTS
// ============================================================================

describe('PolicyBasedChecker', function (): void {

    /**
     * Scenario: Laravel Gate policy authorizes transition based on custom business logic
     * Setup: Define transitionToPublished gate that checks user role; admin user attempts transition
     * Assertions: Policy returns true because user->role matches 'admin', so transition is allowed
     */
    it('allows transition when gate ability is defined and returns true', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        // Define gate ability for this transition
        Gate::define('transitionToPublished', fn ($user, $model) => $user->role === 'admin');

        $checker = new PolicyBasedChecker;

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeTrue();
    });

    /**
     * Scenario: Gate policy denies transition when business logic conditions aren't met
     * Setup: Gate requires admin role; author user (non-admin) attempts transition
     * Assertions: Policy returns false, blocking author from publishing content
     */
    it('denies transition when gate ability returns false', function (): void {
        $user = User::author();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        // Define gate ability for this transition
        Gate::define('transitionToPublished', fn ($user, $model) => $user->role === 'admin');

        $checker = new PolicyBasedChecker;

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeFalse();
    });

    /**
     * Scenario: When no Gate ability is registered, PolicyBasedChecker allows by default (opt-in security)
     * Setup: No gate defined for transitionToPublished; guest user attempts transition
     * Assertions: Allowed because undefined gates fallthrough without restriction
     */
    it('allows transition when no gate ability is defined (fallthrough)', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $checker = new PolicyBasedChecker;

        // No gate defined - should allow by default
        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeTrue();
    });

    /**
     * Scenario: PolicyBasedChecker supports custom gate ability naming prefixes
     * Setup: Define gate with custom prefix 'stateflow_Published'; create checker with matching prefix
     * Assertions: Checker finds and evaluates the correctly prefixed gate ability
     */
    it('uses custom ability prefix', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        // Define gate with custom prefix
        Gate::define('stateflow_Published', fn ($user, $model) => $user->role === 'admin');

        $checker = new PolicyBasedChecker('stateflow_');

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
// COMPLEX GATE LOGIC TESTS
// ============================================================================

describe('Complex Gate Logic', function (): void {

    it('can check model properties in gate', function (): void {
        $user = User::author();

        // Author can only publish their own posts
        Gate::define('transitionToPublished', function ($user, $model) {
            return $model->author_id === $user->id;
        });

        $ownPost = Post::create(['title' => 'Own Post', 'state' => 'review']);
        // Simulate ownership
        $ownPost->author_id = $user->id;

        $checker = new PolicyBasedChecker;

        $canTransition = $checker->canTransition(
            $ownPost,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeTrue();
    });

    it('denies when model properties check fails', function (): void {
        $user = User::author();
        $user->id = 1;

        // Author can only publish their own posts
        Gate::define('transitionToPublished', function ($user, $model) {
            return $model->author_id === $user->id;
        });

        $otherPost = Post::create(['title' => 'Other Post', 'state' => 'review']);
        $otherPost->author_id = 999; // Different author

        $checker = new PolicyBasedChecker;

        $canTransition = $checker->canTransition(
            $otherPost,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeFalse();
    });
});

// ============================================================================
// DENIAL REASON TESTS
// ============================================================================

describe('Denial Reasons', function (): void {

    it('provides denial reason when gate denies', function (): void {
        $user = User::author();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        Gate::define('transitionToPublished', fn ($user, $model) => false);

        $checker = new PolicyBasedChecker;

        $reason = $checker->getDenialReason(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($reason)->toContain('transitionToPublished');
        expect($reason)->toContain('denies');
    });

    it('returns null when gate allows', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        Gate::define('transitionToPublished', fn ($user, $model) => true);

        $checker = new PolicyBasedChecker;

        $reason = $checker->getDenialReason(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($reason)->toBeNull();
    });

    it('returns null when no gate is defined', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $checker = new PolicyBasedChecker;

        $reason = $checker->getDenialReason(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($reason)->toBeNull();
    });
});

// ============================================================================
// USER ROLE TESTS
// ============================================================================

describe('User Role', function (): void {

    it('returns null for getUserRole since policy-based does not use roles', function (): void {
        $user = User::admin();
        $checker = new PolicyBasedChecker;

        expect($checker->getUserRole($user))->toBeNull();
    });
});

// ============================================================================
// NULL USER TESTS
// ============================================================================

describe('Null User', function (): void {

    it('denies when user is null and gate is defined', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        Gate::define('transitionToPublished', fn ($user, $model) => $user !== null);

        $checker = new PolicyBasedChecker;

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            null
        );

        expect($canTransition)->toBeFalse();
    });

    it('denies when user is null even if no gate defined', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $checker = new PolicyBasedChecker;

        // PolicyBasedChecker returns false when user is null
        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            null
        );

        expect($canTransition)->toBeFalse();
    });
});
