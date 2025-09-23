<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Models\Contact;
use App\Models\ContactLink;
use App\Services\ContactLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContactLinkServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContactLinkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContactLinkService();
    }

    public function test_links_user_to_matching_contacts()
    {
        // Create contacts with same email
        $contact1 = Contact::create([
            'email' => 'test@example.com',
            'name' => 'Test User 1',
        ]);
        
        $contact2 = Contact::create([
            'email' => 'test@example.com',
            'name' => 'Test User 2',
        ]);
        
        // Create user
        $user = User::create([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'status' => 'active',
        ]);
        
        // Link contacts
        $this->service->linkUserToContacts($user);
        
        // Verify both contacts were linked
        $this->assertDatabaseHas('contact_links', [
            'contact_id' => $contact1->id,
            'user_id' => $user->id,
            'status' => 'linked',
        ]);
        
        $this->assertDatabaseHas('contact_links', [
            'contact_id' => $contact2->id,
            'user_id' => $user->id,
            'status' => 'linked',
        ]);
    }

    public function test_gets_contacts_for_user()
    {
        // Create user and contacts
        $user = User::create([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'status' => 'active',
        ]);
        
        $contact1 = Contact::create([
            'email' => 'test1@example.com',
            'name' => 'Contact 1',
        ]);
        
        $contact2 = Contact::create([
            'email' => 'test2@example.com',
            'name' => 'Contact 2',
        ]);
        
        // Create links
        ContactLink::create([
            'contact_id' => $contact1->id,
            'user_id' => $user->id,
            'status' => 'linked',
        ]);
        
        ContactLink::create([
            'contact_id' => $contact2->id,
            'user_id' => $user->id,
            'status' => 'linked',
        ]);
        
        // Get contacts
        $contacts = $this->service->getContactsForUser($user);
        
        $this->assertCount(2, $contacts);
        $this->assertTrue($contacts->contains($contact1));
        $this->assertTrue($contacts->contains($contact2));
    }

    public function test_gets_users_for_contact()
    {
        // Create contact and users
        $contact = Contact::create([
            'email' => 'shared@example.com',
            'name' => 'Shared Contact',
        ]);
        
        $user1 = User::create([
            'email' => 'user1@example.com',
            'name' => 'User 1',
            'status' => 'active',
        ]);
        
        $user2 = User::create([
            'email' => 'user2@example.com',
            'name' => 'User 2',
            'status' => 'active',
        ]);
        
        // Create links
        ContactLink::create([
            'contact_id' => $contact->id,
            'user_id' => $user1->id,
            'status' => 'linked',
        ]);
        
        ContactLink::create([
            'contact_id' => $contact->id,
            'user_id' => $user2->id,
            'status' => 'linked',
        ]);
        
        // Get users
        $users = $this->service->getUsersForContact($contact);
        
        $this->assertCount(2, $users);
        $this->assertTrue($users->contains($user1));
        $this->assertTrue($users->contains($user2));
    }

    public function test_blocks_contact_link()
    {
        $user = User::create([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'status' => 'active',
        ]);
        
        $contact = Contact::create([
            'email' => 'contact@example.com',
            'name' => 'Test Contact',
        ]);
        
        ContactLink::create([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'status' => 'linked',
        ]);
        
        // Block the link
        $this->service->blockLink($contact, $user);
        
        $this->assertDatabaseHas('contact_links', [
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'status' => 'blocked',
        ]);
        
        // Verify contact is not returned
        $contacts = $this->service->getContactsForUser($user);
        $this->assertCount(0, $contacts);
    }

    public function test_unblocks_contact_link()
    {
        $user = User::create([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'status' => 'active',
        ]);
        
        $contact = Contact::create([
            'email' => 'contact@example.com',
            'name' => 'Test Contact',
        ]);
        
        ContactLink::create([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'status' => 'blocked',
        ]);
        
        // Unblock the link
        $this->service->unblockLink($contact, $user);
        
        $this->assertDatabaseHas('contact_links', [
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'status' => 'linked',
        ]);
        
        // Verify contact is returned
        $contacts = $this->service->getContactsForUser($user);
        $this->assertCount(1, $contacts);
        $this->assertTrue($contacts->contains($contact));
    }
}
