<?php

namespace Database\Factories\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use SzentirasHu\Models\DailyReading;

class DailyReadingFactory extends Factory
{
    protected $model = DailyReading::class;

    public function definition(): array
    {
        return [
            'date' => $this->faker->unique()->date(),
            'celebration_name' => $this->faker->words(4, true),
            'raw_parts' => [],
            'processed_refs' => ['Mik7,14-15', 'Lk15,1-3'],
            'status' => DailyReading::STATUS_FETCHED,
            'error_message' => null,
        ];
    }

    public function available(): static
    {
        return $this->state([
            'status' => DailyReading::STATUS_FETCHED,
            'processed_refs' => ['Mik7,14-15', 'Lk15,1-3'],
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => DailyReading::STATUS_FAILED,
            'processed_refs' => null,
            'error_message' => 'HTTP 404',
        ]);
    }

    public function commentariesQueued(): static
    {
        return $this->state([
            'status' => DailyReading::STATUS_COMMENTARIES_QUEUED,
            'processed_refs' => ['Mik7,14-15', 'Lk15,1-3'],
        ]);
    }
}
