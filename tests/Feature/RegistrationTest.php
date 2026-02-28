<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SzentirasHu\Data\Entity\AnonymousId;
use SzentirasHu\Rules\TurnstileValidationRule;
use SzentirasHu\Test\Common\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_form_is_accessible(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Regisztráció');
        $response->assertSee('cf-turnstile', false);
    }

    public function test_registration_requires_captcha(): void
    {
        $response = $this->post('/register', [
            'approve' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('cf-turnstile-response');
        $this->assertDatabaseCount('anonymous_ids', 0);
    }

    public function test_registration_requires_approval(): void
    {
        $response = $this->post('/register', [
            'cf-turnstile-response' => 'fake-token',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('approve');
        $this->assertDatabaseCount('anonymous_ids', 0);
    }

    public function test_successful_registration_creates_anonymous_id(): void
    {
        $this->mockTurnstileSuccess();

        $response = $this->post('/register', [
            'approve' => '1',
            'cf-turnstile-response' => 'fake-token',
        ]);

        $this->assertDatabaseCount('anonymous_ids', 1);
        $anonymousId = AnonymousId::first();
        $response->assertRedirect("/profile/{$anonymousId->token}");
        $this->assertTrue(session()->has('anonymous_token'));
    }

    public function test_successful_registration_with_redirect(): void
    {
        $this->mockTurnstileSuccess();

        $response = $this->post('/register', [
            'approve' => '1',
            'cf-turnstile-response' => 'fake-token',
            'r' => '/some-page',
        ]);

        $this->assertDatabaseCount('anonymous_ids', 1);
        $response->assertRedirect('/some-page');
    }

    public function test_registration_rejects_external_redirect(): void
    {
        $this->mockTurnstileSuccess();

        $response = $this->post('/register', [
            'approve' => '1',
            'cf-turnstile-response' => 'fake-token',
            'r' => 'https://evil.com/malicious',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid redirect URL']);
    }

    public function test_already_logged_in_user_is_redirected_from_registration_form(): void
    {
        $anonymousId = AnonymousId::factory()->create();
        $this->withSession(['anonymous_token' => $anonymousId->token]);

        $response = $this->get('/register');

        $response->assertRedirect('/profile');
    }

    public function test_failed_captcha_shows_error(): void
    {
        $this->mockTurnstileFailure();

        $response = $this->post('/register', [
            'approve' => '1',
            'cf-turnstile-response' => 'invalid-token',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('cf-turnstile-response');
        $this->assertDatabaseCount('anonymous_ids', 0);
    }

    private function mockTurnstileSuccess(): void
    {
        $this->app->bind(TurnstileValidationRule::class, function () {
            $mock = $this->createMock(TurnstileValidationRule::class);
            $mock->method('validate')->willReturnCallback(function (string $attribute, mixed $value, \Closure $fail): void {
                // Do nothing — validation passes
            });
            return $mock;
        });
    }

    private function mockTurnstileFailure(): void
    {
        $this->app->bind(TurnstileValidationRule::class, function () {
            $mock = $this->createMock(TurnstileValidationRule::class);
            $mock->method('validate')->willReturnCallback(function (string $attribute, mixed $value, \Closure $fail): void {
                $fail('Sikertelen captcha validáció!');
            });
            return $mock;
        });
    }
}
