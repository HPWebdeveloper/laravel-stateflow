<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Facades;

use Hpwebdeveloper\LaravelStateflow\Testing\StateFlowFake;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Hpwebdeveloper\LaravelStateflow\StateFlow ignoreMigrations()
 * @method static \Hpwebdeveloper\LaravelStateflow\StateFlow useHistoryModel(string $model)
 * @method static string historyModel()
 * @method static void permissionCheckerUsing(string $class)
 * @method static \Hpwebdeveloper\LaravelStateflow\StateFlow registerStates(string $baseStateClass, array<class-string<\Hpwebdeveloper\LaravelStateflow\Contracts\StateContract>> $stateClasses)
 * @method static array<class-string<\Hpwebdeveloper\LaravelStateflow\Contracts\StateContract>> getRegisteredStates(string $baseStateClass)
 * @method static \Hpwebdeveloper\LaravelStateflow\StateFlow registerTransition(string $fromState, string $toState, string $transitionClass)
 * @method static string|null getTransitionClass(string $fromState, string $toState)
 * @method static bool hasFeature(string $feature)
 * @method static bool recordsHistory()
 * @method static bool checksPermissions()
 * @method static \Hpwebdeveloper\LaravelStateflow\Testing\StateFlowFake fake()
 *
 * @see \Hpwebdeveloper\LaravelStateflow\StateFlow
 */
class StateFlow extends Facade
{
    /**
     * Replace the bound instance with a fake for testing.
     */
    public static function fake(): StateFlowFake
    {
        static::swap($fake = new StateFlowFake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return 'stateflow';
    }
}
