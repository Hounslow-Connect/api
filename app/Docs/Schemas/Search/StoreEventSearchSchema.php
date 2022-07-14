<?php

namespace App\Docs\Schemas\Search;

use App\Contracts\EventSearch as Search;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class StoreEventSearchSchema extends Schema
{
    /**
     * @param string|null $objectId
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     * @return static
     */
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->type(static::TYPE_OBJECT)
            ->properties(
                Schema::integer('page'),
                Schema::integer('per_page')
                    ->default(config('ck.pagination_results')),
                Schema::string('query'),
                Schema::string('category'),
                Schema::boolean('is_free'),
                Schema::boolean('is_virtual'),
                Schema::boolean('has_wheelchair_access'),
                Schema::boolean('has_induction_loop'),
                Schema::boolean('has_accessible_toilet'),
                Schema::string('starts_after'),
                Schema::string('ends_before'),
                Schema::string('order')
                    ->enum(Search::ORDER_RELEVANCE, Search::ORDER_DISTANCE, Search::ORDER_START, Search::ORDER_END)
                    ->default(Search::ORDER_START),
                Schema::object('location')
                    ->required('lat', 'lon')
                    ->properties(
                        Schema::number('lat')
                            ->type(Schema::FORMAT_FLOAT),
                        Schema::number('lon')
                            ->type(Schema::FORMAT_FLOAT)
                    ),
                Schema::integer('distance')
                    ->default(config('ck.search_distance')),
                Schema::array('eligibilities')
                    ->items(
                        Schema::string()
                    )
            );
    }
}
