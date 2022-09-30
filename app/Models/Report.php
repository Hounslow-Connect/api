<?php

namespace App\Models;

use App\Models\Mutators\ReportMutators;
use App\Models\Relationships\ReportRelationships;
use App\Models\Scopes\ReportScopes;
use Carbon\CarbonImmutable;
use Closure;
use Exception;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

class Report extends Model
{
    use ReportMutators;
    use ReportRelationships;
    use ReportScopes;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Created a report record and a file record.
     * Then delegates the physical file creation to a `generateReportName` method.
     *
     * @param \App\Models\ReportType $type
     * @param \Carbon\CarbonImmutable|null $startsAt
     * @param \Carbon\CarbonImmutable|null $endsAt
     * @throws \Exception
     * @return \App\Models\Report
     */
    public static function generate(
        ReportType $type,
        CarbonImmutable $startsAt = null,
        CarbonImmutable $endsAt = null
    ): self {
        // Generate the file name.
        $filename = sprintf(
            '%s_%s_%s.csv',
            Date::now()->format('Y-m-d_H-i'),
            Str::slug(config('app.name')),
            Str::slug($type->name)
        );

        // Create the file record.
        $file = File::create([
            'filename' => $filename,
            'mime_type' => 'text/csv',
            'is_private' => true,
        ]);

        // Create the report record.
        $report = static::create([
            'report_type_id' => $type->id,
            'file_id' => $file->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        // Get the name for the report generation method.
        $methodName = 'generate' . ucfirst(Str::camel($type->name));

        // Throw exception if the report type does not have a generate method.
        if (!method_exists($report, $methodName)) {
            throw new Exception("The report type [{$type->name}] does not have a corresponding generate method");
        }

        return $report->$methodName($startsAt, $endsAt);
    }

    /**
     * @return \App\Models\Report
     */
    public function generateUsersExport(): self
    {
        $headings = [
            'User Reference ID',
            'User First Name',
            'User Last Name',
            'Email address',
            'Highest Permission Level',
            'Organisation/Service Permission Levels',
            'Organisation/Service IDs',
        ];

        $data = $this->getUserExportResults()->map(function ($row) {
            return [
                $row->id,
                $row->first_name,
                $row->last_name,
                $row->email,
                $row->max_role,
                $row->all_permissions,
                $row->org_service_ids
            ];
        })->all();

        array_unshift($data, $headings);

        // Upload the report.
        $this->file->upload(array_to_csv($data));

        return $this;
    }

    /**
     * @return \App\Models\Report
     */
    public function generateServicesExport(): self
    {
        $headings = [
            'Organisation',
            'Org Reference ID',
            'Org Email',
            'Org Phone',
            'Service Reference ID',
            'Service Name',
            'Service Web Address',
            'Service Contact Name',
            'Last Updated',
            'Referral Type',
            'Referral Contact',
            'Status',
            'Locations Delivered At',
        ];

        $data = $this->getServiceExportResults()->map(function ($row) {
            return [
                $row->organisation_name,
                $row->organisation_id,
                $row->organisation_email,
                $row->organisation_phone,
                $row->service_id,
                $row->service_name,
                $row->service_url,
                $row->service_contact_name,
                (new CarbonImmutable($row->service_updated_at))->format(CarbonImmutable::ISO8601),
                $row->service_referral_method,
                $row->service_referral_email,
                $row->service_status,
                $row->service_locations
            ];
        })->all();

        array_unshift($data, $headings);

        // Upload the report.
        $this->file->upload(array_to_csv($data));

        return $this;
    }

    /**
     * @return \App\Models\Report
     */
    public function generateOrganisationsExport(): self
    {
        $headings = [
            'Organisation Reference ID',
            'Organisation Name',
            'Number of Services',
            'Organisation Email',
            'Organisation Phone',
            'Organisation URL',
            'Number of Accounts Attributed',
        ];

        $data = [$headings];

        $callback = function (Organisation $organisation) {
            return [
                $organisation->id,
                $organisation->name,
                $organisation->services_count,
                $organisation->email,
                $organisation->phone,
                $organisation->url,
                $organisation->non_admin_users_count,
            ];
        };

        Organisation::query()
            ->withCount('services', 'nonAdminUsers')
            ->chunk(200, function (Collection $organisations) use (&$data, $callback) {
                // Loop through each service in the chunk.
                foreach ($this->reportRowGenerator($organisations, $callback) as $row) {
                    $data[] = $row;
                }
            });

        // Upload the report.
        $this->file->upload(array_to_csv($data));

        return $this;
    }

    /**
     * @return \App\Models\Report
     */
    public function generateLocationsExport(): self
    {
        $headings = [
            'Address Line 1',
            'Address Line 2',
            'Address Line 3',
            'City',
            'County',
            'Postcode',
            'Country',
            'Number of Services Delivered at The Location',
        ];

        $data = [$headings];

        $callback = function (Location $location) {
            return [
                $location->address_line_1,
                $location->address_line_2,
                $location->address_line_3,
                $location->city,
                $location->county,
                $location->postcode,
                $location->country,
                $location->services_count,
            ];
        };

        Location::query()
            ->withCount('services')
            ->chunk(200, function (Collection $locations) use (&$data, $callback) {
                // Loop through each location in the chunk.
                foreach ($this->reportRowGenerator($locations, $callback) as $row) {
                    $data[] = $row;
                }
            });

        // Upload the report.
        $this->file->upload(array_to_csv($data));

        return $this;
    }

    /**
     * @param \Carbon\CarbonImmutable|null $startsAt
     * @param \Carbon\CarbonImmutable|null $endsAt
     * @return \App\Models\Report
     */
    public function generateReferralsExport(
        CarbonImmutable $startsAt = null,
        CarbonImmutable $endsAt = null
    ): self {
        // Update the date range fields if passed.
        if ($startsAt && $endsAt) {
            $this->update([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
        }

        $headings = [
            'Referred to Organisation ID',
            'Referred to Organisation',
            'Referred to Service ID',
            'Referred to Service Name',
            'Date Made',
            'Date Complete',
            'Self/Champion',
            'Refer from organisation',
            'Date Consent Provided',
        ];

        $data = [$headings];

        $callback = function (Referral $referral) {
            return [
                $referral->service->organisation->id,
                $referral->service->organisation->name,
                $referral->service->id,
                $referral->service->name,
                optional($referral->created_at)->format(CarbonImmutable::ISO8601),
                $referral->isCompleted()
                ? $referral->latestCompletedStatusUpdate->created_at->format(CarbonImmutable::ISO8601)
                : '',
                $referral->isSelfReferral() ? 'Self' : 'Champion',
                $referral->isSelfReferral() ? null : $referral->organisationName(),
                optional($referral->referral_consented_at)->format(CarbonImmutable::ISO8601),
            ];
        };

        Referral::query()
            ->with('service.organisation', 'latestCompletedStatusUpdate', 'organisationTaxonomy')
            ->when($startsAt && $endsAt, function (Builder $query) use ($startsAt, $endsAt) {
                // When date range provided, filter referrals which were created between the date range.
                $query->whereBetween(table(Referral::class, 'created_at'), [$startsAt, $endsAt]);
            })
            ->chunk(200, function (Collection $referrals) use (&$data, $callback) {
                // Loop through each referral in the chunk.
                foreach ($this->reportRowGenerator($referrals, $callback) as $row) {
                    $data[] = $row;
                }
            });

        // Upload the report.
        $this->file->upload(array_to_csv($data));

        return $this;
    }

    /**
     * @param \Carbon\CarbonImmutable|null $startsAt
     * @param \Carbon\CarbonImmutable|null $endsAt
     * @return \App\Models\Report
     */
    public function generateFeedbackExport(
        CarbonImmutable $startsAt = null,
        CarbonImmutable $endsAt = null
    ): self {
        // Update the date range fields if passed.
        if ($startsAt && $endsAt) {
            $this->update([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
        }

        $headings = [
            'Date Submitted',
            'Feedback Content',
            'Page URL',
        ];

        $data = [$headings];

        $callback = function (PageFeedback $pageFeedback) {
            return [
                optional($pageFeedback->created_at)->toDateString(),
                $pageFeedback->feedback,
                $pageFeedback->url,
            ];
        };

        PageFeedback::query()
            ->when($startsAt && $endsAt, function (Builder $query) use ($startsAt, $endsAt) {
                // When date range provided, filter page feedback which were created between the date range.
                $query->whereBetween(table(PageFeedback::class, 'created_at'), [$startsAt, $endsAt]);
            })
            ->chunk(200, function (Collection $pageFeedbacks) use (&$data, $callback) {
                // Loop through each page feedback in the chunk.
                foreach ($this->reportRowGenerator($pageFeedbacks, $callback) as $row) {
                    $data[] = $row;
                }
            });

        // Upload the report.
        $this->file->upload(array_to_csv($data));

        return $this;
    }

    /**
     * @param \Carbon\CarbonImmutable|null $startsAt
     * @param \Carbon\CarbonImmutable|null $endsAt
     * @return \App\Models\Report
     */
    public function generateAuditLogsExport(
        CarbonImmutable $startsAt = null,
        CarbonImmutable $endsAt = null
    ): self {
        // Update the date range fields if passed.
        if ($startsAt && $endsAt) {
            $this->update([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
        }

        $headings = [
            'Action',
            'Description',
            'User',
            'Date/Time',
            'IP Address',
            'User Agent',
        ];

        $data = [$headings];

        $callback = function (Audit $audit) {
            return [
                $audit->action,
                $audit->description,
                optional($audit->user)->full_name,
                optional($audit->created_at)->format(CarbonImmutable::ISO8601),
                $audit->ip_address,
                $audit->user_agent,
            ];
        };

        Audit::query()
            ->with('user')
            ->when($startsAt && $endsAt, function (Builder $query) use ($startsAt, $endsAt) {
                // When date range provided, filter page feedback which were created between the date range.
                $query->whereBetween(table(Audit::class, 'created_at'), [$startsAt, $endsAt]);
            })
            ->chunk(200, function (Collection $audits) use (&$data, $callback) {
                // Loop through each audit in the chunk.
                foreach ($this->reportRowGenerator($audits, $callback) as $row) {
                    $data[] = $row;
                }
            });

        // Upload the report.
        $this->file->upload(array_to_csv($data));

        return $this;
    }

    /**
     * @param \Carbon\CarbonImmutable|null $startsAt
     * @param \Carbon\CarbonImmutable|null $endsAt
     * @return \App\Models\Report
     */
    public function generateSearchHistoriesExport(
        CarbonImmutable $startsAt = null,
        CarbonImmutable $endsAt = null
    ): self {
        // Update the date range fields if passed.
        if ($startsAt && $endsAt) {
            $this->update([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
        }

        $headings = [
            'Date made',
            'Search Text',
            'Number Services Returned',
            'Coordinates (Latitude,Longitude)',
        ];

        $data = [$headings];

        $callback = function (SearchHistory $searchHistory) {
            $query = Arr::dot($searchHistory->query);

            $searchQuery = $query['query.bool.must.bool.should.0.match.name.query'] ?? null;
            $lat = $query['sort.0._geo_distance.service_locations.location.lat'] ?? null;
            $lon = $query['sort.0._geo_distance.service_locations.location.lon'] ?? null;
            $coordinate = (!$lat !== null && $lon !== null) ? implode(',', [$lat, $lon]) : null;

            // Append a row to the data array.
            return [
                optional($searchHistory->created_at)->toDateString(),
                $searchQuery,
                $searchHistory->count,
                $coordinate,
            ];
        };

        SearchHistory::query()
            ->withFilledQuery()
            ->when($startsAt && $endsAt, function (Builder $query) use ($startsAt, $endsAt) {
                // When date range provided, filter search history which were created between the date range.
                $query->whereBetween(table(SearchHistory::class, 'created_at'), [$startsAt, $endsAt]);
            })
            ->chunk(200, function (Collection $searchHistories) use (&$data, $callback) {
                // Loop through each search history in the chunk.
                foreach ($this->reportRowGenerator($searchHistories, $callback) as $row) {
                    $data[] = $row;
                }
            });

        // Upload the report.
        $this->file->upload(array_to_csv($data));

        return $this;
    }

    /**
     * @param \Carbon\CarbonImmutable|null $startsAt
     * @param \Carbon\CarbonImmutable|null $endsAt
     * @return \App\Models\Report
     */
    public function generateHistoricUpdateRequestsExport(
        CarbonImmutable $startsAt = null,
        CarbonImmutable $endsAt = null
    ): self {
        // Update the date range fields if passed.
        if ($startsAt && $endsAt) {
            $this->update([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
        }

        $headings = [
            'User Submitted',
            'Type',
            'Entry',
            'Date/Time Request Made',
            'Approved/Declined',
            'Date Actioned',
            'Admin who Actioned',
        ];

        $data = [$headings];

        $callback = function (UpdateRequest $updateRequest) {
            return [
                $updateRequest->user->full_name ?? null,
                $updateRequest->updateable_type,
                $updateRequest->entry,
                $updateRequest->created_at->format(CarbonImmutable::ISO8601),
                $updateRequest->isApproved() ? 'Approved' : 'Declined',
                $updateRequest->isApproved()
                ? $updateRequest->approved_at->format(CarbonImmutable::ISO8601)
                : $updateRequest->deleted_at->format(CarbonImmutable::ISO8601),
                $updateRequest->actioningUser->full_name ?? null,
            ];
        };

        UpdateRequest::withTrashed()
            ->select('*')
            ->withEntry()
            ->whereNotNull('approved_at')
            ->orWhereNotNull('deleted_at')
            ->when($startsAt && $endsAt, function (Builder $query) use ($startsAt, $endsAt) {
                /*
             * When date range provided, filter update requests which were created between the
             * date range.
             */
                $query->whereBetween(
                    table(UpdateRequest::class, 'created_at'),
                    [$startsAt, $endsAt]
                );
            })
            ->chunk(200, function (Collection $updateRequests) use (&$data, $callback) {
                // Loop through each update requests in the chunk.
                foreach ($this->reportRowGenerator($updateRequests, $callback) as $row) {
                    $data[] = $row;
                }
            });

        // Upload the report.
        $this->file->upload(array_to_csv($data));

        return $this;
    }

    /**
     * Report Row Generator.
     *
     * @param Collection $data
     * @param Closure $callback
     * @return Generator
     */
    public function reportRowGenerator(Collection $data, Closure $callback): Generator
    {
        foreach ($data as $dataItem) {
            yield $callback($dataItem);
        }
    }
}
