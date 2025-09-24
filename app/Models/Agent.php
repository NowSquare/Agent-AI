<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agent represents a specialized worker or role with capabilities.
 *
 * New fields (Phase 2):
 * - cost_hint: rough token/time cost used for allocation scoring
 * - reliability: rolling success ratio in [0,1]
 * - reliability_samples: sample count for the moving average
 */
class Agent extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'name',
        'role',
        'capabilities_json',
        'cost_hint',
        'reliability',
        'reliability_samples',
    ];

    protected function casts(): array
    {
        return [
            'capabilities_json' => 'array',
            'cost_hint' => 'integer',
            'reliability' => 'float',
            'reliability_samples' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function specializations(): HasMany
    {
        return $this->hasMany(AgentSpecialization::class);
    }

    public function hasSpecialization(string $name): bool
    {
        return $this->specializations()
            ->where('name', $name)
            ->where('is_active', true)
            ->exists();
    }
}
