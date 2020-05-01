<?php

namespace Airflix\V1;

use League\Fractal;
use Airflix\Contracts;

class MovieTransformer
    extends Fractal\TransformerAbstract
    implements Contracts\MovieTransformer
{
    public $resourceType = 'movies';

    public $resourceVersion = 1.0;

    /**
     * List of resources possible to include
     *
     * @var array
     */
    protected $availableIncludes = [
        'backdrops',
        'genres',
        'posters',
        'results',
        'views',
    ];

    /**
     * Turn this item object into a generic array
     *
     * @param  \Airflix\Movie $movie
     *
     * @return array
     */
    public function transform($movie)
    {
        setlocale(LC_MONETARY, 'en_US.UTF-8');
        $path_parts = pathinfo($movie->folder_path);

        // PAT: the has_file is broken and returns false
        if (file_exists($movie->folder_path)&&$path_parts['extension']=="mp4"){
            $exists = true;
        }else{
            $exists = false;
        }

        return [
            'id' => $movie->uuid,
            'tmdb_movie_id' => $movie->tmdb_movie_id,
            'title' => $movie->title,
            'original_title' => $movie->original_title,
            'folder_name' => $movie->folder_name,
            'overview' => $movie->overview,
            'home_page' => $movie->homepage,
            'poster_url' => $movie->poster_url,
            'backdrop_url' => $movie->backdrop_url,
            'runtime' => (int) $movie->runtime,
            'popularity' => (double) $movie->popularity,
            'release_date' => $movie->release_date ?
                $movie->release_date->toIso8601String() : null,
            'budget' => '$'.number_format($movie->budget, '2'),
            'budget' => '$'.number_format($movie->revenue, '2'),
            'total_views' => (int) $movie->total_views,
            'tmdb_url' => config('airflix.urls.tmdb.movie').
                $movie->tmdb_movie_id,
            'imdb_url' => $movie->imdb_id ?
                config('airflix.urls.imdb').$movie->imdb_id : null,
            'has_file' => $exists,//(bool) $movie->has_file,
        ];
    }

    /**
     * Include backdrops
     *
     * @param  \Airflix\Movie $movie
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeBackdrops($movie)
    {
        $backdrops = $this->movieImages()
            ->getBackdrops($movie);

        $transformer = app(
            Contracts\TmdbImageTransformer::class
        );

        return $this->collection(
            $backdrops,
            $transformer,
            $transformer->resourceType
        );
    }

    /**
     * Include genres
     *
     * @param  \Airflix\Movie $movie
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeGenres($movie)
    {
        $genres = $movie->genres;

        $transformer = app(
            Contracts\GenreTransformer::class
        );

        return $this->collection(
            $genres,
            $transformer,
            $transformer->resourceType
        );
    }

    /**
     * Include posters
     *
     * @param  \Airflix\Movie $movie
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includePosters($movie)
    {
        $posters = $this->movieImages()
            ->getPosters($movie);

        $transformer = app(
            Contracts\TmdbImageTransformer::class
        );

        return $this->collection(
            $posters,
            $transformer,
            $transformer->resourceType
        );
    }

    /**
     * Include results
     *
     * @param  \Airflix\Movie $movie
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeResults($movie)
    {
        $results = $this->movieResults()
            ->get($movie);

        $transformer = app(
            Contracts\TmdbMovieResultTransformer::class
        );

        return $this->collection(
            $results,
            $transformer,
            $transformer->resourceType
        );
    }

    /**
     * Include views
     *
     * @param  \Airflix\Movie $movie
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeViews($movie)
    {
        $views = $movie->monthlyViews();

        $transformer = app(
            Contracts\MovieViewMonthlyTransformer::class
        );

        return $this->collection(
            $views,
            $transformer,
            $transformer->resourceType
        );
    }

    /**
     * Load movie images resource
     *
     * @return \Airflix\Contracts\MovieImages
     */
    protected function movieImages()
    {
        return app(
            Contracts\MovieImages::class
        );
    }

    /**
     * Load movie results resource
     *
     * @return \Airflix\Contracts\MovieResults
     */
    protected function movieResults()
    {
        return app(
            Contracts\MovieResults::class
        );
    }
}
