<?php

namespace App\Http\Controllers;

use Airflix\Contracts\Movies;
use Airflix\Contracts\MovieViews;

class MovieDownloadController extends Controller
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
     * Inject the movie views resource.
     *
     * @return \Airflix\Contracts\MovieViews
     */
    protected function views()
    {
        return app(MovieViews::class);
    }

    /**
     * Get a movie file stream and mark as watched.
     *
     * @param  string $id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function show($id)
    {
        $movie = $this->movies()->get($id);

        /*if (! $movie->has_file) {
            return abort(404);
        }*/

        // PAT: Check if the file is reachable
        if (! file_exists($movie->folder_path)) {
            return abort(404);
        }

        $this->views()->watch($movie);

        $stream_link = explode('public/downloads', $movie->folder_path);

        // PAT: TO-DO will need a better way of serving the movie (HTML5)
        return redirect('downloads'.$stream_link[1]);
    }
}
