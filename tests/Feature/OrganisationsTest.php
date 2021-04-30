<?php

namespace Tests\Feature;

use App\Events\EndpointHit;
use App\Models\Audit;
use App\Models\File;
use App\Models\Organisation;
use App\Models\Service;
use App\Models\UpdateRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class OrganisationsTest extends TestCase
{
    /**
     * Create spreadsheets of organisations
     *
     * @param Illuminate\Support\Collection $organisations
     * @return null
     **/
    public function createOrganisationSpreadsheets(\Illuminate\Support\Collection $organisations)
    {
        $headers = [
            'id',
            'name',
            'description',
            'url',
            'email',
            'phone',
        ];

        $organisations = $organisations->map(function ($organisation) {
            $organisationAttributes = $organisation->getAttributes();
            $organisationAttributes['id'] = $organisation->id ?: uuid();
        });

        $spreadsheet = \Tests\Integration\SpreadsheetParserTest::createSpreadsheets($organisations->all(), $headers);
        \Tests\Integration\SpreadsheetParserTest::writeSpreadsheetsToDisk($spreadsheet, 'test.xlsx', 'test.xls');
    }

    /*
     * List all the organisations.
     */

    public function test_guest_can_list_them()
    {
        $organisation = factory(Organisation::class)->create();

        $response = $this->json('GET', '/core/v1/organisations');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            [
                'id' => $organisation->id,
                'has_logo' => $organisation->hasLogo(),
                'slug' => $organisation->slug,
                'name' => $organisation->name,
                'description' => $organisation->description,
                'url' => $organisation->url,
                'email' => $organisation->email,
                'phone' => $organisation->phone,
                'created_at' => $organisation->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $organisation->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ]);
    }

    public function test_audit_created_when_listed()
    {
        $this->fakeEvents();

        $this->json('GET', '/core/v1/organisations');

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return ($event->getAction() === Audit::ACTION_READ);
        });
    }

    public function test_guest_can_sort_by_name()
    {
        $organisationOne = factory(Organisation::class)->create([
            'name' => 'Organisation A',
        ]);
        $organisationTwo = factory(Organisation::class)->create([
            'name' => 'Organisation B',
        ]);

        $response = $this->json('GET', '/core/v1/organisations?sort=-name');
        $data = $this->getResponseContent($response);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals($organisationOne->id, $data['data'][1]['id']);
        $this->assertEquals($organisationTwo->id, $data['data'][0]['id']);
    }

    /*
     * Create an organisation.
     */

    public function test_guest_cannot_create_one()
    {
        $response = $this->json('POST', '/core/v1/organisations');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_service_worker_cannot_create_one()
    {
        /**
         * @var \App\Models\Service $service
         * @var \App\Models\User $user
         */
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create();
        $user->makeServiceWorker($service);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/organisations');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_service_admin_cannot_create_one()
    {
        /**
         * @var \App\Models\Service $service
         * @var \App\Models\User $user
         */
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create();
        $user->makeServiceAdmin($service);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/organisations');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_organisation_admin_cannot_create_one()
    {
        /**
         * @var \App\Models\Organisation $organisation
         * @var \App\Models\User $user
         */
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create();
        $user->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/organisations');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_global_admin_can_create_one()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();
        $payload = [
            'slug' => 'test-org',
            'name' => 'Test Org',
            'description' => 'Test description',
            'url' => 'http://test-org.example.com',
            'email' => 'info@test-org.example.com',
            'phone' => '07700000000',
        ];

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/organisations', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment($payload);
    }

    public function test_global_admin_can_create_one_with_single_form_of_contact()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();
        $payload = [
            'slug' => 'test-org',
            'name' => 'Test Org',
            'description' => 'Test description',
            'url' => 'http://test-org.example.com',
            'email' => 'info@test-org.example.com',
            'phone' => null,
        ];

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/organisations', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment($payload);
    }

    public function test_global_admin_cannot_create_one_with_no_form_of_contact()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();
        $payload = [
            'slug' => 'test-org',
            'name' => 'Test Org',
            'description' => 'Test description',
            'url' => 'http://test-org.example.com',
            'email' => null,
            'phone' => null,
        ];

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/organisations', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_audit_created_when_created()
    {
        $this->fakeEvents();

        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/organisations', [
            'slug' => 'test-org',
            'name' => 'Test Org',
            'description' => 'Test description',
            'url' => 'http://test-org.example.com',
            'email' => 'info@test-org.example.com',
            'phone' => '07700000000',
        ]);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $response) {
            return ($event->getAction() === Audit::ACTION_CREATE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $this->getResponseContent($response)['data']['id']);
        });
    }

    /*
     * Get a specific organisation.
     */

    public function test_guest_can_view_one()
    {
        $organisation = factory(Organisation::class)->create();

        $response = $this->json('GET', "/core/v1/organisations/{$organisation->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            [
                'id' => $organisation->id,
                'has_logo' => $organisation->hasLogo(),
                'slug' => $organisation->slug,
                'name' => $organisation->name,
                'description' => $organisation->description,
                'url' => $organisation->url,
                'email' => $organisation->email,
                'phone' => $organisation->phone,
                'created_at' => $organisation->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $organisation->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ]);
    }

    public function test_guest_can_view_one_by_slug()
    {
        $organisation = factory(Organisation::class)->create();

        $response = $this->json('GET', "/core/v1/organisations/{$organisation->slug}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            [
                'id' => $organisation->id,
                'has_logo' => $organisation->hasLogo(),
                'slug' => $organisation->slug,
                'name' => $organisation->name,
                'description' => $organisation->description,
                'url' => $organisation->url,
                'email' => $organisation->email,
                'phone' => $organisation->phone,
                'created_at' => $organisation->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $organisation->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ]);
    }

    public function test_audit_created_when_viewed()
    {
        $this->fakeEvents();

        $organisation = factory(Organisation::class)->create();

        $this->json('GET', "/core/v1/organisations/{$organisation->id}");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($organisation) {
            return ($event->getAction() === Audit::ACTION_READ) &&
                ($event->getModel()->id === $organisation->id);
        });
    }

    /*
     * Update a specific organisation.
     */

    public function test_guest_cannot_update_one()
    {
        $organisation = factory(Organisation::class)->create();

        $response = $this->json('PUT', "/core/v1/organisations/{$organisation->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_service_worker_cannot_update_one()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceWorker($service);
        $organisation = factory(Organisation::class)->create();

        Passport::actingAs($user);

        $response = $this->json('PUT', "/core/v1/organisations/{$organisation->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_service_admin_cannot_update_one()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceAdmin($service);
        $organisation = factory(Organisation::class)->create();

        Passport::actingAs($user);

        $response = $this->json('PUT', "/core/v1/organisations/{$organisation->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_organisation_admin_can_update_one()
    {
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);
        $payload = [
            'slug' => 'test-org',
            'name' => 'Test Org',
            'description' => 'Test description',
            'url' => 'http://test-org.example.com',
            'email' => 'info@test-org.example.com',
            'phone' => '07700000000',
        ];

        Passport::actingAs($user);

        $response = $this->json('PUT', "/core/v1/organisations/{$organisation->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['data' => $payload]);
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_ORGANISATION,
            'updateable_id' => $organisation->id,
        ]);
        $data = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_ORGANISATION)
            ->where('updateable_id', $organisation->id)
            ->firstOrFail()->data;
        $this->assertEquals($data, $payload);
    }

    public function test_organisation_admin_can_update_with_single_form_of_contact()
    {
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);
        $payload = [
            'slug' => 'test-org',
            'name' => 'Test Org',
            'description' => 'Test description',
            'url' => 'http://test-org.example.com',
            'email' => 'info@test-org.example.com',
            'phone' => null,
        ];

        Passport::actingAs($user);

        $response = $this->json('PUT', "/core/v1/organisations/{$organisation->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['data' => $payload]);
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_ORGANISATION,
            'updateable_id' => $organisation->id,
        ]);
        $data = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_ORGANISATION)
            ->where('updateable_id', $organisation->id)
            ->firstOrFail()->data;
        $this->assertEquals($data, $payload);
    }

    public function test_organisation_admin_cannot_update_with_no_form_of_contact()
    {
        $organisation = factory(Organisation::class)->create([
            'email' => 'info@test-org.example.com',
            'phone' => null,
        ]);
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $response = $this->json('PUT', "/core/v1/organisations/{$organisation->id}", [
            'slug' => 'test-org',
            'name' => 'Test Org',
            'description' => 'Test description',
            'url' => 'http://test-org.example.com',
            'email' => null,
            'phone' => null,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_only_partial_fields_can_be_updated()
    {
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);
        $payload = [
            'slug' => 'test-org',
        ];

        Passport::actingAs($user);

        $response = $this->json('PUT', "/core/v1/organisations/{$organisation->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['data' => $payload]);
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_ORGANISATION,
            'updateable_id' => $organisation->id,
        ]);
        $data = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_ORGANISATION)
            ->where('updateable_id', $organisation->id)
            ->firstOrFail()->data;
        $this->assertEquals($data, $payload);
    }

    public function test_audit_created_when_updated()
    {
        $this->fakeEvents();

        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $this->json('PUT', "/core/v1/organisations/{$organisation->id}", [
            'slug' => 'test-org',
            'name' => 'Test Org',
            'description' => 'Test description',
            'url' => 'http://test-org.example.com',
            'email' => 'info@test-org.example.com',
            'phone' => '07700000000',
        ]);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $organisation) {
            return ($event->getAction() === Audit::ACTION_UPDATE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $organisation->id);
        });
    }

    public function test_fields_removed_for_existing_update_requests()
    {
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $responseOne = $this->json('PUT', "/core/v1/organisations/{$organisation->id}", [
            'name' => 'Random 1',
        ]);
        $responseOne->assertStatus(Response::HTTP_OK);

        $responseTwo = $this->json('PUT', "/core/v1/organisations/{$organisation->id}", [
            'name' => 'Random 2',
            'slug' => 'random-1',
        ]);
        $responseTwo->assertStatus(Response::HTTP_OK);

        $updateRequestOne = UpdateRequest::withTrashed()->findOrFail($this->getResponseContent($responseOne)['id']);
        $updateRequestTwo = UpdateRequest::findOrFail($this->getResponseContent($responseTwo)['id']);

        $this->assertArrayNotHasKey('name', $updateRequestOne->data);
        $this->assertArrayHasKey('name', $updateRequestTwo->data);
        $this->assertArrayHasKey('slug', $updateRequestTwo->data);
        $this->assertSoftDeleted($updateRequestOne->getTable(), ['id' => $updateRequestOne->id]);
    }

    /*
     * Delete a specific organisation.
     */

    public function test_guest_cannot_delete_one()
    {
        $organisation = factory(Organisation::class)->create();

        $response = $this->json('DELETE', "/core/v1/organisations/{$organisation->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_service_worker_cannot_delete_one()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceWorker($service);
        $organisation = factory(Organisation::class)->create();

        Passport::actingAs($user);

        $response = $this->json('DELETE', "/core/v1/organisations/{$organisation->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_service_admin_cannot_delete_one()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceAdmin($service);
        $organisation = factory(Organisation::class)->create();

        Passport::actingAs($user);

        $response = $this->json('DELETE', "/core/v1/organisations/{$organisation->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_organisation_admin_cannot_delete_one()
    {
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $response = $this->json('DELETE', "/core/v1/organisations/{$organisation->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_global_admin_cannot_delete_one()
    {
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $response = $this->json('DELETE', "/core/v1/organisations/{$organisation->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_super_admin_can_delete_one()
    {
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $response = $this->json('DELETE', "/core/v1/organisations/{$organisation->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing((new Organisation())->getTable(), ['id' => $organisation->id]);
    }

    public function test_audit_created_when_deleted()
    {
        $this->fakeEvents();

        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $this->json('DELETE', "/core/v1/organisations/{$organisation->id}");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $organisation) {
            return ($event->getAction() === Audit::ACTION_DELETE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $organisation->id);
        });
    }

    /*
     * Get a specific organisation's logo.
     */

    public function test_guest_can_view_logo()
    {
        $organisation = factory(Organisation::class)->create();

        $response = $this->get("/core/v1/organisations/{$organisation->id}/logo.png");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertHeader('Content-Type', 'image/png');
    }

    public function test_audit_created_when_logo_viewed()
    {
        $this->fakeEvents();

        $organisation = factory(Organisation::class)->create();

        $this->get("/core/v1/organisations/{$organisation->id}/logo.png");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($organisation) {
            return ($event->getAction() === Audit::ACTION_READ) &&
                ($event->getModel()->id === $organisation->id);
        });
    }

    /*
     * Upload a specific organisation's logo.
     */

    public function test_organisation_admin_can_upload_logo()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();
        $image = Storage::disk('local')->get('/test-data/image.png');

        Passport::actingAs($user);

        $imageResponse = $this->json('POST', '/core/v1/files', [
            'is_private' => false,
            'mime_type' => 'image/png',
            'file' => 'data:image/png;base64,' . base64_encode($image),
        ]);

        $response = $this->json('POST', '/core/v1/organisations', [
            'slug' => 'test-org',
            'name' => 'Test Org',
            'description' => 'Test description',
            'url' => 'http://test-org.example.com',
            'email' => 'info@test-org.example.com',
            'phone' => '07700000000',
            'logo_file_id' => $this->getResponseContent($imageResponse, 'data.id'),
        ]);
        $organisationId = $this->getResponseContent($response, 'data.id');

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas(table(Organisation::class), [
            'id' => $organisationId,
        ]);
        $this->assertDatabaseMissing(table(Organisation::class), [
            'id' => $organisationId,
            'logo_file_id' => null,
        ]);
    }

    /*
     * Delete a specific organisation's logo.
     */

    public function test_organisation_admin_can_delete_logo()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();
        $organisation = factory(Organisation::class)->create([
            'logo_file_id' => factory(File::class)->create()->id,
        ]);
        $payload = [
            'slug' => $organisation->slug,
            'name' => $organisation->name,
            'description' => $organisation->description,
            'url' => $organisation->url,
            'email' => $organisation->email,
            'phone' => $organisation->phone,
            'logo_file_id' => null,
        ];

        Passport::actingAs($user);

        $response = $this->json('PUT', "/core/v1/organisations/{$organisation->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas(table(UpdateRequest::class), ['updateable_id' => $organisation->id]);
        $updateRequest = UpdateRequest::where('updateable_id', $organisation->id)->firstOrFail();
        $this->assertEquals(null, $updateRequest->data['logo_file_id']);
    }

    /**
     * Bulk import organisations
     */

    public function test_guest_cannot_bulk_import()
    {
        Storage::fake('local');

        $organisations = factory(Organisation::class, 2)->make();

        $this->createOrganisationSpreadsheets($organisations);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];
        $response = $this->json('POST', "/core/v1/organisations/import", $data);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_service_worker_cannot_bulk_import()
    {
        Storage::fake('local');

        $organisations = factory(Organisation::class, 2)->make();

        $this->createOrganisationSpreadsheets($organisations);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $service = factory(Service::class)->create();
        $user = factory(User::class)->create();
        $user->makeServiceWorker($service);

        Passport::actingAs($user);

        $response = $this->json('POST', "/core/v1/organisations/import", $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_service_admin_cannot_bulk_import()
    {
        Storage::fake('local');

        $organisations = factory(Organisation::class, 2)->make();

        $this->createOrganisationSpreadsheets($organisations);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $service = factory(Service::class)->create();
        $user = factory(User::class)->create();
        $user->makeServiceAdmin($service);

        Passport::actingAs($user);

        $response = $this->json('POST', "/core/v1/organisations/import", $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_organisation_admin_cannot_bulk_import()
    {
        Storage::fake('local');

        $organisations = factory(Organisation::class, 2)->make();

        $this->createOrganisationSpreadsheets($organisations);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create();
        $user->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $response = $this->json('POST', "/core/v1/organisations/import", $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_global_admin_cannot_bulk_import()
    {
        Storage::fake('local');

        $organisations = factory(Organisation::class, 2)->make();

        $this->createOrganisationSpreadsheets($organisations);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', "/core/v1/organisations/import", $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_super_admin_can_bulk_import()
    {
        Storage::fake('local');

        $organisations = factory(Organisation::class, 2)->make();

        $this->createOrganisationSpreadsheets($organisations);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $user = factory(User::class)->create();
        $user->makeSuperAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', "/core/v1/organisations/import", $data);

        $response->assertStatus(Response::HTTP_CREATED);
    }

    public function test_super_admin_can_bulk_import_with_minimal_fields()
    {
        Storage::fake('local');

        $organisations = factory(Organisation::class, 2)->make([
            'phone' => '',
        ]);

        $this->createOrganisationSpreadsheets($organisations);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $user = factory(User::class)->create();
        $user->makeSuperAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', "/core/v1/organisations/import", $data);

        $response->assertStatus(Response::HTTP_CREATED);
    }

    public function test_validate_file_import_type()
    {
        Storage::fake('local');

        $invalidFieldTypes = [
            ['spreadsheet' => 'This is a string'],
            ['spreadsheet' => 1],
            ['spreadsheet' => ['foo' => 'bar']],
            ['spreadsheet' => UploadedFile::fake()->create('dummy.doc', 3000)],
            ['spreadsheet' => UploadedFile::fake()->create('dummy.txt', 3000)],
            ['spreadsheet' => UploadedFile::fake()->create('dummy.csv', 3000)],
        ];

        $user = factory(User::class)->create();
        $user->makeSuperAdmin();
        Passport::actingAs($user);

        foreach ($invalidFieldTypes as $data) {
            $response = $this->json('POST', "/core/v1/organisations/import", $data);
            $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $organisations = factory(Organisation::class, 2)->make();

        $this->createOrganisationSpreadsheets($organisations);

        $response = $this->json('POST', "/core/v1/organisations/import", ['spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls')))]);
        dump($response->json());
        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson([
            'data' => [
                'imported_row_count' => 2,
            ],
        ]);

        $organisations = factory(Organisation::class, 2)->make();

        $this->createOrganisationSpreadsheets($organisations);

        $response = $this->json('POST', "/core/v1/organisations/import", ['spreadsheet' => 'data:application/octet-stream;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls')))]);
        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson([
            'data' => [
                'imported_row_count' => 2,
            ],
        ]);

        $organisations = factory(Organisation::class, 2)->make();

        $this->createOrganisationSpreadsheets($organisations);

        $response = $this->json('POST', "/core/v1/organisations/import", ['spreadsheet' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xlsx')))]);
        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson([
            'data' => [
                'imported_row_count' => 2,
            ],
        ]);
    }

    public function test_validate_file_import_fields()
    {
        Storage::fake('local');

        $user = factory(User::class)->create();
        $user->makeSuperAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', "/core/v1/organisations/import", ['spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(base_path('tests/assets/organisations_import_2_bad.xls')))]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson([
            'data' => [
                'errors' => [
                    'spreadsheet' => [
                        [
                            'row' => [],
                            'errors' => [
                                'name' => [],
                                'url' => [],
                                'email' => [],
                            ],
                        ],
                        [
                            'row' => [],
                            'errors' => [
                                'email' => [],
                                'phone' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->json('POST', "/core/v1/organisations/import", ['spreadsheet' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' . base64_encode(file_get_contents(base_path('tests/assets/organisations_import_2_bad.xlsx')))]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson([
            'data' => [
                'errors' => [
                    'spreadsheet' => [
                        [
                            'row' => [],
                            'errors' => [
                                'name' => [],
                                'url' => [],
                                'email' => [],
                            ],
                        ],
                        [
                            'row' => [],
                            'errors' => [
                                'email' => [],
                                'phone' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_organisations_file_import_100rows()
    {
        Storage::fake('local');

        $user = factory(User::class)->create();
        $user->makeSuperAdmin();
        Passport::actingAs($user);

        $organisations = factory(Organisation::class, 100)->make();

        $this->createOrganisationSpreadsheets($organisations);

        $response = $this->json('POST', "/core/v1/organisations/import", ['spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls')))]);
        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson([
            'data' => [
                'imported_row_count' => 100,
            ],
        ]);

        $organisations = factory(Organisation::class, 100)->make();

        $this->createOrganisationSpreadsheets($organisations);

        $response = $this->json('POST', "/core/v1/organisations/import", ['spreadsheet' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xlsx')))]);
        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson([
            'data' => [
                'imported_row_count' => 100,
            ],
        ]);
    }

    /**
     * @group slow
     */
    public function test_organisations_file_import_5krows()
    {
        Storage::fake('local');

        $user = factory(User::class)->create();
        $user->makeSuperAdmin();
        Passport::actingAs($user);

        $organisations = factory(Organisation::class, 5000)->make();

        $this->createOrganisationSpreadsheets($organisations);

        $response = $this->json('POST', "/core/v1/organisations/import", ['spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls')))]);
        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson([
            'data' => [
                'imported_row_count' => 5000,
            ],
        ]);

        $organisations = factory(Organisation::class, 5000)->make();

        $this->createOrganisationSpreadsheets($organisations);

        $response = $this->json('POST', "/core/v1/organisations/import", ['spreadsheet' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls')))]);
        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson([
            'data' => [
                'imported_row_count' => 5000,
            ],
        ]);
    }

    /**
     * @test
     */
    public function duplicate_import_organisations_are_detected()
    {
        Storage::fake('local');

        $user = factory(User::class)->create();
        $user->makeSuperAdmin();
        Passport::actingAs($user);

        $organisation1 = factory(Organisation::class)->create(['name' => 'Current Organisation']);
        $organisation2 = factory(Organisation::class)->create(['name' => 'Current  Organisation']);
        $organisation3 = factory(Organisation::class)->create(['name' => 'Current "Organisation"']);
        $organisation4 = factory(Organisation::class)->create(['name' => 'Current.Organisation']);
        $organisation5 = factory(Organisation::class)->create(['name' => 'Current, Organisation']);
        $organisation6 = factory(Organisation::class)->create(['name' => 'Current-Organisation']);

        $organisations = collect([
            factory(Organisation::class)->make(['name' => 'Current Organisation']),
            factory(Organisation::class)->make(['name' => 'New Organisation']),
            factory(Organisation::class)->make(['name' => 'New Organisation']),
        ]);

        $this->createOrganisationSpreadsheets($organisations);

        $response = $this->json('POST', "/core/v1/organisations/import", [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $headers = [
            'name',
            'description',
            'url',
            'email',
            'phone',
        ];
        $headersWithId = array_merge($headers, ['id']);

        $response->assertJsonFragment([
            collect($organisation1->getAttributes())->only($headersWithId)->all(),
        ]);
        $response->assertJsonFragment([
            'row' => collect($organisations->get(0)->getAttributes())->only($headers)->put('index', 2)->all(),
        ]);
        $response->assertJsonFragment([
            'email' => $organisations->get(2)->email,
        ]);
        $response->assertJsonFragment([
            'row' => collect($organisations->get(1)->getAttributes())->only($headers)->put('index', 3)->all(),
        ]);
        $response->assertJsonStructure([
            'data' => [
                'duplicates' => [
                    [
                        'row',
                        'originals' => [
                            $headersWithId,
                        ],
                    ],
                ],
                'imported_row_count',
            ],
        ]);
        $response->assertJsonFragment(collect($organisation1->getAttributes())->only($headersWithId)->all());
        $response->assertJsonFragment(collect($organisation2->getAttributes())->only($headersWithId)->all());
        $response->assertJsonFragment(collect($organisation3->getAttributes())->only($headersWithId)->all());
        $response->assertJsonFragment(collect($organisation4->getAttributes())->only($headersWithId)->all());
        $response->assertJsonFragment(collect($organisation5->getAttributes())->only($headersWithId)->all());
        $response->assertJsonFragment(collect($organisation6->getAttributes())->only($headersWithId)->all());
    }

    /**
     * @test
     */
    public function possible_duplicate_import_organisations_can_be_ignored()
    {
        Storage::fake('local');

        $user = factory(User::class)->create();
        $user->makeSuperAdmin();
        Passport::actingAs($user);

        $organisation1 = factory(Organisation::class)->create(['name' => 'Current Organisation']);
        $organisation2 = factory(Organisation::class)->create(['name' => 'Current  Organisation']);
        $organisation3 = factory(Organisation::class)->create(['name' => 'Current "Organisation"']);
        $organisation4 = factory(Organisation::class)->create(['name' => 'Current.Organisation']);
        $organisation5 = factory(Organisation::class)->create(['name' => 'Current, Organisation']);
        $organisation6 = factory(Organisation::class)->create(['name' => 'Current-Organisation']);
        $organisations = collect([
            factory(Organisation::class)->make(['name' => 'Current Organisation']),
            factory(Organisation::class)->make(['name' => 'New Organisation']),
        ]);

        $this->createOrganisationSpreadsheets($organisations);

        $response = $this->json('POST', "/core/v1/organisations/import", [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
            'ignore_duplicates' => [
                $organisation1->id,
                $organisation2->id,
                $organisation3->id,
                $organisation4->id,
                $organisation5->id,
                $organisation6->id,
            ],
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('organisations', [
            'email' => $organisation1->email,
        ]);
        $this->assertDatabaseHas('organisations', [
            'email' => $organisation2->email,
        ]);
        $this->assertDatabaseHas('organisations', [
            'email' => $organisation3->email,
        ]);
        $this->assertDatabaseHas('organisations', [
            'email' => $organisation4->email,
        ]);
        $this->assertDatabaseHas('organisations', [
            'email' => $organisation5->email,
        ]);
        $this->assertDatabaseHas('organisations', [
            'email' => $organisation6->email,
        ]);
    }

    public function test_duplicate_rows_in_import_are_detected()
    {
        Storage::fake('local');

        $user = factory(User::class)->create();
        $user->makeSuperAdmin();
        Passport::actingAs($user);

        $organisation = factory(Organisation::class)->create([
            'name' => 'Current Organisation',
            'description' => 'Original Organisation',
        ]);

        $organisations = collect([
            factory(Organisation::class)->make([
                'name' => 'Current Organisation',
                'description' => 'Import Organisation 1',
            ]),
            factory(Organisation::class)->make([
                'name' => 'Current Organisation',
                'description' => 'Import Organisation 2',
            ]),
        ]);

        $this->createOrganisationSpreadsheets($organisations);

        $response = $this->json('POST', "/core/v1/organisations/import", [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $headers = [
            'name',
            'description',
            'url',
            'email',
            'phone',
        ];

        $response->assertJson([
            'data' => [
                'imported_row_count' => 0,
            ],
        ]);
        $response->assertJsonFragment([
            'row' => collect($organisations->get(0)->getAttributes())->only($headers)->put('index', 2)->all(),
        ]);
        $response->assertJsonCount(2, 'data.duplicates.*.originals.*');
        $response->assertJsonFragment(collect($organisations->get(1)->getAttributes())->only($headers)->put('id', null)->all());
        $response->assertJsonFragment(collect($organisation->getAttributes())->only(array_merge($headers, ['id']))->all());
    }
}
