<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Support\Facades\Mail;
use App\Mail\AuthMagicLinkEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $auth;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->auth = app(AuthService::class);
    }

    public function test_magic_link_logs_in_user()
    {
        // Create magic link
        $url = $this->auth->createMagicLink('test@example.com');
        $token = substr($url, strrpos($url, '/') + 1);

        $response = $this->get("/login/{$token}");

        $response->assertRedirect(route('dashboard'))
            ->assertSessionHas('success', __('auth.magic_link.success'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_rejects_invalid_magic_link()
    {
        $response = $this->get('/login/invalid-token');

        $response->assertRedirect(route('auth.challenge.form'))
            ->assertSessionHas('error', __('auth.magic_link.invalid'));

        $this->assertGuest();
    }

    public function test_sends_magic_link_email()
    {
        $response = $this->postJson('/auth/magic-link', [
            'email' => 'test@example.com',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message']);

        Mail::assertSent(AuthMagicLinkEmail::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });
    }

    public function test_validates_magic_link_email()
    {
        $response = $this->postJson('/auth/magic-link', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        Mail::assertNothingSent();
    }

    public function test_logs_out_user()
    {
        // Create and login a user
        $user = User::create([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'status' => 'active',
        ]);
        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect(route('auth.challenge.form'))
            ->assertSessionHas('success', __('auth.logout.success'));

        $this->assertGuest();
    }

    public function test_regenerates_csrf_token_on_logout()
    {
        $user = User::create([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'status' => 'active',
        ]);
        $this->actingAs($user);

        $oldToken = session()->token();
        $this->post('/logout');
        $newToken = session()->token();

        $this->assertNotEquals($oldToken, $newToken);
    }
}
