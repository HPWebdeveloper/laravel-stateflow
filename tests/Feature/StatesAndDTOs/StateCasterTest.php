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
    /**
     * Scenario: StateCaster hydrates database string to State instance on model retrieval
     * Setup: Pass 'draft' string through caster's get() method
     * Assertions: Returns Draft instance bound to model (ORM hydration)
     */
    it('casts string to state instance on get', function () {
        $caster = new StateCaster(PostState::class);
        $post = new Post(['title' => 'Test']);

        $result = $caster->get($post, 'state', 'draft', []);

        expect($result)->toBeInstanceOf(Draft::class);
        expect($result->getModel())->toBe($post);
    });

    /**
     * Scenario: Caster handles null values gracefully (nullable state columns)
     * Setup: Pass null through get() method
     * Assertions: Returns null not error (allows optional states)
     */
    it('returns null for null value', function () {
        $caster = new StateCaster(PostState::class);
        $post = new Post(['title' => 'Test']);

        $result = $caster->get($post, 'state', null, []);

        expect($result)->toBeNull();
    });

    /**
     * Scenario: Caster validates state exists in registry (data integrity check)
     * Setup: Pass 'unknown_state' not in PostState registry
     * Assertions: Throws InvalidStateException (prevents invalid state data)
     */
    it('throws on unknown state', function () {
        $caster = new StateCaster(PostState::class);
        $post = new Post(['title' => 'Test']);

        expect(fn () => $caster->get($post, 'state', 'unknown_state', []))
            ->toThrow(InvalidStateException::class, "Unknown state 'unknown_state'");
    });

    /**
     * Scenario: Caster resolves State class FQCN to instance (flexible input)
     * Setup: Pass Draft::class instead of 'draft' string
     * Assertions: Returns Draft instance (normalizes input format)
     */
    it('resolves state by class name', function () {
        $caster = new StateCaster(PostState::class);
        $post = new Post(['title' => 'Test']);

        $result = $caster->get($post, 'state', Draft::class, []);

        expect($result)->toBeInstanceOf(Draft::class);
    });
});

describe('StateCaster - Set', function () {
    /**
     * Scenario: StateCaster dehydrates State instance to string for database storage
     * Setup: Pass Draft instance through caster's set() method
     * Assertions: Returns 'draft' string (ORM dehydration for persistence)
     */
    it('casts state instance to string on set', function () {
        $caster = new StateCaster(PostState::class);
        $post = new Post(['title' => 'Test']);
        $state = new Draft($post);

        $result = $caster->set($post, 'state', $state, []);

        expect($result)->toBe('draft');
    });

    /**
     * Scenario: Caster validates and passes through state name strings
     * Setup: Pass 'draft' string to set() method
     * Assertions: Returns same string (validates it exists in registry first)
     */
    it('validates state string on set', function () {
        $caster = new StateCaster(PostState::class);
        $post = new Post(['title' => 'Test']);

        $result = $caster->set($post, 'state', 'draft', []);

        expect($result)->toBe('draft');
    });

    /**
     * Scenario: Caster preserves null for nullable columns (optional states)
     * Setup: Pass null to set() method
     * Assertions: Returns null (allows clearing state field)
     */
    it('returns null for null value on set', function () {
        $caster = new StateCaster(PostState::class);
        $post = new Post(['title' => 'Test']);

        $result = $caster->set($post, 'state', null, []);

        expect($result)->toBeNull();
    });

    /**
     * Scenario: Caster rejects invalid state strings before storage (data integrity)
     * Setup: Pass 'invalid_state' not in registry to set()
     * Assertions: Throws InvalidStateException (prevents database corruption)
     */
    it('throws on unknown state string on set', function () {
        $caster = new StateCaster(PostState::class);
        $post = new Post(['title' => 'Test']);

        expect(fn () => $caster->set($post, 'state', 'invalid_state', []))
            ->toThrow(InvalidStateException::class);
    });

    /**
     * Scenario: Caster enforces type safety (only accepts State instances or strings)
     * Setup: Pass invalid type (e.g., integer, array) to set()
     * Assertions: Throws exception (prevents type errors in database)
     */
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
