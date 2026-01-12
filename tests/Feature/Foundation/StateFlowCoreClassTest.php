<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Models\StateTransitionHistory;
use Hpwebdeveloper\LaravelStateflow\StateFlow;

it('can disable migrations', function () {
    expect(StateFlow::$runsMigrations)->toBeTrue();

    StateFlow::ignoreMigrations();

    expect(StateFlow::$runsMigrations)->toBeFalse();
});

it('can customize history model', function () {
    expect(StateFlow::historyModel())->toBe(StateTransitionHistory::class);

    StateFlow::useHistoryModel('App\\Models\\CustomHistory');

    expect(StateFlow::historyModel())->toBe('App\\Models\\CustomHistory');
});

it('can register states for a base class', function () {
    StateFlow::registerStates('App\\States\\PostState', [
        'App\\States\\Post\\Draft',
        'App\\States\\Post\\Published',
    ]);

    $states = StateFlow::getRegisteredStates('App\\States\\PostState');

    expect($states)->toHaveCount(2);
    expect($states)->toContain('App\\States\\Post\\Draft');
    expect($states)->toContain('App\\States\\Post\\Published');
});

it('returns empty array for unregistered base class', function () {
    $states = StateFlow::getRegisteredStates('NonExistent\\State');

    expect($states)->toBeArray();
    expect($states)->toBeEmpty();
});

it('can register custom transitions', function () {
    StateFlow::registerTransition(
        'App\\States\\Draft',
        'App\\States\\Published',
        'App\\Transitions\\PublishPost'
    );

    $transitionClass = StateFlow::getTransitionClass(
        'App\\States\\Draft',
        'App\\States\\Published'
    );

    expect($transitionClass)->toBe('App\\Transitions\\PublishPost');
});

it('returns null for unregistered transitions', function () {
    $transitionClass = StateFlow::getTransitionClass(
        'NonExistent\\From',
        'NonExistent\\To'
    );

    expect($transitionClass)->toBeNull();
});

it('can check feature flags', function () {
    config(['laravel-stateflow.features.history' => true]);
    expect(StateFlow::hasFeature('history'))->toBeTrue();

    config(['laravel-stateflow.features.history' => false]);
    expect(StateFlow::hasFeature('history'))->toBeFalse();
});

it('correctly determines if history is recorded', function () {
    config([
        'laravel-stateflow.features.history' => true,
        'laravel-stateflow.history.enabled' => true,
    ]);
    expect(StateFlow::recordsHistory())->toBeTrue();

    config(['laravel-stateflow.features.history' => false]);
    expect(StateFlow::recordsHistory())->toBeFalse();
});

it('correctly determines if permissions are checked', function () {
    config([
        'laravel-stateflow.features.permissions' => true,
        'laravel-stateflow.permissions.enabled' => true,
    ]);
    expect(StateFlow::checksPermissions())->toBeTrue();

    config(['laravel-stateflow.features.permissions' => false]);
    expect(StateFlow::checksPermissions())->toBeFalse();
});

it('can reset all static configuration', function () {
    StateFlow::ignoreMigrations();
    StateFlow::useHistoryModel('CustomModel');
    StateFlow::registerStates('Base', ['State1']);

    StateFlow::reset();

    expect(StateFlow::$runsMigrations)->toBeTrue();
    expect(StateFlow::historyModel())->toBe(StateTransitionHistory::class);
    expect(StateFlow::getRegisteredStates('Base'))->toBeEmpty();
});
