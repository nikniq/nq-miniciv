<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'seed', 'width', 'height', 'turn', 'max_turns', 'status', 'winner_empire_id', 'events'])]
class Game extends Model
{
    protected function casts(): array
    {
        return [
            'events' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function empires(): HasMany
    {
        return $this->hasMany(Empire::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function tiles(): HasMany
    {
        return $this->hasMany(Tile::class);
    }

    public function playerEmpire(): ?Empire
    {
        return $this->empires->firstWhere('is_ai', false);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
