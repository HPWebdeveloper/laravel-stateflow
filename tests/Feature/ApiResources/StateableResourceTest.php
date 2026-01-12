<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Http\Resources\Concerns\StateableResource;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Create test resource using the trait
class TestPostResource extends JsonResource
{
    use StateableResource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            ...$this->stateData($request),
        ];
    }
}

class TestPostResourceNested extends JsonResource
{
    use StateableResource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'state_info' => $this->stateResource($request),
        ];
    }
}

class TestPostResourceMinimal extends JsonResource
{
    use StateableResource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            ...$this->stateMinimal($request),
        ];
    }
}

beforeEach(function () {
    $this->createPostsTable();
    Post::resetStateRegistration();
});

describe('StateableResource trait', function () {
    it('includes state data in resource', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = new TestPostResource($post);
        $array = $resource->toArray($request);

        expect($array)->toHaveKeys([
            'id',
            'title',
            'state',
            'state_title',
            'state_color',
            'state_icon',
            'next_states',
        ]);
        expect($array['state'])->toBe('draft');
        expect($array['state_title'])->toBe('Draft');
        expect($array['state_color'])->toBe('primary');
    });

    it('includes next states in resource', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = new TestPostResource($post);
        $array = $resource->toArray($request);

        expect($array['next_states'])->toBeArray();
        expect(count($array['next_states']))->toBeGreaterThan(0);
    });

    it('next states have UI format', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = new TestPostResource($post);
        $array = $resource->toArray($request);

        $nextState = $array['next_states'][0];
        expect($nextState)->toHaveKeys(['name', 'title', 'color', 'icon', 'description']);
    });

    it('handles state resource nested format', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = new TestPostResourceNested($post);
        $array = $resource->toArray($request);

        expect($array)->toHaveKeys(['id', 'title', 'state_info']);
        expect($array['state_info'])->toHaveKeys(['current', 'available']);
        expect($array['state_info']['current']['name'])->toBe('draft');
        expect($array['state_info']['available'])->toBeArray();
    });

    it('handles minimal state format', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = new TestPostResourceMinimal($post);
        $array = $resource->toArray($request);

        expect($array)->toHaveKeys(['id', 'state']);
        expect($array['state'])->toBe('draft');
        expect($array)->not->toHaveKey('state_title');
    });

    it('filters next states by authenticated user', function () {
        $user = new User(['id' => 1, 'role' => 'admin']);
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $resource = new TestPostResource($post);
        $array = $resource->toArray($request);

        expect($array['next_states'])->toBeArray();
    });

    it('handles null state gracefully', function () {
        $post = Post::create(['title' => 'Test Post']);
        // Force state to null
        $post->state = null;
        $post->saveQuietly();
        $post->refresh();

        // Force state to remain null
        $post = new Post(['id' => 999, 'title' => 'No State']);
        $post->exists = true;
        // Manually set state attribute
        $post->setAttribute('state', null);

        $request = Request::create('/');

        // Test minimal format which should handle null state
        $resource = new TestPostResourceMinimal($post);
        $array = $resource->toArray($request);

        expect($array['state'])->toBeNull();
    });

    it('includes state icon when available', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = new TestPostResource($post);
        $array = $resource->toArray($request);

        // Icon may be null depending on state configuration
        expect($array)->toHaveKey('state_icon');
    });

    it('available states are in UI format in nested resource', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = new TestPostResourceNested($post);
        $array = $resource->toArray($request);

        if (! empty($array['state_info']['available'])) {
            $availableState = $array['state_info']['available'][0];
            expect($availableState)->toHaveKeys(['name', 'title', 'color', 'icon', 'description']);
        }
    });
});
