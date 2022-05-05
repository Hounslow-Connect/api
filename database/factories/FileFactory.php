<?php

use App\Models\File;
use Faker\Generator as Faker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

$factory->define(File::class, function (Faker $faker) {
    return [
        'filename' => Str::random() . '.dat',
        'mime_type' => 'text/plain',
        'is_private' => false,
    ];
});

$factory->state(File::class, 'pending-assignment', [
    'meta' => [
        'type' => File::META_TYPE_PENDING_ASSIGNMENT,
    ],
]);

$factory->state(File::class, 'image-png', [
    'filename' => Str::random() . '.png',
    'mime_type' => 'image/png',
]);

$factory->afterCreatingState(File::class, 'image-png', function (File $file) {
    $image = Storage::disk('local')->get('/test-data/image.png');
    $file->uploadBase64EncodedFile('data:image/png;base64,' . base64_encode($image));
});

$factory->state(File::class, 'image-jpg', [
    'filename' => Str::random() . '.jpg',
    'mime_type' => 'image/jpeg',
]);

$factory->afterCreatingState(File::class, 'image-jpg', function (File $file) {
    $image = Storage::disk('local')->get('/test-data/image.jpg');
    $file->uploadBase64EncodedFile('data:image/jpeg;base64,' . base64_encode($image));
});

$factory->state(File::class, 'image-svg', [
    'filename' => Str::random() . '.svg',
    'mime_type' => 'image/svg+xml',
]);

$factory->afterCreatingState(File::class, 'image-svg', function (File $file) {
    $image = Storage::disk('local')->get('/test-data/image.svg');
    $file->uploadBase64EncodedFile('data:image/svg+xml;base64,' . base64_encode($image));
});
