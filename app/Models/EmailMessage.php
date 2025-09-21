<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailMessage extends Model
{
    use HasUlids;

    protected $fillable = [
        'thread_id',
        'direction',
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
