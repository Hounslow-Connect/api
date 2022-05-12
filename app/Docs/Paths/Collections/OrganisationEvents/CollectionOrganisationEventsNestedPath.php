<?php

namespace App\Docs\Paths\Collections\OrganisationEvents;

use App\Docs\Operations\Collections\OrganisationEvents\DestroyCollectionOrganisationEventOperation;
use App\Docs\Operations\Collections\OrganisationEvents\ShowCollectionOrganisationEventOperation;
use App\Docs\Operations\Collections\OrganisationEvents\UpdateCollectionOrganisationEventOperation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Parameter;
use GoldSpecDigital\ObjectOrientedOAS\Objects\PathItem;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class CollectionOrganisationEventsNestedPath extends PathItem
{
    /**
     * @param string|null $objectId
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     * @return static
     */
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->route('/collections/organisation-events/{collection}')
            ->parameters(
                Parameter::path()
                    ->name('collection')
                    ->description('The ID of the organisation event collection')
                    ->required()
                    ->schema(Schema::string()->format(Schema::FORMAT_UUID))
            )
            ->operations(
                ShowCollectionOrganisationEventOperation::create(),
                UpdateCollectionOrganisationEventOperation::create(),
                DestroyCollectionOrganisationEventOperation::create()
            );
    }
}
