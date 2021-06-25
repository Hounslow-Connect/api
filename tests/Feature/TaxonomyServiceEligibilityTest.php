<?php

namespace Tests\Feature;

use App\Events\EndpointHit;
use App\Models\Audit;
use App\Models\Organisation;
use App\Models\Service;
use App\Models\ServiceEligibility;
use App\Models\Taxonomy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TaxonomyServiceEligibilityTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        $this->testserviceEligibilityType = factory(Taxonomy::class)->create([
            'name' => 'Test Service Eligibility Type',
            'parent_id' => function () {
                return Taxonomy::serviceEligibility()->id;
            },
        ]);
    }

    /*
     * List all the service eligibility taxonomies.
     */

    public function test_guest_can_list_them()
    {
        $response = $this->json('GET', '/core/v1/taxonomies/service-eligibilities');

        $taxonomyCount = Taxonomy::serviceEligibility()->children()->count();

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount($taxonomyCount, 'data');
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'parent_id',
                    'name',
                    'order',
                    'children' => [],
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    public function test_audit_created_when_listed()
    {
        $this->fakeEvents();

        $this->json('GET', '/core/v1/taxonomies/service-eligibilities');

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return ($event->getAction() === Audit::ACTION_READ);
        });
    }

    /*
     * Create a service eligibility taxonomy.
     */

    public function test_guest_cannot_create_one()
    {
        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibility');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_service_worker_cannot_create_one()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceWorker($service);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibility');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_service_admin_cannot_create_one()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibility');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_organisation_admin_cannot_create_one()
    {
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibility');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_global_admin_can_create_one()
    {
        $user = factory(User::class)->create()->makeGlobalAdmin();
        $siblingCount = Taxonomy::serviceEligibility()->children()->count();
        $payload = [
            'parent_id' => null,
            'name' => 'PHPUnit Service Eligibility Test',
            'order' => $siblingCount + 1,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibility', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment($payload);
    }

    public function test_order_is_updated_when_created_at_beginning()
    {
        $user = factory(User::class)->create()->makeGlobalAdmin();

        $testEligibilityTypeTaxonomies = [];
        for ($i = 1; $i < 6; $i++) {
            $testEligibilityTypeTaxonomies[] = factory(Taxonomy::class)->create([
                'parent_id' => $this->testserviceEligibilityType->id,
                'order' => $i,
            ]);
        }

        $payload = [
            'parent_id' => $this->testserviceEligibilityType->id,
            'name' => 'PHPUnit Service Eligibility Test',
            'order' => 1,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibility', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment($payload);
        foreach ($testEligibilityTypeTaxonomies as $eligibility) {
            $this->assertDatabaseHas(
                (new Taxonomy())->getTable(),
                ['id' => $eligibility->id, 'order' => $eligibility->order + 1]
            );
        }
    }

    public function test_order_is_updated_when_created_at_middle()
    {
        $user = factory(User::class)->create()->makeGlobalAdmin();

        $testEligibilityTypeTaxonomies = [];
        for ($i = 1; $i < 6; $i++) {
            $testEligibilityTypeTaxonomies[] = factory(Taxonomy::class)->create([
                'parent_id' => $this->testserviceEligibilityType->id,
                'order' => $i,
            ]);
        }

        $payload = [
            'parent_id' => $this->testserviceEligibilityType->id,
            'name' => 'PHPUnit Service Eligibility Test',
            'order' => 2,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibility', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment($payload);
        foreach ($testEligibilityTypeTaxonomies as $eligibility) {
            if ($eligibility->order < 2) {
                $this->assertDatabaseHas(
                    (new Taxonomy())->getTable(),
                    ['id' => $eligibility->id, 'order' => $eligibility->order]
                );
            } else {
                $this->assertDatabaseHas(
                    (new Taxonomy())->getTable(),
                    ['id' => $eligibility->id, 'order' => $eligibility->order + 1]
                );
            }
        }
    }

    public function test_order_is_updated_when_created_at_end()
    {
        $user = factory(User::class)->create()->makeGlobalAdmin();

        $testEligibilityTypeTaxonomies = [];
        for ($i = 1; $i < 6; $i++) {
            $testEligibilityTypeTaxonomies[] = factory(Taxonomy::class)->create([
                'parent_id' => $this->testserviceEligibilityType->id,
                'order' => $i,
            ]);
        }

        $payload = [
            'parent_id' => $this->testserviceEligibilityType->id,
            'name' => 'PHPUnit Service Eligibility Test',
            'order' => count($testEligibilityTypeTaxonomies) + 1,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibility', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment($payload);
        foreach ($testEligibilityTypeTaxonomies as $eligibility) {
            $this->assertDatabaseHas(
                (new Taxonomy())->getTable(),
                ['id' => $eligibility->id, 'order' => $eligibility->order]
            );
        }
    }

    public function test_order_cannot_be_less_than_1_when_created()
    {
        $user = factory(User::class)->create()->makeGlobalAdmin();
        $payload = [
            'parent_id' => null,
            'name' => 'PHPUnit Service Eligibility Test',
            'order' => 0,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibility', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_order_cannot_be_greater_than_count_plus_1_when_created()
    {
        $user = factory(User::class)->create()->makeGlobalAdmin();
        $siblingCount = Taxonomy::category()->children()->count();
        $payload = [
            'parent_id' => null,
            'name' => 'PHPUnit Service Eligibility Test',
            'order' => $siblingCount + 2,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibility', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_audit_created_when_created()
    {
        $this->fakeEvents();

        $user = factory(User::class)->create()->makeGlobalAdmin();
        $siblingCount = Taxonomy::category()->children()->count();

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibility', [
            'parent_id' => null,
            'name' => 'PHPUnit Service Eligibility Test',
            'order' => $siblingCount + 1,
        ]);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $response) {
            return ($event->getAction() === Audit::ACTION_CREATE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $this->getResponseContent($response)['data']['id']);
        });
    }

    /*
     * Get a service eligibility taxonomy
     */

    public function test_guest_can_view_one()
    {
        $testEligibilityTypeTaxonomy = factory(Taxonomy::class)->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        $response = $this->json('GET', "/core/v1/taxonomies/service-eligibility/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            [
                'id' => $testEligibilityTypeTaxonomy->id,
                'parent_id' => $testEligibilityTypeTaxonomy->parent_id,
                'name' => $testEligibilityTypeTaxonomy->name,
                'order' => $testEligibilityTypeTaxonomy->order,
                'children' => [],
                'created_at' => $testEligibilityTypeTaxonomy->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $testEligibilityTypeTaxonomy->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ]);
    }

    public function test_audit_created_when_viewed()
    {
        $this->fakeEvents();

        $testEligibilityTypeTaxonomy = factory(Taxonomy::class)->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        $this->json('GET', "/core/v1/taxonomies/service-eligibility/{$testEligibilityTypeTaxonomy->id}");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($testEligibilityTypeTaxonomy) {
            return ($event->getAction() === Audit::ACTION_READ) &&
                ($event->getModel()->id === $testEligibilityTypeTaxonomy->id);
        });
    }

    /*
     * Update a specific service eligibility taxonomy.
     */

    public function test_guest_cannot_update_one()
    {
        $testEligibilityTypeTaxonomy = factory(Taxonomy::class)->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibility/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_service_worker_cannot_update_one()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceWorker($service);
        $testEligibilityTypeTaxonomy = factory(Taxonomy::class)->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibility/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_service_admin_cannot_update_one()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceAdmin($service);
        $testEligibilityTypeTaxonomy = factory(Taxonomy::class)->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibility/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_organisation_admin_cannot_update_one()
    {
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);
        $testEligibilityTypeTaxonomy = factory(Taxonomy::class)->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibility/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_global_admin_can_update_one()
    {
        $user = factory(User::class)->create()->makeGlobalAdmin();
        $testEligibilityTypeTaxonomy = factory(Taxonomy::class)->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);
        $payload = [
            'parent_id' => $testEligibilityTypeTaxonomy->parent_id,
            'name' => 'PHPUnit Test Service Eligibility',
            'order' => $testEligibilityTypeTaxonomy->order,
        ];

        Passport::actingAs($user);
        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibility/{$testEligibilityTypeTaxonomy->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);
    }

    public function test_order_is_updated_when_updated_to_beginning()
    {
        $user = factory(User::class)->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $eligibilityOne = $this->testserviceEligibilityType->children()->create([
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $eligibilityTwo = $this->testserviceEligibilityType->children()->create([
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $eligibilityThree = $this->testserviceEligibilityType->children()->create([
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibility/{$eligibilityTwo->id}", [
            'parent_id' => $eligibilityTwo->parent_id,
            'name' => $eligibilityTwo->name,
            'order' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityOne->id, 'order' => 2]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityTwo->id, 'order' => 1]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityThree->id, 'order' => 3]);
    }

    public function test_order_is_updated_when_updated_to_middle()
    {
        $user = factory(User::class)->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $eligibilityOne = $this->testserviceEligibilityType->children()->create([
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $eligibilityTwo = $this->testserviceEligibilityType->children()->create([
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $eligibilityThree = $this->testserviceEligibilityType->children()->create([
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibility/{$eligibilityOne->id}", [
            'parent_id' => $eligibilityOne->parent_id,
            'name' => $eligibilityOne->name,
            'order' => 2,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityOne->id, 'order' => 2]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityTwo->id, 'order' => 1]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityThree->id, 'order' => 3]);
    }

    public function test_order_is_updated_when_updated_to_end()
    {
        $user = factory(User::class)->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $eligibilityOne = $this->testserviceEligibilityType->children()->create([
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $eligibilityTwo = $this->testserviceEligibilityType->children()->create([
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $eligibilityThree = $this->testserviceEligibilityType->children()->create([
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibility/{$eligibilityTwo->id}", [
            'parent_id' => $eligibilityTwo->parent_id,
            'name' => $eligibilityTwo->name,
            'order' => 3,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityOne->id, 'order' => 1]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityTwo->id, 'order' => 3]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityThree->id, 'order' => 2]);
    }

    public function test_order_is_updated_when_updated_to_beginning_of_another_parent()
    {
        $user = factory(User::class)->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $oldEligibilityOne = $this->testserviceEligibilityType->children()->create([
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $oldEligibilityTwo = $this->testserviceEligibilityType->children()->create([
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $oldEligibilityThree = $this->testserviceEligibilityType->children()->create([
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $newParentEligibility = factory(Taxonomy::class)->create([
            'name' => 'New Service Eligibility Type',
            'parent_id' => function () {
                return Taxonomy::serviceEligibility()->id;
            },
        ]);
        $newEligibilityOne = $newParentEligibility->children()->create([
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $newEligibilityTwo = $newParentEligibility->children()->create([
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $newEligibilityThree = $newParentEligibility->children()->create([
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibility/{$oldEligibilityTwo->id}", [
            'parent_id' => $newParentEligibility->id,
            'name' => $oldEligibilityTwo->name,
            'order' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        /*
         * Old parent.
         */
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $this->testserviceEligibilityType->id,
            'id' => $oldEligibilityOne->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $this->testserviceEligibilityType->id,
            'id' => $oldEligibilityThree->id,
            'order' => 2,
        ]);

        /*
         * New parent.
         */
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $oldEligibilityTwo->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityOne->id,
            'order' => 2,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityTwo->id,
            'order' => 3,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityThree->id,
            'order' => 4,
        ]);
    }

    public function test_order_is_updated_when_updated_to_middle_of_another_parent()
    {
        $user = factory(User::class)->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $oldEligibilityOne = $this->testserviceEligibilityType->children()->create([
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $oldEligibilityTwo = $this->testserviceEligibilityType->children()->create([
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $oldEligibilityThree = $this->testserviceEligibilityType->children()->create([
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $newParentEligibility = factory(Taxonomy::class)->create([
            'name' => 'New Service Eligibility Type',
            'parent_id' => function () {
                return Taxonomy::serviceEligibility()->id;
            },
        ]);
        $newEligibilityOne = $newParentEligibility->children()->create([
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $newEligibilityTwo = $newParentEligibility->children()->create([
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $newEligibilityThree = $newParentEligibility->children()->create([
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibility/{$oldEligibilityOne->id}", [
            'parent_id' => $newParentEligibility->id,
            'name' => $oldEligibilityOne->name,
            'order' => 2,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        /*
         * Old parent.
         */
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $this->testserviceEligibilityType->id,
            'id' => $oldEligibilityTwo->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $this->testserviceEligibilityType->id,
            'id' => $oldEligibilityThree->id,
            'order' => 2,
        ]);

        /*
         * New parent.
         */
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityOne->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $oldEligibilityOne->id,
            'order' => 2,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityTwo->id,
            'order' => 3,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityThree->id,
            'order' => 4,
        ]);
    }

    public function test_order_is_updated_when_updated_to_end_of_another_parent()
    {
        $user = factory(User::class)->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $oldEligibilityOne = $this->testserviceEligibilityType->children()->create([
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $oldEligibilityTwo = $this->testserviceEligibilityType->children()->create([
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $oldEligibilityThree = $this->testserviceEligibilityType->children()->create([
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $newParentEligibility = factory(Taxonomy::class)->create([
            'name' => 'New Service Eligibility Type',
            'parent_id' => function () {
                return Taxonomy::serviceEligibility()->id;
            },
        ]);
        $newEligibilityOne = $newParentEligibility->children()->create([
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $newEligibilityTwo = $newParentEligibility->children()->create([
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $newEligibilityThree = $newParentEligibility->children()->create([
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibility/{$oldEligibilityTwo->id}", [
            'parent_id' => $newParentEligibility->id,
            'name' => $oldEligibilityTwo->name,
            'order' => 4,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        /*
         * Old parent.
         */
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $this->testserviceEligibilityType->id,
            'id' => $oldEligibilityOne->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $this->testserviceEligibilityType->id,
            'id' => $oldEligibilityThree->id,
            'order' => 2,
        ]);

        /*
         * New parent.
         */
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityOne->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityTwo->id,
            'order' => 2,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityThree->id,
            'order' => 3,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $oldEligibilityTwo->id,
            'order' => 4,
        ]);
    }

    public function test_order_cannot_be_less_than_1_when_updated()
    {
        $user = factory(User::class)->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $testEligibilityTypeTaxonomy = factory(Taxonomy::class)->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibility/{$testEligibilityTypeTaxonomy->id}", [
            'parent_id' => $testEligibilityTypeTaxonomy->parent_id,
            'name' => $testEligibilityTypeTaxonomy->name,
            'order' => 0,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_order_cannot_be_greater_than_count_plus_1_when_updated()
    {
        $user = factory(User::class)->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $testEligibilityTypeTaxonomies = factory(Taxonomy::class, 5)->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibility/{$testEligibilityTypeTaxonomies->get(0)->id}", [
            'parent_id' => $this->testserviceEligibilityType->id,
            'name' => $testEligibilityTypeTaxonomies->get(0)->name,
            'order' => 6,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_audit_created_when_updated()
    {
        $this->fakeEvents();

        $user = factory(User::class)->create()->makeSuperAdmin();
        $testEligibilityTypeTaxonomy = factory(Taxonomy::class)->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        Passport::actingAs($user);
        $this->json('PUT', "/core/v1/taxonomies/service-eligibility/{$testEligibilityTypeTaxonomy->id}", [
            'parent_id' => $testEligibilityTypeTaxonomy->parent_id,
            'name' => 'PHPUnit Test Eligibility Type Taxonomy',
            'order' => $testEligibilityTypeTaxonomy->order,
        ]);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $testEligibilityTypeTaxonomy) {
            return ($event->getAction() === Audit::ACTION_UPDATE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $testEligibilityTypeTaxonomy->id);
        });
    }

    /*
     * Delete a specific category taxonomy.
     */

    public function test_guest_cannot_delete_one()
    {
        $testEligibilityTypeTaxonomy = factory(Taxonomy::class)->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        $response = $this->json('DELETE', "/core/v1/taxonomies/service-eligibility/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_service_worker_cannot_delete_one()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceWorker($service);
        $testEligibilityTypeTaxonomy = factory(Taxonomy::class)->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('DELETE', "/core/v1/taxonomies/service-eligibility/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_service_admin_cannot_delete_one()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceAdmin($service);
        $testEligibilityTypeTaxonomy = factory(Taxonomy::class)->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('DELETE', "/core/v1/taxonomies/service-eligibility/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_organisation_admin_cannot_delete_one()
    {
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);
        $testEligibilityTypeTaxonomy = factory(Taxonomy::class)->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('DELETE', "/core/v1/taxonomies/service-eligibility/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_global_admin_can_delete_one()
    {
        $user = factory(User::class)->create()->makeGlobalAdmin();
        $newParentEligibility = factory(Taxonomy::class)->create([
            'name' => 'New Service Eligibility Type',
            'parent_id' => function () {
                return Taxonomy::serviceEligibility()->id;
            },
        ]);
        $childEligibilities = factory(Taxonomy::class)->create([
            'parent_id' => $newParentEligibility->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('DELETE', "/core/v1/taxonomies/service-eligibility/{$newParentEligibility->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing((new Taxonomy())->getTable(), ['id' => $newParentEligibility->id]);
        foreach ($childEligibilities as $childEligibility) {
            $this->assertDatabaseMissing((new Taxonomy())->getTable(), ['id' => $childEligibility->id]);
        }
    }

    public function test_audit_created_when_deleted()
    {
        $this->fakeEvents();

        $user = factory(User::class)->create()->makeGlobalAdmin();
        $newParentEligibility = factory(Taxonomy::class)->create([
            'name' => 'New Service Eligibility Type',
            'parent_id' => function () {
                return Taxonomy::serviceEligibility()->id;
            },
        ]);
        factory(Taxonomy::class)->create([
            'parent_id' => $newParentEligibility->id,
        ]);

        Passport::actingAs($user);
        $this->json('DELETE', "/core/v1/taxonomies/service-eligibility/{$newParentEligibility->id}");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $newParentEligibility) {
            return ($event->getAction() === Audit::ACTION_DELETE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $newParentEligibility->id);
        });
    }

    /**
     * @test
     */
    public function service_eligibility_relationships_are_destroyed_when_deleted()
    {
        $user = factory(User::class)->create()->makeGlobalAdmin();
        $service = factory(Service::class)->create();
        $testEligibilityTypeTaxonomy = factory(Taxonomy::class)->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        $service->serviceEligibilities()->create([
            'taxonomy_id' => $testEligibilityTypeTaxonomy->id,
        ]);

        $this->assertDatabaseHas((new ServiceEligibility())->getTable(), [
            'service_id' => $service->id,
            'taxonomy_id' => $testEligibilityTypeTaxonomy->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('DELETE', "/core/v1/taxonomies/service-eligibility/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing((new ServiceEligibility())->getTable(), [
            'service_id' => $service->id,
            'taxonomy_id' => $testEligibilityTypeTaxonomy->id,
        ]);
    }
}
