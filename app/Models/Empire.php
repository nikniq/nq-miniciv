<?php

namespace App\Models;

use App\Game\Rules;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['game_id', 'user_id', 'name', 'color', 'is_ai', 'eliminated', 'gold', 'settlers', 'army', 'units', 'golden_age_until', 'golden_points', 'government', 'anarchy_until', 'captures', 'technologies', 'current_tech', 'research_progress', 'score'])]
class Empire extends Model
{
    protected function casts(): array
    {
        return [
            'is_ai' => 'boolean',
            'eliminated' => 'boolean',
            'technologies' => 'array',
            'units' => 'array',
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

    /** Add a unit to the army. */
    public function addUnit(string $key): void
    {
        $units = $this->units ?? [];
        $units[$key] = ($units[$key] ?? 0) + 1;
        $this->units = $units;
        $this->army += Rules::UNITS[$key]['strength'];
    }

    /**
     * Lose $strength points of army. Weakest units fall first; the last unit
     * hit is removed whole, so losses round against the army. Strength not
     * backed by a recorded unit (older games) is spent last.
     */
    public function loseStrength(int $strength): void
    {
        if ($strength <= 0) {
            return;
        }
        $units = $this->units ?? [];
        $untracked = max(0, $this->army - $this->unitsStrength());
        $remaining = $strength;
        foreach (array_keys(Rules::UNITS) as $key) { // weakest to strongest
            while ($remaining > 0 && ($units[$key] ?? 0) > 0) {
                $units[$key]--;
                $remaining -= Rules::UNITS[$key]['strength'];
            }
            if (($units[$key] ?? 0) === 0) {
                unset($units[$key]);
            }
        }
        if ($remaining > 0) {
            $untracked = max(0, $untracked - $remaining);
        }
        $this->units = $units;
        $this->army = $this->unitsStrength() + $untracked;
    }

    public function unitsStrength(): int
    {
        $sum = 0;
        foreach ($this->units ?? [] as $key => $n) {
            $sum += (Rules::UNITS[$key]['strength'] ?? 0) * $n;
        }

        return $sum;
    }

    /** Strength stationed in each city: the army spread evenly. */
    public function garrison(): int
    {
        return intdiv($this->army, max(1, $this->cities->count()));
    }

    public function inAnarchy(int $turn): bool
    {
        return $this->anarchy_until >= $turn;
    }

    public function inGoldenAge(int $turn): bool
    {
        return $this->golden_age_until >= $turn;
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
