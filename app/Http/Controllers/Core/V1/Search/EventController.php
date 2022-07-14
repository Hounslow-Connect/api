<?php

namespace App\Http\Controllers\Core\V1\Search;

use App\Contracts\EventSearch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Search\Events\Request;
use App\Support\Coordinate;

class EventController extends Controller
{
    /**
     * @param \App\Contracts\EventSearch $search
     * @param \App\Http\Requests\Search\Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function __invoke(EventSearch $search, Request $request)
    {
        // Apply query.
        if ($request->has('query')) {
            $search->applyQuery($request->input('query'));
        }

        if ($request->has('category')) {
            // If category given then filter by category.
            $search->applyCategory($request->category);
        }

        // Apply filter on `is_free` field.
        if ($request->has('is_free')) {
            $search->applyIsFree($request->is_free);
        }

        // Apply filter on `is_virtual` field.
        if ($request->has('is_virtual')) {
            $search->applyIsVirtual($request->is_virtual);
        }

        // Apply filter on `has_wheelchair_access` field.
        if ($request->has('has_wheelchair_access')) {
            $search->applyHasWheelchairAccess($request->has_wheelchair_access);
        }

        // Apply filter on `has_induction_loop` field.
        if ($request->has('has_induction_loop')) {
            $search->applyHasInductionLoop($request->has_induction_loop);
        }

        // Apply filter on `starts_after` field.
        if ($request->has('starts_after') || $request->has('ends_before')) {
            $search->applyDateRange($request->input('starts_after'), $request->input('ends_before'));
        }

        // If location was passed, then parse the location.
        if ($request->has('location') && !$request->is_virtual ?? false) {
            $search->applyIsVirtual(false);
            $location = new Coordinate(
                $request->input('location.lat'),
                $request->input('location.lon')
            );

            // Apply radius filtering.
            $search->applyRadius($location, $request->input('distance', config('ck.search_distance')));
        }

        // Apply order.
        $search->applyOrder($request->order ?? 'start_date', $location ?? null);

        // Perform the search.
        return $search->paginate($request->page, $request->per_page);
    }
}
