<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Memory extends Model
{
    use HasUlids;

    protected $fillable = [
        'scope',
        'scope_id',
        'key',
        'value_json',
        'confidence',
        'ttl_category',
        'supersedes_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'scope_id')->when($this->scope === 'account');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'scope_id')->when($this->scope === 'conversation');
    }
}
