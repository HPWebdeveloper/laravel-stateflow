<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\DTOs\StateData;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;

describe('StateData DTO', function () {
    it('creates from state class', function () {
        $data = StateData::fromStateClass(Draft::class);

        expect($data->name)->toBe('draft');
        expect($data->title)->toBe('Draft');
        expect($data->color)->toBe('primary');
    });

    it('creates with constructor', function () {
        $data = new StateData(
            name: 'custom',
            title: 'Custom State',
            color: 'info',
            icon: 'star',
            description: 'A custom state',
        );

        expect($data->name)->toBe('custom');
        expect($data->title)->toBe('Custom State');
        expect($data->color)->toBe('info');
        expect($data->icon)->toBe('star');
        expect($data->description)->toBe('A custom state');
    });

    it('converts to array with all fields', function () {
        $data = StateData::fromStateClass(Draft::class);
        $array = $data->toArray();

        expect($array)->toHaveKeys([
            'name',
            'title',
            'color',
            'icon',
            'description',
            'allowed_transitions',
            'permitted_roles',
            'metadata',
        ]);
        expect($array['name'])->toBe('draft');
    });

    it('converts to resource array with UI fields only', function () {
        $data = StateData::fromStateClass(Draft::class);
        $resource = $data->toResource();

        expect($resource)->toHaveKeys(['name', 'title', 'color', 'icon', 'description']);
        expect($resource)->not->toHaveKeys(['allowed_transitions', 'permitted_roles']);
    });

    it('includes allowed transitions in array', function () {
        $data = StateData::fromStateClass(Draft::class);
        $array = $data->toArray();

        expect($array['allowed_transitions'])->toBeArray();
    });

    it('includes permitted roles in array', function () {
        $data = StateData::fromStateClass(Draft::class);
        $array = $data->toArray();

        expect($array['permitted_roles'])->toBe(['admin', 'author']);
    });
});

describe('TransitionData DTO', function () {
    it('creates via constructor', function () {
        $post = new Post(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Published::class,
            reason: 'Publishing content'
        );

        expect($data->model)->toBe($post);
        expect($data->field)->toBe('state');
        expect($data->fromState)->toBe(Draft::class);
        expect($data->toState)->toBe(Published::class);
        expect($data->reason)->toBe('Publishing content');
    });

    it('creates via make method', function () {
        $post = new Post(['title' => 'Test', 'state' => 'draft']);

        $data = TransitionData::make(
            model: $post,
            field: 'state',
            toState: Published::class,
            reason: 'Testing'
        );

        expect($data->model)->toBe($post);
        expect($data->toState)->toBe(Published::class);
        expect($data->reason)->toBe('Testing');
    });

    it('stores metadata', function () {
        $post = new Post(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Published::class,
            metadata: ['scheduled_at' => '2024-01-15']
        );

        expect($data->metadata)->toBe(['scheduled_at' => '2024-01-15']);
    });
});

describe('TransitionResult DTO', function () {
    it('creates success result', function () {
        $post = new Post(['title' => 'Test']);

        $result = TransitionResult::success($post, 'draft', 'published');

        expect($result->succeeded())->toBeTrue();
        expect($result->failed())->toBeFalse();
        expect($result->success)->toBeTrue();
        expect($result->model)->toBe($post);
        expect($result->fromState)->toBe('draft');
        expect($result->toState)->toBe('published');
        expect($result->error)->toBeNull();
    });

    it('creates failure result', function () {
        $result = TransitionResult::failure('Transition not allowed');

        expect($result->succeeded())->toBeFalse();
        expect($result->failed())->toBeTrue();
        expect($result->success)->toBeFalse();
        expect($result->error)->toBe('Transition not allowed');
        expect($result->model)->toBeNull();
    });

    it('includes metadata in success result', function () {
        $post = new Post(['title' => 'Test']);

        $result = TransitionResult::success(
            $post,
            'draft',
            'published',
            ['transitioned_by' => 'admin']
        );

        expect($result->metadata)->toBe(['transitioned_by' => 'admin']);
    });

    it('includes metadata in failure result', function () {
        $result = TransitionResult::failure(
            'Permission denied',
            ['required_role' => 'admin']
        );

        expect($result->metadata)->toBe(['required_role' => 'admin']);
    });
});
