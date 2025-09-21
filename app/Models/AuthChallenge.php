<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthChallenge extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_identity_id',
        'identifier',
        'channel',
        'code_hash',
        'token',
        'expires_at',
        'consumed_at',
        'attempts',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function userIdentity(): BelongsTo
    {
        return $this->belongsTo(UserIdentity::class);
    }
}
