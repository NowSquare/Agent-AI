<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttachmentExtraction extends Model
{
    use HasUlids;

    protected $fillable = [
        'attachment_id',
        'text_excerpt',
        'text_disk',
        'text_path',
        'text_bytes',
        'pages',
        'summary_json',
    ];

    protected function casts(): array
    {
        return [
            'summary_json' => 'array',
        ];
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }
}
