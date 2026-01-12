<?php

declare(strict_types=1);

it('merges default configuration', function () {
    expect(config('laravel-stateflow'))->toBeArray();
    expect(config('laravel-stateflow.default_state_field'))->toBe('state');
});

it('has all required config keys', function () {
    $requiredKeys = [
        'default_state_field',
        'states_directory',
        'history',
        'permissions',
        'events',
        'resource_defaults',
        'validation',
        'query',
        'cache',
        'models',
        'features',
    ];

    foreach ($requiredKeys as $key) {
        expect(config("laravel-stateflow.{$key}"))->not->toBeNull(
            "Config key 'laravel-stateflow.{$key}' should exist"
        );
    }
});

it('has correct default values', function () {
    expect(config('laravel-stateflow.default_state_field'))->toBe('state');
    expect(config('laravel-stateflow.history.enabled'))->toBeTrue();
    expect(config('laravel-stateflow.permissions.enabled'))->toBeTrue();
    expect(config('laravel-stateflow.permissions.throw_on_unauthorized'))->toBeTrue();
    expect(config('laravel-stateflow.resource_defaults.color'))->toBe('gray');
});

it('config can be overridden', function () {
    config(['laravel-stateflow.default_state_field' => 'status']);
    expect(config('laravel-stateflow.default_state_field'))->toBe('status');
});

it('has all event toggles', function () {
    $events = config('laravel-stateflow.events');

    expect($events)->toHaveKeys([
        'enabled',
        'subscriber_enabled',
        'log_channel',
        'log_transitioning',
        'log_transitioned',
        'log_failed',
    ]);
});

it('has all feature flags', function () {
    $features = config('laravel-stateflow.features');

    expect($features)->toHaveKeys([
        'history',
        'permissions',
        'resources',
        'attributes',
        'events',
    ]);
});
