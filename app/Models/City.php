<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['game_id', 'empire_id', 'tile_id', 'name', 'population', 'food_stored', 'production_stored', 'current_build', 'buildings', 'culture', 'is_capital'])]
class City extends Model
{
    protected function casts(): array
    {
        return [
            'buildings' => 'array',
            'is_capital' => 'boolean',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function empire(): BelongsTo
    {
        return $this->belongsTo(Empire::class);
    }

    public function tile(): BelongsTo
    {
        return $this->belongsTo(Tile::class);
    }

    /** Tiles inside this city's borders. */
    public function tiles(): HasMany
    {
        return $this->hasMany(Tile::class);
    }

    public function has(string $building): bool
    {
        return in_array($building, $this->buildings ?? [], true);
    }

    public function foodToGrow(): int
    {
        return 12 + 8 * $this->population;
    }
}
