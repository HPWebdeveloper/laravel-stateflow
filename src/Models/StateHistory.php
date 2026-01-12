<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Represents a single state transition in history.
 *
 * Provides comprehensive audit trail for state changes with filtering,
 * performer tracking, and metadata support.
 *
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property string $field
 * @property string $from_state
 * @property string $to_state
 * @property int|null $performer_id
 * @property string|null $performer_type
 * @property string|null $reason
 * @property array|null $metadata
 * @property string|null $transition_class
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static Builder forModel(Model $model)
 * @method static Builder forModelType(string $modelType)
 * @method static Builder forField(string $field)
 * @method static Builder fromState(string $state)
 * @method static Builder toState(string $state)
 * @method static Builder byPerformer(Authenticatable|int $performer)
 * @method static Builder betweenDates($startDate, $endDate)
 * @method static Builder today()
 * @method static Builder lastDays(int $days)
 * @method static Builder latestFirst()
 * @method static Builder oldestFirst()
 * @method static Builder withTransitionClass(string $class)
 * @method static Builder automated()
 * @method static Builder manual()
 */
class StateHistory extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'state_histories';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Get the table name from config.
     */
    public function getTable(): string
    {
        return config('laravel-stateflow.history.table', 'state_histories');
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Get the model that this history entry belongs to.
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the performer who made this transition.
     */
    public function performer(): MorphTo
    {
        return $this->morphTo();
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope to filter by model instance.
     */
    public function scopeForModel(Builder $query, Model $model): Builder
    {
        return $query->where('model_type', get_class($model))
            ->where('model_id', $model->getKey());
    }

    /**
     * Scope to filter by model type.
     */
    public function scopeForModelType(Builder $query, string $modelType): Builder
    {
        return $query->where('model_type', $modelType);
    }

    /**
     * Scope to filter by field name.
     */
    public function scopeForField(Builder $query, string $field): Builder
    {
        return $query->where('field', $field);
    }

    /**
     * Scope to filter by from state.
     */
    public function scopeFromState(Builder $query, string $state): Builder
    {
        return $query->where('from_state', $state);
    }

    /**
     * Scope to filter by to state.
     */
    public function scopeToState(Builder $query, string $state): Builder
    {
        return $query->where('to_state', $state);
    }

    /**
     * Scope to filter by performer.
     */
    public function scopeByPerformer(Builder $query, Authenticatable|int $performer): Builder
    {
        if (is_object($performer)) {
            return $query->where('performer_id', $performer->getAuthIdentifier())
                ->where('performer_type', get_class($performer));
        }

        return $query->where('performer_id', $performer);
    }

    /**
     * Scope to filter by date range.
     *
     * @param  \DateTimeInterface|string  $startDate
     * @param  \DateTimeInterface|string  $endDate
     */
    public function scopeBetweenDates(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter transitions that happened today.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope to filter transitions in the last N days.
     */
    public function scopeLastDays(Builder $query, int $days): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to order by most recent first.
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope to order by oldest first.
     */
    public function scopeOldestFirst(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'asc');
    }

    /**
     * Scope to filter by transition class.
     */
    public function scopeWithTransitionClass(Builder $query, string $class): Builder
    {
        return $query->where('transition_class', $class);
    }

    /**
     * Scope for automated transitions (no performer).
     */
    public function scopeAutomated(Builder $query): Builder
    {
        return $query->whereNull('performer_id');
    }

    /**
     * Scope for manual transitions (has performer).
     */
    public function scopeManual(Builder $query): Builder
    {
        return $query->whereNotNull('performer_id');
    }

    // -------------------------------------------------------------------------
    // Helper Methods
    // -------------------------------------------------------------------------

    /**
     * Check if this transition was performed by a specific user.
     */
    public function wasPerformedBy(Authenticatable|int $user): bool
    {
        $userId = is_object($user) ? $user->getAuthIdentifier() : $user;

        return $this->performer_id === $userId;
    }

    /**
     * Get the duration since this transition occurred.
     */
    public function getTimeSinceTransition(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Check if this was an automated transition (no performer).
     */
    public function isAutomated(): bool
    {
        return $this->performer_id === null;
    }

    /**
     * Check if this was a manual transition (has performer).
     */
    public function isManual(): bool
    {
        return $this->performer_id !== null;
    }

    /**
     * Get metadata value by key.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function getMetadataValue(string $key, $default = null)
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Check if metadata contains a key.
     */
    public function hasMetadata(string $key): bool
    {
        return data_get($this->metadata, $key) !== null;
    }

    /**
     * Get a summary string of this transition.
     */
    public function getSummary(): string
    {
        $performer = $this->performer?->name ?? $this->performer?->email ?? 'System';

        return sprintf(
            '%s changed %s from %s to %s on %s',
            $performer,
            $this->field,
            $this->from_state,
            $this->to_state,
            $this->created_at->format('Y-m-d H:i:s')
        );
    }

    /**
     * Convert to array for API responses.
     *
     * @return array<string, mixed>
     */
    public function toSummaryArray(): array
    {
        return [
            'id' => $this->id,
            'from' => $this->from_state,
            'to' => $this->to_state,
            'field' => $this->field,
            'performer' => $this->performer?->name ?? $this->performer?->email ?? 'System',
            'performer_id' => $this->performer_id,
            'reason' => $this->reason,
            'date' => $this->created_at->toDateTimeString(),
            'human_date' => $this->created_at->diffForHumans(),
        ];
    }
}
