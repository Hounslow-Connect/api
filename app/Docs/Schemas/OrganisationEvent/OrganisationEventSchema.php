<?php

namespace App\Docs\Schemas\OrganisationEvent;

use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class OrganisationEventSchema extends Schema
{
    /**
     * @param string|null $objectId
     * @return static
     */
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->type(static::TYPE_OBJECT)
            ->properties(
                Schema::string('id')
                    ->format(Schema::FORMAT_UUID),
                Schema::string('organisation_id')
                    ->format(Schema::FORMAT_UUID),
                Schema::string('title'),
                Schema::string('intro'),
                Schema::string('description'),
                Schema::string('start_date')
                    ->format(Schema::FORMAT_DATE),
                Schema::string('end_date')
                    ->format(Schema::FORMAT_DATE),
                Schema::string('start_time')
                    ->format('H:i:s'),
                Schema::string('end_time')
                    ->format('H:i:s'),
                Schema::boolean('is_free'),
                Schema::string('fees_text')
                    ->nullable(),
                Schema::string('fees_url')
                    ->nullable(),
                Schema::string('organiser_name')
                    ->nullable(),
                Schema::string('organiser_phone')
                    ->nullable(),
                Schema::string('organiser_email')
                    ->nullable(),
                Schema::string('organiser_url')
                    ->nullable(),
                Schema::string('booking_title')
                    ->nullable(),
                Schema::string('booking_summary')
                    ->nullable(),
                Schema::string('booking_url')
                    ->nullable(),
                Schema::string('booking_cta')
                    ->nullable(),
                Schema::boolean('has_image'),
                Schema::boolean('is_virtual'),
                Schema::string('created_at')
                    ->format(Schema::FORMAT_DATE_TIME)
                    ->nullable(),
                Schema::string('updated_at')
                    ->format(Schema::FORMAT_DATE_TIME)
                    ->nullable()
            );
    }
}
