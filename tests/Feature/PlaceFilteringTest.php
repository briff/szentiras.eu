<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SzentirasHu\Test\Common\TestCase;
use SzentirasHu\Data\Entity\Place;

class PlaceFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_with_coordinates_scope_filters_places_without_lon_lat(): void
    {
        // Create a place with coordinates
        Place::create([
            'external_id' => 'test1',
            'type' => 'city',
            'friendly_id' => 'Jerusalem',
            'comment' => null,
            'lon_lat' => '35.2383,31.7683',
        ]);

        // Create a place without coordinates
        Place::create([
            'external_id' => 'test2',
            'type' => 'city',
            'friendly_id' => 'Unknown Place',
            'comment' => null,
            'lon_lat' => null,
        ]);

        // Create another place with coordinates
        Place::create([
            'external_id' => 'test3',
            'type' => 'mountain',
            'friendly_id' => 'Mount Sinai',
            'comment' => null,
            'lon_lat' => '33.9731,28.3547',
        ]);

        // Test that withCoordinates scope returns only places with coordinates
        $placesWithCoordinates = Place::withCoordinates()->get();

        $this->assertCount(2, $placesWithCoordinates);
        $this->assertTrue($placesWithCoordinates->every(fn ($place) => $place->lon_lat !== null));
        $this->assertTrue($placesWithCoordinates->pluck('friendly_id')->contains('Jerusalem'));
        $this->assertTrue($placesWithCoordinates->pluck('friendly_id')->contains('Mount Sinai'));
        $this->assertFalse($placesWithCoordinates->pluck('friendly_id')->contains('Unknown Place'));
    }

    public function test_show_place_details_excludes_places_without_coordinates(): void
    {
        $place1 = Place::create([
            'external_id' => 'test1',
            'type' => 'city',
            'friendly_id' => 'Jerusalem',
            'comment' => null,
            'lon_lat' => '35.2383,31.7683',
        ]);

        $place2 = Place::create([
            'external_id' => 'test2',
            'type' => 'city',
            'friendly_id' => 'Unknown Place',
            'comment' => null,
            'lon_lat' => null,
        ]);

        // Request details for both places
        $response = $this->get("/place/{$place1->id},{$place2->id}");

        $response->assertStatus(200);
        $this->assertStringContainsString('Jerusalem', $response->getContent());
        $this->assertStringNotContainsString('Unknown Place', $response->getContent());
    }
}
