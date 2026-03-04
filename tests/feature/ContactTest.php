<?php

namespace SzentirasHu\Test;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SzentirasHu\Data\Entity\AnonymousId;
use SzentirasHu\Data\Entity\ContactMessage;
use SzentirasHu\Test\Common\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_contact_form()
    {
        $response = $this->get(route('contact.form'));
        $response->assertOk();
        $response->assertSee('Kapcsolatfelvétel');
        $response->assertSee('CAPTCHA');
    }

    public function test_logged_in_user_can_view_contact_form_without_captcha()
    {
        $anonymousId = AnonymousId::factory()->create();
        $this->withSession(['anonymous_token' => $anonymousId->token]);

        $response = $this->get(route('contact.form'));
        $response->assertOk();
        $response->assertDontSee('CAPTCHA');
    }

    public function test_guest_submission_requires_turnstile()
    {
        $response = $this->post(route('contact.submit'), [
            'message' => 'Test message',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('cf-turnstile-response');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_logged_in_user_can_submit_message()
    {
        $anonymousId = AnonymousId::factory()->create();
        $this->withSession(['anonymous_token' => $anonymousId->token]);

        $response = $this->post(route('contact.submit'), [
            'message' => 'Test message from logged-in user',
        ]);

        $response->assertRedirect(route('contact.thankyou'));
        $this->assertDatabaseHas('contact_messages', [
            'sender_anonymous_id' => $anonymousId->id,
            'message' => 'Test message from logged-in user',
        ]);
    }

    public function test_guest_submission_with_turnstile_creates_message_without_sender()
    {
        // Mock turnstile validation passes
        $this->mockTurnstileSuccess();

        $response = $this->post(route('contact.submit'), [
            'message' => 'Guest message',
            'cf-turnstile-response' => 'fake-token',
        ]);

        $response->assertRedirect(route('contact.thankyou'));
        $this->assertDatabaseHas('contact_messages', [
            'sender_anonymous_id' => null,
            'message' => 'Guest message',
        ]);
    }

    public function test_user_inbox_requires_login()
    {
        $response = $this->get(route('contact.inbox'));
        $response->assertRedirect('/register');
    }

    public function test_logged_in_user_can_view_inbox()
    {
        $anonymousId = AnonymousId::factory()->create();
        $this->withSession(['anonymous_token' => $anonymousId->token]);

        $response = $this->get(route('contact.inbox'));
        $response->assertOk();
        $response->assertSee('Üzeneteim');
    }

    public function test_editor_can_view_contact_messages_index()
    {
        $editorToken = config('editors.tokens')[0] ?? 'editor-token';
        $anonymousId = AnonymousId::factory()->create(['token' => $editorToken]);
        $this->withSession(['anonymous_token' => $editorToken]);

        ContactMessage::factory()->create();

        $response = $this->get(route('editor.contactMessages.index'));
        $response->assertOk();
        $response->assertSee('Szerkesztői beérkezett üzenetek');
    }

    public function test_editor_can_reply_to_user_message()
    {
        $editorToken = config('editors.tokens')[0] ?? 'editor-token';
        $editor = AnonymousId::factory()->create(['token' => $editorToken]);
        $user = AnonymousId::factory()->create();
        $this->withSession(['anonymous_token' => $editorToken]);

        $message = ContactMessage::factory()->create([
            'sender_anonymous_id' => $user->id,
        ]);

        $response = $this->post(route('editor.contactMessages.reply', $message), [
            'reply' => 'Editor reply',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contact_messages', [
            'sender_anonymous_id' => $editor->id,
            'receiver_anonymous_id' => $user->id,
            'parent_id' => $message->id,
            'message' => 'Editor reply',
        ]);
    }

    public function test_editor_cannot_reply_to_guest_message()
    {
        $editorToken = config('editors.tokens')[0] ?? 'editor-token';
        AnonymousId::factory()->create(['token' => $editorToken]);
        $this->withSession(['anonymous_token' => $editorToken]);

        $message = ContactMessage::factory()->create([
            'sender_anonymous_id' => null,
        ]);

        $response = $this->post(route('editor.contactMessages.reply', $message), [
            'reply' => 'Editor reply',
        ]);

        $response->assertStatus(400);
    }

    public function test_editor_can_mark_thread_as_resolved()
    {
        $editorToken = config('editors.tokens')[0] ?? 'editor-token';
        AnonymousId::factory()->create(['token' => $editorToken]);
        $this->withSession(['anonymous_token' => $editorToken]);

        $message = ContactMessage::factory()->create();

        $response = $this->post(route('editor.contactMessages.resolve', $message));
        $response->assertRedirect();

        $this->assertNotNull($message->fresh()->resolved_at);
    }

    public function test_editor_can_delete_message()
    {
        $editorToken = config('editors.tokens')[0] ?? 'editor-token';
        AnonymousId::factory()->create(['token' => $editorToken]);
        $this->withSession(['anonymous_token' => $editorToken]);

        $message = ContactMessage::factory()->create();

        $response = $this->post(route('editor.contactMessages.delete', $message));
        $response->assertRedirect(route('editor.contactMessages.index'));

        $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
    }

    private function mockTurnstileSuccess(): void
    {
        $this->app->bind(\SzentirasHu\Rules\TurnstileValidationRule::class, function () {
            $mock = $this->createMock(\SzentirasHu\Rules\TurnstileValidationRule::class);
            $mock->method('validate')->willReturnCallback(function (string $attribute, mixed $value, \Closure $fail): void {
                // Do nothing — validation passes
            });
            return $mock;
        });
    }
}