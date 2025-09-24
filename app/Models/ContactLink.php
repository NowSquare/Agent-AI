<?php
/**
 * What this file does — Links a Contact to a User (or blocks it).
 * Plain: The bridge that says “this user is allowed to see this contact’s threads”.
 * How this fits in:
 * - Visibility: threads are “yours” if linked via ContactLink
 * - Created on first login after email contact exists
 * - Status can be linked/blocked for control
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Purpose: Map contacts to users to drive visibility.
 * Responsibilities:
 * - Hold link status
 * - Provide relations to Contact and User
 * Collaborators: Contact, User
 */
class ContactLink extends Model
{
    use HasUlids;

    protected $fillable = [
        'contact_id',
        'user_id',
        'status',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
