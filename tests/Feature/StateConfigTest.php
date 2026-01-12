<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\StateConfig;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\PostState;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Rejected;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;

describe('StateConfig', function (): void {
    it('can be created with make factory', function (): void {
        $config = StateConfig::make(PostState::class);

        expect($config)->toBeInstanceOf(StateConfig::class)
            ->and($config->getBaseStateClass())->toBe(PostState::class);
    });

    it('can set default state', function (): void {
        $config = StateConfig::make(PostState::class)
            ->default(Draft::class);

        expect($config->getDefaultStateClass())->toBe(Draft::class);
    });

    it('can set field name', function (): void {
        $config = StateConfig::make(PostState::class)
            ->field('status');

        expect($config->getField())->toBe('status');
    });

    it('defaults to state field', function (): void {
        $config = StateConfig::make(PostState::class);

        expect($config->getField())->toBe('state');
    });

    it('can register a single state', function (): void {
        $config = StateConfig::make(PostState::class)
            ->registerState(Draft::class);

        expect($config->getStates())->toBe([Draft::class]);
    });

    it('can register multiple states', function (): void {
        $config = StateConfig::make(PostState::class)
            ->registerStates([Draft::class, Review::class, Published::class]);

        expect($config->getStates())->toBe([
            Draft::class,
            Review::class,
            Published::class,
        ]);
    });

    it('prevents duplicate state registration', function (): void {
        $config = StateConfig::make(PostState::class)
            ->registerState(Draft::class)
            ->registerState(Draft::class);

        expect($config->getStates())->toBe([Draft::class]);
    });

    it('can allow transition between states', function (): void {
        $config = StateConfig::make(PostState::class)
            ->allowTransition(Draft::class, Review::class);

        expect($config->isTransitionAllowed(Draft::class, Review::class))->toBeTrue()
            ->and($config->isTransitionAllowed(Review::class, Draft::class))->toBeFalse();
    });

    it('can allow multiple transitions from a state', function (): void {
        $config = StateConfig::make(PostState::class)
            ->allowTransitions(Review::class, [Published::class, Rejected::class]);

        expect($config->isTransitionAllowed(Review::class, Published::class))->toBeTrue()
            ->and($config->isTransitionAllowed(Review::class, Rejected::class))->toBeTrue();
    });

    it('can allow transitions from array of transition definitions', function (): void {
        $transitions = [
            ['from' => Draft::class, 'to' => Review::class],
            ['from' => Review::class, 'to' => Published::class],
            ['from' => Review::class, 'to' => Rejected::class],
        ];

        $config = StateConfig::make(PostState::class)
            ->allowTransitionsFromArray($transitions);

        expect($config->isTransitionAllowed(Draft::class, Review::class))->toBeTrue()
            ->and($config->isTransitionAllowed(Review::class, Published::class))->toBeTrue()
            ->and($config->isTransitionAllowed(Review::class, Rejected::class))->toBeTrue();
    });

    it('registers states when using allowTransitionsFromArray', function (): void {
        $transitions = [
            ['from' => Draft::class, 'to' => Review::class],
            ['from' => Review::class, 'to' => Published::class],
        ];

        $config = StateConfig::make(PostState::class)
            ->allowTransitionsFromArray($transitions);

        expect($config->getStates())
            ->toContain(Draft::class)
            ->toContain(Review::class)
            ->toContain(Published::class);
    });

    it('throws exception for invalid transition format in allowTransitionsFromArray', function (): void {
        $invalidTransitions = [
            ['from' => Draft::class], // Missing 'to' key
        ];

        StateConfig::make(PostState::class)
            ->allowTransitionsFromArray($invalidTransitions);
    })->throws(\Hpwebdeveloper\LaravelStateflow\Exceptions\StateConfigurationException::class);

    it('returns allowed transitions', function (): void {
        $config = StateConfig::make(PostState::class)
            ->allowTransition(Draft::class, Review::class)
            ->allowTransition(Review::class, Published::class)
            ->allowTransition(Review::class, Rejected::class);

        $allowed = $config->getAllowedTransitions(Review::class);

        expect($allowed)->toContain(Published::class)
            ->and($allowed)->toContain(Rejected::class)
            ->and($allowed)->toHaveCount(2);
    });

    it('registers states when defining transitions', function (): void {
        $config = StateConfig::make(PostState::class)
            ->allowTransition(Draft::class, Review::class);

        expect($config->getStates())->toContain(Draft::class)
            ->and($config->getStates())->toContain(Review::class);
    });

    it('registers state when setting default', function (): void {
        $config = StateConfig::make(PostState::class)
            ->default(Draft::class);

        expect($config->getStates())->toContain(Draft::class);
    });

    it('can resolve state class from name', function (): void {
        $config = StateConfig::make(PostState::class)
            ->registerStates([Draft::class, Review::class]);

        expect($config->resolveStateClass('draft'))->toBe(Draft::class)
            ->and($config->resolveStateClass('review'))->toBe(Review::class);
    });

    it('can resolve state class from class string', function (): void {
        $config = StateConfig::make(PostState::class)
            ->registerStates([Draft::class]);

        expect($config->resolveStateClass(Draft::class))->toBe(Draft::class);
    });

    it('returns null for unknown state', function (): void {
        $config = StateConfig::make(PostState::class)
            ->registerStates([Draft::class]);

        expect($config->resolveStateClass('unknown'))->toBeNull();
    });

    it('can set custom transition class', function (): void {
        $customTransition = 'App\\Transitions\\PublishPostTransition';

        $config = StateConfig::make(PostState::class)
            ->allowTransition(Review::class, Published::class, $customTransition);

        expect($config->getTransitionClass(Review::class, Published::class))
            ->toBe($customTransition);
    });

    it('returns null for transition without custom class', function (): void {
        $config = StateConfig::make(PostState::class)
            ->allowTransition(Draft::class, Review::class);

        expect($config->getTransitionClass(Draft::class, Review::class))->toBeNull();
    });

    it('falls back to state with isDefault true before first', function (): void {
        $config = StateConfig::make(PostState::class)
            ->registerStates([Review::class, Draft::class]);

        // Draft has IS_DEFAULT = true, so it gets priority
        expect($config->getDefaultStateClass())->toBe(Draft::class);
    });

    it('falls back to first state if none has isDefault', function (): void {
        $config = StateConfig::make(PostState::class)
            ->registerStates([Review::class, Published::class]);

        // Neither has IS_DEFAULT = true, so first one wins
        expect($config->getDefaultStateClass())->toBe(Review::class);
    });
});

describe('StateConfig Validation', function (): void {
    it('throws exception for non-existent class', function (): void {
        StateConfig::make(PostState::class)
            ->registerState('NonExistentClass');
    })->throws(\Hpwebdeveloper\LaravelStateflow\Exceptions\StateConfigurationException::class);

    it('throws exception for class not extending base', function (): void {
        StateConfig::make(PostState::class)
            ->registerState(\stdClass::class);
    })->throws(\Hpwebdeveloper\LaravelStateflow\Exceptions\StateConfigurationException::class);

    it('throws exception when default is not subclass', function (): void {
        StateConfig::make(PostState::class)
            ->default(\stdClass::class);
    })->throws(\Hpwebdeveloper\LaravelStateflow\Exceptions\StateConfigurationException::class);

    it('throws exception when transition from is not subclass', function (): void {
        StateConfig::make(PostState::class)
            ->allowTransition(\stdClass::class, Draft::class);
    })->throws(\Hpwebdeveloper\LaravelStateflow\Exceptions\StateConfigurationException::class);

    it('throws exception when transition to is not subclass', function (): void {
        StateConfig::make(PostState::class)
            ->allowTransition(Draft::class, \stdClass::class);
    })->throws(\Hpwebdeveloper\LaravelStateflow\Exceptions\StateConfigurationException::class);
});
