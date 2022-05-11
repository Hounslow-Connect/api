<?php

namespace Tests\Feature;

use App\Events\EndpointHit;
use App\Models\Audit;
use App\Models\Organisation;
use App\Models\Service;
use App\Models\Taxonomy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TaxonomyOrganisationEventsTest extends TestCase
{
    /*
     * List all the organisation taxonomies.
     */

    public function test_guest_can_list_them()
    {
        $taxonomy = $this->createTaxonomyOrganisationEvent();

        $response = $this->json('GET', '/core/v1/taxonomies/organisation-events');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            [
                'id' => $taxonomy->id,
                'name' => $taxonomy->name,
                'order' => $taxonomy->order,
                'created_at' => $taxonomy->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $taxonomy->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ]);
    }

    public function test_audit_created_when_listed()
    {
        $this->fakeEvents();

        $this->json('GET', '/core/v1/taxonomies/organisation-events');

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return ($event->getAction() === Audit::ACTION_READ);
        });
    }

    /*
     * Create an organisation taxonomy.
     */

    public function test_guest_cannot_create_one()
    {
        $response = $this->json('POST', '/core/v1/taxonomies/organisation-events');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_service_worker_cannot_create_one()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceWorker($service);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/taxonomies/organisation-events');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_service_admin_cannot_create_one()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/taxonomies/organisation-events');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_organisation_admin_cannot_create_one()
    {
        $organisationEvent = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisationEvent);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/taxonomies/organisation-events');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_global_admin_can_create_one()
    {
        $user = factory(User::class)->create()->makeGlobalAdmin();
        $siblingCount = Taxonomy::organisationEvent()->children()->count();
        $payload = [
            'name' => 'PHPUnit Taxonomy Organisation Event Test',
            'order' => $siblingCount + 1,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment($payload);
    }

    public function test_order_is_updated_when_created_at_beginning()
    {
        $this->createTaxonomyOrganisationEvent();
        $this->createTaxonomyOrganisationEvent();
        $this->createTaxonomyOrganisationEvent();

        $user = factory(User::class)->create()->makeSuperAdmin();
        $taxonomyOrganisationEvents = Taxonomy::organisationEvent()->children()->orderBy('order')->get();
        $payload = [
            'name' => 'PHPUnit Taxonomy Organisation Event Test',
            'order' => 1,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment($payload);
        foreach ($taxonomyOrganisationEvents as $taxonomyOrganisationEvent) {
            $this->assertDatabaseHas(
                (new Taxonomy())->getTable(),
                ['id' => $taxonomyOrganisationEvent->id, 'order' => $taxonomyOrganisationEvent->order + 1]
            );
        }
    }

    public function test_order_is_updated_when_created_at_middle()
    {
        $this->createTaxonomyOrganisationEvent();
        $this->createTaxonomyOrganisationEvent();
        $this->createTaxonomyOrganisationEvent();

        $user = factory(User::class)->create()->makeSuperAdmin();
        $taxonomyOrganisationEvents = Taxonomy::organisationEvent()->children()->orderBy('order')->get();
        $payload = [
            'name' => 'PHPUnit Taxonomy Organisation Event Test',
            'order' => 2,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment($payload);
        foreach ($taxonomyOrganisationEvents as $taxonomyOrganisationEvent) {
            if ($taxonomyOrganisationEvent->order < 2) {
                $this->assertDatabaseHas(
                    (new Taxonomy())->getTable(),
                    ['id' => $taxonomyOrganisationEvent->id, 'order' => $taxonomyOrganisationEvent->order]
                );
            } else {
                $this->assertDatabaseHas(
                    (new Taxonomy())->getTable(),
                    ['id' => $taxonomyOrganisationEvent->id, 'order' => $taxonomyOrganisationEvent->order + 1]
                );
            }
        }
    }

    public function test_order_is_updated_when_created_at_end()
    {
        $this->createTaxonomyOrganisationEvent();
        $this->createTaxonomyOrganisationEvent();
        $this->createTaxonomyOrganisationEvent();

        $user = factory(User::class)->create()->makeSuperAdmin();
        $taxonomyOrganisationEvents = Taxonomy::organisationEvent()->children()->orderBy('order')->get();
        $payload = [
            'name' => 'PHPUnit Taxonomy Organisation Event Test',
            'order' => $taxonomyOrganisationEvents->count() + 1,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment($payload);
        foreach ($taxonomyOrganisationEvents as $taxonomyOrganisationEvent) {
            $this->assertDatabaseHas(
                (new Taxonomy())->getTable(),
                ['id' => $taxonomyOrganisationEvent->id, 'order' => $taxonomyOrganisationEvent->order]
            );
        }
    }

    public function test_order_cannot_be_less_than_1_when_created()
    {
        $user = factory(User::class)->create()->makeSuperAdmin();
        $payload = [
            'name' => 'PHPUnit Taxonomy Organisation Event Test',
            'order' => 0,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_order_cannot_be_greater_than_count_plus_1_when_created()
    {
        $user = factory(User::class)->create()->makeSuperAdmin();
        $siblingCount = Taxonomy::organisationEvent()->children()->count();
        $payload = [
            'name' => 'PHPUnit Taxonomy Organisation Event Test',
            'order' => $siblingCount + 2,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_audit_created_when_created()
    {
        $this->fakeEvents();

        $user = factory(User::class)->create()->makeSuperAdmin();
        $siblingCount = Taxonomy::organisationEvent()->children()->count();

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/organisation-events', [
            'name' => 'PHPUnit Taxonomy Organisation Event Test',
            'order' => $siblingCount + 1,
        ]);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $response) {
            return ($event->getAction() === Audit::ACTION_CREATE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $this->getResponseContent($response)['data']['id']);
        });
    }

    /*
     * Get a specific organisation taxonomy.
     */

    public function test_guest_can_view_one()
    {
        $taxonomy = $this->createTaxonomyOrganisationEvent();

        $response = $this->json('GET', "/core/v1/taxonomies/organisation-events/{$taxonomy->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'data' => [
                'id' => $taxonomy->id,
                'name' => $taxonomy->name,
                'order' => $taxonomy->order,
                'created_at' => $taxonomy->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $taxonomy->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ]);
    }

    public function test_audit_created_when_viewed()
    {
        $this->fakeEvents();

        $taxonomy = $this->createTaxonomyOrganisationEvent();

        $this->json('GET', "/core/v1/taxonomies/organisation-events/{$taxonomy->id}");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($taxonomy) {
            return ($event->getAction() === Audit::ACTION_READ) &&
                ($event->getModel()->id === $taxonomy->id);
        });
    }

    /*
     * Update a specific organisation taxonomy.
     */

    public function test_guest_cannot_update_one()
    {
        $organisationEvent = $this->createTaxonomyOrganisationEvent();

        $response = $this->json('PUT', "/core/v1/taxonomies/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_service_worker_cannot_update_one()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceWorker($service);
        $organisationEvent = $this->createTaxonomyOrganisationEvent();

        Passport::actingAs($user);
        $response = $this->json('PUT', "/core/v1/taxonomies/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_service_admin_cannot_update_one()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceAdmin($service);
        $organisationEvent = $this->createTaxonomyOrganisationEvent();

        Passport::actingAs($user);
        $response = $this->json('PUT', "/core/v1/taxonomies/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_organisation_admin_cannot_update_one()
    {
        $organisationEvent = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisationEvent);
        $organisationEvent = $this->createTaxonomyOrganisationEvent();

        Passport::actingAs($user);
        $response = $this->json('PUT', "/core/v1/taxonomies/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_global_admin_can_update_one()
    {
        $user = factory(User::class)->create()->makeGlobalAdmin();
        $organisationEvent = $this->createTaxonomyOrganisationEvent();
        $payload = [
            'name' => 'PHPUnit Test Organisation',
            'order' => $organisationEvent->order,
        ];

        Passport::actingAs($user);
        $response = $this->json('PUT', "/core/v1/taxonomies/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);
    }

    public function test_order_is_updated_when_updated_to_beginning()
    {
        $user = factory(User::class)->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $organisationEventOne = $this->createTaxonomyOrganisationEvent(['name' => 'One', 'order' => 1]);
        $organisationEventTwo = $this->createTaxonomyOrganisationEvent(['name' => 'Two', 'order' => 2]);
        $organisationEventThree = $this->createTaxonomyOrganisationEvent(['name' => 'Three', 'order' => 3]);

        $response = $this->json('PUT', "/core/v1/taxonomies/organisation-events/{$organisationEventTwo->id}", [
            'name' => $organisationEventTwo->name,
            'order' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $organisationEventOne->id, 'order' => 2]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $organisationEventTwo->id, 'order' => 1]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $organisationEventThree->id, 'order' => 3]);
    }

    public function test_order_is_updated_when_updated_to_middle()
    {
        $user = factory(User::class)->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $organisationEventOne = $this->createTaxonomyOrganisationEvent(['name' => 'One', 'order' => 1]);
        $organisationEventTwo = $this->createTaxonomyOrganisationEvent(['name' => 'Two', 'order' => 2]);
        $organisationEventThree = $this->createTaxonomyOrganisationEvent(['name' => 'Three', 'order' => 3]);

        $response = $this->json('PUT', "/core/v1/taxonomies/organisation-events/{$organisationEventOne->id}", [
            'name' => $organisationEventOne->name,
            'order' => 2,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $organisationEventOne->id, 'order' => 2]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $organisationEventTwo->id, 'order' => 1]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $organisationEventThree->id, 'order' => 3]);
    }

    public function test_order_is_updated_when_updated_to_end()
    {
        $user = factory(User::class)->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $organisationEventOne = $this->createTaxonomyOrganisationEvent(['name' => 'One', 'order' => 1]);
        $organisationEventTwo = $this->createTaxonomyOrganisationEvent(['name' => 'Two', 'order' => 2]);
        $organisationEventThree = $this->createTaxonomyOrganisationEvent(['name' => 'Three', 'order' => 3]);

        $response = $this->json('PUT', "/core/v1/taxonomies/organisation-events/{$organisationEventTwo->id}", [
            'name' => $organisationEventTwo->name,
            'order' => 3,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $organisationEventOne->id, 'order' => 1]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $organisationEventTwo->id, 'order' => 3]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $organisationEventThree->id, 'order' => 2]);
    }

    public function test_order_cannot_be_less_than_1_when_updated()
    {
        $user = factory(User::class)->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $organisationEvent = $this->createTaxonomyOrganisationEvent();

        $response = $this->json('PUT', "/core/v1/taxonomies/organisation-events/{$organisationEvent->id}", [
            'name' => $organisationEvent->name,
            'order' => 0,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_order_cannot_be_greater_than_count_plus_1_when_updated()
    {
        $user = factory(User::class)->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $organisationEvent = $this->createTaxonomyOrganisationEvent(['name' => 'One', 'order' => 1]);
        $this->createTaxonomyOrganisationEvent(['name' => 'Two', 'order' => 2]);
        $this->createTaxonomyOrganisationEvent(['name' => 'Three', 'order' => 3]);

        $response = $this->json('PUT', "/core/v1/taxonomies/organisation-events/{$organisationEvent->id}", [
            'name' => $organisationEvent->name,
            'order' => 4,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_audit_created_when_updated()
    {
        $this->fakeEvents();

        $user = factory(User::class)->create()->makeSuperAdmin();
        $organisationEvent = $this->createTaxonomyOrganisationEvent();

        Passport::actingAs($user);
        $this->json('PUT', "/core/v1/taxonomies/organisation-events/{$organisationEvent->id}", [
            'name' => 'PHPUnit Test Organisation',
            'order' => $organisationEvent->order,
        ]);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $organisationEvent) {
            return ($event->getAction() === Audit::ACTION_UPDATE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $organisationEvent->id);
        });
    }

    /*
     * Delete a specific organisation taxonomy.
     */

    public function test_guest_cannot_delete_one()
    {
        $organisationEvent = $this->createTaxonomyOrganisationEvent();

        $response = $this->json('DELETE', "/core/v1/taxonomies/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_service_worker_cannot_delete_one()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceWorker($service);
        $organisationEvent = $this->createTaxonomyOrganisationEvent();

        Passport::actingAs($user);
        $response = $this->json('DELETE', "/core/v1/taxonomies/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_service_admin_cannot_delete_one()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceAdmin($service);
        $organisationEvent = $this->createTaxonomyOrganisationEvent();

        Passport::actingAs($user);
        $response = $this->json('DELETE', "/core/v1/taxonomies/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_organisation_admin_cannot_delete_one()
    {
        $organisationEvent = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisationEvent);
        $organisationEvent = $this->createTaxonomyOrganisationEvent();

        Passport::actingAs($user);
        $response = $this->json('DELETE', "/core/v1/taxonomies/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_global_admin_can_delete_one()
    {
        $user = factory(User::class)->create()->makeGlobalAdmin();
        $organisationEvent = $this->createTaxonomyOrganisationEvent();

        Passport::actingAs($user);
        $response = $this->json('DELETE', "/core/v1/taxonomies/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing((new Taxonomy())->getTable(), ['id' => $organisationEvent->id]);
    }

    public function test_audit_created_when_deleted()
    {
        $this->fakeEvents();

        $user = factory(User::class)->create()->makeSuperAdmin();
        $organisationEvent = $this->createTaxonomyOrganisationEvent();

        Passport::actingAs($user);
        $this->json('DELETE', "/core/v1/taxonomies/organisation-events/{$organisationEvent->id}");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $organisationEvent) {
            return ($event->getAction() === Audit::ACTION_DELETE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $organisationEvent->id);
        });
    }

    /*
     * Helpers.
     */

    protected function createTaxonomyOrganisationEvent(array $data = []): Taxonomy
    {
        $count = Taxonomy::organisationEvent()->children()->count();

        return Taxonomy::organisationEvent()->children()->create(array_merge([
            'name' => 'PHPUnit Organisation Event',
            'order' => $count + 1,
            'depth' => 1,
        ], $data));
    }
}
