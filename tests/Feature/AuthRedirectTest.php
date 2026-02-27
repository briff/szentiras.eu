<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use SzentirasHu\Test\Common\TestCase;
use SzentirasHu\Data\Entity\AnonymousId;

class AuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_accepts_redirect_parameter()
    {
        $response = $this->get('/login?redirect=/test-page');
        
        $response->assertStatus(200);
        $response->assertSee('name="redirect"', false);
        $response->assertSee('value="/test-page"', false);
    }

    public function test_register_page_accepts_redirect_parameter()
    {
        $response = $this->get('/register?redirect=/test-page');
        
        $response->assertStatus(200);
        $response->assertSee('name="redirect"', false);
        $response->assertSee('value="/test-page"', false);
    }

    public function test_successful_login_redirects_to_target_url()
    {
        $anonymousId = AnonymousId::create([
            'token' => 'testtoken123',
            'last_login' => now(),
        ]);

        $response = $this->post('/login', [
            'anonymous_token' => 'testtoken123',
            'redirect' => '/test-page',
        ]);

        $response->assertRedirect('/test-page');
        // Check session was set
        $this->assertTrue(session()->has('anonymous_token'));
    }

    public function test_successful_login_without_redirect_goes_to_home()
    {
        $anonymousId = AnonymousId::create([
            'token' => 'testtoken456',
            'last_login' => now(),
        ]);

        $response = $this->post('/login', [
            'anonymous_token' => 'testtoken456',
        ]);

        $response->assertRedirect('/');
        $this->assertTrue(session()->has('anonymous_token'));
    }

    public function test_login_rejects_external_redirect_urls()
    {
        $anonymousId = AnonymousId::create([
            'token' => 'testtoken789',
            'last_login' => now(),
        ]);

        $response = $this->post('/login', [
            'anonymous_token' => 'testtoken789',
            'redirect' => 'https://evil.com/malicious',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid redirect URL']);
    }

    public function test_login_rejects_dangerous_protocols()
    {
        $anonymousId = AnonymousId::create([
            'token' => 'testtokenabc',
            'last_login' => now(),
        ]);

        $response = $this->post('/login', [
            'anonymous_token' => 'testtokenabc',
            'redirect' => 'javascript:alert("xss")',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid redirect URL']);
    }

    public function test_login_rejects_protocol_relative_urls()
    {
        $anonymousId = AnonymousId::create([
            'token' => 'testtokenxyz',
            'last_login' => now(),
        ]);

        $response = $this->post('/login', [
            'anonymous_token' => 'testtokenxyz',
            'redirect' => '//google.com',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid redirect URL']);
    }

    public function test_login_rejects_url_encoded_protocol_relative_urls()
    {
        $anonymousId = AnonymousId::create([
            'token' => 'testtokenurlenc',
            'last_login' => now(),
        ]);

        $response = $this->post('/login', [
            'anonymous_token' => 'testtokenurlenc',
            'redirect' => '/%2f%2fgoogle.com', // URL-encoded "//google.com"
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid redirect URL']);
    }

    public function test_profile_page_auto_redirects_when_valid_redirect_provided()
    {
        $anonymousId = AnonymousId::create([
            'token' => 'testprofiletoken',
            'last_login' => now(),
        ]);

        // Don't set session - simulating auto-login via profile link
        $response = $this->get('/profile/testprofiletoken?redirect=/test-page');
        
        $response->assertRedirect('/test-page');
        // Check session was set
        $this->assertTrue(session()->has('anonymous_token'));
    }

    public function test_profile_page_shows_continue_button_when_invalid_redirect_provided()
    {
        $anonymousId = AnonymousId::create([
            'token' => 'testprofiletoken2',
            'last_login' => now(),
        ]);

        session(['anonymous_token' => 'testprofiletoken2']);

        // Invalid redirect (external) should show profile page WITHOUT continue button
        $response = $this->get('/profile/testprofiletoken2?redirect=https://evil.com');
        
        $response->assertStatus(200);
        // Should not show continue button for external URL
        $response->assertDontSee('Tovább az eredeti oldalra', false);
    }

    public function test_profile_page_does_not_show_continue_button_without_redirect()
    {
        $anonymousId = AnonymousId::create([
            'token' => 'testprofiletoken2',
            'last_login' => now(),
        ]);

        session(['anonymous_token' => 'testprofiletoken2']);

        $response = $this->get('/profile/testprofiletoken2');
        
        $response->assertStatus(200);
        $response->assertDontSee('Tovább az eredeti oldalra', false);
    }

    public function test_register_link_in_login_includes_redirect_parameter()
    {
        $response = $this->get('/login?redirect=/test-page');
        
        $response->assertStatus(200);
        $response->assertSee('href="/register?redirect=', false);
        $response->assertSee('/test-page', false);
    }
}
