<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionContext;
use Hpwebdeveloper\LaravelStateflow\Exceptions\TransitionException;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;

beforeEach(function (): void {
    $this->createPostsTable();
    Post::resetStateRegistration();
});

// ============================================================================
// TRANSITION EXCEPTION FACTORY METHODS
// ============================================================================

describe('TransitionException Factory Methods', function (): void {

    /**
     * Scenario: Create exception for disallowed transition (state graph violation)
     * Setup: Call notAllowed() factory with from/to states and model name
     * Assertions: Exception message contains state names and 'not allowed' for user clarity
     */
    it('creates notAllowed exception', function (): void {
        $exception = TransitionException::notAllowed('draft', 'published', 'Post');

        expect($exception)->toBeInstanceOf(TransitionException::class);
        expect($exception->getMessage())->toContain('draft');
        expect($exception->getMessage())->toContain('published');
        expect($exception->getMessage())->toContain('Post');
        expect($exception->getMessage())->toContain('not allowed');
    });

    /**
     * Scenario: Create exception for authorization failure (user lacks permission)
     * Setup: Call unauthorized() factory with target state and role/username
     * Assertions: Message includes who was denied and what they tried to access
     */
    it('creates unauthorized exception', function (): void {
        $exception = TransitionException::unauthorized('published', 'viewer');

        expect($exception)->toBeInstanceOf(TransitionException::class);
        expect($exception->getMessage())->toContain('viewer');
        expect($exception->getMessage())->toContain('published');
        expect($exception->getMessage())->toContain('not authorized');
    });

    /**
     * Scenario: Create exception when lifecycle hook returns false (business rule veto)
     * Setup: Call abortedByHook() with hook name (e.g., 'beforeTransition')
     * Assertions: Message identifies which hook aborted execution for debugging
     */
    it('creates abortedByHook exception', function (): void {
        $exception = TransitionException::abortedByHook('beforeTransition');

        expect($exception)->toBeInstanceOf(TransitionException::class);
        expect($exception->getMessage())->toContain('beforeTransition');
        expect($exception->getMessage())->toContain('aborted');
    });

    /**
     * Scenario: Create exception for metadata validation failures with error details
     * Setup: Call validationFailed() with Laravel validator errors array
     * Assertions: Message includes field names and error messages for user feedback
     */
    it('creates validationFailed exception', function (): void {
        $errors = [
            'field1' => ['Error message 1'],
            'field2' => ['Error message 2', 'Another error'],
        ];

        $exception = TransitionException::validationFailed($errors);

        expect($exception)->toBeInstanceOf(TransitionException::class);
        expect($exception->getMessage())->toContain('validation failed');
        expect($exception->getMessage())->toContain('field1');
        expect($exception->getMessage())->toContain('Error message 1');
    });

    /**
     * Scenario: Create exception for action execution failure with contextual error
     * Setup: Call actionFailed() with action class name and error description
     * Assertions: Message identifies failing action and specific error for debugging
     */
    it('creates actionFailed exception', function (): void {
        $exception = TransitionException::actionFailed('MyAction', 'Something went wrong');

        expect($exception)->toBeInstanceOf(TransitionException::class);
        expect($exception->getMessage())->toContain('MyAction');
        expect($exception->getMessage())->toContain('Something went wrong');
    });

    /**
     * Scenario: Create exception when state machine configuration is missing
     * Setup: Call missingConfiguration() with field name
     * Assertions: Message indicates configuration not found for specified field
     */
    it('creates missingConfiguration exception', function (): void {
        $exception = TransitionException::missingConfiguration('state');

        expect($exception)->toBeInstanceOf(TransitionException::class);
        expect($exception->getMessage())->toContain('state');
        expect($exception->getMessage())->toContain('configuration');
    });

    /**
     * Scenario: Create exception when state field contains unexpected null value
     * Setup: Call nullState() with field name
     * Assertions: Message warns about null state which should have a value
     */
    it('creates nullState exception', function (): void {
        $exception = TransitionException::nullState('state');

        expect($exception)->toBeInstanceOf(TransitionException::class);
        expect($exception->getMessage())->toContain('null');
        expect($exception->getMessage())->toContain('state');
    });

    /**
     * Scenario: Create exception for state value not found in state registry
     * Setup: Call unknownState() with invalid state name
     * Assertions: Message identifies unknown state name for troubleshooting
     */
    it('creates unknownState exception', function (): void {
        $exception = TransitionException::unknownState('invalid_state');

        expect($exception)->toBeInstanceOf(TransitionException::class);
        expect($exception->getMessage())->toContain('invalid_state');
        expect($exception->getMessage())->toContain('Unknown');
    });

});

// ============================================================================
// CONTEXT ATTACHMENT
// ============================================================================

describe('TransitionException Context', function (): void {

    /**
     * Scenario: Attach TransitionContext to exception for rich error reporting
     * Setup: Create context from TransitionData, call withContext() on exception
     * Assertions: Exception getContext() returns attached context (debugging aid)
     */
    it('attaches context to exception', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new \Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $context = TransitionContext::fromTransitionData($data);

        $exception = TransitionException::notAllowed('draft', 'review', 'Post')
            ->withContext($context);

        expect($exception->getContext())->toBe($context);
    });

    /**
     * Scenario: Exception context is optional (graceful degradation when unavailable)
     * Setup: Create exception without calling withContext()
     * Assertions: getContext() returns null (doesn't throw error, safe access)
     */
    it('returns null when no context attached', function (): void {
        $exception = TransitionException::notAllowed('draft', 'review', 'Post');

        expect($exception->getContext())->toBeNull();
    });

    /**
     * Scenario: withContext() returns exception for method chaining (fluent API)
     * Setup: Call withContext() and verify return value
     * Assertions: Returns self for fluent throws (e.g., throw ex->withContext())
     */
    it('allows fluent context attachment', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new \Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $context = TransitionContext::fromTransitionData($data);

        $exception = TransitionException::abortedByHook('beforeTransition')
            ->withContext($context);

        // Should return self for fluent interface
        expect($exception)->toBeInstanceOf(TransitionException::class);
        expect($exception->getContext())->not->toBeNull();
    });

});

// ============================================================================
// VALIDATION ERROR FORMATTING
// ============================================================================

describe('Validation Error Formatting', function (): void {

    /**
     * Scenario: Format single validation error per field into readable message
     * Setup: Pass errors array with one error per field
     * Assertions: Exception message contains field name and error text (user-friendly)
     */
    it('formats single error per field', function (): void {
        $errors = [
            'name' => ['The name is required'],
        ];

        $exception = TransitionException::validationFailed($errors);

        expect($exception->getMessage())->toContain('name: The name is required');
    });

    it('formats multiple errors per field', function (): void {
        $errors = [
            'email' => ['Invalid email', 'Email already taken'],
        ];

        $exception = TransitionException::validationFailed($errors);

        expect($exception->getMessage())->toContain('Invalid email');
        expect($exception->getMessage())->toContain('Email already taken');
    });

    it('formats multiple fields', function (): void {
        $errors = [
            'name' => ['Required'],
            'email' => ['Invalid'],
        ];

        $exception = TransitionException::validationFailed($errors);

        expect($exception->getMessage())->toContain('name: Required');
        expect($exception->getMessage())->toContain('email: Invalid');
    });

    it('handles empty errors array', function (): void {
        $exception = TransitionException::validationFailed([]);

        expect($exception->getMessage())->toContain('validation failed');
    });

});
