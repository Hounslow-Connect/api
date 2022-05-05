<?php

namespace App\Http\Controllers\Core\V1\OrganisationEvent;

use App\Events\EndpointHit;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrganisationEvent\Image\ShowRequest;
use App\Models\File;
use App\Models\OrganisationEvent;

class ImageController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param \App\Http\Requests\OrganisationEvent\Image\ShowRequest $request
     * @param \App\Models\OrganisationEvent $organisationEvent
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @return \Illuminate\Http\Response
     */
    public function __invoke(ShowRequest $request, OrganisationEvent $organisationEvent)
    {
        event(EndpointHit::onRead($request, "Viewed image for Organisation Event [{$organisationEvent->id}]", $organisationEvent));

        // Get the image file associated.
        $file = File::find($organisationEvent->image_file_id);

        // Return the file, or placeholder if the file is null.
        return optional($file)->resizedVersion($request->max_dimension) ?? OrganisationEvent::placeholderImage($request->max_dimension);
    }
}
