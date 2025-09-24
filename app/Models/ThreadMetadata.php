<?php
/**
 * What this file does — Stores extra key/value facts about a thread.
 * Plain: Small add‑on notes (like labels) attached to a conversation.
 * How this fits in:
 * - UI can show metadata chips
 * - Useful for filters and experiments
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreadMetadata extends Model
{
    protected $table = 'thread_metadata';

    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }
}
