<?php

namespace App\Http\Controllers\Api;

use Airflix\Contracts\MovieResults;
use Airflix\Contracts\Movies;
use App\Http\Requests;
use Illuminate\Http\Request;

class MovieResultController extends ApiController
{
    /**
     * Inject the movies resource.
     *
     * @return \Airflix\Contracts\Movies
     */
    protected function movies()
    {
        return app(Movies::class);
    }

    /**
     * Inject the movie results resource.
     *
     * @return \Airflix\Contracts\MovieResults
     */
    protected function movieResults()
    {
        return app(MovieResults::class);
    }

    /**
     * Get a paginated set of movie results from the tmdb API.
     *
     * @param  string $id
     * @param  \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id, Request $request)
    {
        $movie = $this->movies()
            ->get($id);

        $currentPage = $request->query('page', 1);
        $url = $request->url();

        $results = $this->movieResults()
            ->get($movie, $currentPage, $url);

        $transformer = $this->movieResults()
            ->transformer();

        return $this->apiResponse()
            ->respondWithPaginator(
                $results,
                $transformer
            );
    }
}
