<?php

namespace App\Http\Controllers\Api;

use Airflix\Contracts\ShowImages;
use Airflix\Contracts\Shows;
use App\Http\Requests;
use Illuminate\Http\Request;

class ShowBackdropController extends ApiController
{
    /**
     * Inject the tv shows resource.
     *
     * @return \Airflix\Contracts\Shows
     */
    protected function shows()
    {
        return app(Shows::class);
    }

    /**
     * Inject the tv show images resource.
     *
     * @return \Airflix\Contracts\ShowImages
     */
    protected function showImages()
    {
        return app(ShowImages::class);
    }

    /**
     * Get a collection of tv show backdrops from the tmdb API.
     *
     * @param  string $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $show = $this->shows()
            ->get($id);

        $backdrops = $this->showImages()
            ->getBackdrops($show);

        $transformer = $this->showImages()
            ->transformer();

        return $this->apiResponse()
            ->respondWithCollection(
                $backdrops,
                $transformer
            );
    }
}
