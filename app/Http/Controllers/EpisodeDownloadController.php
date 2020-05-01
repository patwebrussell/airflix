<?php

namespace App\Http\Controllers;

use Airflix\Contracts\Episodes;
use Airflix\Contracts\EpisodeViews;

class EpisodeDownloadController extends Controller
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
     * Inject the episode views resource.
     *
     * @return \Airflix\Contracts\EpisodeViews
     */
    protected function views()
    {
        return app(EpisodeViews::class);
    }

    /**
     * Get an episode file stream and mark as watched.
     *
     * @param  string $id
     *
     * @return \Illuminate\Http\RedirectResponse 
     */
    public function show($id)
    {

        $episode = $this->episodes()->get($id);

    
        $this->views()->watch($episode);
        
        $stream_link = explode('public/downloads', $episode->folder_path);
        $folder['fold_path'] = $stream_link;

        // PAT: TO-DO will need a better way of serving the movie (HTML5)

        //return json_encode($folder);
        return redirect('shows/player/'.$id);
        //return redirect('downloads'.$stream_link[1]);
    }
}



