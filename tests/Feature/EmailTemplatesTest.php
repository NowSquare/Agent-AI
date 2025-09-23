<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;

class EmailTemplatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en'); // Reset locale before each test
    }
    public function test_auth_challenge_email_renders_correctly()
    {
        $expiresAt = Carbon::now()->addMinutes(30);
        $view = View::make('emails.auth-challenge', [
            'code' => '123456',
            'magicLink' => 'https://example.com/auth/verify/123',
            'expiresAt' => $expiresAt,
        ]);

        $rendered = $view->render();

        // Assert basic structure
        $this->assertStringContainsString('123456', $rendered);
        $this->assertStringContainsString('https://example.com/auth/verify/123', $rendered);
        $this->assertStringContainsString($expiresAt->format('g:i A T'), $rendered);

        // Assert translations
        $this->assertStringContainsString(__('emails.auth.header'), $rendered);
        $this->assertStringContainsString(__('emails.auth.code_instructions'), $rendered);
    }

    public function test_clarification_email_renders_correctly()
    {
        $view = View::make('emails.clarification', [
            'summary' => 'Schedule a meeting for tomorrow',
            'confirmUrl' => 'https://example.com/confirm/123',
            'cancelUrl' => 'https://example.com/cancel/123',
        ]);

        $rendered = $view->render();

        // Assert content
        $this->assertStringContainsString('Schedule a meeting for tomorrow', $rendered);
        $this->assertStringContainsString('https://example.com/confirm/123', $rendered);
        $this->assertStringContainsString('https://example.com/cancel/123', $rendered);

        // Assert translations
        $this->assertStringContainsString(__('emails.clarification.header'), $rendered);
        $this->assertStringContainsString(__('emails.clarification.interpretation'), $rendered);
    }

    public function test_options_email_renders_correctly()
    {
        $options = [
            ['label' => 'Option 1', 'url' => 'https://example.com/option/1'],
            ['label' => 'Option 2', 'url' => 'https://example.com/option/2'],
        ];

        $view = View::make('emails.options', [
            'options' => $options,
        ]);

        $rendered = $view->render();

        // Assert options are rendered
        foreach ($options as $option) {
            $this->assertStringContainsString($option['label'], $rendered);
            $this->assertStringContainsString($option['url'], $rendered);
        }

        // Assert translations
        $this->assertStringContainsString(__('emails.options.header'), $rendered);
        $this->assertStringContainsString(__('emails.options.intro'), $rendered);
    }

    public function test_action_response_success_email_renders_correctly()
    {
        $view = View::make('emails.action-response', [
            'success' => true,
            'message' => 'Meeting scheduled successfully',
            'detailsUrl' => 'https://example.com/details/123',
        ]);

        $rendered = $view->render();

        // Assert success content
        $this->assertStringContainsString('Meeting scheduled successfully', $rendered);
        $this->assertStringContainsString('https://example.com/details/123', $rendered);
        $this->assertStringContainsString('✅', $rendered);

        // Assert translations
        $this->assertStringContainsString(__('emails.action_response.success.header'), $rendered);
    }

    public function test_action_response_failure_email_renders_correctly()
    {
        $view = View::make('emails.action-response', [
            'success' => false,
            'message' => 'Unable to schedule meeting',
            'detailsUrl' => 'https://example.com/details/123',
        ]);

        $rendered = $view->render();

        // Assert failure content
        $this->assertStringContainsString('Unable to schedule meeting', $rendered);
        $this->assertStringContainsString('https://example.com/details/123', $rendered);
        $this->assertStringContainsString('❌', $rendered);

        // Assert translations
        $this->assertStringContainsString(__('emails.action_response.failure.header'), $rendered);
    }

    public function test_dark_mode_styles_are_included()
    {
        $view = View::make('emails.layouts.base');
        $rendered = $view->render();

        // Assert dark mode support
        $this->assertStringContainsString('prefers-color-scheme: dark', $rendered);
        $this->assertStringContainsString('color-scheme: light dark', $rendered);
        $this->assertStringContainsString('--bg-primary: #1a1a1a', $rendered);
    }

    public function test_auth_challenge_email_renders_correctly_in_dutch()
    {
        app()->setLocale('nl');
        $expiresAt = Carbon::now()->addMinutes(30);
        $view = View::make('emails.auth-challenge', [
            'code' => '123456',
            'magicLink' => 'https://example.com/auth/verify/123',
            'expiresAt' => $expiresAt,
        ]);

        $rendered = $view->render();

        // Assert Dutch translations
        $this->assertStringContainsString(__('emails.auth.header'), $rendered);
        $this->assertStringContainsString(__('emails.auth.code_instructions'), $rendered);
        $this->assertStringContainsString('Inloggen bij Agent AI', $rendered);
    }

    public function test_clarification_email_renders_correctly_in_dutch()
    {
        app()->setLocale('nl');
        $view = View::make('emails.clarification', [
            'summary' => 'Plan een vergadering voor morgen',
            'confirmUrl' => 'https://example.com/confirm/123',
            'cancelUrl' => 'https://example.com/cancel/123',
        ]);

        $rendered = $view->render();

        // Assert Dutch content
        $this->assertStringContainsString('Plan een vergadering voor morgen', $rendered);
        $this->assertStringContainsString(__('emails.clarification.header'), $rendered);
        $this->assertStringContainsString(__('emails.clarification.interpretation'), $rendered);
        $this->assertStringContainsString('Ja, Dat Klopt', $rendered);
    }

    public function test_options_email_renders_correctly_in_dutch()
    {
        app()->setLocale('nl');
        $options = [
            ['label' => 'Optie 1', 'url' => 'https://example.com/option/1'],
            ['label' => 'Optie 2', 'url' => 'https://example.com/option/2'],
        ];

        $view = View::make('emails.options', [
            'options' => $options,
        ]);

        $rendered = $view->render();

        // Assert Dutch content
        foreach ($options as $option) {
            $this->assertStringContainsString($option['label'], $rendered);
            $this->assertStringContainsString($option['url'], $rendered);
        }
        $this->assertStringContainsString(__('emails.options.header'), $rendered);
        $this->assertStringContainsString(__('emails.options.intro'), $rendered);
    }

    public function test_action_response_success_email_renders_correctly_in_dutch()
    {
        app()->setLocale('nl');
        $view = View::make('emails.action-response', [
            'success' => true,
            'message' => 'Vergadering succesvol gepland',
            'detailsUrl' => 'https://example.com/details/123',
        ]);

        $rendered = $view->render();

        // Assert Dutch content
        $this->assertStringContainsString('Vergadering succesvol gepland', $rendered);
        $this->assertStringContainsString(__('emails.action_response.success.header'), $rendered);
        $this->assertStringContainsString('Details Bekijken', $rendered);
    }
}
