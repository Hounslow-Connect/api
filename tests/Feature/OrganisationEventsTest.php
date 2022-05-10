<?php

namespace Tests\Feature;

use App\Events\EndpointHit;
use App\Models\Audit;
use App\Models\Location;
use App\Models\Organisation;
use App\Models\OrganisationEvent;
use App\Models\Service;
use App\Models\UpdateRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTime;
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

        $response = $this->json('GET', '/core/v1/organisation-events');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCollection([
            'id',
            'title',
            'start_date',
            'end_date',
            'start_time',
            'end_time',
            'intro',
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
            'is_virtual',
            'location_id',
            'organisation_id',
            'created_at',
            'updated_at',
        ]);

        $response->assertJsonFragment([
            'id' => $organisationEvent->id,
            'title' => $organisationEvent->title,
            'start_date' => $organisationEvent->start_date->toDateString(),
            'end_date' => $organisationEvent->end_date->toDateString(),
            'start_time' => $organisationEvent->start_time,
            'end_time' => $organisationEvent->end_time,
            'intro' => $organisationEvent->intro,
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
            'is_virtual' => $organisationEvent->is_virtual,
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

        $response = $this->json('GET', "/core/v1/organisation-events?filter[organisation_id]={$organisationEvent1->organisation_id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
    }

    /**
     * @test
     */
    public function getAllOrganisationEventsFilterByHomepageAsGuest200()
    {
        $organisationEvent1 = factory(OrganisationEvent::class)->states('homepage')->create();
        $organisationEvent2 = factory(OrganisationEvent::class)->create();

        $response = $this->json('GET', "/core/v1/organisation-events?filter[homepage]=1");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
    }

    /**
     * @test
     */
    public function getAllOrganisationEventsOnlyPastEventsAsGuest200()
    {
        $future = $this->faker->dateTimeBetween('+1 week', '+3 weeks')->format('Y-m-d');
        $past = $this->faker->dateTimeBetween('-1 week', '-1 day')->format('Y-m-d');
        $today = (new DateTime('now'))->format('Y-m-d');
        $endtime = $this->faker->time('H:i:s', '+1 hour');
        $starttime = $this->faker->time('H:i:s', 'now');

        $organisationEvent1 = factory(OrganisationEvent::class)->create([
            'start_date' => $future,
            'end_date' => $future,
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);
        $organisationEvent2 = factory(OrganisationEvent::class)->create([
            'start_date' => $past,
            'end_date' => $past,
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);
        $organisationEvent3 = factory(OrganisationEvent::class)->create([
            'start_date' => $today,
            'end_date' => $today,
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);

        $response = $this->json('GET', "/core/v1/organisation-events");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
        $response->assertJsonFragment(['id' => $organisationEvent3->id]);
    }

    /**
     * @test
     */
    public function getAllOrganisationEventsOnlyPastEventsAsOrganisationAdmin200()
    {
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $future = $this->faker->dateTimeBetween('+1 week', '+3 weeks')->format('Y-m-d');
        $past = $this->faker->dateTimeBetween('-1 week', 'now')->format('Y-m-d');
        $today = (new DateTime('now'))->format('Y-m-d');
        $endtime = $this->faker->time('H:i:s', '+1 hour');
        $starttime = $this->faker->time('H:i:s', 'now');
        $organisationEvent1 = factory(OrganisationEvent::class)->create([
            'start_date' => $future,
            'end_date' => $future,
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);
        $organisationEvent2 = factory(OrganisationEvent::class)->create([
            'start_date' => $past,
            'end_date' => $past,
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);
        $organisationEvent3 = factory(OrganisationEvent::class)->create([
            'start_date' => $today,
            'end_date' => $today,
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);

        $response = $this->json('GET', "/core/v1/organisation-events");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonFragment(['id' => $organisationEvent2->id]);
        $response->assertJsonFragment(['id' => $organisationEvent3->id]);
    }

    /**
     * @test
     */
    public function getAllOrganisationEventsFilterByDatesAsGuest200()
    {
        $date1 = $this->faker->dateTimeBetween('+2 days', '+1 weeks');
        $date2 = $this->faker->dateTimeBetween('+2 week', '+3 weeks');
        $date3 = (new DateTime('now'));
        $endtime = $this->faker->time('H:i:s', '+1 hour');
        $starttime = $this->faker->time('H:i:s', 'now');

        $organisationEvent1 = factory(OrganisationEvent::class)->create([
            'start_date' => $date1->format('Y-m-d'),
            'end_date' => $date1->format('Y-m-d'),
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);
        $organisationEvent2 = factory(OrganisationEvent::class)->create([
            'start_date' => $date2->format('Y-m-d'),
            'end_date' => $date2->format('Y-m-d'),
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);
        $organisationEvent3 = factory(OrganisationEvent::class)->create([
            'start_date' => $date3->format('Y-m-d'),
            'end_date' => $date3->format('Y-m-d'),
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);

        $from = $date1->modify('-1 day')->format('Y-m-d');
        $response = $this->json('GET', "/core/v1/organisation-events?filter[ends_after]={$from}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonFragment(['id' => $organisationEvent2->id]);
        $response->assertJsonMissing(['id' => $organisationEvent3->id]);

        $to = $date1->modify('+1 day')->format('Y-m-d');
        $response = $this->json('GET', "/core/v1/organisation-events?filter[ends_before]={$to}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonFragment(['id' => $organisationEvent3->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);

        $response = $this->json('GET', "/core/v1/organisation-events?filter[ends_after]={$from}&filter[ends_before]={$to}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonMissing(['id' => $organisationEvent3->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
    }

    /**
     * @test
     */
    public function getAllOrganisationEventsCreatesAuditAsGuest200()
    {
        $this->fakeEvents();

        $event = factory(OrganisationEvent::class)->create();

        $this->json('GET', '/core/v1/organisation-events');

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
        $response = $this->json('POST', '/core/v1/organisation-events');

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

        $response = $this->json('POST', '/core/v1/organisation-events');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventAsServiceAdmin403()
    {
        $service = factory(Service::class)->create();
        $user = factory(User::class)->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/organisation-events');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventAsOrganisationAdmin201()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $image = Storage::disk('local')->get('/test-data/image.png');
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $imageResponse = $this->json('POST', '/core/v1/files', [
            'is_private' => false,
            'mime_type' => 'image/png',
            'file' => 'data:image/png;base64,' . base64_encode($image),
        ]);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
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
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
            'image_file_id' => $this->getResponseContent($imageResponse, 'data.id'),
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        unset($payload['image_file_id']);
        $payload['has_image'] = true;

        $response->assertJsonFragment($payload);

        $responseData = json_decode($response->getContent())->data;

        // The organisation event is created
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), ['id' => $responseData->id]);

        // And no update request was created
        $this->assertEmpty(UpdateRequest::all());
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

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
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
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        $responseData = json_decode($response->getContent())->data;

        // The organisation event is created
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

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
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
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        $responseData = json_decode($response->getContent())->data;

        // The organisation event is created
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), ['id' => $responseData->id]);

        // And no update request was created
        $this->assertEmpty(UpdateRequest::all());
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventAsOtherOrganisationAdmin403()
    {
        $organisation1 = factory(Organisation::class)->create();
        $organisation2 = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation2);

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
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
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation1->id,
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function postCreateHomepageOrganisationEventAsOrganisationAdmin422()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
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
            'homepage' => true,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function postCreateHomepageOrganisationEventAsGlobalAdmin201()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
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
            'homepage' => true,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        unset($payload['image_file_id']);
        $payload['has_image'] = false;

        $response->assertJsonFragment($payload);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventCreatesAuditAsOrganisationAdmin201()
    {
        $this->fakeEvents();

        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
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
            'homepage' => false,
            'is_virtual' => true,
            'location_id' => null,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $response) {
            return ($event->getAction() === Audit::ACTION_CREATE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $this->getResponseContent($response)['data']['id']);
        });
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventWithImageAsGlobalAdmin201()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $image = Storage::disk('local')->get('/test-data/image.png');
        $user = factory(User::class)->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $imageResponse = $this->json('POST', '/core/v1/files', [
            'is_private' => false,
            'mime_type' => 'image/png',
            'file' => 'data:image/png;base64,' . base64_encode($image),
        ]);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
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
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
            'image_file_id' => $this->getResponseContent($imageResponse, 'data.id'),
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        $responseData = json_decode($response->getContent())->data;

        $content = $this->get("/core/v1/organisation-events/{$responseData->id}/image.png")->content();
        $this->assertEquals($image, $content);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventMinimumFieldsAsOrganisationAdmin201()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
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
            'homepage' => false,
            'is_virtual' => true,
            'location_id' => null,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
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

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');

        $response = $this->json('POST', '/core/v1/organisation-events', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/organisation-events', [
            'title' => $this->faker->sentence(3),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/organisation-events', [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/organisation-events', [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/organisation-events', [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/organisation-events', [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/organisation-events', [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/organisation-events', [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
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

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
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
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        $payload['is_free'] = false;

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['fees_url'] = $this->faker->url;

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['fees_text'] = $this->faker->sentence;

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
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

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'organiser_name' => null,
            'organiser_phone' => null,
            'organiser_email' => null,
            'organiser_url' => null,
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence,
            'booking_url' => $this->faker->url,
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        $payload['organiser_name'] = $this->faker->name;

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['organiser_phone'] = random_uk_phone();

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        $payload['organiser_phone'] = null;
        $payload['organiser_email'] = $this->faker->safeEmail;

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        $payload['organiser_email'] = null;
        $payload['organiser_url'] = $this->faker->url;

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
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

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'organiser_name' => $this->faker->name,
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail,
            'organiser_url' => $this->faker->url,
            'booking_title' => null,
            'booking_summary' => null,
            'booking_url' => null,
            'booking_cta' => null,
            'homepage' => false,
            'is_virtual' => true,
            'location_id' => null,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        $payload['booking_title'] = $this->faker->sentence(3);

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['booking_summary'] = $this->faker->sentence;

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['booking_url'] = $this->faker->url;

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['booking_cta'] = $this->faker->words(2, true);

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
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

        $response = $this->json('GET', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment([
            'id' => $organisationEvent->id,
            'title' => $organisationEvent->title,
            'start_date' => $organisationEvent->start_date->toDateString(),
            'end_date' => $organisationEvent->end_date->toDateString(),
            'start_time' => $organisationEvent->start_time,
            'end_time' => $organisationEvent->end_time,
            'intro' => $organisationEvent->intro,
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
        $this->fakeEvents();

        $organisationEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('GET', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_OK);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($organisationEvent) {
            return ($event->getAction() === Audit::ACTION_READ) &&
                ($event->getModel()->id === $organisationEvent->id);
        });
    }

    /**
     * @test
     */
    public function getSingleOrganisationEventImageAsGuest200()
    {
        $organisationEvent = factory(OrganisationEvent::class)->states('withImage')->create();

        $response = $this->get("/core/v1/organisation-events/{$organisationEvent->id}/image.png");

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

        $response = $this->get("/core/v1/organisation-events/{$organisationEvent->id}/image.png");

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

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}");

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

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
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

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
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

        $organisationEvent = factory(OrganisationEvent::class)->create([
            'organisation_id' => $organisation->id,
        ]);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
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
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment($payload);

        $globalAdminUser = factory(User::class)->create()->makeGlobalAdmin();

        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_ORGANISATION_EVENT,
            'updateable_id' => $organisationEvent->id,
        ]);

        $data = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_ORGANISATION_EVENT)
            ->where('updateable_id', $organisationEvent->id)
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
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAutoApprovedAsGlobalAdmin200()
    {
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create();

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
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
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'image_file_id' => null,
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        // The organisation event is updated
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), array_merge(['id' => $organisationEvent->id], $payload));

        $data = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_ORGANISATION_EVENT)
            ->where('updateable_id', $organisationEvent->id)
            ->firstOrFail()
            ->data;
        $this->assertEquals($data, $payload);

        // Simulate frontend check by making call with UpdateRequest ID.
        $updateRequestId = json_decode($response->getContent())->id;
        Passport::actingAs($user);

        $updateRequestCheckResponse = $this->get(
            route(
                'core.v1.update-requests.show',
                ['update_request' => $updateRequestId]
            )
        );

        $updateRequestCheckResponse->assertSuccessful();
        $updateRequestResponseData = json_decode($updateRequestCheckResponse->getContent());

        // Update request should already have been approved.
        $this->assertNotNull($updateRequestResponseData->approved_at);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAutoApprovedAsSuperAdmin200()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeSuperAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create();

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
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
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        // The organisation event is updated
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), array_merge(['id' => $organisationEvent->id], $payload));

        $data = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_ORGANISATION_EVENT)
            ->where('updateable_id', $organisationEvent->id)
            ->firstOrFail()
            ->data;
        $this->assertEquals($data, $payload);

        // Simulate frontend check by making call with UpdateRequest ID.
        $updateRequestId = json_decode($response->getContent())->id;
        Passport::actingAs($user);

        $updateRequestCheckResponse = $this->get(
            route(
                'core.v1.update-requests.show',
                ['update_request' => $updateRequestId]
            )
        );

        $updateRequestCheckResponse->assertSuccessful();
        $updateRequestResponseData = json_decode($updateRequestCheckResponse->getContent());

        // Update request should already have been approved.
        $this->assertNotNull($updateRequestResponseData->approved_at);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsOrganisationAdminCreatesAudit200()
    {
        $this->fakeEvents();

        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create([
            'organisation_id' => $organisation->id,
        ]);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
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
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

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

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $content = $this->get("/core/v1/organisation-events/{$organisationEvent->id}/image.png")->content();
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

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $organisationEvent = $organisationEvent->fresh();
        $this->assertEquals(null, $organisationEvent->image_file_id);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsOtherOrganisationAdmin403()
    {
        $organisation1 = factory(Organisation::class)->create();
        $organisation2 = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation2);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create([
            'organisation_id' => $organisation1->id,
        ]);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
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
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAddToHomepageAsOrganisationAdmin422()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create([
            'organisation_id' => $organisation->id,
        ]);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
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
            'homepage' => true,
            'is_virtual' => false,
            'location_id' => $location->id,
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAddToHomepageAsGlobalAdmin403()
    {
        $organisation = factory(Organisation::class)->create();
        $location = factory(Location::class)->create();
        $user = factory(User::class)->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create([
            'organisation_id' => $organisation->id,
        ]);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => $this->faker->sentence(3),
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence,
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
            'homepage' => true,
            'is_virtual' => false,
            'location_id' => $location->id,
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        // The organisation event is updated
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), array_merge(['id' => $organisationEvent->id], $payload));

        $data = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_ORGANISATION_EVENT)
            ->where('updateable_id', $organisationEvent->id)
            ->firstOrFail()
            ->data;
        $this->assertEquals($data, $payload);

        // Simulate frontend check by making call with UpdateRequest ID.
        $updateRequestId = json_decode($response->getContent())->id;
        Passport::actingAs($user);

        $updateRequestCheckResponse = $this->get(
            route(
                'core.v1.update-requests.show',
                ['update_request' => $updateRequestId]
            )
        );

        $updateRequestCheckResponse->assertSuccessful();
        $updateRequestResponseData = json_decode($updateRequestCheckResponse->getContent());

        // Update request should already have been approved.
        $this->assertTrue($updateRequestResponseData->data->homepage);
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

        $response = $this->json('DELETE', "/core/v1/organisation-events/{$organisationEvent->id}");

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

        $response = $this->json('DELETE', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
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

        $response = $this->json('DELETE', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function deleteRemoveOrganisationEventAsOrganisationAdmin200()
    {
        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create([
            'organisation_id' => $organisation->id,
        ]);

        $response = $this->json('DELETE', "/core/v1/organisation-events/{$organisationEvent->id}");

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

        $response = $this->json('DELETE', "/core/v1/organisation-events/{$organisationEvent->id}");

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

        $response = $this->json('DELETE', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing((new OrganisationEvent())->getTable(), ['id' => $organisationEvent->id]);
    }

    /**
     * @test
     */
    public function deleteRemoveOrganisationEventAsOrganisationAdminCreatesAudit200()
    {
        $this->fakeEvents();

        $organisation = factory(Organisation::class)->create();
        $user = factory(User::class)->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = factory(OrganisationEvent::class)->create([
            'organisation_id' => $organisation->id,
        ]);

        $response = $this->json('DELETE', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_OK);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $organisationEvent) {
            return ($event->getAction() === Audit::ACTION_DELETE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $organisationEvent->id);
        });
    }
}
