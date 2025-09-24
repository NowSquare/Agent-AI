<?php
/**
 * What this file does — Stores one email in the conversation.
 * Plain: One row per message with who sent it, body, and status.
 * How this fits in:
 * - Threads are made of many EmailMessages
 * - Attachments and processing status live here
 * - Embeddings for search may be added via migration (pgvector)
 * Key terms: direction (inbound/outbound), processing_status
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Purpose: Represent a single email with metadata and relationships.
 * Responsibilities:
 * - Track delivery/processing states
 * - Link to attachments and its thread
 * Collaborators: Thread, Attachment
 */
class EmailMessage extends Model
{
    use HasUlids;

    protected $fillable = [
        'thread_id',
        'direction',
        'processing_status',
        'message_id',
        'in_reply_to',
        'references',
        'from_email',
        'from_name',
        'to_json',
        'cc_json',
        'bcc_json',
        'subject',
        'body_text',
        'body_html',
        'headers_json',
        'delivery_status',
        'delivered_at',
        'clean_reply_text',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'to_json' => 'array',
            'cc_json' => 'array',
            'bcc_json' => 'array',
            'headers_json' => 'array',
            'delivered_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
