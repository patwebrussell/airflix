<?php

namespace Airflix;

use Carbon\Carbon;
use DB;
use Storage;
use Tmdb;

use Airflix\Contracts\Settings;
use Illuminate\Console\Command;

class Movies implements Contracts\Movies
{
    use Retriable;

    /**
     * Inject the movie filters.
     *
     * @return \Airflix\MovieFilters
     */
    public function filters()
    {
        return app(
            MovieFilters::class
        );
    }

    /**
     * Inject the genres resource.
     *
     * @return \Airflix\Contracts\Genres
     */
    public function genres()
    {
        return app(
            Contracts\Genres::class
        );
    }

    /**
     * Inject the tmdb image client.
     *
     * @return \Airflix\Contracts\TmdbImageClient
     */
    public function imageClient()
    {
        return app(
            Contracts\TmdbImageClient::class
        );
    }

    /**
     * Inject the movie transformer.
     *
     * @return \Airflix\Contracts\MovieTransformer
     */
    public function transformer()
    {
        return app(
            Contracts\MovieTransformer::class
        );
    }

    /**
     * Inject the movie views resource.
     *
     * @return \Airflix\Contracts\MovieViews
     */
    public function views()
    {
        return app(
            Contracts\MovieViews::class
        );
    }

    /**
     * Get a set of movies.
     *
     * @param  array $relationships
     * @param  bool $pagination
     * @param  \Illuminate\Database\Eloquent\Builder $query
     *
     * @return mixed
     */
    public function index($relationships = [], $pagination = true, $query = null)
    {
        if (!$query) {
            $query = new Movie;
        }

        $results = $query->with($relationships)
            ->filter($this->filters());

        if ($pagination) {
            $results = $results->paginate(config('airflix.per_page', 100))
                ->appends($this->filters()->parameters());
        } else {
            $results = $results->get();
        }

        return $results;
    }

    /**
     * Get a movie.
     *
     * @param  string $id
     * @param  array $relationships
     * @param  \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Airflix\Movie
     */
    public function get($id, $relationships = [], $query = null)
    {
        if (!$query) {
            $query = new Movie;
        }

        return $query->with($relationships)
            ->where('uuid', $id)
            ->firstOrFail();
    }

    /**
     * Patch a movie.
     *
     * @param  string $id
     * @param  array $input
     *
     * @return \Airflix\Movie
     */
    public function patch($id, $input)
    {
        $movie = $this->get($id);

        if (isset($input['title'])) {
            $movie->title = $input['title'];
        }

        if (isset($input['backdrop_path'])) {
            $movie->backdrop_path = $input['backdrop_path'];
        }

        if (isset($input['poster_path'])) {
            $movie->poster_path = $input['poster_path'];
        }

        $movie->save();

        $this->imageClient()
            ->download($movie->backdrop_path, 'backdrops');

        $this->imageClient()
            ->download($movie->poster_path, 'posters');

        if (isset($input['tmdb_movie_id'])) {
            $useDefaults = true;
            $tmdbMovieId = $input['tmdb_movie_id'];

            $movie = $this->refreshMovie(
                $movie, $tmdbMovieId, $useDefaults
            );
        }

        return $movie;
    }

    /**
     * Update the total views of a movie.
     *
     * @param  \Airflix\Movie $movie
     *
     * @return \Airflix\Movie
     */
    public function updateTotalViews($movie)
    {
        $movie->total_views = $movie->views()->count();

        $movie->save();

        return $movie;
    }

    /**
     * Update the movie with data from the tmdb API.
     *
     * @param  \Airflix\Movie $movie
     * @param  integer $tmdbMovieId
     * @param  bool $useDefaults
     *
     * @return \Airflix\Movie
     */
    public function refreshMovie($movie, $tmdbMovieId, $useDefaults = false)
    {
        // Remove links to old Views
        if ($tmdbMovieId != $movie->tmdb_movie_id) {
            $movie->views()
                ->update([
                    'movie_id' => 0,
                    'movie_uuid' => null,
                ]);
        }

        // Get result for current Movie
        $result = $this->retry(3,
            function () use ($tmdbMovieId) {
                return Tmdb::getMoviesApi()
                    ->getMovie($tmdbMovieId);
            }, function () {
                sleep(config('airflix.tmdb.throttle_seconds'));
            });

        if (! $result) {
            return $movie;
        }

        // Relink any Movie Views
        $this->views()
            ->link($movie, $tmdbMovieId);

        // Update total Movie Views
        $this->updateTotalViews($movie);

        // Retrieve valid Genre identifiers
        $genreIds = $this->genres()
            ->getIds(
                collect($result['genres'])
                    ->pluck('id')
            );

        // Update Genres on current Movie
        $movie->genres()
            ->sync($genreIds);

        // Update fillable fields
        $movie->update(array_merge($result, [
            'tmdb_movie_id' => $result['id'],
        ]));

        // Update editable fields
        $movie->title = $movie->title && !$useDefaults ?
            $movie->title : $result['title'];
        $movie->poster_path = $movie->poster_path && !$useDefaults ?
            $movie->poster_path: $result['poster_path'];
        $movie->backdrop_path = $movie->backdrop_path && !$useDefaults ?
            $movie->backdrop_path : $result['backdrop_path'];

        $movie->save();

        // Download poster
        $this->imageClient()
            ->download($movie->poster_path, 'posters');

        // Download backdrop
        $this->imageClient()
            ->download($movie->backdrop_path, 'backdrops');

        return $movie;
    }

    /**
     * Update movies with data from the tmdb API.
     *
     * @param  bool $onlyNewFolders
     * @param  \Symfony\Component\Console\Output\ConsoleOutput $output
     *
     * @return integer
     */
    public function refreshMovies($onlyNewFolders = false, $output = null)
    {

        // PAT: Get all files recursivly
        $Directory = new \RecursiveDirectoryIterator(
            public_path('downloads/movies')
        );
        $iterator = new \RecursiveIteratorIterator($Directory);
        $folderPaths = iterator_to_array($iterator, true);

        Movie::whereNotIn('folder_path', $folderPaths)->delete();

        // Only refresh new folders
        if ($onlyNewFolders) {
            Movie::chunk(config('airflix.per_page', 100),
                function ($movies) use (&$folderPaths) {
                    $folderPaths = $folderPaths->diff(
                        $movies->pluck('folder_path')
                    );
                });
        }

        $bar = null;

        if($output) {
            $bar = $output->createProgressBar(count($folderPaths));
            $bar->setFormat('verbose');
        }

        foreach ($folderPaths as $folderPath) {

            // PAT: Ignore all folders with out a valide extenssion
            $extList =  env("AIRFLIX_EXTENSIONS_VIDEO", "m4v, mp4, mkv");
            $extList = explode(', ', $extList );
            $found =0;
            foreach($extList as $ext){
                if (strstr($folderPath, $ext)) {
                    $found=1;
                }
            }
            if ($found==0){
                continue;
            }

            // PAT: Get fileName and use that for TMDB as well as folderPath and folderName
            $fileName = basename($folderPath); 
            $folderPathex = explode('downloads/movies/', $folderPath);
            $folderName = str_replace($fileName, '', $folderPathex[1]);

            // PAT: Get the file name from the path
            $path_parts = pathinfo($folderPath);
            $searchName = $path_parts['filename'];

            // PAT: Use the filname not the folder name
            // Remove year, such as '(2016)', from folder name for search
            $searchName = trim(
                preg_replace('/\((\d+)\)/', '', $searchName)
            );

            // PAT: Remove [stuff], from folder name for search
            $searchName = trim(
                preg_replace('(\\[.*?\\])', '', $searchName)
            );

            // PAT: we skip if name is empty for some weird reason
            if ($searchName=="") {
                continue;
            }

            $movie = Movie::firstOrCreate([
                'folder_path' => $folderPath,
            ]);
            $movie->file_name = $fileName;
            $movie->folder_name = $folderName;
            $movie->title = $searchName;

            // PAT: Search for results by file name
            $query = $this->retry(10,
                function () use ($searchName) {
                    return Tmdb::getSearchApi()
                        ->searchMovies($searchName);
                }, function () {
                    sleep(config('airflix.tmdb.throttle_seconds'));
                }
            );

            $totalResults = $query['total_results'];
            $found = 0;

            foreach ($query['results'] as $queryMovie) {
                $tmdbMovieId = $queryMovie['id'];

                $attributes = [
                    $searchName,
                    $movie,
                    $queryMovie,
                    $totalResults,
                    $tmdbMovieId,
                ];

                if ($this->hasMatch($attributes)) {
                    $movie = $this->refreshMovie(
                        $movie, $tmdbMovieId
                    );
                    $found=1;
                    break;
                }
            }

            if ($found=="0"){
                $movie->file_name = $fileName;
                $movie->title = $searchName;
                $movie->folder_name = $folderName;
                $movie->save();
            }

            // Advance the progress bar
            if($bar) {
                $bar->advance();
            }

        }

        // Finish and clear the progress bar
        if($bar) {
            $bar->finish();
            $bar->clear();
        }

        return count($folderPaths);
    }

    /**
     * Determines if a match is found from the tmdb API.
     *
     * @param  array $attributes
     *
     * @return bool
     */
    protected function hasMatch($attributes)
    {
        list(
            $Name,
            $movie,
            $queryMovie,
            $totalResults,
            $tmdbMovieId
        ) = $attributes;

        // PAT: Check the value before using it if not use '-' as the date to avoid crash
        // PAT: Use $releaseDate as a string not an object to avoid crash if undefined
        if (isset($queryMovie['release_date'])){
            $releaseDate = new Carbon($queryMovie['release_date']);
            $releaseDate = $releaseDate->year;
        }else{
            // PAT: No release date provided
            $releaseDate ='-';
        }

        // Remove colons and periods from the title
        $title = preg_replace('/[\:\.]/', '', $queryMovie['title']);

        // PAT: Using $releaseDate instead of $releaseDate->year
        // Add back the year, such as '(2016)', to the title
        $titleWithYear = $title.' ('.$releaseDate.')';

        return $totalResults == 1 ||
            $Name == $title ||
            $Name == $titleWithYear ||
            $movie->tmdb_movie_id == $tmdbMovieId;
    }

    /**
     * Truncate the movies table.
     * 
     * @return bool
     */
    public function truncate()
    {
        return Movie::truncate();
    }
}
