<?php

namespace App\Models;

use App\Game\Rules;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['game_id', 'x', 'y', 'terrain', 'resource', 'camp', 'river', 'feature', 'empire_id', 'city_id'])]
class Tile extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['camp' => 'boolean', 'river' => 'boolean'];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function empire(): BelongsTo
    {
        return $this->belongsTo(Empire::class);
    }

    /** The city whose borders contain this tile. */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** The city built on this tile, if any. */
    public function cityHere(): HasOne
    {
        return $this->hasOne(City::class, 'tile_id');
    }

    public function isLand(): bool
    {
        return Rules::TERRAIN[$this->terrain]['land'];
    }

    public function canSettle(): bool
    {
        return Rules::TERRAIN[$this->terrain]['settle'] && $this->feature === null;
    }

    public function distanceTo(Tile $other): int
    {
        return max(abs($this->x - $other->x), abs($this->y - $other->y));
    }
}
