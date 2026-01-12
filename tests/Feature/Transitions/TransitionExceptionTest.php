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

    it('creates notAllowed exception', function (): void {
        $exception = TransitionException::notAllowed('draft', 'published', 'Post');

        expect($exception)->toBeInstanceOf(TransitionException::class);
        expect($exception->getMessage())->toContain('draft');
        expect($exception->getMessage())->toContain('published');
        expect($exception->getMessage())->toContain('Post');
        expect($exception->getMessage())->toContain('not allowed');
    });

    it('creates unauthorized exception', function (): void {
        $exception = TransitionException::unauthorized('published', 'viewer');

        expect($exception)->toBeInstanceOf(TransitionException::class);
        expect($exception->getMessage())->toContain('viewer');
        expect($exception->getMessage())->toContain('published');
        expect($exception->getMessage())->toContain('not authorized');
    });

    it('creates abortedByHook exception', function (): void {
        $exception = TransitionException::abortedByHook('beforeTransition');

        expect($exception)->toBeInstanceOf(TransitionException::class);
        expect($exception->getMessage())->toContain('beforeTransition');
        expect($exception->getMessage())->toContain('aborted');
    });

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

    it('creates actionFailed exception', function (): void {
        $exception = TransitionException::actionFailed('MyAction', 'Something went wrong');

        expect($exception)->toBeInstanceOf(TransitionException::class);
        expect($exception->getMessage())->toContain('MyAction');
        expect($exception->getMessage())->toContain('Something went wrong');
    });

    it('creates missingConfiguration exception', function (): void {
        $exception = TransitionException::missingConfiguration('state');

        expect($exception)->toBeInstanceOf(TransitionException::class);
        expect($exception->getMessage())->toContain('state');
        expect($exception->getMessage())->toContain('configuration');
    });

    it('creates nullState exception', function (): void {
        $exception = TransitionException::nullState('state');

        expect($exception)->toBeInstanceOf(TransitionException::class);
        expect($exception->getMessage())->toContain('null');
        expect($exception->getMessage())->toContain('state');
    });

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

    it('returns null when no context attached', function (): void {
        $exception = TransitionException::notAllowed('draft', 'review', 'Post');

        expect($exception->getContext())->toBeNull();
    });

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
