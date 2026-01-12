<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Contracts\PermissionChecker;
use Hpwebdeveloper\LaravelStateflow\Facades\StateFlow;
use Hpwebdeveloper\LaravelStateflow\LaravelStateflowServiceProvider;

it('registers the service provider', function () {
    expect(app()->getProviders(LaravelStateflowServiceProvider::class))
        ->toHaveCount(1);
});

it('binds PermissionChecker contract to container', function () {
    expect(app()->bound(PermissionChecker::class))->toBeTrue();
    expect(app()->make(PermissionChecker::class))->toBeInstanceOf(PermissionChecker::class);
});

it('registers the facade accessor', function () {
    expect(app()->bound('stateflow'))->toBeTrue();
});

it('facade resolves to StateFlow class', function () {
    $resolved = StateFlow::getFacadeRoot();
    expect($resolved)->toBeInstanceOf(\Hpwebdeveloper\LaravelStateflow\StateFlow::class);
});
