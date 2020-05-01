<?php

namespace Airflix;

use Carbon\Carbon;
use DB;
use Storage;
use Tmdb;

class Shows implements Contracts\Shows
{
    use Retriable;

    /**
     * Inject the tv show filters.
     *
     * @return \Airflix\ShowFilters
     */
    public function filters()
    {
        return app(
            ShowFilters::class
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
     * Inject the seasons resource.
     *
     * @return \Airflix\Contracts\Seasons
     */
    public function seasons()
    {
        return app(
            Contracts\Seasons::class
        );
    }

    /**
     * Inject the tv show transformer.
     *
     * @return \Airflix\Contracts\ShowTransformer
     */
    public function transformer()
    {
        return app(
            Contracts\ShowTransformer::class
        );
    }

    /**
     * Inject the episode views resource.
     *
     * @return \Airflix\Contracts\EpisodeViews
     */
    public function views()
    {
        return app(
            Contracts\EpisodeViews::class
        );
    }

    /**
     * Get a set of tv shows.
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
            $query = new Show;
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
     * Get a tv show.
     *
     * @param  string $id
     * @param  array $relationships
     * @param  \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Airflix\Show
     */
    public function get($id, $relationships = [], $query = null)
    {
        if (!$query) {
            $query = new Show;
        }

        return $query->with($relationships)
            ->where('uuid', $id)
            ->firstOrFail();
    }

    /**
     * Patch a tv show.
     *
     * @param  string $id
     * @param  array $input
     *
     * @return \Airflix\Show
     */
    public function patch($id, $input)
    {
        $show = $this->get($id);

        if (isset($input['name'])) {
            $show->name = $input['name'];
        }

        if (isset($input['backdrop_path'])) {
            $show->backdrop_path = $input['backdrop_path'];
        }

        if (isset($input['poster_path'])) {
            $show->poster_path = $input['poster_path'];
        }

        $show->save();

        $this->imageClient()
            ->download($show->backdrop_path, 'backdrops');

        $this->imageClient()
            ->download($show->poster_path, 'posters');

        if (isset($input['tmdb_show_id'])) {
            $useDefaults = true;
            $tmdbShowId = $input['tmdb_show_id'];

            $show = $this->refreshShow(
                $show, $tmdbShowId, $useDefaults
            );
        }

        return $show;
    }

    /**
     * Update the total views of a tv show.
     *
     * @param  \Airflix\Show $show
     *
     * @return \Airflix\Show
     */
    public function updateTotalViews($show)
    {
        $show->total_views = $show->views()->count();

        $show->save();

        return $show;
    }

    /**
     * Update the tv show with data from the tmdb API.
     *
     * @param  \Airflix\Show $show
     * @param  integer $tmdbShowID
     * @param  bool $useDefaults
     *
     * @return \Airflix\Show
     */
    public function refreshShow($show, $tmdbShowId, $useDefaults = false, $folder_path)
    {
        
        // Remove links to old Seasons and Episodes
        if ($tmdbShowId != $show->tmdb_show_id) {
            $show->seasons()
                ->update([
                    'show_id' => 0,
                    'show_uuid' => null,
                ]);

            $show->episodes()
                ->update([
                    'show_id' => 0,
                    'show_uuid' => null,
                    'folder_path' => $folder_path,
                ]);

            $show->views()
                ->update([
                    'show_id' => 0,
                    'show_uuid' => null,
                ]);
        }

        // Get result for current Show
        $result = $this->retry(3,
            function () use ($tmdbShowId) {
                return Tmdb::getTvApi()
                    ->getTvshow($tmdbShowId);
            }, function () {
                sleep(config('airflix.tmdb.throttle_seconds'));
            });

        if (! $result) {
            return $show;
        }

        // Relink any Episode Views
        $this->views()
            ->link($show, $tmdbShowId);

        // Update total Episode Views
        $this->updateTotalViews($show);

        // Retrieve valid Genre identifiers
        $genreIds = $this->genres()
            ->getIds(
                collect($result['genres'])
                    ->pluck('id')
            );

        // Update Genres on current Show
        $show->genres()
            ->sync($genreIds);

        // Retrieve external ids
        $externalIds = $this->retry(3,
            function () use ($tmdbShowId) {
                return Tmdb::getTvApi()
                    ->getExternalIds($tmdbShowId);
            }, function () {
                sleep(config('airflix.tmdb.throttle_seconds'));
            });

        // Update fillable fields
        $show->update(array_merge($result, [
            'tmdb_show_id' => $result['id'],
            'imdb_id' => $externalIds['imdb_id'],
            'tvdb_id' => $externalIds['tvdb_id'],
            'tvrage_id' => $externalIds['tvrage_id'],
        ]));

        // Update editable fields
        $show->name = $show->name && !$useDefaults ?
            $show->name : $result['name'];
        $show->poster_path = $show->poster_path && !$useDefaults ?
            $show->poster_path : $result['poster_path'];
        $show->backdrop_path = $show->backdrop_path && !$useDefaults ?
            $show->backdrop_path : $result['backdrop_path'];

        // Calculate average episode runtime
        $show->average_runtime = collect($result['episode_run_time'])->avg();

        $show->save();

        // Download poster
        $this->imageClient()
            ->download($show->poster_path, 'posters');

        // Download backdrop
        $this->imageClient()
            ->download($show->backdrop_path, 'backdrops');

        foreach ($result['seasons'] as $season) {
            $seasonNumber = $season['season_number'];
            $season = $this->seasons()->refreshSeason($seasonNumber, $show, $folder_path);
        }

        return $show;
    }

    /**
     * Update tv shows with data from the tmdb API.
     *
     * @param  bool $onlyNewFolders
     * @param  \Symfony\Component\Console\Output\ConsoleOutput $output
     * 
     * @return integer
     */
    public function refreshShows($onlyNewFolders = false, $output = null) {
        $imageClient = new TmdbImageClient;

        // PAT: Get all files recursivly
        $Directory = new \RecursiveDirectoryIterator(
            public_path('downloads/episodes')
        );
        $iterator = new \RecursiveIteratorIterator($Directory);
        $folderPaths = iterator_to_array($iterator, true);

        Show::whereNotIn('folder_path', $folderPaths)->delete();

        // Only refresh new folders
        if ($onlyNewFolders) {
            Show::chunk(config('airflix.per_page', 100),
                function ($shows) use (&$folderPaths) {
                    $folderPaths = $folderPaths->diff(
                        $shows->pluck('folder_path')
                    );
                });
        }

        $bar = null;
        
        // Create the progress bar
        if($output) {
            $bar = $output->createProgressBar(count($folderPaths));
            $bar->setFormat('verbose');
        }

        foreach ($folderPaths as $folderPath) {

            $path_parts = pathinfo($folderPath);

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

            // Convert/Skip files that are not mp4
            /*if ($path_parts['extension']!="mp4"){
                $folderPath_new = $path_parts['dirname']."/".$path_parts['filename'].".mp4";
                if (file_exists($folderPath_new)){
                    continue;
                }else{
                    dump("\nffmpeg: (".$path_parts['filename'].") ".$path_parts['extension']." -> mp4 ");
                    exec("sudo ffmpeg -v quiet -stats -i \"$folderPath\" -c:v copy -c:a aac \"".$path_parts['dirname']."/".$path_parts['filename'].".mp4\"");
                    //exec("sudo rm $folderPath");
                    dump("\n".$path_parts['filename'].".".$path_parts['extension']." was deleted"."\n");
                    $folderPath = $folderPath_new;
                    $path_parts = pathinfo($folderPath);
                }
            }*/

            // PAT: Get fileName and extract show name, season and episode
            $fileName = basename($folderPath); 

            if (preg_match("'^(.+)\.S([0-9]+)E([0-9]+).*$'i",$fileName,$n))
            {
                $name = preg_replace("'\.'"," ",$n[1]);
                $season = str_pad(intval($n[2],10), 2, '0', STR_PAD_LEFT); 
                $episode = str_pad(intval($n[3],10), 2, '0', STR_PAD_LEFT); 
            }

            if (!isset($name)||!isset($season)||!isset($episode)){
                continue;
            }

            $folderPathex = explode('downloads/episodes/', $folderPath);
            $folderName = str_replace($fileName, '', $folderPathex[1]);
            
            // Remove year, such as '(2016)', from folder name for search
            $searchName = trim(
                preg_replace('/\((\d+)\)/', '', $name)
            );

            // PAT: Remove [stuff], from folder name for search
            $searchName = trim(
                preg_replace('(\\[.*?\\])', '', $searchName)
            );

            // PAT: we skip if name is empty for some weird reason
            if ($searchName==""||$searchName==null) {
                continue;
            }
            $show = Show::firstOrCreate([
                'name' => $name,
            ]);
            $show->file_name = $fileName;
            $show->folder_path = $folderPath;
            $show->folder_name = $folderName;
            $show->name = $searchName;

            // PAT: Search for results by file name
            $query = $this->retry(10,
                function () use ($searchName) {
                    return Tmdb::getSearchApi()
                        ->searchTv($searchName);
                }, function () {
                    sleep(config('airflix.tmdb.throttle_seconds'));
                }
            );

            $totalResults = $query['total_results'];

            $nomatch=0;

            foreach ($query['results'] as $queryShow) {
                $tmdbShowId = $queryShow['id'];

                $attributes = [
                    $name,
                    $show,
                    $queryShow,
                    $totalResults,
                    $tmdbShowId,
                ];

                if ($this->hasMatch($attributes)) {
                    $show = $this->refreshShow($show, $tmdbShowId, false, $folderPath);
                    $nomatch=1;
                    break;
                }
            }

            // If no result could be matched
            if ($nomatch==0) {
                $show->name = $searchName;
                $show->save();
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
            $folderName,
            $show,
            $queryShow,
            $totalResults,
            $tmdbShowId
        ) = $attributes;

        if (isset($queryShow['first_air_date'])){
            $firstAirDate = new Carbon($queryShow['first_air_date']);
            $firstAirDate = $firstAirDate->year;
        }else{
            $firstAirDate = '-';
        }

        // Remove colons and periods from the name
        $name = preg_replace('/[\:\.]/', '', $queryShow['name']);

        // Add back the year, such as '(2016)', to the name
        $nameWithYear = $name.' ('.$firstAirDate.')';

        return $totalResults == 1 ||
            $folderName == $name ||
            $folderName == $nameWithYear ||
            $show->tmdb_show_id == $tmdbShowId;
    }

    /**
     * Truncate the shows table.
     * 
     * @return bool
     */
    public function truncate()
    {
        return Show::truncate();
    }
}