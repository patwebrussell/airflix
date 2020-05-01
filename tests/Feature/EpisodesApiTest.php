<?php

namespace Tests\Feature;

use Airflix\Episode;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class EpisodesApiTest extends TestCase
{
    use DatabaseMigrations;

    protected $episode;

    public function setUp()
    {
        parent::setUp();

        $this->episode = factory(Episode::class)->create();
    }

    /** @test */
    public function it_fetches_a_single_episode()
    {
        $response = $this->json('GET', '/api/episodes/'.$this->episode->uuid);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $this->episode->uuid,
        ]);
        $response->assertJsonStructure([
            'data' => [
                'attributes',
                'relationships' => [
                    'show',
                    'season',
                    'views',
                ],
            ],
            'included',
            'meta',
        ]);
    }

    /** @test */
    public function it_404s_if_a_episode_is_not_found()
    {
        $response = $this->json('GET', '/api/episodes/x');

        $response->assertStatus(404);
    }
}
