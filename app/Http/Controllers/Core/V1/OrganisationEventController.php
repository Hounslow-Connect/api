<?php

namespace App\Http\Controllers\Core\V1;

use App\Events\EndpointHit;
use App\Http\Controllers\Controller;
use App\Http\Filters\OrganisationEvent\HasPermissionFilter;
use App\Http\Requests\OrganisationEvent\DestroyRequest;
use App\Http\Requests\OrganisationEvent\IndexRequest;
use App\Http\Requests\OrganisationEvent\ShowRequest;
use App\Http\Requests\OrganisationEvent\StoreRequest;
use App\Http\Requests\OrganisationEvent\UpdateRequest;
use App\Http\Resources\OrganisationEventResource;
use App\Http\Responses\ResourceDeleted;
use App\Http\Responses\UpdateRequestReceived;
use App\Models\OrganisationEvent;
use App\Models\UpdateRequest as UpdateRequestModel;
use App\Services\DataPersistence\OrganisationEventPersistenceService;
use DateTime;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\Filter;
use Spatie\QueryBuilder\QueryBuilder;

class OrganisationEventController extends Controller
{
    /**
     * OrganisationController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:api')->except('index', 'show');
    }

    /**
     * Display a listing of the resource.
     *
     * @param \App\Http\Requests\OrganisationEvent\IndexRequest $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(IndexRequest $request)
    {
        $baseQuery = OrganisationEvent::query();

        if (!$request->user() && !$request->has('filter[ends_after]')) {
            $baseQuery->endsAfter((new DateTime('now'))->format('Y-m-d'));
        }

        $organisationEvents = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                Filter::exact('id'),
                Filter::exact('organisation_id'),
                Filter::exact('homepage'),
                'title',
                Filter::scope('starts_before'),
                Filter::scope('starts_after'),
                Filter::scope('ends_before'),
                Filter::scope('ends_after'),
                Filter::scope('has_wheelchair_access', 'hasWheelchairAccess'),
                Filter::scope('has_induction_loop', 'hasInductionLoop'),
                Filter::scope('collections', 'inCollections'),
                Filter::custom('has_permission', HasPermissionFilter::class),
            ])
            ->allowedIncludes(['organisation'])
            ->allowedSorts([
                'start_date',
                '-start_date',
                'end_date',
                '-end_date',
                'title',
            ])
            ->defaultSort('start_date')
            ->paginate(per_page($request->per_page));

        event(EndpointHit::onRead($request, 'Viewed all organisation events'));

        return OrganisationEventResource::collection($organisationEvents);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\OrganisationEvent\StoreRequest $request
     * @param \App\Services\DataPersistence\OrganisationEventPersistenceService $persistenceService
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request, OrganisationEventPersistenceService $persistenceService)
    {
        $entity = $persistenceService->store($request);

        if ($entity instanceof UpdateRequestModel) {
            event(EndpointHit::onCreate($request, "Created organisation event as update request [{$entity->id}]", $entity));

            return new UpdateRequestReceived($entity);
        }

        // Ensure conditional fields are reset if needed.
        $entity->resetConditionalFields();

        event(EndpointHit::onCreate($request, "Created organisation event [{$entity->id}]", $entity));

        return new OrganisationEventResource($entity);
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Http\Requests\OrganisationEvent\ShowRequest $request
     * @param \App\Models\OrganisationEvent $organisationEvent
     * @return \App\Http\Resources\OrganisationEventResource
     */
    public function show(ShowRequest $request, OrganisationEvent $organisationEvent)
    {
        $baseQuery = OrganisationEvent::query()
            ->where('id', $organisationEvent->id);

        $organisationEvent = QueryBuilder::for($baseQuery)
            ->allowedIncludes(['organisation', 'location'])
            ->firstOrFail();

        event(EndpointHit::onRead($request, "Viewed Organisation Event [{$organisationEvent->id}]", $organisationEvent));

        return new OrganisationEventResource($organisationEvent);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \App\Http\Requests\OrganisationEvent\UpdateRequest $request
     * @param \App\Models\OrganisationEvent $organisationEvent
     * @param \App\Services\DataPersistence\OrganisationEventPersistenceService $persistenceService
     * @return UpdateRequestReceived
     */
    public function update(
        UpdateRequest $request,
        OrganisationEvent $organisationEvent,
        OrganisationEventPersistenceService $persistenceService
    ) {
        $updateRequest = $persistenceService->update($request, $organisationEvent);

        event(EndpointHit::onUpdate($request, "Updated organisation event [{$organisationEvent->id}]", $organisationEvent));

        return new UpdateRequestReceived($updateRequest);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Http\Requests\OrganisationEvent\DestroyRequest $request
     * @param \App\Models\OrganisationEvent $organisationEvent
     * @return \Illuminate\Http\Response
     */
    public function destroy(DestroyRequest $request, OrganisationEvent $organisationEvent)
    {
        return DB::transaction(function () use ($request, $organisationEvent) {
            event(EndpointHit::onDelete($request, "Deleted service [{$organisationEvent->id}]", $organisationEvent));

            $organisationEvent->delete();

            return new ResourceDeleted('service');
        });
    }
}
