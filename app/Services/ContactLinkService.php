<?php

namespace App\Services;

use App\Models\User;
use App\Models\Contact;
use App\Models\ContactLink;
use Illuminate\Support\Facades\Log;

class ContactLinkService
{
    /**
     * Link a user to all matching contacts by email.
     */
    public function linkUserToContacts(User $user): void
    {
        // Find all contacts with matching email
        $contacts = Contact::where('email', $user->email)->get();

        foreach ($contacts as $contact) {
            // Create link if it doesn't exist
            ContactLink::firstOrCreate(
                [
                    'contact_id' => $contact->id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => 'linked',
                ]
            );

            Log::info('Contact linked to user', [
                'user_id' => $user->id,
                'contact_id' => $contact->id,
                'email' => $contact->email,
            ]);
        }
    }

    /**
     * Get all contacts linked to a user.
     */
    public function getContactsForUser(User $user)
    {
        return Contact::whereHas('links', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->where('status', 'linked');
        })->get();
    }

    /**
     * Get all users linked to a contact.
     */
    public function getUsersForContact(Contact $contact)
    {
        return User::whereHas('contactLinks', function ($query) use ($contact) {
            $query->where('contact_id', $contact->id)
                  ->where('status', 'linked');
        })->get();
    }

    /**
     * Block a specific contact-user link.
     */
    public function blockLink(Contact $contact, User $user): void
    {
        $link = ContactLink::where('contact_id', $contact->id)
            ->where('user_id', $user->id)
            ->first();

        if ($link) {
            $link->update(['status' => 'blocked']);
            
            Log::info('Contact link blocked', [
                'user_id' => $user->id,
                'contact_id' => $contact->id,
                'email' => $contact->email,
            ]);
        }
    }

    /**
     * Unblock a specific contact-user link.
     */
    public function unblockLink(Contact $contact, User $user): void
    {
        $link = ContactLink::where('contact_id', $contact->id)
            ->where('user_id', $user->id)
            ->first();

        if ($link) {
            $link->update(['status' => 'linked']);
            
            Log::info('Contact link unblocked', [
                'user_id' => $user->id,
                'contact_id' => $contact->id,
                'email' => $contact->email,
            ]);
        }
    }
}
