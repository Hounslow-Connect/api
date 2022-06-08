<?php

namespace App\Contracts;

use App\Support\Coordinate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

interface EventSearch
{
    const ORDER_RELEVANCE = 'relevance';
    const ORDER_DISTANCE = 'distance';
    const ORDER_START = 'start_date';
    const ORDER_END = 'end_date';

    /**
     * @param string $term
     * @return \App\Contracts\EventSearch
     */
    public function applyQuery(string $term): EventSearch;

    /**
     * @param string $category
     * @return \App\Contracts\EventSearch
     */
    public function applyCategory(string $category): EventSearch;

    /**
     * @param string $order
     * @param \App\Support\Coordinate|null $location
     * @return \App\Contracts\EventSearch
     */
    public function applyOrder(string $order): EventSearch;

    /**
     * @param bool $isFree
     * @return \App\Contracts\EventSearch
     */
    public function applyIsFree(bool $isFree): EventSearch;

    /**
     * @param bool $isVirtual
     * @return \App\Contracts\EventSearch
     */
    public function applyIsVirtual(bool $isVirtual): EventSearch;

    /**
     * @param bool $hasWheelchairAccess
     * @return \App\Contracts\EventSearch
     */
    public function applyHasWheelchairAccess(bool $hasWheelchairAccess): EventSearch;

    /**
     * @param bool $hasInductionLoop
     * @return \App\Contracts\EventSearch
     */
    public function applyHasInductionLoop(bool $hasInductionLoop): EventSearch;

    /**
     * @param string $startsAfter
     * @param string $endsBefore
     * @return \App\Contracts\EventSearch
     */
    public function applyDateRange(string $startsAfter, string $endsBefore): EventSearch;

    /**
     * @param \App\Support\Coordinate $location
     * @param int $radius
     * @return \App\Contracts\EventSearch
     */
    public function applyRadius(Coordinate $location, int $radius): EventSearch;

    /**
     * Returns the underlying query. Only intended for use in testing.
     *
     * @return array
     */
    public function getQuery(): array;

    /**
     * @param int|null $page
     * @param int|null $perPage
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function paginate(int $page = null, int $perPage = null): AnonymousResourceCollection;

    /**
     * @param int|null $perPage
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function get(int $perPage = null): AnonymousResourceCollection;
}
