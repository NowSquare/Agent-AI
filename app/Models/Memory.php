<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Memory extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'scope',
        'scope_id',
        'key',
        'value_json',
        'confidence',
        'ttl_category',
        'supersedes_id',
        'expires_at',
        'first_seen_at',
        'last_seen_at',
        'last_used_at',
        'usage_count',
        'meta',
        'email_message_id',
        'thread_id',
        'provenance',
    ];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'meta' => 'array',
            'confidence' => 'float',
            'usage_count' => 'integer',
            'expires_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_used_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public const TTL_VOLATILE = 'volatile';
    public const TTL_SEASONAL = 'seasonal';
    public const TTL_DURABLE = 'durable';
    public const TTL_LEGAL = 'legal';

    public const SCOPE_CONVERSATION = 'conversation';
    public const SCOPE_USER = 'user';
    public const SCOPE_ACCOUNT = 'account';

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('expires_at')
            ->orWhere('expires_at', '>', Carbon::now());
    }

    public function scopeByScope(Builder $query, string $scope, string $scopeId): Builder
    {
        return $query->where('scope', $scope)
            ->where('scope_id', $scopeId);
    }

    public function scopeByTtlCategory(Builder $query, string $category): Builder
    {
        return $query->where('ttl_category', $category);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::now());
    }

    /**
     * Update usage tracking metrics.
     */
    public function touchUsage(): bool
    {
        return $this->update([
            'last_used_at' => now(),
            'usage_count' => $this->usage_count + 1,
        ]);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'scope_id')->when($this->scope === 'account');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'scope_id')->when($this->scope === 'conversation');
    }
}
