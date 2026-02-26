<?php

namespace Database\Factories\Data\Entity;

use Illuminate\Database\Eloquent\Factories\Factory;
use SzentirasHu\Data\Entity\Translation;

class TranslationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Translation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'abbrev' => strtoupper($this->faker->lexify('???')),
            'order' => $this->faker->numberBetween(1, 100),
            'denom' => $this->faker->word(),
            'lang' => $this->faker->languageCode(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}