<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Permissions\RoleBasedChecker;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\User;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;

beforeEach(function (): void {
    $this->createPostsTable();
    Post::resetStateRegistration();
});

// ============================================================================
// ROLE-BASED CHECKER TESTS
// ============================================================================

describe('RoleBasedChecker', function (): void {

    /**
     * Scenario: User with admin role can publish content (Published state requires admin role)
     * Setup: Create admin user and post in review state; Published state has PERMITTED_ROLES=['admin']
     * Assertions: canTransition() returns true because user's role matches required role
     */
    it('allows transition when user has permitted role', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $checker = new RoleBasedChecker;

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeTrue();
    });

    /**
     * Scenario: Regular author cannot publish content (lacks admin privilege for Published state)
     * Setup: Create author user attempting review->published transition (requires admin)
     * Assertions: canTransition() returns false due to insufficient role permissions
     */
    it('denies transition when user lacks permitted role', function (): void {
        $user = User::author();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $checker = new RoleBasedChecker;

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeFalse();
    });

    /**
     * Scenario: States can allow multiple roles; editor can submit for review alongside admin
     * Setup: Create editor user with draft post; Review state permits both admin and editor roles
     * Assertions: Editor is authorized because they're in the PERMITTED_ROLES list for Review
     */
    it('allows transition when editor has permitted role', function (): void {
        $user = User::editor();
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $checker = new RoleBasedChecker;

        // Draft -> Review allows ['admin', 'editor']
        $canTransition = $checker->canTransition(
            $post,
            Draft::class,
            Review::class,
            $user
        );

        expect($canTransition)->toBeTrue();
    });

    /**
     * Scenario: Users without a role attribute cannot pass role-based checks
     * Setup: Create bare User object with no role property attempting transition
     * Assertions: Denied because RoleBasedChecker cannot extract user role (returns null)
     */
    it('denies transition when user has no role attribute', function (): void {
        $user = new User;
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $checker = new RoleBasedChecker;

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeFalse();
    });

    /**
     * Scenario: States with empty PERMITTED_ROLES array allow any user to transition
     * Setup: Create test state returning empty array from permittedRoles(); guest user attempts transition
     * Assertions: Allowed because empty permissions means no restrictions (public state)
     */
    it('allows transition when state has no permitted roles', function (): void {
        // Create a test state class with no permitted roles
        $testState = new class
        {
            public static function permittedRoles(): array
            {
                return [];
            }
        };

        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $checker = new RoleBasedChecker;

        // Test with the anonymous class - when no roles defined, should allow
        $canTransition = $checker->canTransition(
            $post,
            Draft::class,
            get_class($testState),
            $user
        );

        expect($canTransition)->toBeTrue();
    });
});

// ============================================================================
// DENIAL REASON TESTS
// ============================================================================

describe('Denial Reasons', function (): void {

    it('provides denial reason with user role', function (): void {
        $user = User::author();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $checker = new RoleBasedChecker;

        $reason = $checker->getDenialReason(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($reason)->toContain('author');
        expect($reason)->toContain('not permitted');
    });

    it('returns null when transition is allowed', function (): void {
        $user = User::admin();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $checker = new RoleBasedChecker;

        $reason = $checker->getDenialReason(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($reason)->toBeNull();
    });

    it('shows permitted roles in denial reason', function (): void {
        $user = User::guest();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $checker = new RoleBasedChecker;

        $reason = $checker->getDenialReason(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($reason)->toContain('admin');
    });
});

// ============================================================================
// USER ROLE EXTRACTION TESTS
// ============================================================================

describe('User Role Extraction', function (): void {

    it('gets user role from default attribute', function (): void {
        $user = User::admin();
        $checker = new RoleBasedChecker;

        expect($checker->getUserRole($user))->toBe('admin');
    });

    it('gets user role from custom attribute', function (): void {
        $user = new User(['user_type' => 'moderator']);
        $checker = new RoleBasedChecker('user_type');

        expect($checker->getUserRole($user))->toBe('moderator');
    });

    it('returns null for user without role attribute', function (): void {
        $user = new User;
        $checker = new RoleBasedChecker;

        expect($checker->getUserRole($user))->toBeNull();
    });

    it('returns null for null user', function (): void {
        $checker = new RoleBasedChecker;

        expect($checker->getUserRole(null))->toBeNull();
    });

    it('handles array roles', function (): void {
        $user = new User(['role' => ['admin', 'editor']]);
        $checker = new RoleBasedChecker;

        $roles = $checker->getUserRole($user);

        expect($roles)->toBeArray();
        expect($roles)->toContain('admin');
        expect($roles)->toContain('editor');
    });
});

// ============================================================================
// ARRAY ROLE TESTS
// ============================================================================

describe('Array Roles', function (): void {

    it('allows transition when user has any permitted role in array', function (): void {
        $user = new User(['role' => ['viewer', 'admin']]);
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $checker = new RoleBasedChecker;

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeTrue();
    });

    it('denies when user has no matching role in array', function (): void {
        $user = new User(['role' => ['viewer', 'guest']]);
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $checker = new RoleBasedChecker;

        $canTransition = $checker->canTransition(
            $post,
            Review::class,
            Published::class,
            $user
        );

        expect($canTransition)->toBeFalse();
    });
});
