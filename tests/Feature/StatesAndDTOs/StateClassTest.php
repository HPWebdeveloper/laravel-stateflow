<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Rejected;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;

describe('State Class - Constants', function () {
    /**
     * Scenario: State classes provide canonical name() for database storage and comparison
     * Setup: Call static name() on various State classes
     * Assertions: Returns lowercase string identifier (draft, published, review, rejected)
     */
    it('returns correct name from constant', function () {
        expect(Draft::name())->toBe('draft');
        expect(Published::name())->toBe('published');
        expect(Review::name())->toBe('review');
        expect(Rejected::name())->toBe('rejected');
    });

    /**
     * Scenario: State classes provide human-readable title() for UI display
     * Setup: Call static title() on State classes
     * Assertions: Returns capitalized, formatted titles (Draft, Published, Under Review)
     */
    it('returns correct title from constant', function () {
        expect(Draft::title())->toBe('Draft');
        expect(Published::title())->toBe('Published');
        expect(Review::title())->toBe('Under Review');
    });

    /**
     * Scenario: State classes define color() for badge/pill styling in UI
     * Setup: Query color metadata from states
     * Assertions: Returns semantic colors (primary, success, warning, danger for CSS)
     */
    it('returns correct color from constant', function () {
        expect(Draft::color())->toBe('primary');
        expect(Published::color())->toBe('success');
        expect(Review::color())->toBe('warning');
        expect(Rejected::color())->toBe('danger');
    });

    /**
     * Scenario: State classes optionally provide icon() for visual indicators (not required)
     * Setup: Query icon() on states that define icons
     * Assertions: Returns icon identifier for icon libraries (check-circle, x-circle)
     */
    it('returns icon when defined', function () {
        expect(Published::icon())->toBe('check-circle');
        expect(Rejected::icon())->toBe('x-circle');
    });

    /**
     * Scenario: State classes define allowedTransitions() list (state graph edges)
     * Setup: Query Draft allowed transitions
     * Assertions: Includes Review::class, excludes Published::class (enforces workflow)
     */
    it('returns allowed transitions from constant', function () {
        $transitions = Draft::allowedTransitions();

        expect($transitions)->toContain(Review::class);
        expect($transitions)->not->toContain(Published::class);
    });

    /**
     * Scenario: State classes specify permittedRoles() for RBAC authorization
     * Setup: Query permitted roles for Published and Draft states
     * Assertions: Published allows only admins, Draft allows admin+author (permission levels)
     */
    it('returns permitted roles from constant', function () {
        $roles = Published::permittedRoles();

        expect($roles)->toBe(['admin']);
        expect(Draft::permittedRoles())->toBe(['admin', 'author']);
    });

    /**
     * Scenario: State classes identify if they're the default initial state
     * Setup: Check isDefault() on Draft (default) and Published (not default)
     * Assertions: Draft is default (new models start here), Published is not
     */
    it('detects default state from constant', function () {
        expect(Draft::isDefault())->toBeTrue();
        expect(Published::isDefault())->toBeFalse();
    });
});

describe('State Class - Transitions', function () {
    /**
     * Scenario: canTransitionTo() validates if transition is allowed in state graph
     * Setup: Check Draft->Review (allowed) and Draft->Published (not allowed)
     * Assertions: Returns true/false for path validation (enforces workflow)
     */
    it('checks if transition is allowed', function () {
        expect(Draft::canTransitionTo(Review::class))->toBeTrue();
        expect(Draft::canTransitionTo(Published::class))->toBeFalse();
    });

    /**
     * Scenario: canTransitionTo() accepts State instance not just class (flexible API)
     * Setup: Create Review state instance, check if Draft can transition to it
     * Assertions: Works with instance (extracts class internally)
     */
    it('checks if transition to instance is allowed', function () {
        $post = new Post(['title' => 'Test']);
        $review = new Review($post);

        expect(Draft::canTransitionTo($review))->toBeTrue();
    });

    /**
     * Scenario: Terminal/final states return empty allowedTransitions() (workflow end)
     * Setup: Query Published state's allowed transitions
     * Assertions: Returns empty array (no outgoing transitions, workflow complete)
     */
    it('returns empty array for terminal states', function () {
        expect(Published::allowedTransitions())->toBe([]);
    });

    /**
     * Scenario: States support backward transitions when configured (undo/revert)
     * Setup: Check if Rejected can transition back to Draft
     * Assertions: Returns true (allows workflow reversal for corrections)
     */
    it('allows bidirectional transitions when configured', function () {
        expect(Rejected::canTransitionTo(Draft::class))->toBeTrue();
    });
});

describe('State Class - Model Integration', function () {
    /**
     * Scenario: State instances bind to specific model instance (context preservation)
     * Setup: Create Draft state with Post model
     * Assertions: getModel() returns original model (state knows its context)
     */
    it('creates state instance with model', function () {
        $post = new Post(['title' => 'Test']);
        $state = new Draft($post);

        expect($state->getModel())->toBe($post);
    });

    /**
     * Scenario: State classes provide getMorphClass() for polymorphic relations
     * Setup: Query static getMorphClass() on State classes
     * Assertions: Returns state name string (maps to morph type column)
     */
    it('returns morph class as state name', function () {
        expect(Draft::getMorphClass())->toBe('draft');
        expect(Published::getMorphClass())->toBe('published');
    });
});

describe('State Class - Serialization', function () {
    /**
     * Scenario: toResource() serializes state to array for API responses
     * Setup: Create Draft state instance, call toResource()
     * Assertions: Returns dict with name, title, color, icon, description (JSON-ready)
     */
    it('converts to resource array', function () {
        $post = new Post(['title' => 'Test']);
        $state = new Draft($post);

        $resource = $state->toResource();

        expect($resource)->toHaveKeys(['name', 'title', 'color', 'icon', 'description']);
        expect($resource['name'])->toBe('draft');
        expect($resource['title'])->toBe('Draft');
        expect($resource['color'])->toBe('primary');
    });

    it('serializes to JSON correctly', function () {
        $post = new Post(['title' => 'Test']);
        $state = new Draft($post);

        expect(json_encode($state))->toBe('"draft"');
    });

    it('converts to string correctly', function () {
        $post = new Post(['title' => 'Test']);
        $state = new Draft($post);

        expect((string) $state)->toBe('draft');
    });
});

describe('State Class - Equality', function () {
    it('checks equality with same state class', function () {
        $post = new Post(['title' => 'Test']);
        $draft1 = new Draft($post);
        $draft2 = new Draft($post);

        expect($draft1->equals($draft2))->toBeTrue();
    });

    it('checks equality with state name string', function () {
        $post = new Post(['title' => 'Test']);
        $draft = new Draft($post);

        expect($draft->equals('draft'))->toBeTrue();
        expect($draft->equals('published'))->toBeFalse();
    });

    it('checks equality with class name string', function () {
        $post = new Post(['title' => 'Test']);
        $draft = new Draft($post);

        expect($draft->equals(Draft::class))->toBeTrue();
        expect($draft->equals(Published::class))->toBeFalse();
    });

    it('returns false for different state classes', function () {
        $post = new Post(['title' => 'Test']);
        $draft = new Draft($post);
        $published = new Published($post);

        expect($draft->equals($published))->toBeFalse();
    });
});
