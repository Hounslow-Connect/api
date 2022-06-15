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
use App\Models\File;
use App\Models\OrganisationEvent;
use App\Models\Taxonomy;
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
                'end_date',
                'title',
            ])
            ->defaultSort('-start_date')
            ->paginate(per_page($request->per_page));

        event(EndpointHit::onRead($request, 'Viewed all organisation events'));

        return OrganisationEventResource::collection($organisationEvents);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\OrganisationEvent\StoreRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request)
    {
        return DB::transaction(function () use ($request) {
            // Create the organisation.
            $organisationEvent = OrganisationEvent::create([
                'title' => $request->title,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'intro' => $request->intro,
                'description' => sanitize_markdown($request->description),
                'is_free' => $request->is_free,
                'fees_text' => $request->fees_text,
                'fees_url' => $request->fees_url,
                'organiser_name' => $request->organiser_name,
                'organiser_phone' => $request->organiser_phone,
                'organiser_email' => $request->organiser_email,
                'organiser_url' => $request->organiser_url,
                'booking_title' => $request->booking_title,
                'booking_summary' => $request->booking_summary,
                'booking_url' => $request->booking_url,
                'booking_cta' => $request->booking_cta,
                'homepage' => $request->homepage,
                'is_virtual' => $request->is_virtual,
                'location_id' => $request->location_id,
                'image_file_id' => $request->image_file_id,
                'organisation_id' => $request->organisation_id,
            ]);

            if ($request->filled('location_id')) {
                $organisationEvent->load('location');
            }

            if ($request->filled('image_file_id')) {
                /** @var \App\Models\File $file */
                $file = File::findOrFail($request->image_file_id)->assigned();

                // Create resized version for common dimensions.
                foreach (config('ck.cached_image_dimensions') as $maxDimension) {
                    $file->resizedVersion($maxDimension);
                }
            }

            // Create the category taxonomy records.
            $taxonomies = Taxonomy::whereIn('id', $request->category_taxonomies)->get();
            $organisationEvent->syncTaxonomyRelationships($taxonomies);

            event(EndpointHit::onCreate($request, "Created organisation event [{$organisationEvent->id}]", $organisationEvent));

            return new OrganisationEventResource($organisationEvent);
        });
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
     * @return UpdateRequestReceived
     */
    public function update(UpdateRequest $request, OrganisationEvent $organisationEvent)
    {
        return DB::transaction(function () use ($request, $organisationEvent) {
            $data = array_filter_missing([
                'title' => $request->missing('title'),
                'start_date' => $request->missing('start_date'),
                'end_date' => $request->missing('end_date'),
                'start_time' => $request->missing('start_time'),
                'end_time' => $request->missing('end_time'),
                'intro' => $request->missing('intro'),
                'description' => $request->missing('description', function ($description) {
                    return sanitize_markdown($description);
                }),
                'is_free' => $request->missing('is_free'),
                'fees_text' => $request->missing('fees_text'),
                'fees_url' => $request->missing('fees_url'),
                'organiser_name' => $request->missing('organiser_name'),
                'organiser_phone' => $request->missing('organiser_phone'),
                'organiser_email' => $request->missing('organiser_email'),
                'organiser_url' => $request->missing('organiser_url'),
                'booking_title' => $request->missing('booking_title'),
                'booking_summary' => $request->missing('booking_summary'),
                'booking_url' => $request->missing('booking_url'),
                'booking_cta' => $request->missing('booking_cta'),
                'homepage' => $request->missing('homepage'),
                'is_virtual' => $request->missing('is_virtual'),
                'location_id' => $request->missing('location_id'),
                'image_file_id' => $request->missing('image_file_id'),
                'category_taxonomies' => $request->missing('category_taxonomies'),
            ]);

            if ($request->filled('image_file_id')) {
                /** @var \App\Models\File $file */
                $file = File::findOrFail($request->image_file_id)->assigned();

                // Create resized version for common dimensions.
                foreach (config('ck.cached_image_dimensions') as $maxDimension) {
                    $file->resizedVersion($maxDimension);
                }
            }

            /** @var \App\Models\UpdateRequest $updateRequest */
            $updateRequest = $organisationEvent->updateRequests()->create([
                'user_id' => $request->user()->id,
                'data' => $data,
            ]);

            event(EndpointHit::onUpdate($request, "Updated organisation event [{$organisationEvent->id}]", $organisationEvent));

            return new UpdateRequestReceived($updateRequest);
        });
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
