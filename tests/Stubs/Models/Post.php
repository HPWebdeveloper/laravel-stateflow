<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models;

use Hpwebdeveloper\LaravelStateflow\Concerns\HasStateHistory;
use Hpwebdeveloper\LaravelStateflow\HasStates;
use Hpwebdeveloper\LaravelStateflow\HasStatesContract;
use Hpwebdeveloper\LaravelStateflow\StateConfig;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\PostState;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Rejected;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;
use Illuminate\Database\Eloquent\Model;

/**
 * Post model stub for testing.
 *
 * @property PostState|null $state
 */
class Post extends Model implements HasStatesContract
{
    use HasStateHistory;
    use HasStates;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'posts';

    /**
     * Register state configurations.
     */
    public static function registerStates(): void
    {
        static::addState('state', StateConfig::make(PostState::class)
            ->default(Draft::class)
            ->registerStates([
                Draft::class,
                Review::class,
                Published::class,
                Rejected::class,
            ])
            ->allowTransition(Draft::class, Review::class)
            ->allowTransition(Review::class, Published::class)
            ->allowTransition(Review::class, Rejected::class)
            ->allowTransition(Rejected::class, Draft::class)
        );
    }
}
