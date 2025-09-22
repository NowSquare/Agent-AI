<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Thread extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = [
        'account_id',
        'subject',
        'starter_message_id',
        'context_json',
    ];

    protected function casts(): array
    {
        return [
            'context_json' => 'array',
            'version_history' => 'array',
            'last_activity_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function starterMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'starter_message_id');
    }

    public function emailMessages(): HasMany
    {
        return $this->hasMany(EmailMessage::class)->orderBy('created_at');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(Action::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    public function metadata(): HasMany
    {
        return $this->hasMany(ThreadMetadata::class);
    }

    public function incrementVersion(string $reason = null): void
    {
        $history = $this->version_history ?? [];
        $history[] = [
            'version' => $this->version + 1,
            'reason' => $reason,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->update([
            'version' => $this->version + 1,
            'version_history' => $history,
            'last_activity_at' => now(),
        ]);
    }
}
