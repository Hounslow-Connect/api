<?php

namespace Tests\Feature;

use App\Events\EndpointHit;
use App\Models\Audit;
use App\Models\Service;
use App\Models\UpdateRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class OrganisationEventsTest extends TestCase
{
    /**
     * Get all OrganisationEvents
     */

    /**
     * @test
     */
    public function getAllOrganisationEventsAsGuest200()
    {
        $organisationEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('GET', '/core/v1/events');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCollection([
            'id',
            'title',
            'start_date',
            'end_date',
            'start_time',
            'end_time',
            'summary',
            'description',
            'is_free',
            'fees_text',
            'fees_url',
            'organiser_name',
            'organiser_phone',
            'organiser_email',
            'organiser_url',
            'booking_title',
            'booking_summary',
            'booking_url',
            'booking_cta',
            'location_id',
            'organisation_id',
            'created_at',
            'updated_at',
        ]);

        $response->assertJsonFragment([
            'id' => $organisationEvent->id,
            'title' => $organisationEvent->title,
            'start_date' => $organisationEvent->start_date,
            'end_date' => $organisationEvent->end_date,
            'start_time' => $organisationEvent->start_time,
            'end_time' => $organisationEvent->end_time,
            'summary' => $organisationEvent->summary,
            'description' => $organisationEvent->description,
            'is_free' => $organisationEvent->is_free,
            'fees_text' => $organisationEvent->fees_text,
            'fees_url' => $organisationEvent->fees_url,
            'organiser_name' => $organisationEvent->organisation_name,
            'organiser_phone' => $organisationEvent->organiser_phone,
            'organiser_email' => $organisationEvent->organiser_email,
            'organiser_url' => $organisationEvent->organiser_url,
            'booking_title' => $organisationEvent->booking_title,
            'booking_summary' => $organisationEvent->booking_summary,
            'booking_url' => $organisationEvent->booking_url,
            'booking_cta' => $organisationEvent->booking_cta,
            'location_id' => $organisationEvent->location_id,
            'organisation_id' => $organisationEvent->organisation_id,
            'created_at' => $organisationEvent->created_at->format(CarbonImmutable::ISO8601),
            'updated_at' => $organisationEvent->updated_at->format(CarbonImmutable::ISO8601),
        ]);
    }

    /**
     * @test
     */
    public function getAllOrganisationEventsFilterByOrganisationAsGuest200()
    {
        $organisationEvent1 = factory(OrganisationEvent::class)->create();
        $organisationEvent2 = factory(OrganisationEvent::class)->create();

        $response = $this->json('GET', "/core/v1/events?filter[organisation_id]={$organisationEvent1->organisation_id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
    }

    /**
     * @test
     */
    public function getAllOrganisationEventsCreatesAuditAsGuest200()
    {
        $this->fakeEvents();

        $event = factory(OrganisationEvent::class)->create();

        $this->json('GET', '/core/v1/events');

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return ($event->getAction() === Audit::ACTION_READ);
        });
    }

    /**
     * Create an OrganisationEvent
     */

    /**
     * @test
     */
    public function postCreateOrganisationEventAsGuest401()
    {
        $response = $this->json('POST', '/core/v1/events');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventAsServiceWorker403()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceWorker($service);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/events');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventAsServiceAdmin403()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/events');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventAsOrganisationAdmin201()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->date('Y-m-d', '+6 weeks');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'summary' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'is_free' => false,
            'fees_text' => $this->faker->sentence,
            'fees_url' => $this->faker->url,
            'organiser_name' => $this->faker->name,
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail,
            'organiser_url' => $this->faker->url,
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence,
            'booking_url' => $this->faker->url,
            'booking_cta' => $this->faker->words(2, true),
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);

        $globalAdminUser = factory(User::class)->create()->makeGlobalAdmin();

        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::NEW_TYPE_EVENT,
            'updateable_id' => null,
        ]);

        $data = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_EVENT)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail()->data;

        $this->assertEquals($data, $payload);

        // Simulate frontend check by making call with UpdateRequest ID.
        $updateRequestId = json_decode($response->getContent())->id;

        Passport::actingAs($globalAdminUser);

        $updateRequestCheckResponse = $this->get(
            route('core.v1.update-requests.show',
                ['update_request' => $updateRequestId])
        );

        $updateRequestCheckResponse->assertSuccessful();
        $this->assertEquals($data, $payload);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventAsGlobalAdmin201()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $date = $this->faker->date('Y-m-d', '+6 weeks');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'summary' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'is_free' => false,
            'fees_text' => $this->faker->sentence,
            'fees_url' => $this->faker->url,
            'organiser_name' => $this->faker->name,
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail,
            'organiser_url' => $this->faker->url,
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence,
            'booking_url' => $this->faker->url,
            'booking_cta' => $this->faker->words(2, true),
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $responseData = json_decode($response->getContent())->data;

        // The service is created
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), ['id' => $responseData->id]);

        // And no update request was created
        $this->assertEmpty(UpdateRequest::all());
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventAsSuperAdmin201()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $date = $this->faker->date('Y-m-d', '+6 weeks');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'summary' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'is_free' => false,
            'fees_text' => $this->faker->sentence,
            'fees_url' => $this->faker->url,
            'organiser_name' => $this->faker->name,
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail,
            'organiser_url' => $this->faker->url,
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence,
            'booking_url' => $this->faker->url,
            'booking_cta' => $this->faker->words(2, true),
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $responseData = json_decode($response->getContent())->data;

        // The service is created
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), ['id' => $responseData->id]);

        // And no update request was created
        $this->assertEmpty(UpdateRequest::all());
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventAsOtherOrganisationAdmin422()
    {
        $organisation1 = factory(Organisation::class)->create();
        $organisation2 = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation2);

        Passport::actingAs($user);

        $date = $this->faker->date('Y-m-d', '+6 weeks');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'summary' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'is_free' => false,
            'fees_text' => $this->faker->sentence,
            'fees_url' => $this->faker->url,
            'organiser_name' => $this->faker->name,
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail,
            'organiser_url' => $this->faker->url,
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence,
            'booking_url' => $this->faker->url,
            'booking_cta' => $this->faker->words(2, true),
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation1->id,
        ];

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventCreatesAuditAsOrganisationAdmin201()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->date('Y-m-d', '+6 weeks');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'summary' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'is_free' => false,
            'fees_text' => $this->faker->sentence,
            'fees_url' => $this->faker->url,
            'organiser_name' => $this->faker->name,
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail,
            'organiser_url' => $this->faker->url,
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence,
            'booking_url' => $this->faker->url,
            'booking_cta' => $this->faker->words(2, true),
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $response) {
            return ($event->getAction() === Audit::ACTION_CREATE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $this->getResponseContent($response)['data']['id']);
        });
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventRequiredFieldsAsOrganisationAdmin422()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->date('Y-m-d', '+6 weeks');

        $response = $this->json('POST', '/core/v1/events', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/events', [
            'title' => $this->faker->sentence(3),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/events', [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/events', [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/events', [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/events', [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/events', [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'summary' => $this->faker->sentence,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/events', [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'summary' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventIfNotFreeRequiresFeeDataAsOrganisationAdmin201()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->date('Y-m-d', '+6 weeks');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'summary' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'organiser_name' => $this->faker->name,
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail,
            'organiser_url' => $this->faker->url,
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence,
            'booking_url' => $this->faker->url,
            'booking_cta' => $this->faker->words(2, true),
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $payload['is_free'] = false;

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['fees_url'] = $this->faker->url;

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['fees_text'] = $this->faker->sentence;

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventWithOrganiserRequiresOrganiserContactAsOrganisationAdmin201()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->date('Y-m-d', '+6 weeks');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'summary' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'organiser_name' => null,
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail,
            'organiser_url' => $this->faker->url,
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence,
            'booking_url' => $this->faker->url,
            'booking_cta' => $this->faker->words(2, true),
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $payload['organiser_name'] = $this->faker->name;

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['organiser_phone'] = random_uk_phone();

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $payload['organiser_phone'] = null;
        $payload['organiser_email'] = $this->faker->safeEmail;

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $payload['organiser_email'] = null;
        $payload['organiser_url'] = $this->faker->url;

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventWithBookingDetailsRequiresAllBookingFieldsAsOrganisationAdmin201()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->date('Y-m-d', '+6 weeks');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'summary' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'organiser_name' => $this->faker->name,
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail,
            'organiser_url' => $this->faker->url,
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence,
            'booking_url' => $this->faker->url,
            'booking_cta' => $this->faker->words(2, true),
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $payload['booking_title'] = $this->faker->sentence(3);

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['booking_summary'] = $this->faker->sentence;

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['booking_url'] = $this->faker->url;

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['booking_cta'] = $this->faker->words(2, true);

        $response = $this->json('POST', '/core/v1/events', $payload);

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * Get a single OrganisationEvent
     */

    /**
     * @test
     */
    public function getSingleOrganisationEventAsGuest200()
    {
        $organisationEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('GET', "/core/v1/events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment([
            'id' => $organisationEvent->id,
            'title' => $organisationEvent->title,
            'start_date' => $organisationEvent->start_date,
            'end_date' => $organisationEvent->end_date,
            'start_time' => $organisationEvent->start_time,
            'end_time' => $organisationEvent->end_time,
            'summary' => $organisationEvent->summary,
            'description' => $organisationEvent->description,
            'is_free' => $organisationEvent->is_free,
            'fees_text' => $organisationEvent->fees_text,
            'fees_url' => $organisationEvent->fees_url,
            'organiser_name' => $organisationEvent->organisation_name,
            'organiser_phone' => $organisationEvent->organiser_phone,
            'organiser_email' => $organisationEvent->organiser_email,
            'organiser_url' => $organisationEvent->organiser_url,
            'booking_title' => $organisationEvent->booking_title,
            'booking_summary' => $organisationEvent->booking_summary,
            'booking_url' => $organisationEvent->booking_url,
            'booking_cta' => $organisationEvent->booking_cta,
            'location_id' => $organisationEvent->location_id,
            'organisation_id' => $organisationEvent->organisation_id,
            'created_at' => $organisationEvent->created_at->format(CarbonImmutable::ISO8601),
            'updated_at' => $organisationEvent->updated_at->format(CarbonImmutable::ISO8601),
        ]);
    }

    /**
     * @test
     */
    public function getSingleOrganisationEventAsGuestCreatesAudit200()
    {
        $organisationEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('GET', "/core/v1/events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_OK);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($organisationEvent) {
            return ($event->getAction() === Audit::ACTION_READ) &&
                ($event->getModel()->id === $event->id);
        });
    }

    /**
     * @test
     */
    public function getSingleOrganisationEventImageAsGuest200()
    {
        $organisationEvent = factory(OrganisationEvent::class)->states('withImage')->create();

        $response = $this->get("/core/v1/events/{$organisationEvent->id}/image.png");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertHeader('Content-Type', 'image/png');
    }

    /**
     * @test
     */
    public function getSingleOrganisationEventImageCreatesAuditAsGuest200()
    {
        $this->fakeEvents();

        $organisationEvent = factory(OrganisationEvent::class)->states('withImage')->create();

        $response = $this->get("/core/v1/events/{$organisationEvent->id}/image.png");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($organisationEvent) {
            return ($event->getAction() === Audit::ACTION_READ) &&
                ($event->getModel()->id === $organisationEvent->id);
        });
    }

    /**
     * Update an OrganisationEvent
     */

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsGuest401()
    {
        $organisationEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('PUT', "/core/v1/events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsServiceWorker403()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceWorker($service);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('PUT', "/core/v1/events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsServiceAdmin403()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('PUT', "/core/v1/events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsOrganisationAdmin200()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create();

        $date = $this->faker->date('Y-m-d', '+6 weeks');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'summary' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'is_free' => false,
            'fees_text' => $this->faker->sentence,
            'fees_url' => $this->faker->url,
            'organiser_name' => $this->faker->name,
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail,
            'organiser_url' => $this->faker->url,
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence,
            'booking_url' => $this->faker->url,
            'booking_cta' => $this->faker->words(2, true),
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('PUT', "/core/v1/events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment($payload);

        $globalAdminUser = factory(User::class)->create()->makeGlobalAdmin();

        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_EVENT,
            'updateable_id' => null,
        ]);

        $data = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_EVENT)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail()->data;

        $this->assertEquals($data, $payload);

        // Simulate frontend check by making call with UpdateRequest ID.
        $updateRequestId = json_decode($response->getContent())->id;

        Passport::actingAs($globalAdminUser);

        $updateRequestCheckResponse = $this->get(
            route(
                'core.v1.update-requests.show',
                ['update_request' => $updateRequestId]
            )
        );

        $updateRequestCheckResponse->assertSuccessful();
        $this->assertEquals($data, $payload);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsGlobalAdmin200()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeGlobalAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create();

        $date = $this->faker->date('Y-m-d', '+6 weeks');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'summary' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'is_free' => false,
            'fees_text' => $this->faker->sentence,
            'fees_url' => $this->faker->url,
            'organiser_name' => $this->faker->name,
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail,
            'organiser_url' => $this->faker->url,
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence,
            'booking_url' => $this->faker->url,
            'booking_cta' => $this->faker->words(2, true),
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('PUT', "/core/v1/events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $responseData = json_decode($response->getContent())->data;

        // The service is updated
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), array_merge(['id' => $organisationEvent->id], $payload));

        // And no update request was created
        $this->assertEmpty(UpdateRequest::all());
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsSuperAdmin200()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeSuperAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create();

        $date = $this->faker->date('Y-m-d', '+6 weeks');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'summary' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'is_free' => false,
            'fees_text' => $this->faker->sentence,
            'fees_url' => $this->faker->url,
            'organiser_name' => $this->faker->name,
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail,
            'organiser_url' => $this->faker->url,
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence,
            'booking_url' => $this->faker->url,
            'booking_cta' => $this->faker->words(2, true),
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('PUT', "/core/v1/events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $responseData = json_decode($response->getContent())->data;

        // The service is updated
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), array_merge(['id' => $organisationEvent->id], $payload));

        // And no update request was created
        $this->assertEmpty(UpdateRequest::all());
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsOrganisationAdminCreatesAudit200()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create();

        $date = $this->faker->date('Y-m-d', '+6 weeks');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'summary' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'is_free' => false,
            'fees_text' => $this->faker->sentence,
            'fees_url' => $this->faker->url,
            'organiser_name' => $this->faker->name,
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail,
            'organiser_url' => $this->faker->url,
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence,
            'booking_url' => $this->faker->url,
            'booking_cta' => $this->faker->words(2, true),
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('PUT', "/core/v1/events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $organisationEvent) {
            return ($event->getAction() === Audit::ACTION_UPDATE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $organisationEvent->id);
        });
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsGlobalAdminAddImage200()
    {
        $user = factory(User::class)->create()->makeGlobalAdmin();
        $image = Storage::disk('local')->get('/test-data/image.png');

        Passport::actingAs($user);

        $imageResponse = $this->json('POST', '/core/v1/files', [
            'is_private' => false,
            'mime_type' => 'image/png',
            'file' => 'data:image/png;base64,' . base64_encode($image),
        ]);

        $organisationEvent = factory(OrganisationEvent::class)->create();

        $payload = [
            'image_file_id' => $this->getResponseContent($imageResponse, 'data.id'),
        ];

        $response = $this->json('PUT', "/core/v1/events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $content = $this->get("/core/v1/events/{$organisationEvent->id}/image.png")->content();
        $this->assertEquals($image, $content);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsGlobalAdminRemoveImage200()
    {
        $user = factory(User::class)->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->states('withImage')->create();

        $payload = [
            'image_file_id' => null,
        ];

        $response = $this->json('PUT', "/core/v1/events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $organisationEvent = $organisationEvent->fresh();
        $this->assertEquals(null, $organisationEvent->image_file_id);
    }

    /**
     * Delete an OrganisationEvent
     */

    /**
     * @test
     */
    public function deleteRemoveOrganisationEventAsGuest401()
    {
        $organisationEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('DELETE', "/core/v1/events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function deleteRemoveOrganisationEventAsServiceWorker403()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceWorker($service);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('DELETE', "/core/v1/events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function deleteRemoveOrganisationEventAsServiceAdmin403()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('DELETE', "/core/v1/events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function deleteRemoveOrganisationEventAsOrganisationAdmin200()
    {
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('DELETE', "/core/v1/events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing((new OrganisationEvent())->getTable(), ['id' => $organisationEvent->id]);
    }

    /**
     * @test
     */
    public function deleteRemoveOrganisationEventAsGlobalAdmin200()
    {
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeGlobalAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('DELETE', "/core/v1/events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing((new OrganisationEvent())->getTable(), ['id' => $organisationEvent->id]);
    }

    /**
     * @test
     */
    public function deleteRemoveOrganisationEventAsSuperAdmin200()
    {
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeSuperAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('DELETE', "/core/v1/events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing((new OrganisationEvent())->getTable(), ['id' => $organisationEvent->id]);
    }

    /**
     * @test
     */
    public function deleteRemoveOrganisationEventAsOrganisationAdminCreatesAudit200()
    {
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('DELETE', "/core/v1/events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_OK);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $organisationEvent) {
            return ($event->getAction() === Audit::ACTION_DELETE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $organisationEvent->id);
        });
    }
}
