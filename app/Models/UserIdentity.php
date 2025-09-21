<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIdentity extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'type',
        'identifier',
        'verified_at',
        'primary',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'primary' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
