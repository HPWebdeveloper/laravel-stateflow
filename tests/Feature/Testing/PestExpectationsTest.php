<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;

describe('PestExpectations', function () {
    beforeEach(function () {
        $this->createPostsTable();
    });

    describe('toBeInState', function () {
        it('passes when model is in expected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)->toBeInState('draft');
        });

        it('fails when model is not in expected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => expect($post)->toBeInState('published'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });

        it('accepts optional field parameter', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)->toBeInState('draft', 'state');
        });
    });

    describe('toNotBeInState', function () {
        it('passes when model is not in unexpected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)->toNotBeInState('published');
        });

        it('fails when model is in unexpected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => expect($post)->toNotBeInState('draft'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toBeAbleToTransitionTo', function () {
        it('passes for valid transition', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)->toBeAbleToTransitionTo('review');
        });

        it('fails for invalid transition', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => expect($post)->toBeAbleToTransitionTo('published'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toNotBeAbleToTransitionTo', function () {
        it('passes for invalid transition', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)->toNotBeAbleToTransitionTo('published');
        });

        it('fails for valid transition', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => expect($post)->toNotBeAbleToTransitionTo('review'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toHaveAllowedTransitions', function () {
        it('passes when all expected transitions are allowed', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)->toHaveAllowedTransitions(['review']);
        });

        it('fails when expected transition not allowed', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => expect($post)->toHaveAllowedTransitions(['published']))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toHaveExactlyAllowedTransitions', function () {
        it('passes when transitions match exactly', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)->toHaveExactlyAllowedTransitions(['review']);
        });

        it('handles multiple transitions', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'review']);

            expect($post)->toHaveExactlyAllowedTransitions(['published', 'rejected']);
        });

        it('fails when transitions do not match', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => expect($post)->toHaveExactlyAllowedTransitions(['review', 'published']))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toBeInTerminalState', function () {
        it('passes for terminal state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'published']);

            expect($post)->toBeInTerminalState();
        });

        it('fails for non-terminal state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => expect($post)->toBeInTerminalState())
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toBeInInitialState', function () {
        it('passes for default state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)->toBeInInitialState();
        });

        it('fails for non-default state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'review']);

            expect(fn () => expect($post)->toBeInInitialState())
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toBeSuccessfulTransition', function () {
        it('passes for successful result', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            expect($result)->toBeSuccessfulTransition();
        });

        it('fails for failed result', function () {
            $result = TransitionResult::failure('test error');

            expect(fn () => expect($result)->toBeSuccessfulTransition())
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toBeFailedTransition', function () {
        it('passes for failed result', function () {
            $result = TransitionResult::failure('test error');

            expect($result)->toBeFailedTransition();
        });

        it('fails for successful result', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            expect(fn () => expect($result)->toBeFailedTransition())
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });

        it('can check for expected error message', function () {
            $result = TransitionResult::failure('Invalid transition');

            expect($result)->toBeFailedTransition('Invalid');
        });
    });

    describe('toHaveTransitionedTo', function () {
        it('passes when transitioned to expected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            expect($result)->toHaveTransitionedTo('review');
        });

        it('fails when transitioned to different state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            expect(fn () => expect($result)->toHaveTransitionedTo('published'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toHaveTransitionedFrom', function () {
        it('passes when transitioned from expected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            expect($result)->toHaveTransitionedFrom('draft');
        });

        it('fails when transitioned from different state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            expect(fn () => expect($result)->toHaveTransitionedFrom('published'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('chaining expectations', function () {
        it('supports chaining model expectations', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)
                ->toBeInState('draft')
                ->toBeAbleToTransitionTo('review')
                ->toNotBeAbleToTransitionTo('published');
        });

        it('supports chaining result expectations', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            expect($result)
                ->toBeSuccessfulTransition()
                ->toHaveTransitionedFrom('draft')
                ->toHaveTransitionedTo('review');
        });
    });
});
