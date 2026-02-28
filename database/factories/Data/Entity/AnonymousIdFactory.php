<?php

namespace Database\Factories\Data\Entity;

use Illuminate\Database\Eloquent\Factories\Factory;
use SzentirasHu\Data\Entity\AnonymousId;

class AnonymousIdFactory extends Factory
{
    protected $model = AnonymousId::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => $this->faker->unique()->regexify('[A-Za-z0-9]{22}'),
            'last_login' => now(),
        ];
    }
}
