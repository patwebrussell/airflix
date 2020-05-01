<?php

namespace App\Http\Controllers\Api;

use Airflix\Contracts\Episodes;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests;
use Illuminate\Http\Request;

class EpisodeController extends ApiController
{
    /**
     * Inject the episodes resource.
     *
     * @return \Airflix\Contracts\Episodes
     */
    protected function episodes()
    {
        return app(Episodes::class);
    }

    /**
     * Get an episode.
     *
     * @param  string $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $relationships = [
            'show',
            'show.genres',
            'season',
            'views',
        ];

        $episode = $this->episodes()
            ->get($id, $relationships);

        $transformer = $this->episodes()
            ->transformer();

        $this->apiResponse()
            ->fractal()
            ->parseIncludes($relationships);

        return $this->apiResponse()
            ->respondWithItem(
                $episode,
                $transformer
            );
    }
}
