<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Contracts;

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;

/**
 * Contract for transition action classes.
 *
 * All custom transitions must implement this interface.
 * Following lorisleiva/laravel-actions pattern.
 */
interface TransitionActionContract
{
    /**
     * Execute the transition.
     */
    public function handle(TransitionData $data): TransitionResult;

    /**
     * Get authorization rules for this transition.
     */
    public function authorize(TransitionData $data): bool;

    /**
     * Get validation rules for transition metadata.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;
}
