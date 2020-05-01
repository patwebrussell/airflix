<?php

namespace Tests\Feature;

use Airflix\Genre;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class GenresApiTest extends TestCase
{
    use DatabaseMigrations;

    protected $genre;

    public function setUp()
    {
        parent::setUp();

        $this->genre = factory(Genre::class)->create();
    }

    /** @test */
    public function it_fetches_genres()
    {
        $response = $this->json('GET', '/api/genres');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $this->genre->uuid,
        ]);
        $response->assertJsonStructure([
            'data',
            'meta',
        ]);
    }
}
