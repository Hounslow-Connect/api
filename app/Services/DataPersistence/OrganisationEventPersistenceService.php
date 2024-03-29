<?php

namespace App\Services\DataPersistence;

use App\Models\File;
use App\Models\Model;
use App\Models\OrganisationEvent;
use App\Models\Taxonomy;
use App\Models\UpdateRequest as UpdateRequestModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class OrganisationEventPersistenceService implements DataPersistenceService
{
    /**
     * Store the model.
     *
     * @param \Illuminate\Foundation\Http\FormRequest $request
     * @return \App\Models\UpdateRequest | \App\Models\OrganisationEvent
     */
    public function store(FormRequest $request)
    {
        return $request->user()->isGlobalAdmin()
        ? $this->processAsNewEntity($request)
        : $this->processAsUpdateRequest($request, null);
    }

    /**
     * Update the model.
     *
     * @param \Illuminate\Foundation\Http\FormRequest $request
     * @return \App\Models\UpdateRequest
     */
    public function update(FormRequest $request, Model $model)
    {
        return $this->processAsUpdateRequest($request, $model);
    }

    /**
     * Process the requested changes and either update the model or store an update request.
     *
     * @param Illuminate\Foundation\Http\FormRequest $request
     * @return \App\Models\OrganisationEvent
     */
    public function processAsNewEntity(FormRequest $request)
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

            return $organisationEvent;
        });
    }

    /**
     * Process the requested changes and either update the model or store an update request.
     *
     * @param Illuminate\Foundation\Http\FormRequest $request
     * @param \App\Models\OrganisationEvent $event
     * @return \App\Models\UpdateRequest
     */
    public function processAsUpdateRequest(FormRequest $request, ?OrganisationEvent $event)
    {
        return DB::transaction(function () use ($request, $event) {
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
                'organisation_id' => $request->missing('organisation_id'),
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
            $updateRequest = new UpdateRequestModel([
                'updateable_type' => $event ? UpdateRequestModel::EXISTING_TYPE_ORGANISATION_EVENT : UpdateRequestModel::NEW_TYPE_ORGANISATION_EVENT,
                'updateable_id' => $event->id ?? null,
                'user_id' => $request->user()->id,
                'data' => $data,
            ]);

            // Only persist to the database if the user did not request a preview.
            if ($updateRequest->updateable_type === UpdateRequestModel::EXISTING_TYPE_ORGANISATION_EVENT) {
                // Preview currently only available for update operations
                if (!$request->isPreview()) {
                    $updateRequest->save();
                }
            } else {
                $updateRequest->save();
            }

            return $updateRequest;
        });
    }
}
