<?php

namespace App\Listeners;

use App\Events\EndpointHit;
use App\Models\Audit;
use Illuminate\Events\Dispatcher;

class EndpointHitSubscriber
{
    /**
     * All the events to listen to.
     *
     * @var array
     */
    protected $events = [
        /*
         * Audits.
         */
        \App\Events\Audit\AuditsListed::class,
        \App\Events\Audit\AuditRead::class,

        /*
         * Collection Categories.
         */
        \App\Events\CollectionCategory\CollectionCategoriesListed::class,
        \App\Events\CollectionCategory\CollectionCategoryCreated::class,
        \App\Events\CollectionCategory\CollectionCategoryRead::class,
        \App\Events\CollectionCategory\CollectionCategoryUpdated::class,
        \App\Events\CollectionCategory\CollectionCategoryDeleted::class,

        /*
         * Collection Personas.
         */
        \App\Events\CollectionPersona\CollectionPersonasListed::class,
        \App\Events\CollectionPersona\CollectionPersonaCreated::class,
        \App\Events\CollectionPersona\CollectionPersonaRead::class,
        \App\Events\CollectionPersona\CollectionPersonaUpdated::class,
        \App\Events\CollectionPersona\CollectionPersonaDeleted::class,

        /*
         * Locations.
         */
        \App\Events\Location\LocationsListed::class,
        \App\Events\Location\LocationCreated::class,
        \App\Events\Location\LocationRead::class,
        \App\Events\Location\LocationUpdated::class,
        \App\Events\Location\LocationDeleted::class,
    ];

    /**
     * @param \App\Events\EndpointHit $event
     */
    public function onHit(EndpointHit $event)
    {
        // Filter out any null values.
        $attributes = array_filter([
            'action' => $event->getAction(),
            'description' => $event->getDescription(),
            'ip_address' => $event->getIpAddress(),
            'user_agent' => $event->getUserAgent(),
        ]);

        // When an authenticated user makes the request.
        if ($event->getUser()) {
            $event->getUser()->audits()->create($attributes);
            return;
        }

        // When a guest makes the request.
        Audit::create($attributes);
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param \Illuminate\Events\Dispatcher $events
     * @throws \Exception
     */
    public function subscribe(Dispatcher $events)
    {
        foreach ($this->events as $event) {
            if (!is_subclass_of($event, EndpointHit::class)) {
                throw new \Exception("[$event] is not an instance of ".EndpointHit::class);
            }

            $events->listen($event, static::class . '@onHit');
        }
    }
}
