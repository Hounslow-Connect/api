<?php

namespace App\Http\Controllers\Core\V1;

use App\Events\EndpointHit;
use App\Http\Controllers\Controller;
use App\Http\Requests\TaxonomyOrganisationEvent\DestroyRequest;
use App\Http\Requests\TaxonomyOrganisationEvent\IndexRequest;
use App\Http\Requests\TaxonomyOrganisationEvent\ShowRequest;
use App\Http\Requests\TaxonomyOrganisationEvent\StoreRequest;
use App\Http\Requests\TaxonomyOrganisationEvent\UpdateRequest;
use App\Http\Resources\TaxonomyOrganisationEventResource;
use App\Http\Responses\ResourceDeleted;
use App\Models\Taxonomy;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;

class TaxonomyOrganisationEventController extends Controller
{
    /**
     * TaxonomyOrganisationEventController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:api')->except('index', 'show');
    }

    /**
     * Display a listing of the resource.
     *
     * @param \App\Http\Requests\TaxonomyOrganisationEvent\IndexRequest $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(IndexRequest $request)
    {
        $baseQuery = Taxonomy::query()
            ->topLevelOrganisationEvents()
            ->with('children.children.children.children.children.children')
            ->orderBy('order');

        $organisationEvents = QueryBuilder::for($baseQuery)
            ->get();

        event(EndpointHit::onRead($request, 'Viewed all taxonomy organisation events'));

        return TaxonomyOrganisationEventResource::collection($organisationEvents);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\TaxonomyOrganisationEvent\StoreRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $organisationEvent = Taxonomy::organisationEvent()->children()->create([
                'name' => $request->name,
                'order' => $request->order,
                'depth' => 1,
            ]);

            event(EndpointHit::onCreate($request, "Created taxonomy organisation event [{$organisationEvent->id}]", $organisationEvent));

            return new TaxonomyOrganisationEventResource($organisationEvent);
        });
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Http\Requests\TaxonomyOrganisationEvent\ShowRequest $request
     * @param \App\Models\Taxonomy $taxonomy
     * @return \App\Http\Resources\TaxonomyOrganisationEventResource
     */
    public function show(ShowRequest $request, Taxonomy $taxonomy)
    {
        $baseQuery = Taxonomy::query()
            ->where('id', $taxonomy->id);

        $taxonomy = QueryBuilder::for($baseQuery)
            ->firstOrFail();

        event(EndpointHit::onRead($request, "Viewed taxonomy organisation event [{$taxonomy->id}]", $taxonomy));

        return new TaxonomyOrganisationEventResource($taxonomy);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \App\Http\Requests\TaxonomyOrganisationEvent\UpdateRequest $request
     * @param \App\Models\Taxonomy $taxonomy
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, Taxonomy $taxonomy)
    {
        return DB::transaction(function () use ($request, $taxonomy) {
            $taxonomy->update([
                'name' => $request->name,
                'order' => $request->order,
            ]);

            event(EndpointHit::onUpdate($request, "Updated taxonomy organisation event [{$taxonomy->id}]", $taxonomy));

            return new TaxonomyOrganisationEventResource($taxonomy);
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Http\Requests\TaxonomyOrganisationEvent\DestroyRequest $request
     * @param \App\Models\Taxonomy $taxonomy
     * @return \Illuminate\Http\Response
     */
    public function destroy(DestroyRequest $request, Taxonomy $taxonomy)
    {
        return DB::transaction(function () use ($request, $taxonomy) {
            event(EndpointHit::onDelete($request, "Deleted taxonomy organisation event [{$taxonomy->id}]", $taxonomy));

            $taxonomy->delete();

            return new ResourceDeleted('taxonomy organisation event');
        });
    }
}
