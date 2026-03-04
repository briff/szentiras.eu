<?php

namespace Database\Factories\Data\Entity;

use Illuminate\Database\Eloquent\Factories\Factory;
use Pgvector\Laravel\Vector;
use SzentirasHu\Data\Entity\EmbeddedExcerpt;

class EmbeddedExcerptFactory extends Factory
{
    protected $model = EmbeddedExcerpt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gepi' => '1PE_2_3',
            'usx_code' => '1PE',
            'chapter' => 2,
            'verse' => 3,
            'model' => config('settings.ai.embeddingModel', 'text-embedding-3-small'),
            'embedding' => new Vector(array_fill(0, 512, 0.1)),
            'hash' => $this->faker->md5(),
            'reference' => '1Pt 2,3',
            'translation_abbrev' => 'SZIT'
        ];
    }
}
