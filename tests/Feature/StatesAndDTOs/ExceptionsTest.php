<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Exceptions\InvalidStateException;
use Hpwebdeveloper\LaravelStateflow\Exceptions\StateConfigurationException;
use Hpwebdeveloper\LaravelStateflow\Exceptions\TransitionNotAllowedException;

describe('InvalidStateException', function () {
    it('creates unknown state exception', function () {
        $exception = InvalidStateException::unknownState('invalid', 'App\\States\\PostState');

        expect($exception)->toBeInstanceOf(InvalidStateException::class);
        expect($exception->getMessage())->toContain("Unknown state 'invalid'");
        expect($exception->getMessage())->toContain('PostState');
    });

    it('creates invalid value exception for object', function () {
        $exception = InvalidStateException::invalidValue(new stdClass);

        expect($exception->getMessage())->toContain('stdClass');
        expect($exception->getMessage())->toContain('Invalid state value of type');
    });

    it('creates invalid value exception for scalar', function () {
        $exception = InvalidStateException::invalidValue(123);

        expect($exception->getMessage())->toContain('integer');
    });
});

describe('TransitionNotAllowedException', function () {
    it('creates not in allowed transitions exception', function () {
        $exception = TransitionNotAllowedException::notInAllowedTransitions(
            'draft',
            'published',
            'App\\Models\\Post'
        );

        expect($exception)->toBeInstanceOf(TransitionNotAllowedException::class);
        expect($exception->getMessage())->toContain('draft');
        expect($exception->getMessage())->toContain('published');
        expect($exception->getMessage())->toContain('Post');
    });

    it('creates insufficient permission exception', function () {
        $exception = TransitionNotAllowedException::insufficientPermission(
            'published',
            'guest'
        );

        expect($exception->getMessage())->toContain('guest');
        expect($exception->getMessage())->toContain('published');
        expect($exception->getMessage())->toContain('permission');
    });
});

describe('StateConfigurationException', function () {
    it('creates missing configuration exception', function () {
        $exception = StateConfigurationException::missingConfiguration(
            'App\\Models\\Post',
            'State field not defined'
        );

        expect($exception)->toBeInstanceOf(StateConfigurationException::class);
        expect($exception->getMessage())->toContain('Post');
        expect($exception->getMessage())->toContain('State field not defined');
    });

    it('creates invalid state class exception', function () {
        $exception = StateConfigurationException::invalidStateClass('App\\States\\Invalid');

        expect($exception->getMessage())->toContain('Invalid');
        expect($exception->getMessage())->toContain('StateContract');
    });
});
