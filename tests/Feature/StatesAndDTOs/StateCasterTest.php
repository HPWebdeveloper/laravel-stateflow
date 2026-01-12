<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Exceptions\InvalidStateException;
use Hpwebdeveloper\LaravelStateflow\StateCaster;
use Hpwebdeveloper\LaravelStateflow\StateFlow;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\PostState;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Rejected;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;

beforeEach(function () {
    StateFlow::registerStates(PostState::class, [
        Draft::class,
        Published::class,
        Review::class,
        Rejected::class,
    ]);
});

describe('StateCaster - Get', function () {
    it('casts string to state instance on get', function () {
        $caster = new StateCaster(PostState::class);
        $post = new Post(['title' => 'Test']);

        $result = $caster->get($post, 'state', 'draft', []);

        expect($result)->toBeInstanceOf(Draft::class);
        expect($result->getModel())->toBe($post);
    });

    it('returns null for null value', function () {
        $caster = new StateCaster(PostState::class);
        $post = new Post(['title' => 'Test']);

        $result = $caster->get($post, 'state', null, []);

        expect($result)->toBeNull();
    });

    it('throws on unknown state', function () {
        $caster = new StateCaster(PostState::class);
        $post = new Post(['title' => 'Test']);

        expect(fn () => $caster->get($post, 'state', 'unknown_state', []))
            ->toThrow(InvalidStateException::class, "Unknown state 'unknown_state'");
    });

    it('resolves state by class name', function () {
        $caster = new StateCaster(PostState::class);
        $post = new Post(['title' => 'Test']);

        $result = $caster->get($post, 'state', Draft::class, []);

        expect($result)->toBeInstanceOf(Draft::class);
    });
});

describe('StateCaster - Set', function () {
    it('casts state instance to string on set', function () {
        $caster = new StateCaster(PostState::class);
        $post = new Post(['title' => 'Test']);
        $state = new Draft($post);

        $result = $caster->set($post, 'state', $state, []);

        expect($result)->toBe('draft');
    });

    it('validates state string on set', function () {
        $caster = new StateCaster(PostState::class);
        $post = new Post(['title' => 'Test']);

        $result = $caster->set($post, 'state', 'draft', []);

        expect($result)->toBe('draft');
    });

    it('returns null for null value on set', function () {
        $caster = new StateCaster(PostState::class);
        $post = new Post(['title' => 'Test']);

        $result = $caster->set($post, 'state', null, []);

        expect($result)->toBeNull();
    });

    it('throws on unknown state string on set', function () {
        $caster = new StateCaster(PostState::class);
        $post = new Post(['title' => 'Test']);

        expect(fn () => $caster->set($post, 'state', 'invalid_state', []))
            ->toThrow(InvalidStateException::class);
    });

    it('throws on invalid value type', function () {
        $caster = new StateCaster(PostState::class);
        $post = new Post(['title' => 'Test']);

        expect(fn () => $caster->set($post, 'state', 123, []))
            ->toThrow(InvalidStateException::class, 'Invalid state value of type');
    });
});

describe('StateCaster - Castable Interface', function () {
    it('creates caster via castUsing', function () {
        $caster = Draft::castUsing([]);

        expect($caster)->toBeInstanceOf(StateCaster::class);
    });
});
