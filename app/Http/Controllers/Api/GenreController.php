<?php

namespace App\Http\Controllers\Api;

use Airflix\Contracts\Genres;
use App\Http\Requests;
use Illuminate\Http\Request;

class GenreController extends ApiController
{
    /**
     * Inject the genres resource.
     *
     * @return \Airflix\Contracts\Genres
     */
    protected function genres()
    {
        return app(Genres::class);
    }

    /**
     * Get a set of genres.
     *
     * @param  \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $relationships = [];
        $pagination = false;

        $genres = $this->genres()
            ->index($relationships, $pagination);

        $transformer = $this->genres()
            ->transformer();

        return $this->apiResponse()
            ->respondWithCollection(
                $genres,
                $transformer
            );
    }
}
