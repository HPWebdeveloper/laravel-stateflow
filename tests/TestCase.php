<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Tests;

use Hpwebdeveloper\LaravelStateflow\LaravelStateflowServiceProvider;
use Hpwebdeveloper\LaravelStateflow\StateFlow;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Compatibility shim for older/newer Testbench/Laravel versions that
     * expect this static property to exist on the concrete test case.
     * Some Testbench versions reset static::$latestResponse in tearDown().
     *
     * @var mixed
     */
    public static $latestResponse;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset static state between tests
        StateFlow::reset();

        // Reset Post model state registration
        Post::resetStateRegistration();
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelStateflowServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Configure SQLite in-memory by default
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', ':memory:'),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    /**
     * Create the posts table for testing.
     */
    protected function createPostsTable(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->string('state')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Create the state_histories table for testing.
     */
    protected function createStateHistoriesTable(): void
    {
        Schema::create('state_histories', function (Blueprint $table): void {
            $table->id();
            $table->morphs('model');
            $table->string('field', 50)->default('state');
            $table->string('from_state', 100);
            $table->string('to_state', 100);
            $table->unsignedBigInteger('performer_id')->nullable();
            $table->string('performer_type')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->string('transition_class')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Create the users table for testing.
     */
    protected function createUsersTable(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->nullable();
            $table->timestamps();
        });
    }
}
