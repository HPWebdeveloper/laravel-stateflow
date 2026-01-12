<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * User stub for testing permissions.
 *
 * Implements Authenticatable interface for testing
 * permission checking functionality.
 */
class User extends Model implements Authenticatable
{
    /**
     * The table associated with the model.
     */
    protected $table = 'users';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>|bool
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Authenticatable Implementation
    // -------------------------------------------------------------------------

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->getAttribute('id');
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): string
    {
        return $this->getAttribute('password') ?? '';
    }

    /**
     * Get the column name for the "remember me" token.
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    /**
     * Set the token value for the "remember me" session.
     */
    public function setRememberToken($value): void
    {
        // Not needed for testing
    }

    /**
     * Get the column name for the "remember me" token.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    /**
     * Get the name of the password attribute for the user.
     */
    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    // -------------------------------------------------------------------------
    // Factory Methods
    // -------------------------------------------------------------------------

    /**
     * Create a user with the given role.
     */
    public static function withRole(string $role, array $attributes = []): self
    {
        return new self(array_merge(['role' => $role], $attributes));
    }

    /**
     * Create an admin user.
     */
    public static function admin(array $attributes = []): self
    {
        return self::withRole('admin', $attributes);
    }

    /**
     * Create an editor user.
     */
    public static function editor(array $attributes = []): self
    {
        return self::withRole('editor', $attributes);
    }

    /**
     * Create an author user.
     */
    public static function author(array $attributes = []): self
    {
        return self::withRole('author', $attributes);
    }

    /**
     * Create a guest user.
     */
    public static function guest(array $attributes = []): self
    {
        return self::withRole('guest', $attributes);
    }
}
