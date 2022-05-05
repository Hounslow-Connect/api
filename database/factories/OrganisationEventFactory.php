<?php

use App\Models\File;
use App\Models\Location;
use App\Models\Organisation;
use App\Models\OrganisationEvent;
use Faker\Generator as Faker;

$factory->define(OrganisationEvent::class, function (Faker $faker) {
    $date = $faker->dateTimeBetween('+1 week', '+6 weeks');
    $endtime = $faker->time('H:i:s');
    $starttime = $faker->time('H:i:s', $endtime);

    return [
        'title' => $faker->sentence(3),
        'start_date' => $date->format('Y-m-d'),
        'end_date' => $date->format('Y-m-d'),
        'start_time' => $starttime,
        'end_time' => $endtime,
        'intro' => $faker->sentence,
        'description' => $faker->paragraph,
        'is_free' => true,
        'fees_text' => null,
        'fees_url' => null,
        'organiser_name' => null,
        'organiser_phone' => null,
        'organiser_email' => null,
        'organiser_url' => null,
        'booking_title' => null,
        'booking_summary' => null,
        'booking_url' => null,
        'booking_cta' => null,
        'is_virtual' => true,
        'location_id' => null,
        'organisation_id' => function () {
            return factory(Organisation::class)->create()->id;
        },
        'image_file_id' => null,
    ];
});

$factory->state(OrganisationEvent::class, 'nonFree', function (Faker $faker) {
    return [
        'is_free' => false,
        'fees_text' => $faker->sentence,
        'fees_url' => $faker->url,
    ];
});

$factory->state(OrganisationEvent::class, 'withOrganiser', function (Faker $faker) {
    return [
        'organiser_name' => $faker->name,
        'organiser_phone' => random_uk_phone(),
        'organiser_email' => $faker->safeEmail,
        'organiser_url' => $faker->url,
    ];
});

$factory->state(OrganisationEvent::class, 'withBookingInfo', function (Faker $faker) {
    return [
        'booking_title' => $faker->sentence(3),
        'booking_summary' => $faker->sentence,
        'booking_url' => $faker->url,
        'booking_cta' => $faker->words(2, true),
    ];
});

$factory->state(OrganisationEvent::class, 'notVirtual', function (Faker $faker) {
    return [
        'is_virtual' => false,
        'location_id' => function () {
            return factory(Location::class)->create()->id;
        },
    ];
});

$factory->state(OrganisationEvent::class, 'withImage', function (Faker $faker) {
    return [
        'image_file_id' => function () {
            return factory(File::class)->states('image-png')->create()->id;
        },
    ];
});
