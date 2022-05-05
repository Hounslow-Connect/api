<?php

namespace App\Docs\Operations\OrganisationEvents;

use App\Docs\Parameters\FilterIdParameter;
use App\Docs\Parameters\FilterParameter;
use App\Docs\Parameters\IncludeParameter;
use App\Docs\Parameters\PageParameter;
use App\Docs\Parameters\PerPageParameter;
use App\Docs\Schemas\OrganisationEvent\OrganisationEventSchema;
use App\Docs\Schemas\PaginationSchema;
use App\Docs\Tags\OrganisationEventsTag;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Operation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Response;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class IndexOrganisationEventOperation extends Operation
{
    /**
     * @param string|null $objectId
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     * @return static
     */
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->action(static::ACTION_GET)
            ->tags(OrganisationEventsTag::create())
            ->summary('List all the organisation events')
            ->description(
                <<<'EOT'
**Permission:** `Open`

---

Organisation events are returned in descending order of their start_date.
EOT
            )
            ->noSecurity()
            ->parameters(
                PageParameter::create(),
                PerPageParameter::create(),
                FilterIdParameter::create(),
                FilterParameter::create(null, 'organisation_id')
                    ->description('Comma separated list of organisation IDs to filter by')
                    ->schema(
                        Schema::array()->items(
                            Schema::string()->format(Schema::FORMAT_UUID)
                        )
                    )
                    ->style(FilterParameter::STYLE_SIMPLE),
                IncludeParameter::create(null, ['organisation'])
            )
            ->responses(
                Response::ok()->content(
                    MediaType::json()->schema(
                        PaginationSchema::create(null, OrganisationEventSchema::create())
                    )
                )
            );
    }
}
