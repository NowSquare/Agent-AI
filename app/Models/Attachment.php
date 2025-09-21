<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    use HasUlids;

    protected $table = 'email_attachments';

    protected $fillable = [
        'email_message_id',
        'filename',
        'mime',
        'size_bytes',
        'storage_disk',
        'storage_path',
        'scan_status',
        'scan_result',
        'scanned_at',
        'extract_status',
        'extract_result_json',
        'extracted_at',
        'summary_text',
        'summarized_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'scanned_at' => 'datetime',
            'extracted_at' => 'datetime',
            'summarized_at' => 'datetime',
            'extract_result_json' => 'array',
        ];
    }

    public function emailMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class);
    }
}
