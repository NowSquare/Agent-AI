<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityVote extends Model
{
    use HasUlids;

    protected $fillable = [
        'poll_id',
        'type',
        'user_id',
        'contact_id',
        'choices_json',
    ];

    protected function casts(): array
    {
        return [
            'choices_json' => 'array',
        ];
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(AvailabilityPoll::class, 'poll_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
