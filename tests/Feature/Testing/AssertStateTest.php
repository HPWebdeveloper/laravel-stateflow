<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Hpwebdeveloper\LaravelStateflow\Testing\AssertState;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;

uses(AssertState::class);

describe('AssertState trait', function () {
    beforeEach(function () {
        $this->createPostsTable();
    });

    describe('assertModelInState', function () {
        it('passes when model is in expected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $this->assertModelInState($post, 'draft');
        });

        it('fails when model is not in expected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => $this->assertModelInState($post, 'published'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });

        it('accepts optional field parameter', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $this->assertModelInState($post, 'draft', 'state');
        });
    });

    describe('assertModelNotInState', function () {
        it('passes when model is not in unexpected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $this->assertModelNotInState($post, 'published');
        });

        it('fails when model is in unexpected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => $this->assertModelNotInState($post, 'draft'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertCanTransitionTo', function () {
        it('passes for valid transition', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $this->assertCanTransitionTo($post, 'review');
        });

        it('fails for invalid transition', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => $this->assertCanTransitionTo($post, 'published'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertCannotTransitionTo', function () {
        it('passes for invalid transition', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $this->assertCannotTransitionTo($post, 'published');
        });

        it('fails for valid transition', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => $this->assertCannotTransitionTo($post, 'review'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertTransitionSucceeded', function () {
        it('passes for successful result', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            $this->assertTransitionSucceeded($result);
        });

        it('fails for failed result', function () {
            $result = TransitionResult::failure('test error');

            expect(fn () => $this->assertTransitionSucceeded($result))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertTransitionFailed', function () {
        it('passes for failed result', function () {
            $result = TransitionResult::failure('test error');

            $this->assertTransitionFailed($result);
        });

        it('fails for successful result', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            expect(fn () => $this->assertTransitionFailed($result))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });

        it('can check for expected error message', function () {
            $result = TransitionResult::failure('Invalid transition');

            $this->assertTransitionFailed($result, 'Invalid');

            expect(fn () => $this->assertTransitionFailed($result, 'Different error'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertHasAllowedTransitions', function () {
        it('passes when all expected transitions are allowed', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $this->assertHasAllowedTransitions($post, ['review']);
        });

        it('fails when expected transition not allowed', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => $this->assertHasAllowedTransitions($post, ['published']))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertAllowedTransitionsExactly', function () {
        it('passes when transitions match exactly', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $this->assertAllowedTransitionsExactly($post, ['review']);
        });

        it('fails when transitions do not match', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => $this->assertAllowedTransitionsExactly($post, ['review', 'published']))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });

        it('handles multiple transitions', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'review']);

            // Review can transition to published or rejected
            $this->assertAllowedTransitionsExactly($post, ['published', 'rejected']);
        });
    });

    describe('assertNoAllowedTransitions', function () {
        it('passes for terminal state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'published']);

            // Published is a terminal state with no outgoing transitions
            $this->assertNoAllowedTransitions($post);
        });

        it('fails for non-terminal state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => $this->assertNoAllowedTransitions($post))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertInTerminalState', function () {
        it('passes for terminal state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'published']);

            $this->assertInTerminalState($post);
        });

        it('fails for non-terminal state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => $this->assertInTerminalState($post))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertInInitialState', function () {
        it('passes for default state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $this->assertInInitialState($post);
        });

        it('fails for non-default state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'review']);

            expect(fn () => $this->assertInInitialState($post))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });
});
