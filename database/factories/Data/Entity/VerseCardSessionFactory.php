<?php

namespace Database\Factories\Data\Entity;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use SzentirasHu\Data\Entity\VerseCardSession;

class VerseCardSessionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = VerseCardSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'user_id' => null,
            'verse_ref' => 'Jn 3,16',
            'verse_text' => 'Mert úgy szerette Isten a világot, hogy az Ő egyszülött Fiát adta, hogy aki hisz őbenne, el ne vesszen, hanem örök élete legyen.',
            'theme_slug' => 'nature',
            'keywords' => ['peace', 'love'],
            'status' => 'searching',
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the session belongs to a user.
     *
     * @return static
     */
    public function forUser(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    /**
     * Indicate that the session is expired.
     *
     * @return static
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subHour(),
        ]);
    }

    /**
     * Indicate that the session has a specific status.
     *
     * @param string $status
     * @return static
     */
    public function withStatus(string $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}