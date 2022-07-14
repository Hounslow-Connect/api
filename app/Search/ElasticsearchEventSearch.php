<?php

namespace App\Search;

use App\Contracts\EventSearch;
use App\Http\Resources\OrganisationEventResource;
use App\Models\Collection as CollectionModel;
use App\Models\OrganisationEvent;
use App\Models\Page;
use App\Models\SearchHistory;
use App\Support\Coordinate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class ElasticsearchEventSearch implements EventSearch
{
    const MILES = 'mi';
    const YARDS = 'yd';
    const FEET = 'ft';
    const INCHES = 'in';
    const KILOMETERS = 'km';
    const METERS = 'm';
    const CENTIMETERS = 'cm';
    const MILLIMETERS = 'mm';
    const NAUTICAL_MILES = 'nmi';

    /**
     * @var array
     */
    protected $query;

    /**
     * Search constructor.
     */
    public function __construct()
    {
        $this->query = [
            'from' => 0,
            'size' => config('ck.pagination_results'),
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'range' => [
                                'end_date' => [
                                    'gte' => 'now/d',
                                ],
                            ],
                        ],
                    ],
                    'must' => [
                        'bool' => [
                            'should' => [
                                //
                            ],
                        ],
                    ],
                ],
            ],
            'sort' => [
                '_score',
            ],
        ];
    }

    /**
     * @param string $term
     * @return \App\Search\ElasticsearchEventSearch
     */
    public function applyQuery(string $term): EventSearch
    {
        $should = &$this->query['query']['bool']['must']['bool']['should'];

        $should[] = $this->match('title', $term, 3);
        $should[] = $this->match('organisation_name', $term, 3);
        $should[] = $this->match('intro', $term, 2);
        $should[] = $this->match('description', $term, 1.5);
        $should[] = $this->match('taxonomy_categories', $term);

        if (empty($this->query['query']['bool']['must']['bool']['minimum_should_match'])) {
            $this->query['query']['bool']['must']['bool']['minimum_should_match'] = 1;
        }

        return $this;
    }

    /**
     * @param string $field
     * @param string $term
     * @param int $boost
     * @param mixed $fuzziness
     * @return array
     */
    protected function match(string $field, string $term, int $boost = 1, $fuzziness = 'AUTO'): array
    {
        return [
            'match' => [
                $field => [
                    'query' => $term,
                    'boost' => $boost,
                    'fuzziness' => $fuzziness,
                ],
            ],
        ];
    }

    /**
     * @param string $field
     * @param string $term
     * @param int $boost
     * @return array
     */
    protected function matchPhrase(string $field, string $term, int $boost = 1): array
    {
        return [
            'match_phrase' => [
                $field => [
                    'query' => $term,
                    'boost' => $boost,
                ],
            ],
        ];
    }

    /**
     * @param string $category
     * @return \App\Search\ElasticsearchEventSearch
     */
    public function applyCategory(string $category): EventSearch
    {
        $categoryModel = CollectionModel::query()
            ->with('taxonomies')
            ->organisationEvents()
            ->where('name', $category)
            ->firstOrFail();

        $should = &$this->query['query']['bool']['must']['bool']['should'];

        foreach ($categoryModel->taxonomies as $taxonomy) {
            $should[] = $this->match('taxonomy_categories', $taxonomy->name);
        }

        $this->query['query']['bool']['filter'][] = [
            'term' => [
                'collection_categories' => $category,
            ],
        ];

        return $this;
    }

    /**
     * @param bool $isFree
     * @return \App\Contracts\EventSearch
     */
    public function applyIsFree(bool $isFree): EventSearch
    {
        $this->query['query']['bool']['filter'][] = [
            'term' => [
                'is_free' => $isFree,
            ],
        ];

        return $this;
    }

    /**
     * @param bool $isVirtual
     * @return \App\Contracts\EventSearch
     */
    public function applyIsVirtual(bool $isVirtual): EventSearch
    {
        $this->query['query']['bool']['filter'][] = [
            'term' => [
                'is_virtual' => $isVirtual,
            ],
        ];

        return $this;
    }

    /**
     * @param bool $hasWheelchairAccess
     * @return \App\Contracts\EventSearch
     */
    public function applyHasWheelchairAccess(bool $hasWheelchairAccess): EventSearch
    {
        $this->query['query']['bool']['filter'][] = [
            'nested' => [
                'path' => 'event_location',
                'query' => [
                    'term' => [
                        'event_location.has_wheelchair_access' => $hasWheelchairAccess,
                    ],
                ],
            ],
        ];

        return $this;
    }

    /**
     * @param bool $hasInductionLoop
     * @return \App\Contracts\EventSearch
     */
    public function applyHasInductionLoop(bool $hasInductionLoop): EventSearch
    {
        $this->query['query']['bool']['filter'][] = [
            'nested' => [
                'path' => 'event_location',
                'query' => [
                    'term' => [
                        'event_location.has_induction_loop' => $hasInductionLoop,
                    ],
                ],
            ],
        ];

        return $this;
    }

    /**
     * @param string $order
     * @param \App\Support\Coordinate|null $location
     * @return \App\Search\ElasticsearchEventSearch
     */
    public function applyOrder(string $order, Coordinate $location = null): EventSearch
    {
        $this->query['sort'] = [];
        if ($order === static::ORDER_DISTANCE) {
            $this->query['sort'][] = [
                '_geo_distance' => [
                    'event_location.location' => $location->toArray(),
                    'nested_path' => 'event_location',
                ],
            ];
        } elseif ($order === static::ORDER_START) {
            $this->query['sort'][] = [
                'start_date' => [
                    'order' => 'asc',
                ],
            ];
        } elseif ($order === static::ORDER_END) {
            $this->query['sort'][] = [
                'end_date' => [
                    'order' => 'asc',
                ],
            ];
        }

        $this->query['sort'][] = '_score';

        return $this;
    }

    /**
     * @param string $startsAfter
     * @param string $endsBefore
     * @return \App\Contracts\Search
     */
    public function applyDateRange(string $startsAfter = null, string $endsBefore = null): EventSearch
    {
        if ($startsAfter) {
            $this->query['query']['bool']['filter'][] = [
                'range' => [
                    'start_date' => ['gte' => Carbon::parse($startsAfter)->toDateTimeLocalString()],
                ],
            ];
        }
        if ($endsBefore) {
            $this->query['query']['bool']['filter'][] = [
                'range' => [
                    'end_date' => ['lte' => Carbon::parse($endsBefore)->toDateTimeLocalString()],
                ],
            ];
        }

        return $this;
    }

    /**
     * @param \App\Support\Coordinate $location
     * @param int $radius
     * @return \App\Contracts\Search
     */
    public function applyRadius(Coordinate $location, int $radius): EventSearch
    {
        $this->query['query']['bool']['filter'][] = [
            'nested' => [
                'path' => 'event_location',
                'query' => [
                    'geo_distance' => [
                        'distance' => $this->distance($radius),
                        'event_location.location' => $location->toArray(),
                    ],
                ],
            ],
        ];

        return $this;
    }

    /**
     * @param int $distance
     * @param string $units
     * @return string
     */
    protected function distance(int $distance, string $units = self::MILES): string
    {
        return $distance . $units;
    }

    /**
     * Returns the underlying query. Only intended for use in testing.
     *
     * @return array
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * @param int|null $page
     * @param int|null $perPage
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function paginate(int $page = null, int $perPage = null): AnonymousResourceCollection
    {
        $page = page($page);
        $perPage = per_page($perPage);

        $this->query['from'] = ($page - 1) * $perPage;
        $this->query['size'] = $perPage;

        $response = OrganisationEvent::searchRaw($this->query);

        $this->logMetrics($response);

        return $this->toResource($response, true, $page);
    }

    /**
     * @param int|null $perPage
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function get(int $perPage = null): AnonymousResourceCollection
    {
        $this->query['size'] = per_page($perPage);

        $response = OrganisationEvent::searchRaw($this->query);
        $this->logMetrics($response);

        return $this->toResource($response, false);
    }

    /**
     * @param array $response
     * @param bool $paginate
     * @param int|null $page
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    protected function toResource(array $response, bool $paginate = true, int $page = null)
    {
        // Extract the hits from the array.
        $hits = $response['hits']['hits'];

        // Get all of the ID's for the pages from the hits.
        $eventIds = collect($hits)->map->_id->toArray();

        // Implode the page ID's so we can sort by them in database.
        $eventIdsImploded = implode("','", $eventIds);
        $eventIdsImploded = "'$eventIdsImploded'";

        // Create the query to get the pages, and keep ordering from Elasticsearch.
        $events = OrganisationEvent::query()
            ->with('location')
            ->whereIn('id', $eventIds)
            ->orderByRaw("FIELD(id,$eventIdsImploded)")
            ->get();

        $sorts = array_map(function ($order) {
            return is_array($order) ? array_keys($order)[0] : $order;
        }, $this->query['sort']);

        // Check if the query has been ordered by distance.
        if (in_array('_geo_distance', $sorts)) {
            $events = $this->orderEventsByLocation($events);
        }

        // If paginated, then create a new pagination instance.
        if ($paginate) {
            $events = new LengthAwarePaginator(
                $events,
                $response['hits']['total']['value'],
                config('ck.pagination_results'),
                $page,
                ['path' => Paginator::resolveCurrentPath()]
            );
        }

        return OrganisationEventResource::collection($events);
    }

    /**
     * @param array $response
     * @return \App\Search\ElasticsearchEventSearch
     */
    protected function logMetrics(array $response): EventSearch
    {
        SearchHistory::create([
            'query' => $this->query,
            'count' => $response['hits']['total']['value'],
        ]);

        return $this;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection $events
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function orderEventsByLocation(Collection $events): Collection
    {
        return $events->filter(function (OrganisationEvent $event) {
            return !$event->is_virtual;
        })
            ->sortBy(function (OrganisationEvent $event) {
                $location = $this->query['sort'][0]['_geo_distance']['event_location.location'];
                $location = new Coordinate($location['lat'], $location['lon']);

                return $location->distanceFrom($event->location->toCoordinate());
            });
    }
}
