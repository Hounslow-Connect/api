<?php

namespace App\Models\Scopes;

use App\Models\Collection;
use App\Models\CollectionTaxonomy;
use App\Models\Location;
use App\Models\OrganisationEvent;
use App\Models\OrganisationEventTaxonomy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait OrganisationEventScopes
{
    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $dateString
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEndsAfter(Builder $query, $dateString): Builder
    {
        return $query->whereDate('end_date', '>=', $dateString);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $dateString
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEndsBefore(Builder $query, $dateString): Builder
    {
        return $query->whereDate('end_date', '<=', $dateString);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $dateString
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStartsAfter(Builder $query, $dateString): Builder
    {
        return $query->whereDate('start_date', '>=', $dateString);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $dateString
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStartsBefore(Builder $query, $dateString): Builder
    {
        return $query->whereDate('start_date', '<=', $dateString);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param bool $required
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHasWheelchairAccess(Builder $query, $required): Builder
    {
        return $query->whereExists(function ($query) use ($required) {
            $locationsTable = (new Location())->getTable();
            $query->select(DB::raw(1))
                ->from($locationsTable)
                ->whereRaw("$locationsTable.id = {$this->getTable()}.location_id")
                ->where("$locationsTable.has_wheelchair_access", (bool)$required);
        });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param bool $required
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHasInductionLoop(Builder $query, $required): Builder
    {
        return $query->whereExists(function ($query) use ($required) {
            $locationsTable = (new Location())->getTable();
            $query->select(DB::raw(1))
                ->from($locationsTable)
                ->whereRaw("$locationsTable.id = {$this->getTable()}.location_id")
                ->where("$locationsTable.has_induction_loop", (bool)$required);
        });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param bool $required
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInCollections(Builder $query, ...$collectionIds): Builder
    {
        return $query->whereIn('id', function ($query) use ($collectionIds) {
            $organisationEventTaxonomyTable = (new OrganisationEventTaxonomy())->getTable();
            $collectionTaxonomyTable = (new CollectionTaxonomy())->getTable();
            $query->select("$organisationEventTaxonomyTable.organisation_event_id")
                ->from($organisationEventTaxonomyTable)
                ->join($collectionTaxonomyTable, "$collectionTaxonomyTable.taxonomy_id", '=', "$organisationEventTaxonomyTable.taxonomy_id")
                ->whereIn("$collectionTaxonomyTable.collection_id", $collectionIds);
        });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCollectionTaxonomies(Builder $query): Builder
    {
        return $query->from((new CollectionTaxonomy())->getTable())->whereIn('taxonomy_id', function ($query) {
            $query->select('taxonomy_id')
                ->from((new OrganisationEventTaxonomy())->getTable())
                ->where('organisation_event_id', $this->id);
        });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \App\Models\OrganisationEvent $organisationEvent
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCollections(Builder $query): Builder
    {
        return $query->from((new Collection())->getTable())
            ->where('type', Collection::TYPE_ORGANISATION_EVENT)
            ->whereIn('id', function ($query) {
                $query->from((new CollectionTaxonomy())->getTable())
                    ->whereIn('taxonomy_id', function ($query) {
                        $query->select('taxonomy_id')
                            ->from((new OrganisationEventTaxonomy())->getTable())
                            ->where('organisation_event_id', $this->id);
                    })->select('collection_id');
            });
    }
}
