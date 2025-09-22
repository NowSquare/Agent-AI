<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentSpecialization extends Model
{
    protected $fillable = [
        'name',
        'description',
        'capabilities',
        'confidence_threshold',
        'is_active',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'confidence_threshold' => 'float',
        'is_active' => 'boolean',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
