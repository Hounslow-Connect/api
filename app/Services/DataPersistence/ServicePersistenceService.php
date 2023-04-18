<?php

namespace App\Services\DataPersistence;

use App\Models\File;
use App\Models\Model;
use App\Models\Service;
use App\Models\Taxonomy;
use App\Models\UpdateRequest as UpdateRequestModel;
use App\Support\MissingValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class ServicePersistenceService implements DataPersistenceService
{
    public function store(FormRequest $request)
    {
        return $request->user()->isGlobalAdmin()
        ? $this->processAsNewEntity($request)
        : $this->processAsUpdateRequest($request);
    }

    public function update(FormRequest $request, Model $model)
    {
        return $this->processAsUpdateRequest($request, $model);
    }

    private function processAsUpdateRequest($request, $service = null)
    {
        return DB::transaction(function () use ($request, $service) {
            // Initialise the data array
            $data = array_filter_missing([
                'organisation_id' => $request->missing('organisation_id'),
                'slug' => $request->missing('slug'),
                'name' => $request->missing('name'),
                'type' => $request->missing('type'),
                'status' => $request->missing('status'),
                'intro' => $request->missing('intro'),
                'description' => $request->missing('description', function ($description) {
                    return sanitize_markdown($description);
                }),
                'wait_time' => $request->missing('wait_time'),
                'is_free' => $request->missing('is_free'),
                'fees_text' => $request->missing('fees_text'),
                'fees_url' => $request->missing('fees_url'),
                'testimonial' => $request->missing('testimonial'),
                'video_embed' => $request->missing('video_embed'),
                'url' => $request->missing('url'),
                'contact_name' => $request->missing('contact_name'),
                'contact_phone' => $request->missing('contact_phone'),
                'contact_email' => $request->missing('contact_email'),
                'show_referral_disclaimer' => $request->missing('show_referral_disclaimer'),
                'referral_method' => $request->missing('referral_method'),
                'referral_button_text' => $request->missing('referral_button_text'),
                'referral_email' => $request->missing('referral_email'),
                'referral_url' => $request->missing('referral_url'),
                'useful_infos' => $request->has('useful_infos') ? [] : new MissingValue(),
                'offerings' => $request->has('offerings') ? [] : new MissingValue(),
                'gallery_items' => $request->has('gallery_items') ? [] : new MissingValue(),
                'category_taxonomies' => $request->missing('category_taxonomies'),
                'eligibility_types' => $request->filled('eligibility_types') ? $request->eligibility_types : new MissingValue(),
                'logo_file_id' => $request->missing('logo_file_id'),
            ]);

            // Loop through each useful info.
            foreach ($request->input('useful_infos', []) as $usefulInfo) {
                $data['useful_infos'][] = [
                    'title' => $usefulInfo['title'],
                    'description' => sanitize_markdown($usefulInfo['description']),
                    'order' => $usefulInfo['order'],
                ];
            }

            // Loop through each offering.
            foreach ($request->input('offerings', []) as $offering) {
                $data['offerings'][] = [
                    'offering' => $offering['offering'],
                    'order' => $offering['order'],
                ];
            }

            // Loop through each gallery item.
            foreach ($request->input('gallery_items', []) as $galleryItem) {
                $data['gallery_items'][] = [
                    'file_id' => $galleryItem['file_id'],
                ];
            }

            $updateRequest = new UpdateRequestModel([
                'updateable_type' => $service ? UpdateRequestModel::EXISTING_TYPE_SERVICE : UpdateRequestModel::NEW_TYPE_SERVICE,
                'updateable_id' => $service ? $service->id : null,
                'user_id' => $request->user()->id,
                'data' => $data,
            ]);

            // Only persist to the database if the user did not request a preview.
            if ($updateRequest->updateable_type === UpdateRequestModel::EXISTING_TYPE_SERVICE) {
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

    private function processAsNewEntity($request)
    {
        return DB::transaction(function () use ($request) {
            $initialCreateData = [
                'organisation_id' => $request->organisation_id,
                'slug' => $this->uniqueSlug($request->slug),
                'name' => $request->name,
                'type' => $request->type,
                'status' => $request->status,
                'intro' => $request->intro,
                'description' => sanitize_markdown($request->description),
                'wait_time' => $request->wait_time,
                'is_free' => $request->is_free,
                'fees_text' => $request->fees_text,
                'fees_url' => $request->fees_url,
                'testimonial' => $request->testimonial,
                'video_embed' => $request->video_embed,
                'url' => $request->url,
                'contact_name' => $request->contact_name,
                'contact_phone' => $request->contact_phone,
                'contact_email' => $request->contact_email,
                'show_referral_disclaimer' => $request->show_referral_disclaimer,
                'referral_method' => $request->referral_method,
                'referral_button_text' => $request->referral_button_text,
                'referral_email' => $request->referral_email,
                'referral_url' => $request->referral_url,
                'logo_file_id' => $request->logo_file_id,
                'last_modified_at' => Date::now(),
            ];

            foreach ($request->input('eligibility_types.custom', []) as $customEligibilityType => $value) {
                $fieldName = 'eligibility_' . $customEligibilityType . '_custom';
                $initialCreateData[$fieldName] = $value;
            }

            // Create the service record.
            /** @var \App\Models\Service $service */
            $service = Service::create($initialCreateData);

            if ($request->filled('gallery_items')) {
                foreach ($request->gallery_items as $galleryItem) {
                    /** @var \App\Models\File $file */
                    $file = File::findOrFail($galleryItem['file_id'])->assigned();

                    // Create resized version for common dimensions.
                    foreach (config('ck.cached_image_dimensions') as $maxDimension) {
                        $file->resizedVersion($maxDimension);
                    }
                }
            }

            if ($request->filled('logo_file_id')) {
                /** @var \App\Models\File $file */
                $file = File::findOrFail($request->logo_file_id)->assigned();

                // Create resized version for common dimensions.
                foreach (config('ck.cached_image_dimensions') as $maxDimension) {
                    $file->resizedVersion($maxDimension);
                }
            }

            // Create the useful info records.
            foreach ($request->useful_infos as $usefulInfo) {
                $service->usefulInfos()->create([
                    'title' => $usefulInfo['title'],
                    'description' => sanitize_markdown($usefulInfo['description']),
                    'order' => $usefulInfo['order'],
                ]);
            }

            // Create the offering records.
            foreach ($request->offerings as $offering) {
                $service->offerings()->create([
                    'offering' => $offering['offering'],
                    'order' => $offering['order'],
                ]);
            }

            // Create the gallery item records.
            foreach ($request->gallery_items as $galleryItem) {
                $service->serviceGalleryItems()->create([
                    'file_id' => $galleryItem['file_id'],
                ]);
            }

            // Create the category taxonomy records.
            $taxonomies = Taxonomy::whereIn('id', $request->category_taxonomies)->get();
            $service->syncTaxonomyRelationships($taxonomies);

            // Create the service eligibility taxonomy records and custom fields
            $eligibilityTypes = Taxonomy::whereIn('id', $request->input('eligibility_types.taxonomies', []))->get();
            $service->syncEligibilityRelationships($eligibilityTypes);

            return $service;
        });
    }

    /**
     * Return a unique version of the proposed slug.
     *
     * @param string $slug
     * @return string
     */
    public function uniqueSlug($slug)
    {
        $uniqueSlug = $baseSlug = preg_replace('|\-\d$|', '', $slug);
        $suffix = 1;
        do {
            $exists = DB::table((new Service())->getTable())->where('slug', $uniqueSlug)->exists();
            if ($exists) {
                $uniqueSlug = $baseSlug . '-' . $suffix;
            }
            $suffix++;
        } while ($exists);

        return $uniqueSlug;
    }
}
