<?php

namespace App\Models;

use App\Game\Rules;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['game_id', 'user_id', 'name', 'color', 'is_ai', 'eliminated', 'gold', 'settlers', 'army', 'units', 'veterancy', 'golden_age_until', 'golden_points', 'great_progress', 'great_people', 'great_count', 'explored', 'government', 'trait', 'belief', 'luxuries', 'anarchy_until', 'captures', 'technologies', 'current_tech', 'research_progress', 'score'])]
class Empire extends Model
{
    protected function casts(): array
    {
        return [
            'is_ai' => 'boolean',
            'eliminated' => 'boolean',
            'technologies' => 'array',
            'units' => 'array',
            'great_progress' => 'array',
            'great_people' => 'array',
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
        if ($this->army === 0) {
            $this->veterancy = 0; // a destroyed army's experience dies with it
        }
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

    /** Veterancy rank, 0 (Green) to 3 (Elite). */
    public function rank(): int
    {
        $rank = 0;
        foreach (array_keys(Rules::VETERAN_RANKS) as $i => $xp) {
            if ((int) $this->veterancy >= $xp) {
                $rank = $i;
            }
        }

        return $rank;
    }

    public function rankName(): string
    {
        return array_values(Rules::VETERAN_RANKS)[$this->rank()];
    }

    /** Experience needed for the next rank, or null at Elite. */
    public function nextRankAt(): ?int
    {
        return array_keys(Rules::VETERAN_RANKS)[$this->rank() + 1] ?? null;
    }

    /** Fighting strength: army size lifted by veterancy. */
    public function strength(): int
    {
        return (int) floor($this->army * (1 + Rules::VETERAN_BONUS * $this->rank()));
    }

    public function gainVeterancy(int $xp): void
    {
        $this->veterancy = min(60, (int) $this->veterancy + $xp * ($this->belief === 'war' ? 2 : 1));
    }

    /** Unit types weaker than the best one known: [from => [to, count, gold]]. */
    public function upgrades(): array
    {
        $best = null;
        foreach (Rules::UNITS as $key => $u) {
            if (! $u['tech'] || $this->knows($u['tech'])) {
                $best = $key; // listed weakest to strongest
            }
        }
        $out = [];
        foreach ($this->units ?? [] as $key => $n) {
            if ($best && $key !== $best && $n > 0 && Rules::UNITS[$key]['strength'] < Rules::UNITS[$best]['strength']) {
                $out[$key] = ['to' => $best, 'n' => $n, 'gold' => $n * max(1, Rules::UNITS[$best]['cost'] - Rules::UNITS[$key]['cost']) * Rules::UPGRADE_RATE];
            }
        }

        return $out;
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

    /** Great-person points needed for the next arrival; each one costs more. */
    public function greatPointsNeeded(): int
    {
        return Rules::GREAT_POINTS_BASE + Rules::GREAT_POINTS_STEP * (int) $this->great_count;
    }

    public function greatPointsTotal(): int
    {
        return (int) array_sum($this->great_progress ?? []);
    }

    /** Add great-person points of one kind; returns the kind that arrives, if any. */
    public function addGreatPoints(array $points): ?string
    {
        $progress = $this->great_progress ?? [];
        foreach ($points as $kind => $n) {
            $progress[$kind] = ($progress[$kind] ?? 0) + $n;
        }
        $this->great_progress = $progress;
        if (array_sum($progress) < $this->greatPointsNeeded()) {
            return null;
        }
        arsort($progress);
        $kind = array_key_first($progress);
        $this->great_progress = [];
        $this->great_count = (int) $this->great_count + 1;
        $people = $this->great_people ?? [];
        $people[] = $kind;
        $this->great_people = $people;

        return $kind;
    }

    /** Remove one great person of a kind from the court; false if none. */
    public function takeGreatPerson(string $kind): bool
    {
        $people = $this->great_people ?? [];
        $i = array_search($kind, $people, true);
        if ($i === false) {
            return false;
        }
        unset($people[$i]);
        $this->great_people = array_values($people);

        return true;
    }
}
