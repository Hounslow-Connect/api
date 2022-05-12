<?php

use App\Models\Collection;
use App\Models\File;
use Faker\Generator as Faker;

$factory->define(Collection::class, function (Faker $faker) {
    $imageId = factory(File::class)->states('image-svg')->create()->id;
    $collection = [
        'type' => Collection::TYPE_CATEGORY,
        'name' => $faker->sentence(2),
        'meta' => [
            'intro' => $faker->sentence,
            'sideboxes' => [],
            'image_file_id' => $imageId,
        ],
        'order' => Collection::categories()->count() + 1,
        'enabled' => true,
    ];

    return $collection;
});

$factory->state(Collection::class, 'typePersona', function (Faker $faker) {
    return [
        'type' => Collection::TYPE_PERSONA,
        'meta' => [
            'intro' => $faker->sentence,
            'subtitle' => $faker->sentence,
            'sideboxes' => [],
        ],
        'order' => Collection::personas()->count() + 1,
    ];
});

$factory->state(Collection::class, 'typeOrganisationEvent', function (Faker $faker) {
    return [
        'type' => Collection::TYPE_ORGANISATION_EVENT,
        'order' => Collection::organisationEvents()->count() + 1,
    ];
});
