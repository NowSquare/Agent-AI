<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class EmailInboundPayload extends Model
{
    use HasUlids;

    protected $fillable = [
        'provider',
        'ciphertext',
        'meta_json',
        'signature_verified',
        'remote_ip',
        'content_length',
        'received_at',
        'purge_after',
    ];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
            'signature_verified' => 'boolean',
            'received_at' => 'datetime',
            'purge_after' => 'datetime',
        ];
    }
}
