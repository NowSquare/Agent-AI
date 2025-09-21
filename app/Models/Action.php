<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Action extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'thread_id',
        'type',
        'payload_json',
        'status',
        'expires_at',
        'completed_at',
        'clarification_rounds',
        'clarification_max',
        'last_clarification_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_clarification_sent_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }
}
