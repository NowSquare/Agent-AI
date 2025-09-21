<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvailabilityPoll extends Model
{
    use HasUlids;

    protected $fillable = [
        'thread_id',
        'options_json',
        'status',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'options_json' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(AvailabilityVote::class, 'poll_id');
    }
}
