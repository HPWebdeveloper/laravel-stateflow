<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Rejected;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;

beforeEach(function (): void {
    $this->createPostsTable();
    Post::resetStateRegistration();
});

// ============================================================================
// AUTOMATIC NEXT STATE DISCOVERY
// ============================================================================

describe('Automatic Next State Discovery', function (): void {

    /**
     * Scenario: When in draft state, only review is the allowed next step in the workflow
     * Setup: Create post in draft state where transitions are configured: draft -> review
     * Assertions: Returns Review class only; Published and Rejected are not directly reachable
     */
    it('returns next states from draft state', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $nextStates = $post->getNextStates();

        expect($nextStates)->toBeArray()
            ->and($nextStates)->toContain(Review::class)
            ->and($nextStates)->not->toContain(Published::class)
            ->and($nextStates)->not->toContain(Rejected::class);
    });

    /**
     * Scenario: Review state offers branching workflow - can be approved or rejected
     * Setup: Create post in review state with transitions: review -> published OR review -> rejected
     * Assertions: Both Published and Rejected are available; Draft is not (no backward transition)
     */
    it('returns multiple next states from review', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $nextStates = $post->getNextStates();

        expect($nextStates)->toBeArray()
            ->and($nextStates)->toContain(Published::class)
            ->and($nextStates)->toContain(Rejected::class)
            ->and($nextStates)->not->toContain(Draft::class);
    });

    /**
     * Scenario: Published is a terminal state representing end of workflow lifecycle
     * Setup: Create post in published state which has no configured outgoing transitions
     * Assertions: Empty array returned - no further state changes possible
     */
    it('returns empty array for terminal state', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'published']);

        $nextStates = $post->getNextStates();

        expect($nextStates)->toBe([]);
    });

    /**
     * Scenario: Rejected posts can be sent back to draft for revisions and resubmission
     * Setup: Create post in rejected state with configured transition: rejected -> draft
     * Assertions: Draft state is available, allowing content to re-enter the workflow
     */
    it('returns states that can return to draft from rejected', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'rejected']);

        $nextStates = $post->getNextStates();

        expect($nextStates)->toContain(Draft::class);
    });

    /**
     * Scenario: State field name is configurable; explicitly specifying it should work identically
     * Setup: Create draft post, call getNextStates with explicit 'state' field parameter
     * Assertions: Returns same Review transition as when called without parameter (field defaults to 'state')
     */
    it('works with custom field name', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        // Default field should work when passed explicitly
        $nextStates = $post->getNextStates('state');

        expect($nextStates)->toContain(Review::class);
    });

});

// ============================================================================
// UI METADATA FROM STATE CLASSES
// ============================================================================

describe('UI Metadata From State Classes', function (): void {

    /**
     * Scenario: Each state provides a semantic color code for visual differentiation in UI
     * Setup: Access color() static method on all four Post state classes
     * Assertions: Draft=primary, Review=warning, Published=success, Rejected=danger
     */
    it('provides color from state class', function (): void {
        expect(Draft::color())->toBe('primary');
        expect(Review::color())->toBe('warning');
        expect(Published::color())->toBe('success');
        expect(Rejected::color())->toBe('danger');
    });

    /**
     * Scenario: Each state provides a user-friendly title for display in UI dropdowns/badges
     * Setup: Access title() static method on all Post state classes
     * Assertions: Returns formatted titles like "Under Review" instead of machine names
     */
    it('provides title from state class', function (): void {
        expect(Draft::title())->toBe('Draft');
        expect(Review::title())->toBe('Under Review');
        expect(Published::title())->toBe('Published');
        expect(Rejected::title())->toBe('Rejected');
    });

    /**
     * Scenario: Each state provides its database identifier used for persistence and querying
     * Setup: Access name() static method on all Post state classes
     * Assertions: Returns lowercase machine names: draft, review, published, rejected
     */
    it('provides name from state class', function (): void {
        expect(Draft::name())->toBe('draft');
        expect(Review::name())->toBe('review');
        expect(Published::name())->toBe('published');
        expect(Rejected::name())->toBe('rejected');
    });

    /**
     * Scenario: Models provide convenience method to access current state's color directly
     * Setup: Create post in draft, then change to review state
     * Assertions: getStateColor() returns appropriate color for current state at each point
     */
    it('model can get state color', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        expect($post->getStateColor())->toBe('primary');

        $post->state = 'review';
        expect($post->getStateColor())->toBe('warning');
    });

    /**
     * Scenario: Models provide quick access to current state's human-readable title
     * Setup: Create post in draft state
     * Assertions: getStateTitle() returns "Draft" for display purposes
     */
    it('model can get state title', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        expect($post->getStateTitle())->toBe('Draft');
    });

    /**
     * Scenario: State instance on model provides direct access to all metadata methods
     * Setup: Create post in draft state (state attribute is casted to State object)
     * Assertions: Can call name(), color(), title() directly on the $post->state object
     */
    it('state instance provides metadata', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        expect($post->state->name())->toBe('draft');
        expect($post->state->color())->toBe('primary');
        expect($post->state->title())->toBe('Draft');
    });

});

// ============================================================================
// TRANSITION VALIDATION - canTransitionTo
// ============================================================================

describe('Transition Validation', function (): void {

    /**
     * Scenario: Validate if a transition is permitted before attempting it
     * Setup: Create post in draft state where draft -> review is configured as allowed
     * Assertions: canTransitionTo() accepts both class reference and string name, returns true
     */
    it('validates allowed transition', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        expect($post->canTransitionTo(Review::class))->toBeTrue();
        expect($post->canTransitionTo('review'))->toBeTrue();
    });

    it('rejects disallowed transition', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        expect($post->canTransitionTo(Published::class))->toBeFalse();
        expect($post->canTransitionTo('published'))->toBeFalse();
    });

    it('validates transition sequence', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        // Draft can only go to Review
        expect($post->canTransitionTo(Review::class))->toBeTrue();
        expect($post->canTransitionTo(Published::class))->toBeFalse();
        expect($post->canTransitionTo(Rejected::class))->toBeFalse();

        // Move to review
        $post->transitionTo(Review::class);

        // Review can go to Published or Rejected
        expect($post->canTransitionTo(Published::class))->toBeTrue();
        expect($post->canTransitionTo(Rejected::class))->toBeTrue();
        expect($post->canTransitionTo(Draft::class))->toBeFalse();
    });

});

// ============================================================================
// STATE CHECKING - isState
// ============================================================================

describe('State Checking', function (): void {

    it('can check if in state by class', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        expect($post->isState(Draft::class))->toBeTrue();
        expect($post->isState(Review::class))->toBeFalse();
    });

    it('can check if in state by name', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        expect($post->isState('draft'))->toBeTrue();
        expect($post->isState('review'))->toBeFalse();
    });

    it('getStateName returns state string', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        expect($post->getStateName())->toBe('draft');

        $post->transitionTo(Review::class);

        expect($post->getStateName())->toBe('review');
    });

    it('can check if in any of multiple states', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        expect($post->isAnyState([Draft::class, Review::class]))->toBeTrue();
        expect($post->isAnyState([Review::class, Published::class]))->toBeFalse();
    });

});

// ============================================================================
// FULL WORKFLOW TESTS
// ============================================================================

describe('Full Workflow', function (): void {

    it('completes publish workflow: draft -> review -> published', function (): void {
        $post = Post::create(['title' => 'Test Article', 'state' => 'draft']);

        // Start in draft
        expect($post->state)->toBeInstanceOf(Draft::class);
        expect($post->getNextStates())->toContain(Review::class);

        // Transition to review
        $result1 = $post->transitionTo(Review::class, reason: 'Ready for review');
        expect($result1->succeeded())->toBeTrue();
        expect($post->state)->toBeInstanceOf(Review::class);

        // Now can go to published or rejected
        expect($post->getNextStates())->toContain(Published::class);
        expect($post->getNextStates())->toContain(Rejected::class);

        // Transition to published
        $result2 = $post->transitionTo(Published::class, reason: 'Approved');
        expect($result2->succeeded())->toBeTrue();
        expect($post->state)->toBeInstanceOf(Published::class);

        // No more transitions from published
        expect($post->getNextStates())->toBe([]);
    });

    it('handles rejection workflow: draft -> review -> rejected -> draft', function (): void {
        $post = Post::create(['title' => 'Test Article', 'state' => 'draft']);

        // Submit for review
        $post->transitionTo(Review::class);
        expect($post->isState(Review::class))->toBeTrue();

        // Get rejected
        $post->transitionTo(Rejected::class, reason: 'Needs more work');
        expect($post->isState(Rejected::class))->toBeTrue();

        // Can go back to draft
        expect($post->canTransitionTo(Draft::class))->toBeTrue();
        $post->transitionTo(Draft::class, reason: 'Revising content');
        expect($post->isState(Draft::class))->toBeTrue();

        // Can resubmit
        expect($post->canTransitionTo(Review::class))->toBeTrue();
    });

});

// ============================================================================
// MODEL STATE CONFIG ACCESS
// ============================================================================

describe('Model State Config Access', function (): void {

    it('can get state config for field', function (): void {
        $config = Post::getStateConfig('state');

        expect($config)->not->toBeNull();
    });

    it('can check if state config exists', function (): void {
        expect(Post::hasStateConfig('state'))->toBeTrue();
        expect(Post::hasStateConfig('nonexistent'))->toBeFalse();
    });

    it('can get all state configs', function (): void {
        $configs = Post::getAllStateConfigs();

        expect($configs)->toBeArray()
            ->and($configs)->toHaveKey('state');
    });

    it('state config provides registered states', function (): void {
        $config = Post::getStateConfig('state');

        $registeredStates = $config->getStates();

        expect($registeredStates)->toContain(Draft::class)
            ->and($registeredStates)->toContain(Review::class)
            ->and($registeredStates)->toContain(Published::class)
            ->and($registeredStates)->toContain(Rejected::class);
    });

    it('state config provides default state', function (): void {
        $config = Post::getStateConfig('state');

        $default = $config->getDefaultStateClass();

        expect($default)->toBe(Draft::class);
    });

});
