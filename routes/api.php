<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('core/v1')->namespace('Core\\V1')->name('core.v1.')->group(function () {
    // Audits.
    Route::apiResource('/audits', 'AuditController')->only('index', 'show');

    // Collection Categories.
    Route::apiResource('/collections/categories', 'CollectionCategoryController')->names([
        'index' => 'collection-categories.index',
        'store' => 'collection-categories.store',
        'show' => 'collection-categories.show',
        'update' => 'collection-categories.update',
        'destroy' => 'collection-categories.destroy',
    ]);

    // Collection Personas.
    Route::apiResource('/collections/personas', 'CollectionPersonaController')->names([
        'index' => 'collection-personas.index',
        'store' => 'collection-personas.store',
        'show' => 'collection-personas.show',
        'update' => 'collection-personas.update',
        'destroy' => 'collection-personas.destroy',
    ]);
    Route::post('/collections/personas/{persona}/image', 'CollectionPersona\\ImageController@store')->name('collection-personas.image.store');
    Route::get('/collections/personas/{persona}/image', 'CollectionPersona\\ImageController@show')->name('collection-personas.image.show');
    Route::delete('/collections/personas/{persona}/image', 'CollectionPersona\\ImageController@destroy')->name('collection-personas.image.destroy');

    // Locations.
    Route::apiResource('/locations', 'LocationController');
});
