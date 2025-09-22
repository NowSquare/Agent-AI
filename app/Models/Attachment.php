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
        'meta_json',
        'summarize_json',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'scanned_at' => 'datetime',
            'extracted_at' => 'datetime',
            'summarized_at' => 'datetime',
            'extract_result_json' => 'array',
            'meta_json' => 'array',
            'summarize_json' => 'array',
        ];
    }

    public function emailMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class);
    }

    // Helper methods for status checks
    public function isClean(): bool
    {
        return $this->scan_status === 'clean';
    }

    public function isInfected(): bool
    {
        return $this->scan_status === 'infected';
    }

    public function isPendingScan(): bool
    {
        return $this->scan_status === 'pending';
    }

    public function isExtracted(): bool
    {
        return $this->extract_status === 'done';
    }

    public function isSummarized(): bool
    {
        return ! empty($this->summarize_json);
    }

    public function canDownload(): bool
    {
        return $this->isClean() && ! empty($this->storage_path);
    }

    public function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function getAttachmentsExcerpt(): string
    {
        if (! $this->isSummarized()) {
            return '';
        }

        $summary = $this->summarize_json;

        // Build excerpt from gist + bullets, capped at ~600 chars
        $excerpt = $summary['gist'] ?? '';

        if (! empty($summary['key_points']) && is_array($summary['key_points'])) {
            $bullets = array_slice($summary['key_points'], 0, 3); // First 3 bullets
            $excerpt .= ' '.implode(' ', $bullets);
        }

        return substr($excerpt, 0, 600);
    }
}
