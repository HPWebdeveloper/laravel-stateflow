<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Hpwebdeveloper\LaravelStateflow\Facades\StateFlow;
use Hpwebdeveloper\LaravelStateflow\Testing\StateFlowFake;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;

describe('StateFlowFake', function () {
    beforeEach(function () {
        $this->createPostsTable();
    });

    describe('fake() method', function () {
        it('returns a StateFlowFake instance', function () {
            $fake = StateFlow::fake();

            expect($fake)->toBeInstanceOf(StateFlowFake::class);
        });

        it('can be instantiated directly', function () {
            $fake = new StateFlowFake;

            expect($fake)->toBeInstanceOf(StateFlowFake::class);
        });
    });

    describe('recordTransition', function () {
        it('records a transition', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review');

            expect($fake->getRecordedTransitions())->toHaveCount(1);
        });

        it('records multiple transitions', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review');
            $fake->recordTransition($post, 'state', 'review', 'published');

            expect($fake->getRecordedTransitions())->toHaveCount(2);
        });

        it('records transition with success flag', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review', true);

            $transitions = $fake->getRecordedTransitions();
            expect($transitions->first()['success'])->toBeTrue();
        });

        it('records transition with failure flag', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review', false, 'Test error');

            $transitions = $fake->getRecordedTransitions();
            expect($transitions->first()['success'])->toBeFalse();
            expect($transitions->first()['error'])->toBe('Test error');
        });

        it('records model type and id', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review');

            $transitions = $fake->getRecordedTransitions();
            expect($transitions->first()['model_type'])->toBe(Post::class);
            expect($transitions->first()['model_id'])->toBe($post->id);
        });
    });

    describe('assertTransitioned', function () {
        it('passes when transition was recorded', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review');

            $fake->assertTransitioned($post, 'draft', 'review');
        });

        it('fails when transition was not recorded', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => $fake->assertTransitioned($post, 'draft', 'review'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });

        it('returns self for chaining', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review');

            $result = $fake->assertTransitioned($post, 'draft', 'review');
            expect($result)->toBeInstanceOf(StateFlowFake::class);
        });
    });

    describe('assertNotTransitioned', function () {
        it('passes when no transitions recorded', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->assertNotTransitioned($post);
        });

        it('passes when different transition recorded', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $otherPost = Post::create(['title' => 'Other', 'state' => 'draft']);

            $fake->recordTransition($otherPost, 'state', 'draft', 'review');

            $fake->assertNotTransitioned($post);
        });

        it('fails when transition was recorded', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review');

            expect(fn () => $fake->assertNotTransitioned($post))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });

        it('can filter by from state', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review');

            // Should pass - different from state
            $fake->assertNotTransitioned($post, 'published');

            // Should fail - same from state
            expect(fn () => $fake->assertNotTransitioned($post, 'draft'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });

        it('can filter by to state', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review');

            // Should pass - different to state
            $fake->assertNotTransitioned($post, null, 'published');

            // Should fail - same to state
            expect(fn () => $fake->assertNotTransitioned($post, null, 'review'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertTransitionCount', function () {
        it('passes with correct count', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review');
            $fake->recordTransition($post, 'state', 'review', 'published');

            $fake->assertTransitionCount(2);
        });

        it('fails with incorrect count', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review');

            expect(fn () => $fake->assertTransitionCount(2))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });

        it('filters by model when provided', function () {
            $fake = new StateFlowFake;
            $post1 = Post::create(['title' => 'Test 1', 'state' => 'draft']);
            $post2 = Post::create(['title' => 'Test 2', 'state' => 'draft']);

            $fake->recordTransition($post1, 'state', 'draft', 'review');
            $fake->recordTransition($post2, 'state', 'draft', 'review');
            $fake->recordTransition($post2, 'state', 'review', 'published');

            $fake->assertTransitionCount(1, $post1);
            $fake->assertTransitionCount(2, $post2);
        });
    });

    describe('assertNoTransitions', function () {
        it('passes when no transitions recorded', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->assertNoTransitions();
            $fake->assertNoTransitions($post);
        });

        it('fails when transitions recorded', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review');

            expect(fn () => $fake->assertNoTransitions())
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });

        it('passes for specific model with no transitions', function () {
            $fake = new StateFlowFake;
            $post1 = Post::create(['title' => 'Test 1', 'state' => 'draft']);
            $post2 = Post::create(['title' => 'Test 2', 'state' => 'draft']);

            $fake->recordTransition($post1, 'state', 'draft', 'review');

            $fake->assertNoTransitions($post2);
        });
    });

    describe('assertTransitionSucceeded', function () {
        it('passes for successful transition', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review', true);

            $fake->assertTransitionSucceeded($post, 'draft', 'review');
        });

        it('fails for failed transition', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review', false);

            expect(fn () => $fake->assertTransitionSucceeded($post, 'draft', 'review'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertTransitionFailed', function () {
        it('passes for failed transition', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review', false, 'Test error');

            $fake->assertTransitionFailed($post, 'draft', 'review');
        });

        it('fails for successful transition', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review', true);

            expect(fn () => $fake->assertTransitionFailed($post, 'draft', 'review'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });

        it('checks error message when provided', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review', false, 'Invalid transition');

            $fake->assertTransitionFailed($post, 'draft', 'review', null, 'Invalid');

            expect(fn () => $fake->assertTransitionFailed($post, 'draft', 'review', null, 'wrong error'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('preventTransition', function () {
        it('marks transition as prevented', function () {
            $fake = new StateFlowFake;

            $fake->preventTransition('draft', 'review');

            expect($fake->isTransitionPrevented('draft', 'review'))->toBeTrue();
            expect($fake->isTransitionPrevented('review', 'published'))->toBeFalse();
        });

        it('returns self for chaining', function () {
            $fake = new StateFlowFake;

            $result = $fake->preventTransition('draft', 'review');

            expect($result)->toBeInstanceOf(StateFlowFake::class);
        });
    });

    describe('preventAllTransitions', function () {
        it('marks all transitions as prevented', function () {
            $fake = new StateFlowFake;

            $fake->preventAllTransitions();

            expect($fake->isTransitionPrevented('draft', 'review'))->toBeTrue();
            expect($fake->isTransitionPrevented('any', 'other'))->toBeTrue();
        });
    });

    describe('forceTransitionResult', function () {
        it('stores forced result', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            $fake->forceTransitionResult('review', $result);

            expect($fake->getForcedResult('review'))->toBe($result);
            expect($fake->getForcedResult('published'))->toBeNull();
        });

        it('returns self for chaining', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            $chainResult = $fake->forceTransitionResult('review', $result);

            expect($chainResult)->toBeInstanceOf(StateFlowFake::class);
        });
    });

    describe('getTransitionsFor', function () {
        it('returns transitions for specific model', function () {
            $fake = new StateFlowFake;
            $post1 = Post::create(['title' => 'Test 1', 'state' => 'draft']);
            $post2 = Post::create(['title' => 'Test 2', 'state' => 'draft']);

            $fake->recordTransition($post1, 'state', 'draft', 'review');
            $fake->recordTransition($post2, 'state', 'draft', 'review');
            $fake->recordTransition($post2, 'state', 'review', 'published');

            expect($fake->getTransitionsFor($post1))->toHaveCount(1);
            expect($fake->getTransitionsFor($post2))->toHaveCount(2);
        });
    });

    describe('clear', function () {
        it('clears all recorded transitions', function () {
            $fake = new StateFlowFake;
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $fake->recordTransition($post, 'state', 'draft', 'review');
            $fake->preventTransition('review', 'published');
            $fake->forceTransitionResult('review', TransitionResult::failure('test'));

            $fake->clear();

            expect($fake->getRecordedTransitions())->toBeEmpty();
            expect($fake->isTransitionPrevented('review', 'published'))->toBeFalse();
            expect($fake->getForcedResult('review'))->toBeNull();
        });

        it('returns self for chaining', function () {
            $fake = new StateFlowFake;

            $result = $fake->clear();

            expect($result)->toBeInstanceOf(StateFlowFake::class);
        });
    });
});
