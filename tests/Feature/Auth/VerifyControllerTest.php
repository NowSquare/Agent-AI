<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Models\AuthChallenge;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

class VerifyControllerTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $auth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = app(AuthService::class);
        RateLimiter::clear('auth-verify:test@example.com|127.0.0.1');
    }

    public function test_verifies_valid_code()
    {
        // Create a challenge
        $challenge = $this->auth->createChallenge('test@example.com');

        $response = $this->postJson('/auth/verify', [
            'email' => 'test@example.com',
            'code' => $challenge->code,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'redirect']);

        // Verify user was created and logged in
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_rejects_invalid_code()
    {
        // Create a challenge
        $this->auth->createChallenge('test@example.com');

        $response = $this->postJson('/auth/verify', [
            'email' => 'test@example.com',
            'code' => '000000', // Wrong code
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => __('auth.verify.invalid_code')]);

        $this->assertGuest();
    }

    public function test_validates_code_format()
    {
        $response = $this->postJson('/auth/verify', [
            'email' => 'test@example.com',
            'code' => 'abc123', // Non-numeric code
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_enforces_rate_limit()
    {
        // Create a challenge
        $challenge = $this->auth->createChallenge('test@example.com');

        // Make 10 requests (the limit)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/auth/verify', [
                'email' => 'test@example.com',
                'code' => '000000', // Wrong code
            ]);
            $response->assertStatus(422);
        }

        // 11th request should be rate limited
        $response = $this->postJson('/auth/verify', [
            'email' => 'test@example.com',
            'code' => $challenge->code,
        ]);

        $response->assertStatus(429)
            ->assertJsonStructure(['message']);
    }

    public function test_rate_limit_includes_ip_address()
    {
        // Create a challenge
        $challenge = $this->auth->createChallenge('test@example.com');

        // Make 10 requests with first IP
        for ($i = 0; $i < 10; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '1.1.1.1'])
                ->postJson('/auth/verify', [
                    'email' => 'test@example.com',
                    'code' => '000000',
                ]);
            $response->assertStatus(422);
        }

        // Should succeed with different IP
        $response = $this->withServerVariables(['REMOTE_ADDR' => '2.2.2.2'])
            ->postJson('/auth/verify', [
                'email' => 'test@example.com',
                'code' => $challenge->code,
            ]);
        $response->assertOk();
    }

    public function test_remembers_user_when_requested()
    {
        // Create a challenge
        $challenge = $this->auth->createChallenge('test@example.com');

        $response = $this->postJson('/auth/verify', [
            'email' => 'test@example.com',
            'code' => $challenge->code,
            'remember' => true,
        ]);

        $response->assertOk();

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user->remember_token);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('auth-verify:test@example.com|127.0.0.1');
        parent::tearDown();
    }
}
