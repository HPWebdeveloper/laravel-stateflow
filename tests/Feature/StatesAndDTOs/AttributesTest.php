<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Cancelled;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Delivered;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Pending;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Processing;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Shipped;

beforeEach(function () {
    // Enable attributes feature for these tests
    config()->set('laravel-stateflow.features.attributes', true);
});

describe('State Class - PHP 8 Attributes', function () {
    it('returns correct name from static property', function () {
        expect(Pending::name())->toBe('pending');
        expect(Processing::name())->toBe('processing');
        expect(Shipped::name())->toBe('shipped');
        expect(Delivered::name())->toBe('delivered');
        expect(Cancelled::name())->toBe('cancelled');
    });

    it('returns correct title from StateMetadata attribute', function () {
        expect(Pending::title())->toBe('Pending');
        expect(Processing::title())->toBe('Processing');
        expect(Shipped::title())->toBe('Shipped');
    });

    it('returns correct color from StateMetadata attribute', function () {
        expect(Pending::color())->toBe('warning');
        expect(Processing::color())->toBe('info');
        expect(Shipped::color())->toBe('primary');
        expect(Delivered::color())->toBe('success');
        expect(Cancelled::color())->toBe('danger');
    });

    it('returns correct icon from StateMetadata attribute', function () {
        expect(Pending::icon())->toBe('clock');
        expect(Processing::icon())->toBe('cog');
        expect(Shipped::icon())->toBe('truck');
    });

    it('returns permitted roles from StatePermission attribute', function () {
        expect(Pending::permittedRoles())->toBe(['admin', 'customer']);
        expect(Processing::permittedRoles())->toBe(['admin']);
        expect(Cancelled::permittedRoles())->toBe(['admin', 'customer']);
    });

    it('returns allowed transitions from AllowTransition attributes', function () {
        $transitions = Pending::allowedTransitions();

        expect($transitions)->toContain(Processing::class);
        expect($transitions)->toContain(Cancelled::class);
        expect($transitions)->not->toContain(Shipped::class);
    });

    it('detects default state from DefaultState attribute', function () {
        expect(Pending::isDefault())->toBeTrue();
        expect(Processing::isDefault())->toBeFalse();
    });

    it('checks transition is allowed via attributes', function () {
        expect(Pending::canTransitionTo(Processing::class))->toBeTrue();
        expect(Pending::canTransitionTo(Cancelled::class))->toBeTrue();
        expect(Pending::canTransitionTo(Shipped::class))->toBeFalse();
    });
});

describe('State Class - Attributes Disabled', function () {
    beforeEach(function () {
        config()->set('laravel-stateflow.features.attributes', false);
    });

    it('falls back to defaults when attributes feature disabled', function () {
        // When attributes are disabled, title falls back to class name
        expect(Pending::title())->toBe('Pending');
    });

    it('returns empty transitions when attributes disabled and no constants', function () {
        // When attributes are disabled and no NEXT constant, returns empty
        expect(Pending::allowedTransitions())->toBe([]);
    });
});
