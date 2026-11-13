<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'google_id', 'avatar', 'is_guest', 'achievements', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /** A throwaway account made by "Play now"; can be claimed by registering. */
    public function isGuest(): bool
    {
        return (bool) $this->is_guest;
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /** May open the admin area. Never mass-assignable: set with forceFill or `php artisan admin:grant`. */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin && ! $this->isGuest();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_guest' => 'boolean',
            'is_admin' => 'boolean',
            'achievements' => 'array',
            'password' => 'hashed',
        ];
    }
}
