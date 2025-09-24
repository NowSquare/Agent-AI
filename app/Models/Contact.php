<?php
/**
 * What this file does — Stores a person’s email identity in an account.
 * Plain: One row per email address we’ve seen for your account.
 * How this fits in:
 * - Email messages create/update contacts
 * - Users link to contacts via ContactLink for access
 * - Used to decide which threads you can see
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Purpose: Represent an email participant tied to an account.
 * Responsibilities:
 * - Persist email/name/meta
 * - Connect to users through ContactLink
 * Collaborators: ContactLink, Account
 */
class Contact extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'email',
        'name',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function contactLinks(): HasMany
    {
        return $this->hasMany(ContactLink::class);
    }
}
