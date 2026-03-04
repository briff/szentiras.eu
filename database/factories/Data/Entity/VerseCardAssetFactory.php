<?php

namespace Database\Factories\Data\Entity;

use Illuminate\Database\Eloquent\Factories\Factory;
use SzentirasHu\Data\Entity\VerseCardAsset;
use SzentirasHu\Data\Entity\VerseCardSession;

class VerseCardAssetFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = VerseCardAsset::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $session = VerseCardSession::factory()->create();

        return [
            'session_id' => $session->id,
            'kind' => 'candidate',
            'state' => 'queued',
            'pixabay_id' => $this->faker->unique()->numberBetween(10000, 99999),
            'pixabay_user' => $this->faker->userName(),
            'pixabay_page_url' => $this->faker->url(),
            'remote_url' => $this->faker->imageUrl(),
            'disk' => 'ephemeral',
            'path' => null,
            'thumb_path' => null,
            'bytes' => null,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the asset belongs to a specific session.
     *
     * @param VerseCardSession|string $session
     * @return static
     */
    public function forSession($session): static
    {
        return $this->state(fn (array $attributes) => [
            'session_id' => $session instanceof VerseCardSession ? $session->id : $session,
        ]);
    }

    /**
     * Indicate that the asset is of a specific kind.
     *
     * @param string $kind
     * @return static
     */
    public function withKind(string $kind): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => $kind,
        ]);
    }

    /**
     * Indicate that the asset is in a specific state.
     *
     * @param string $state
     * @return static
     */
    public function withState(string $state): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => $state,
        ]);
    }

    /**
     * Indicate that the asset has been downloaded (ready).
     *
     * @return static
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => 'ready',
            'path' => 'verse-cards/' . $attributes['session_id'] . '/c/' . $this->faker->unique()->numberBetween(1, 1000) . '.jpg',
            'thumb_path' => 'verse-cards/' . $attributes['session_id'] . '/c/' . $this->faker->unique()->numberBetween(1, 1000) . '_t.jpg',
            'bytes' => $this->faker->numberBetween(100000, 500000),
        ]);
    }
}