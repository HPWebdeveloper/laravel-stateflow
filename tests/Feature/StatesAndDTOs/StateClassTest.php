<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Rejected;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;

describe('State Class - Constants', function () {
    it('returns correct name from constant', function () {
        expect(Draft::name())->toBe('draft');
        expect(Published::name())->toBe('published');
        expect(Review::name())->toBe('review');
        expect(Rejected::name())->toBe('rejected');
    });

    it('returns correct title from constant', function () {
        expect(Draft::title())->toBe('Draft');
        expect(Published::title())->toBe('Published');
        expect(Review::title())->toBe('Under Review');
    });

    it('returns correct color from constant', function () {
        expect(Draft::color())->toBe('primary');
        expect(Published::color())->toBe('success');
        expect(Review::color())->toBe('warning');
        expect(Rejected::color())->toBe('danger');
    });

    it('returns icon when defined', function () {
        expect(Published::icon())->toBe('check-circle');
        expect(Rejected::icon())->toBe('x-circle');
    });

    it('returns allowed transitions from constant', function () {
        $transitions = Draft::allowedTransitions();

        expect($transitions)->toContain(Review::class);
        expect($transitions)->not->toContain(Published::class);
    });

    it('returns permitted roles from constant', function () {
        $roles = Published::permittedRoles();

        expect($roles)->toBe(['admin']);
        expect(Draft::permittedRoles())->toBe(['admin', 'author']);
    });

    it('detects default state from constant', function () {
        expect(Draft::isDefault())->toBeTrue();
        expect(Published::isDefault())->toBeFalse();
    });
});

describe('State Class - Transitions', function () {
    it('checks if transition is allowed', function () {
        expect(Draft::canTransitionTo(Review::class))->toBeTrue();
        expect(Draft::canTransitionTo(Published::class))->toBeFalse();
    });

    it('checks if transition to instance is allowed', function () {
        $post = new Post(['title' => 'Test']);
        $review = new Review($post);

        expect(Draft::canTransitionTo($review))->toBeTrue();
    });

    it('returns empty array for terminal states', function () {
        expect(Published::allowedTransitions())->toBe([]);
    });

    it('allows bidirectional transitions when configured', function () {
        expect(Rejected::canTransitionTo(Draft::class))->toBeTrue();
    });
});

describe('State Class - Model Integration', function () {
    it('creates state instance with model', function () {
        $post = new Post(['title' => 'Test']);
        $state = new Draft($post);

        expect($state->getModel())->toBe($post);
    });

    it('returns morph class as state name', function () {
        expect(Draft::getMorphClass())->toBe('draft');
        expect(Published::getMorphClass())->toBe('published');
    });
});

describe('State Class - Serialization', function () {
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
