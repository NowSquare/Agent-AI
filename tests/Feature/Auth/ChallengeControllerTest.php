<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\AuthChallenge;
use Illuminate\Support\Facades\Mail;
use App\Mail\AuthChallengeEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

class ChallengeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        RateLimiter::clear('auth-challenge:test@example.com|127.0.0.1');
    }

    public function test_creates_challenge_for_valid_email()
    {
        $response = $this->postJson('/auth/challenge', [
            'email' => 'test@example.com',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'expires_in']);

        $this->assertDatabaseHas('auth_challenges', [
            'email' => 'test@example.com',
        ]);

        Mail::assertSent(AuthChallengeEmail::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });
    }

    public function test_normalizes_email_case()
    {
        $response = $this->postJson('/auth/challenge', [
            'email' => 'TEST@EXAMPLE.COM',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('auth_challenges', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_validates_email_format()
    {
        $response = $this->postJson('/auth/challenge', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_enforces_rate_limit()
    {
        // Make 5 requests (the limit)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/auth/challenge', [
                'email' => 'test@example.com',
            ]);
            $response->assertOk();
        }

        // 6th request should be rate limited
        $response = $this->postJson('/auth/challenge', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(429)
            ->assertJsonStructure(['message']);
    }

    public function test_rate_limit_includes_ip_address()
    {
        // Make 5 requests with first IP
        for ($i = 0; $i < 5; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '1.1.1.1'])
                ->postJson('/auth/challenge', ['email' => 'test@example.com']);
            $response->assertOk();
        }

        // Should succeed with different IP
        $response = $this->withServerVariables(['REMOTE_ADDR' => '2.2.2.2'])
            ->postJson('/auth/challenge', ['email' => 'test@example.com']);
        $response->assertOk();
    }

    public function test_challenge_expires_in_15_minutes()
    {
        $response = $this->postJson('/auth/challenge', [
            'email' => 'test@example.com',
        ]);

        $challenge = AuthChallenge::where('email', 'test@example.com')->first();

        $this->assertTrue(
            now()->addMinutes(14)->lt($challenge->expires_at) &&
            now()->addMinutes(16)->gt($challenge->expires_at)
        );
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('auth-challenge:test@example.com|127.0.0.1');
        parent::tearDown();
    }
}
