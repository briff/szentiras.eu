<?php

namespace Database\Factories\Data\Entity;

use Illuminate\Database\Eloquent\Factories\Factory;
use SzentirasHu\Data\Entity\ContactMessage;

class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_anonymous_id' => null,
            'receiver_anonymous_id' => null,
            'parent_id' => null,
            'message' => $this->faker->paragraph(),
            'is_read' => false,
            'resolved_at' => null,
        ];
    }
}
