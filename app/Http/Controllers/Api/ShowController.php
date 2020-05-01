<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Airflix\Contracts\Shows;

class ShowController extends ApiController
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
     * Get a set of tv shows.
     *
     * @param  \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $relationships = ['genres',];

        $shows = $this->shows()
            ->index($relationships);

        $transformer = $this->shows()
            ->transformer();

        $this->apiResponse()
            ->fractal()
            ->parseIncludes($relationships);

        return $this->apiResponse()
            ->respondWithPaginator(
                $shows, 
                $transformer
            );
    }

    /**
     * Get a tv show.
     *
     * @param  string  $id
     * @param  \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id, Request $request)
    {
        $relationships = ['genres', 'seasons', 'views',];

        $includes = (array) array_filter(
            explode(',', $request->input('include')), 'strlen'
        );
        $includes = array_merge($includes, $relationships);

        $show = $this->shows()
            ->get($id, $relationships);


        $transformer = $this->shows()
            ->transformer();

        $this->apiResponse()
            ->fractal()
            ->parseIncludes($includes);
        
        return $this->apiResponse()
            ->respondWithItem(
                $show, 
                $transformer
            );
    }

    /**
     * Patch a tv show.
     *
     * @param  string  $id
     * @param  \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function patch($id, Request $request)
    {
        $this->validate($request, [
            'data.attributes.tmdb_show_id' => 'sometimes|integer|not_in:0',
            'data.attributes.name' => 'sometimes|max:255',
            'data.attributes.poster_path' => 'sometimes|max:255',
            'data.attributes.backdrop_path' => 'sometimes|max:255',
        ]);

        $input = $request->input();
        $type = 'shows';

        // 403 - Unsupported request format
        if (! $this->validRequestStructure($input)) {
            return $this->apiResponse()
                ->errorForbidden(
                    'Unsupported request format (requires JSON-API).'
                );
        }

        // 409 - Incorrect id or type
        if (! $this->validRequestData($input, $id, $type)) {
            return $this->apiResponse()
                ->errorConflict(
                    'Bad request data (verify id and type).'
                );
        }

        $attributes = $request->input('data.attributes');

        $show = $this->shows()
            ->patch($id, $attributes);

        $transformer = $this->shows()
            ->transformer();
        
        return $this->apiResponse()
            ->setStatusCode(201)
            ->respondWithItem(
                $show, 
                $transformer
            );
    }
}
