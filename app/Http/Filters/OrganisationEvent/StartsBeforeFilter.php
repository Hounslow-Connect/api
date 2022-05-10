<?php

namespace App\Http\Filters\OrganisationEvent;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class StartsBeforeFilter implements Filter
{
    public function __invoke(Builder $query, $value, string $property): Builder
    {
        return $query->whereDate('start_date', '<=', $value);
    }
}
