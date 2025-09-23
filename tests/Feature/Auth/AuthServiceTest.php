<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Models\Contact;
use App\Models\AuthChallenge;
use App\Services\AuthService;
use App\Services\ContactLinkService;
use Illuminate\Support\Facades\Mail;
use App\Mail\AuthChallengeEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;
    private ContactLinkService $contactLinks;

    protected function setUp(): void
    {
        parent::setUp();
        
        Mail::fake();
        
        $this->contactLinks = new ContactLinkService();
        $this->authService = new AuthService($this->contactLinks);
    }

    public function test_creates_challenge_with_valid_code()
    {
        $email = 'test@example.com';
        
        $challenge = $this->authService->createChallenge($email);
        
        $this->assertNotNull($challenge);
        $this->assertEquals($email, $challenge->email);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $challenge->code);
        $this->assertTrue(now()->addMinutes(14)->lt($challenge->expires_at));
        $this->assertTrue(now()->addMinutes(16)->gt($challenge->expires_at));
        
        Mail::assertSent(AuthChallengeEmail::class, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });
    }

    public function test_verifies_valid_challenge()
    {
        // Create a contact first
        $contact = Contact::create([
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        // Create and verify challenge
        $challenge = $this->authService->createChallenge('test@example.com');
        $user = $this->authService->verifyChallenge('test@example.com', $challenge->code);
        
        $this->assertNotNull($user);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertEquals('active', $user->status);
        
        // Verify contact was linked
        $this->assertDatabaseHas('contact_links', [
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'status' => 'linked',
        ]);
    }

    public function test_rejects_invalid_challenge_code()
    {
        $challenge = $this->authService->createChallenge('test@example.com');
        $user = $this->authService->verifyChallenge('test@example.com', 'wrong-code');
        
        $this->assertNull($user);
    }

    public function test_rejects_expired_challenge()
    {
        $challenge = $this->authService->createChallenge('test@example.com');
        
        // Expire the challenge
        $challenge->expires_at = now()->subMinutes(1);
        $challenge->save();
        
        $user = $this->authService->verifyChallenge('test@example.com', $challenge->code);
        
        $this->assertNull($user);
    }

    public function test_creates_magic_link()
    {
        $email = 'test@example.com';
        $url = $this->authService->createMagicLink($email);
        
        $this->assertStringContainsString('/login/', $url);
        
        // Verify challenge was created
        $this->assertDatabaseHas('auth_challenges', [
            'email' => $email,
            'meta_json->type' => 'magic_link',
        ]);
    }

    public function test_verifies_valid_magic_link()
    {
        // Create a contact first
        $contact = Contact::create([
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $url = $this->authService->createMagicLink('test@example.com');
        $token = substr($url, strrpos($url, '/') + 1);
        
        $user = $this->authService->verifyMagicLink($token);
        
        $this->assertNotNull($user);
        $this->assertEquals('test@example.com', $user->email);
        
        // Verify contact was linked
        $this->assertDatabaseHas('contact_links', [
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'status' => 'linked',
        ]);
    }

    public function test_rejects_invalid_magic_link()
    {
        $user = $this->authService->verifyMagicLink('invalid-token');
        $this->assertNull($user);
    }

    public function test_rejects_expired_magic_link()
    {
        $url = $this->authService->createMagicLink('test@example.com');
        $token = substr($url, strrpos($url, '/') + 1);
        
        // Expire the challenge
        AuthChallenge::where('code', $token)->update([
            'expires_at' => now()->subMinutes(1),
        ]);
        
        $user = $this->authService->verifyMagicLink($token);
        $this->assertNull($user);
    }

    public function test_remembers_user_when_requested()
    {
        $challenge = $this->authService->createChallenge('test@example.com');
        $user = $this->authService->verifyChallenge('test@example.com', $challenge->code, true);
        
        $this->assertNotNull($user->remember_token);
    }

    public function test_normalizes_email_addresses()
    {
        $challenge = $this->authService->createChallenge('TEST@EXAMPLE.COM');
        $user = $this->authService->verifyChallenge('Test@Example.com', $challenge->code);
        
        $this->assertNotNull($user);
        $this->assertEquals('test@example.com', $user->email);
    }
}
