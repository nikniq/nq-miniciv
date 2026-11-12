<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'seed', 'width', 'height', 'turn', 'max_turns', 'difficulty', 'status', 'winner_empire_id', 'events', 'choice', 'raids', 'quests', 'diplomacy', 'history', 'log', 'replay'])]
class Game extends Model
{
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'choice' => 'array',
            'raids' => 'array',
            'quests' => 'array',
            'diplomacy' => 'array',
            'history' => 'array',
            'log' => 'array',
            'replay' => 'array',
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

    /** Diplomatic state between two empires: 'war' or 'peace'. */
    public function relation(int $a, int $b): array
    {
        $key = min($a, $b).'-'.max($a, $b);

        return ($this->diplomacy ?? [])[$key] ?? ['state' => 'peace', 'since' => 0];
    }

    public function setDiplomacy(int $a, int $b, string $state): void
    {
        $key = min($a, $b).'-'.max($a, $b);
        $diplomacy = $this->diplomacy ?? [];
        $attitude = $diplomacy[$key]['attitude'] ?? 0;
        $diplomacy[$key] = ['state' => $state, 'since' => $this->turn, 'attitude' => $attitude];
        $this->diplomacy = $diplomacy;
    }

    /** Attitude of the pair, -100 (hostile) to 100 (devoted). */
    public function attitude(int $a, int $b): int
    {
        return (int) ($this->relation($a, $b)['attitude'] ?? 0);
    }

    public function adjustAttitude(int $a, int $b, int $delta): void
    {
        $key = min($a, $b).'-'.max($a, $b);
        $diplomacy = $this->diplomacy ?? [];
        $rel = $diplomacy[$key] ?? ['state' => 'peace', 'since' => 0];
        $rel['attitude'] = max(-100, min(100, (int) ($rel['attitude'] ?? 0) + $delta));
        $diplomacy[$key] = $rel;
        $this->diplomacy = $diplomacy;
    }

    /** Allied pairs fight together and share maps. Stored on the relation; war clears it. */
    public function allied(int $a, int $b): bool
    {
        return (bool) ($this->relation($a, $b)['allied'] ?? false);
    }

    public function setAllied(int $a, int $b, bool $allied): void
    {
        $key = min($a, $b).'-'.max($a, $b);
        $diplomacy = $this->diplomacy ?? [];
        $rel = $diplomacy[$key] ?? ['state' => 'peace', 'since' => 0];
        if ($allied) {
            $rel['allied'] = true;
        } else {
            unset($rel['allied']);
        }
        $diplomacy[$key] = $rel;
        $this->diplomacy = $diplomacy;
    }

    /** Names of the empires allied with $id (excluding $except). */
    public function alliesOf(int $id, ?int $except = null): array
    {
        $names = [];
        foreach ($this->empires as $e) {
            if ($e->id !== $id && $e->id !== $except && ! $e->eliminated && $this->allied($id, $e->id)) {
                $names[] = $e->name;
            }
        }

        return $names;
    }

    public function atWar(int $a, int $b): bool
    {
        return $this->relation($a, $b)['state'] === 'war';
    }

    /**
     * How a finished game was decided, read from its headline:
     * [icon, label] such as ['⛵', 'Science'], or null while it is running.
     */
    public function victoryKind(): ?array
    {
        if ($this->isActive()) {
            return null;
        }
        $head = ($this->events ?? [])[0] ?? '';

        return match (true) {
            str_contains($head, 'Grand Expedition') || str_contains($head, 'Science victory') => ['⛵', 'Science'],
            str_contains($head, 'Grand Alliance') || str_contains($head, 'Diplomatic victory') => ['🤝', 'Diplomacy'],
            str_contains($head, 'dominates') || str_contains($head, 'rules most of the known world') => ['🗺️', 'Domination'],
            str_contains($head, 'ultur') => ['🎭', 'Culture'],
            str_contains($head, 'has fallen') || str_contains($head, 'no more') => ['💀', 'Conquered'],
            default => ['⭐', 'Score'],
        };
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
