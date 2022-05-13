<?php

namespace App\Models\Scopes;

use App\Models\CollectionTaxonomy;
use App\Models\Location;
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
}
