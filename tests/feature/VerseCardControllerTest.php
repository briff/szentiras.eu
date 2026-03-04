<?php

namespace SzentirasHu\Test;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use SzentirasHu\Data\Entity\Theme;
use SzentirasHu\Data\Entity\VerseCardAsset;
use SzentirasHu\Data\Entity\VerseCardSession;
use SzentirasHu\Data\Enum\VerseCardSessionStatus;
use SzentirasHu\Jobs\SearchAndPrepareCandidates;
use SzentirasHu\Jobs\RenderVerseCardJob;
use SzentirasHu\Test\Common\TestCase;

class VerseCardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_session_generates_valid_uuid(): void
    {
        Bus::fake();

        $theme = Theme::create([
            'hungarian_keyword' => 'Test Theme',
            'embedding' => array_fill(0, 512, 0.1),
            'photo_keywords' => 'test,keyword',
        ]);

        $response = $this->postJson('/verse-card/create', [
            'verse_ref' => 'Mt1,1',
            'verse_text' => 'Test verse text',
            'theme_id' => $theme->id,
            'keywords' => ['test', 'keyword'],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['session_id', 'status']);

        $sessionId = $response->json('session_id');

        // Verify the session was created with a valid UUID
        $this->assertNotNull($sessionId);
        $this->assertTrue(strlen($sessionId) === 36); // UUID format

        // Verify the session exists in the database
        $session = VerseCardSession::find($sessionId);
        $this->assertNotNull($session);
        $this->assertEquals('Mt1,1', $session->verse_ref);
        $this->assertEquals('Test verse text', $session->verse_text);
        $this->assertEquals($theme->id, $session->theme_slug);
        $this->assertEquals(['test', 'keyword'], $session->keywords);
        $this->assertEquals('initializing', $session->status);

        // Verify the job was dispatched
        Bus::assertDispatched(SearchAndPrepareCandidates::class);
    }

    public function test_create_session_with_null_user_id(): void
    {
        Bus::fake();

        $theme = Theme::create([
            'hungarian_keyword' => 'Test Theme',
            'embedding' => array_fill(0, 512, 0.1),
            'photo_keywords' => 'test,keyword',
        ]);

        $response = $this->postJson('/verse-card/create', [
            'verse_ref' => 'Jn3,16',
            'verse_text' => 'For God so loved the world',
            'theme_id' => $theme->id,
            'keywords' => [],
        ]);

        $response->assertStatus(200);

        $sessionId = $response->json('session_id');
        $session = VerseCardSession::find($sessionId);

        $this->assertNull($session->user_id);
    }

    public function test_create_session_validates_required_fields(): void
    {
        $response = $this->postJson('/verse-card/create', [
            'verse_ref' => 'Mt1,1',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['theme_id']);
    }

    public function test_create_session_validates_theme_exists(): void
    {
        $response = $this->postJson('/verse-card/create', [
            'verse_ref' => 'Mt1,1',
            'verse_text' => 'Test',
            'theme_id' => 99999,
            'keywords' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['theme_id']);
    }

    public function test_get_status_returns_downloading_with_placeholder_metadata(): void
    {
        $session = VerseCardSession::factory()->create([
            'status' => VerseCardSessionStatus::Downloading->value,
            'expires_at' => now()->addHour(),
        ]);

        VerseCardAsset::factory()->count(4)->create([
            'session_id' => $session->id,
            'kind' => 'candidate',
            'state' => 'queued',
            'pixabay_user' => 'artist1',
            'pixabay_page_url' => 'https://pixabay.com/photo/1',
            'disk' => 'ephemeral',
        ]);

        $response = $this->getJson("/verse-card/status/{$session->id}");

        $response->assertStatus(200);
        $response->assertJson(['status' => 'downloading']);
        $response->assertJsonCount(4, 'candidates');
        $response->assertJsonPath('candidates.0.pixabay_user', 'artist1');
        $response->assertJsonPath('candidates.0.thumb_url', null);
    }

    public function test_get_status_returns_downloading_with_partial_ready_thumbnails(): void
    {
        $session = VerseCardSession::factory()->create([
            'status' => VerseCardSessionStatus::Downloading->value,
            'expires_at' => now()->addHour(),
        ]);

        VerseCardAsset::factory()->count(2)->create([
            'session_id' => $session->id,
            'kind' => 'candidate',
            'state' => 'ready',
            'thumb_path' => 'verse-cards/test/c/thumb.jpg',
            'disk' => 'local',
        ]);

        VerseCardAsset::factory()->count(2)->create([
            'session_id' => $session->id,
            'kind' => 'candidate',
            'state' => 'queued',
            'disk' => 'ephemeral',
        ]);

        $response = $this->getJson("/verse-card/status/{$session->id}");

        $response->assertStatus(200);
        $response->assertJson(['status' => 'downloading']);
        $response->assertJsonCount(4, 'candidates');
    }

    public function test_get_status_returns_choosing_with_candidates_when_four_ready(): void
    {
        $session = VerseCardSession::factory()->create([
            'status' => VerseCardSessionStatus::Choosing->value,
            'expires_at' => now()->addHour(),
        ]);

        VerseCardAsset::factory()->count(4)->create([
            'session_id' => $session->id,
            'kind' => 'candidate',
            'state' => 'ready',
            'thumb_path' => 'verse-cards/test/c/thumb.jpg',
            'disk' => 'local',
        ]);

        $response = $this->getJson("/verse-card/status/{$session->id}");

        $response->assertStatus(200);
        $response->assertJson(['status' => 'choosing']);
        $response->assertJsonCount(4, 'candidates');
    }

    public function test_get_status_returns_ready_with_final_url(): void
    {
        $session = VerseCardSession::factory()->create([
            'status' => VerseCardSessionStatus::Ready->value,
            'expires_at' => now()->addHour(),
        ]);

        VerseCardAsset::factory()->create([
            'session_id' => $session->id,
            'kind' => 'final',
            'state' => 'ready',
            'path' => 'verse-cards/test/final/1.jpg',
            'disk' => 'local',
        ]);

        $response = $this->getJson("/verse-card/status/{$session->id}");

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ready']);
        $response->assertJsonStructure(['status', 'final_url', 'download_url']);
    }

    public function test_request_more_sets_status_to_downloading(): void
    {
        Bus::fake();

        $session = VerseCardSession::factory()->create([
            'status' => VerseCardSessionStatus::Choosing->value,
            'pixabay_offset' => 4,
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->postJson("/verse-card/more/{$session->id}");

        $response->assertStatus(200);

        $session->refresh();
        $this->assertEquals(VerseCardSessionStatus::Downloading->value, $session->status);
        $this->assertEquals(8, $session->pixabay_offset);
        Bus::assertDispatched(SearchAndPrepareCandidates::class);
    }

    public function test_update_and_render_updates_session_and_dispatches_job(): void
    {
        Bus::fake();

        $session = VerseCardSession::factory()->create([
            'status' => VerseCardSessionStatus::Ready->value,
            'verse_ref' => 'Mt1,1',
            'verse_text' => 'Original text',
            'expires_at' => now()->addHour(),
        ]);

        // Create a selected candidate asset
        $selectedAsset = VerseCardAsset::factory()->create([
            'session_id' => $session->id,
            'kind' => 'candidate',
            'state' => 'selected',
        ]);

        $response = $this->postJson("/verse-card/update/{$session->id}", [
            'verse_ref' => 'Jn3,16',
            'verse_text' => 'Updated verse text',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'rendering']);

        $session->refresh();
        $this->assertEquals('Jn3,16', $session->verse_ref);
        $this->assertEquals('Updated verse text', $session->verse_text);
        $this->assertEquals(VerseCardSessionStatus::Rendering->value, $session->status);
        Bus::assertDispatched(RenderVerseCardJob::class);
    }

    public function test_update_and_render_validates_required_fields(): void
    {
        $session = VerseCardSession::factory()->create([
            'status' => VerseCardSessionStatus::Ready->value,
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->postJson("/verse-card/update/{$session->id}", [
            'verse_ref' => 'Mt1,1',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['verse_text']);
    }

    public function test_update_and_render_fails_without_selected_candidate(): void
    {
        $session = VerseCardSession::factory()->create([
            'status' => VerseCardSessionStatus::Ready->value,
            'expires_at' => now()->addHour(),
        ]);

        // No selected candidate asset

        $response = $this->postJson("/verse-card/update/{$session->id}", [
            'verse_ref' => 'Jn3,16',
            'verse_text' => 'Updated text',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['status' => 'error']);
    }
}
