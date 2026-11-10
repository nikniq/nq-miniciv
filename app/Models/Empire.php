<?php

namespace App\Models;

use App\Game\Rules;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['game_id', 'user_id', 'name', 'color', 'is_ai', 'eliminated', 'gold', 'settlers', 'army', 'technologies', 'current_tech', 'research_progress', 'score'])]
class Empire extends Model
{
    protected function casts(): array
    {
        return [
            'is_ai' => 'boolean',
            'eliminated' => 'boolean',
            'technologies' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function tiles(): HasMany
    {
        return $this->hasMany(Tile::class);
    }

    public function knows(string $tech): bool
    {
        return in_array($tech, $this->technologies ?? [], true);
    }

    /** Techs whose prerequisites are all known and which are not yet known. */
    public function availableTechs(): array
    {
        $known = $this->technologies ?? [];
        $out = [];
        foreach (Rules::TECHS as $key => $tech) {
            if (in_array($key, $known, true)) {
                continue;
            }
            if (array_diff($tech['requires'], $known) === []) {
                $out[$key] = $tech;
            }
        }

        return $out;
    }

    /** Wonders this empire owns, in any of its cities. */
    public function wonders(): array
    {
        $wonders = [];
        foreach ($this->cities as $city) {
            foreach ($city->buildings ?? [] as $b) {
                if (Rules::BUILDINGS[$b]['wonder'] ?? false) {
                    $wonders[] = $b;
                }
            }
        }

        return $wonders;
    }
}
