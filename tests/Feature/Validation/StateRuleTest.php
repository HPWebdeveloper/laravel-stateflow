<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\StateFlow;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\PostState;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Rejected;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;
use Hpwebdeveloper\LaravelStateflow\Validation\Rules\ValidState;
use Hpwebdeveloper\LaravelStateflow\Validation\StateRule;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->createPostsTable();
    Post::resetStateRegistration();

    // Register states for PostState base class
    StateFlow::registerStates(PostState::class, [
        Draft::class,
        Review::class,
        Published::class,
        Rejected::class,
    ]);
});

describe('StateRule - Basic Validation', function () {
    it('passes for valid state', function () {
        $validator = Validator::make(
            ['state' => 'draft'],
            ['state' => [StateRule::for(PostState::class)]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('passes for all registered states', function () {
        $states = ['draft', 'review', 'published', 'rejected'];

        foreach ($states as $state) {
            $validator = Validator::make(
                ['state' => $state],
                ['state' => [StateRule::for(PostState::class)]]
            );

            expect($validator->passes())->toBeTrue("State '{$state}' should be valid");
        }
    });

    it('fails for invalid state', function () {
        $validator = Validator::make(
            ['state' => 'invalid_state'],
            ['state' => [StateRule::for(PostState::class)]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toContain('valid state');
    });

    it('fails for empty string', function () {
        $validator = Validator::make(
            ['state' => ''],
            ['state' => [StateRule::for(PostState::class)]]
        );

        expect($validator->fails())->toBeTrue();
    });

    it('fails for non-string value', function () {
        $validator = Validator::make(
            ['state' => 123],
            ['state' => [StateRule::for(PostState::class)]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toContain('string');
    });

    it('includes valid states in error message', function () {
        $validator = Validator::make(
            ['state' => 'invalid'],
            ['state' => [StateRule::for(PostState::class)]]
        );

        $message = $validator->errors()->first('state');
        expect($message)->toContain('draft')
            ->and($message)->toContain('review')
            ->and($message)->toContain('published');
    });
});

describe('StateRule - Nullable', function () {
    it('fails for null when not nullable', function () {
        $validator = Validator::make(
            ['state' => null],
            ['state' => [StateRule::for(PostState::class)]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toContain('required');
    });

    it('passes for null when nullable', function () {
        $validator = Validator::make(
            ['state' => null],
            ['state' => [StateRule::for(PostState::class)->nullable()]]
        );

        expect($validator->passes())->toBeTrue();
    });
});

describe('StateRule - Only Filter', function () {
    it('passes when state is in only list', function () {
        $validator = Validator::make(
            ['state' => 'draft'],
            ['state' => [StateRule::for(PostState::class)->only(['draft', 'review'])]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails when state is not in only list', function () {
        $validator = Validator::make(
            ['state' => 'published'],
            ['state' => [StateRule::for(PostState::class)->only(['draft', 'review'])]]
        );

        expect($validator->fails())->toBeTrue();
    });
});

describe('StateRule - Except Filter', function () {
    it('passes when state is not in except list', function () {
        $validator = Validator::make(
            ['state' => 'draft'],
            ['state' => [StateRule::for(PostState::class)->except(['rejected'])]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails when state is in except list', function () {
        $validator = Validator::make(
            ['state' => 'rejected'],
            ['state' => [StateRule::for(PostState::class)->except(['rejected'])]]
        );

        expect($validator->fails())->toBeTrue();
    });
});

describe('StateRule - Static Factory', function () {
    it('can create rule using static for method', function () {
        $rule = StateRule::for(PostState::class);

        expect($rule)->toBeInstanceOf(StateRule::class);
    });

    it('can chain methods fluently', function () {
        $rule = StateRule::for(PostState::class)
            ->nullable()
            ->only(['draft', 'review'])
            ->except(['rejected']);

        expect($rule)->toBeInstanceOf(StateRule::class);
    });
});

describe('ValidState Rule - Basic Validation', function () {
    it('passes for valid state', function () {
        $validator = Validator::make(
            ['state' => 'draft'],
            ['state' => [new ValidState(PostState::class)]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails for invalid state', function () {
        $validator = Validator::make(
            ['state' => 'invalid'],
            ['state' => [new ValidState(PostState::class)]]
        );

        expect($validator->fails())->toBeTrue();
    });
});

describe('ValidState Rule - Filters', function () {
    it('filters with only', function () {
        $validator = Validator::make(
            ['state' => 'published'],
            ['state' => [(new ValidState(PostState::class))->only(['draft', 'review'])]]
        );

        expect($validator->fails())->toBeTrue();
    });

    it('filters with except', function () {
        $validator = Validator::make(
            ['state' => 'rejected'],
            ['state' => [(new ValidState(PostState::class))->except(['rejected'])]]
        );

        expect($validator->fails())->toBeTrue();
    });

    it('can hide valid states from error message', function () {
        $validator = Validator::make(
            ['state' => 'invalid'],
            ['state' => [(new ValidState(PostState::class))->hideValidStates()]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('state'))->toBe('The state is not a valid state.');
    });
});
