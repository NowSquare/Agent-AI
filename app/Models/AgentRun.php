<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentRun extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id', 'thread_id', 'state', 'round_no',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'round_no' => 'integer',
        ];
    }

    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function thread(): BelongsTo { return $this->belongsTo(Thread::class); }
}


