<?php

namespace App\Models;

use App\Http\Requests\OrganisationEvent\UpdateRequest as UpdateOrganisationEventRequest;
use App\Models\IndexConfigurators\EventsIndexConfigurator;
use App\Models\Mutators\OrganisationEventMutators;
use App\Models\Relationships\OrganisationEventRelationships;
use App\Models\Scopes\OrganisationEventScopes;
use App\Rules\FileIsMimeType;
use App\TaxonomyRelationships\HasTaxonomyRelationships;
use App\TaxonomyRelationships\UpdateTaxonomyRelationships;
use App\UpdateRequest\AppliesUpdateRequests;
use App\UpdateRequest\UpdateRequests;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use ScoutElastic\Searchable;

class OrganisationEvent extends Model implements AppliesUpdateRequests, HasTaxonomyRelationships
{
    use OrganisationEventMutators;
    use OrganisationEventRelationships;
    use OrganisationEventScopes;
    use UpdateRequests;
    use UpdateTaxonomyRelationships;
    use Searchable;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_free' => 'boolean',
        'is_virtual' => 'boolean',
        'homepage' => 'boolean',
        'start_time' => 'string',
        'end_time' => 'string',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'start_date',
        'end_date',
        'created_at',
        'updated_at',
    ];

    /**
     * The Elasticsearch index configuration class.
     *
     * @var string
     */
    protected $indexConfigurator = EventsIndexConfigurator::class;

    /**
     * Allows you to set different search algorithms.
     *
     * @var array
     */
    protected $searchRules = [
        //
    ];

    /**
     * The mapping for the fields.
     *
     * @var array
     */
    protected $mapping = [
        'properties' => [
            'id' => ['type' => 'keyword'],
            'enabled' => ['type' => 'boolean'],
            'title' => [
                'type' => 'text',
                'fields' => [
                    'keyword' => ['type' => 'keyword'],
                ],
            ],
            'intro' => ['type' => 'text'],
            'description' => ['type' => 'text'],
            'start_date' => ['type' => 'date'],
            'end_date' => ['type' => 'date'],
            'start_time' => ['type' => 'text'],
            'end_time' => ['type' => 'text'],
            'is_free' => ['type' => 'boolean'],
            'is_virtual' => ['type' => 'boolean'],
            'has_wheelchair_access' => ['type' => 'boolean'],
            'has_induction_loop' => ['type' => 'boolean'],
            'organisation_name' => [
                'type' => 'text',
                'fields' => [
                    'keyword' => ['type' => 'keyword'],
                ],
            ],
            'taxonomy_categories' => [
                'type' => 'text',
                'fields' => [
                    'keyword' => ['type' => 'keyword'],
                ],
            ],
            'collection_categories' => ['type' => 'keyword'],
            'event_location' => [
                'type' => 'nested',
                'properties' => [
                    'id' => ['type' => 'keyword'],
                    'location' => ['type' => 'geo_point'],
                    'has_wheelchair_access' => ['type' => 'boolean'],
                    'has_induction_loop' => ['type' => 'boolean'],
                ],
            ],
        ],
    ];

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        $organisationEvent = [
            'id' => $this->id,
            'title' => $this->title,
            'intro' => $this->intro,
            'description' => $this->description,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'is_free' => $this->is_free,
            'is_virtual' => $this->is_virtual,
            'organisation_name' => $this->organisation->name,
            'taxonomy_categories' => $this->taxonomies()->pluck('name')->toArray(),
            'collection_categories' => $this->collections()->pluck('name')->toArray(),
            'event_location' => null,
        ];

        if (!$this->is_virtual) {
            $organisationEvent['event_location'] = [
                'id' => $this->location->id,
                'location' => [
                    'lat' => $this->location->lat,
                    'lon' => $this->location->lon,
                ],
                'has_wheelchair_access' => $this->location->has_wheelchair_access,
                'has_induction_loop' => $this->location->has_induction_loop,
            ];
        }

        return $organisationEvent;
    }

    /**
     * Check if the update request is valid.
     *
     * @param \App\Models\UpdateRequest $updateRequest
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function validateUpdateRequest(UpdateRequest $updateRequest): Validator
    {
        $rules = (new UpdateOrganisationEventRequest())
            ->setUserResolver(function () use ($updateRequest) {
                return $updateRequest->user;
            })
            ->merge(['organisationEvent' => $this])
            ->merge($updateRequest->data)
            ->rules();

        // Remove the pending assignment rule since the file is now uploaded.
        $rules['image_file_id'] = [
            'nullable',
            'exists:files,id',
            new FileIsMimeType(File::MIME_TYPE_PNG),
        ];

        return ValidatorFacade::make($updateRequest->data, $rules);
    }

    /**
     * Apply the update request.
     *
     * @param \App\Models\UpdateRequest $updateRequest
     * @return \App\Models\UpdateRequest
     */
    public function applyUpdateRequest(UpdateRequest $updateRequest): UpdateRequest
    {
        $data = $updateRequest->data;

        // Update the Image File entity if new
        if (Arr::get($data, 'image_file_id', $this->image_file_id) !== $this->image_file_id && !empty($data['image_file_id'])) {
            /** @var \App\Models\File $file */
            $file = File::findOrFail($data['image_file_id'])->assigned();

            // Create resized version for common dimensions.
            foreach (config('ck.cached_image_dimensions') as $maxDimension) {
                $file->resizedVersion($maxDimension);
            }
        }

        // Update the organisation event record.
        $this->update([
            'organisation_id' => $this->organisation_id,
            'title' => Arr::get($data, 'title', $this->title),
            'intro' => Arr::get($data, 'intro', $this->intro),
            'description' => sanitize_markdown(
                Arr::get($data, 'description', $this->description)
            ),
            'start_date' => Arr::get($data, 'start_date', $this->start_date),
            'end_date' => Arr::get($data, 'end_date', $this->end_date),
            'start_time' => Arr::get($data, 'start_time', $this->start_time),
            'end_time' => Arr::get($data, 'end_time', $this->end_time),
            'is_free' => Arr::get($data, 'is_free', $this->is_free),
            'fees_text' => Arr::get($data, 'fees_text', $this->fees_text),
            'fees_url' => Arr::get($data, 'fees_url', $this->fees_url),
            'organiser_name' => Arr::get($data, 'organiser_name', $this->organiser_name),
            'organiser_phone' => Arr::get($data, 'organiser_phone', $this->organiser_phone),
            'organiser_email' => Arr::get($data, 'organiser_email', $this->organiser_email),
            'organiser_url' => Arr::get($data, 'organiser_url', $this->organiser_url),
            'booking_title' => Arr::get($data, 'booking_title', $this->booking_title),
            'booking_summary' => Arr::get($data, 'booking_summary', $this->booking_summary),
            'booking_url' => Arr::get($data, 'booking_url', $this->booking_url),
            'booking_cta' => Arr::get($data, 'booking_cta', $this->booking_cta),
            'homepage' => Arr::get($data, 'homepage', $this->homepage),
            'is_virtual' => Arr::get($data, 'is_virtual', $this->is_virtual),
            'location_id' => Arr::get($data, 'location_id', $this->location_id),
            'image_file_id' => Arr::get($data, 'image_file_id', $this->image_file_id),
        ]);

        // Update the category taxonomy records.
        if (array_key_exists('category_taxonomies', $data)) {
            $taxonomies = Taxonomy::whereIn('id', $data['category_taxonomies'])->get();
            $this->syncTaxonomyRelationships($taxonomies);
        }

        // Ensure conditional fields are reset if needed.
        $this->resetConditionalFields();

        return $updateRequest;
    }

    /**
     * Custom logic for returning the data. Useful when wanting to transform
     * or modify the data before returning it, e.g. removing passwords.
     *
     * @param array $data
     * @return array
     */
    public function getData(array $data): array
    {
        return $data;
    }

    /**
     * Ensures conditional fields are reset to expected values.
     *
     * @return \App\Models\OrganisationEvent
     */
    public function resetConditionalFields(): self
    {
        if ($this->is_free) {
            $this->update([
                'fees_text' => null,
                'fees_url' => null,
            ]);
        }

        if ($this->organiser_name === null) {
            $this->update([
                'organiser_phone' => null,
                'organiser_email' => null,
                'organiser_url' => null,
            ]);
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function hasImage(): bool
    {
        return $this->image_file_id !== null;
    }

    /**
     * @param int|null $maxDimension
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException|\InvalidArgumentException
     * @return \App\Models\File|\Illuminate\Http\Response|\Illuminate\Contracts\Support\Responsable
     */
    public static function placeholderImage(int $maxDimension = null)
    {
        if ($maxDimension !== null) {
            return File::resizedPlaceholder($maxDimension, File::META_PLACEHOLDER_FOR_ORGANISATION_EVENT);
        }

        return response()->make(
            Storage::disk('local')->get('/placeholders/service.png'),
            Response::HTTP_OK,
            ['Content-Type' => File::MIME_TYPE_PNG]
        );
    }

    /**
     * Add the OrganisationEvent to the homepage.
     *
     * @return \App\Models\OrganisationEvent
     */
    public function addToHomepage()
    {
        $this->homepage = true;

        return $this;
    }

    /**
     * Remove the OrganisationEvent from the homepage.
     *
     * @return \App\Models\OrganisationEvent
     */
    public function removeFromHomepage()
    {
        $this->homepage = false;

        return $this;
    }
}
