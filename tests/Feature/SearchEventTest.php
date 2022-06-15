<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Location;
use App\Models\Organisation;
use App\Models\OrganisationEvent;
use App\Models\Taxonomy;
use Illuminate\Http\Response;
use Tests\TestCase;
use Tests\UsesElasticsearch;

class SearchEventTest extends TestCase implements UsesElasticsearch
{
    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateTaxonomies();
        $this->truncateCollectionCategories();
        $this->truncateCollectionPersonas();
    }

    /*
     * Perform a search for events.
     */

    /**
     * @test
     */
    public function searchEventsAsGuest()
    {
        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'test',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * @test
     */
    public function searchEventsMatchTitle()
    {
        $event = factory(OrganisationEvent::class)->create();

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => $event->title,
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $event->id,
        ]);
    }

    /**
     * @test
     */
    public function searchEventsMatchSingleWordFromTitle()
    {
        $event = factory(OrganisationEvent::class)->create([
            'title' => 'Quick Brown Fox',
        ]);

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'brown',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $event->id]);
    }

    /**
     * @test
     */
    public function searchEventsMatchMultipleWordsFromTitle()
    {
        $event = factory(OrganisationEvent::class)->create([
            'title' => 'Quick Brown Fox',
        ]);

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'quick fox',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $event->id]);
    }

    /**
     * @test
     */
    public function searchEventsMatchIntro()
    {
        $event = factory(OrganisationEvent::class)->create();

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => $event->intro,
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $event->id,
        ]);
    }

    /**
     * @test
     */
    public function searchEventsMatchSingleWordFromIntro()
    {
        $event = factory(OrganisationEvent::class)->create([
            'intro' => 'This is an event that helps to homeless find temporary housing.',
        ]);

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'homeless',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $event->id]);
    }

    /**
     * @test
     */
    public function searchEventsMatchMultipleWordsFromIntro()
    {
        $event = factory(OrganisationEvent::class)->create([
            'intro' => 'This is an event that helps to homeless find temporary housing.',
        ]);

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'temporary housing',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $event->id]);
    }

    /**
     * @test
     */
    public function searchEventsMatchDescription()
    {
        $event = factory(OrganisationEvent::class)->create();

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => $event->description,
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $event->id,
        ]);
    }

    /**
     * @test
     */
    public function searchEventsMatchSingleWordFromDescription()
    {
        $event = factory(OrganisationEvent::class)->create([
            'description' => '<p>This is an event that helps to homeless find temporary housing.</p>',
        ]);

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'homeless',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $event->id]);
    }

    /**
     * @test
     */
    public function searchEventsMatchMultipleWordsFromDescription()
    {
        $event = factory(OrganisationEvent::class)->create([
            'description' => '<p>This is an event that helps to homeless find temporary housing.</p>',
        ]);

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'temporary housing',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $event->id]);
    }

    /**
     * @test
     */
    public function searchEventsMatchCategoryName()
    {
        $event1 = factory(OrganisationEvent::class)->create([
            'title' => 'Event title',
        ]);
        $taxonomy1 = Taxonomy::category()->children()->create([
            'name' => 'Quick Brown Fox',
            'order' => 1,
            'depth' => 1,
        ]);
        $event1->organisationEventTaxonomies()->create(['taxonomy_id' => $taxonomy1->id]);
        $event1->save();

        $event2 = factory(OrganisationEvent::class)->create([
            'title' => 'Event title',
        ]);
        $taxonomy2 = Taxonomy::category()->children()->create([
            'name' => 'Lazy Dog',
            'order' => 1,
            'depth' => 1,
        ]);
        $event2->organisationEventTaxonomies()->create(['taxonomy_id' => $taxonomy2->id]);
        $event2->save();

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'Quick Brown Fox',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $event1->id]);
        $response->assertJsonMissing(['id' => $event2->id]);

        // Fuzzy
        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'Foxy Brown',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $event1->id]);
        $response->assertJsonMissing(['id' => $event2->id]);
    }

    /**
     * @test
     */
    public function searchEventsRankTitleAboveIntro()
    {
        $event1 = factory(OrganisationEvent::class)->create(['title' => 'Thisisatest']);
        $event2 = factory(OrganisationEvent::class)->create([
            'intro' => 'Thisisatest',
        ]);

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'Thisisatest',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $event1->id,
        ]);

        $response->assertJsonFragment([
            'id' => $event2->id,
        ]);

        $data = $this->getResponseContent($response)['data'];
        $this->assertEquals($event1->id, $data[0]['id']);
        $this->assertEquals($event2->id, $data[1]['id']);
    }

    /**
     * @test
     */
    public function searchEventsRankTitleAboveDescription()
    {
        $event1 = factory(OrganisationEvent::class)->create(['title' => 'Thisisatest']);
        $event2 = factory(OrganisationEvent::class)->create([
            'description' => '<p>Thisisatest</p>',
        ]);

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'Thisisatest',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $event1->id,
        ]);

        $response->assertJsonFragment([
            'id' => $event2->id,
        ]);

        $data = $this->getResponseContent($response)['data'];
        $this->assertEquals($event1->id, $data[0]['id']);
        $this->assertEquals($event2->id, $data[1]['id']);
    }

    /**
     * @test
     */
    public function searchEventsRankIntroAboveDescription()
    {
        $event1 = factory(OrganisationEvent::class)->create(['intro' => 'Thisisatest']);
        $event2 = factory(OrganisationEvent::class)->create([
            'description' => '<p>Thisisatest</p>',
        ]);

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'Thisisatest',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $event1->id,
        ]);

        $response->assertJsonFragment([
            'id' => $event2->id,
        ]);

        $data = $this->getResponseContent($response)['data'];
        $this->assertEquals($event1->id, $data[0]['id']);
        $this->assertEquals($event2->id, $data[1]['id']);
    }

    /**
     * @test
     */
    public function searchEventsRankPerfectMatchAboveCloseMatch()
    {
        $event1 = factory(OrganisationEvent::class)->create(['title' => 'Thisisatest']);
        $event2 = factory(OrganisationEvent::class)->create(['title' => 'Thsiisatst']);

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'Thisisatest',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $event1->id,
        ]);

        $response->assertJsonFragment([
            'id' => $event2->id,
        ]);

        $data = $this->getResponseContent($response)['data'];
        $this->assertEquals($event1->id, $data[0]['id']);
        $this->assertEquals($event2->id, $data[1]['id']);
    }

    /**
     * @test
     */
    public function searchEventsRankDescriptionAboveCategoryName()
    {
        $event1 = factory(OrganisationEvent::class)->create([
            'description' => '<p>Quick Brown Fox</p>',
        ]);
        $taxonomy1 = Taxonomy::category()->children()->create([
            'name' => 'Lazy Dog',
            'order' => 1,
            'depth' => 1,
        ]);
        $event1->organisationEventTaxonomies()->create(['taxonomy_id' => $taxonomy1->id]);
        $event1->save();

        $event2 = factory(OrganisationEvent::class)->create([
            'description' => '<p>Lazy Dog</p>',
        ]);
        $taxonomy2 = Taxonomy::category()->children()->create([
            'name' => 'Quick Brown Fox',
            'order' => 1,
            'depth' => 1,
        ]);
        $event2->organisationEventTaxonomies()->create(['taxonomy_id' => $taxonomy2->id]);
        $event2->save();

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'Quick Brown Fox',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['id' => $event1->id]);
        $response->assertJsonFragment(['id' => $event2->id]);

        $data = $this->getResponseContent($response)['data'];
        $this->assertEquals($event1->id, $data[0]['id']);
        $this->assertEquals($event2->id, $data[1]['id']);

        // Fuzzy
        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'Foxy Brown',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['id' => $event1->id]);
        $response->assertJsonFragment(['id' => $event2->id]);

        $data = $this->getResponseContent($response)['data'];
        $this->assertEquals($event1->id, $data[0]['id']);
        $this->assertEquals($event2->id, $data[1]['id']);
    }

    /**
     * @test
     */
    public function searchEventsFilterByCollectionName()
    {
        $event1 = factory(OrganisationEvent::class)->create([
            'title' => 'Event title',
        ]);
        $collection1 = Collection::create([
            'type' => Collection::TYPE_ORGANISATION_EVENT,
            'name' => 'Quick Brown Fox',
            'meta' => [],
            'order' => 1,
        ]);
        $taxonomy1 = Taxonomy::category()->children()->create([
            'name' => 'Category 1',
            'order' => 1,
            'depth' => 1,
        ]);
        $collection1->collectionTaxonomies()->create(['taxonomy_id' => $taxonomy1->id]);
        $event1->organisationEventTaxonomies()->create(['taxonomy_id' => $taxonomy1->id]);
        $event1->save();

        $event2 = factory(OrganisationEvent::class)->create([
            'title' => 'Event title',
        ]);
        $collection2 = Collection::create([
            'type' => Collection::TYPE_ORGANISATION_EVENT,
            'name' => 'Lazy Dog',
            'meta' => [],
            'order' => 1,
        ]);
        $taxonomy2 = Taxonomy::category()->children()->create([
            'name' => 'Category 2',
            'order' => 1,
            'depth' => 1,
        ]);
        $collection2->collectionTaxonomies()->create(['taxonomy_id' => $taxonomy2->id]);
        $event2->organisationEventTaxonomies()->create(['taxonomy_id' => $taxonomy2->id]);
        $event2->save();

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'Event title',
            'category' => 'Quick Brown Fox',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $event1->id]);
        $response->assertJsonMissing(['id' => $event2->id]);

        // Fuzzy
        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'Title Events',
            'category' => 'Quick Brown Fox',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $event1->id]);
        $response->assertJsonMissing(['id' => $event2->id]);
    }

    /**
     * @test
     */
    public function searchEventsFilterByisFree()
    {
        $paidEvent = factory(OrganisationEvent::class)->states('nonFree')->create();
        $freeEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('POST', '/core/v1/search/events', [
            'is_free' => true,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $freeEvent->id]);
        $response->assertJsonMissing(['id' => $paidEvent->id]);
    }

    /**
     * @test
     */
    public function searchEventsFilterByNotisFree()
    {
        $paidEvent = factory(OrganisationEvent::class)->states('nonFree')->create();
        $freeEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('POST', '/core/v1/search/events', [
            'is_free' => false,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonMissing(['id' => $freeEvent->id]);
        $response->assertJsonFragment(['id' => $paidEvent->id]);
    }

    /**
     * @test
     */
    public function searchEventsFilterByisVirtual()
    {
        $locatedEvent = factory(OrganisationEvent::class)->states('notVirtual')->create();
        $virtualEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('POST', '/core/v1/search/events', [
            'is_virtual' => true,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $virtualEvent->id]);
        $response->assertJsonMissing(['id' => $locatedEvent->id]);
    }

    /**
     * @test
     */
    public function searchEventsFilterByNotisVirtual()
    {
        $locatedEvent = factory(OrganisationEvent::class)->states('notVirtual')->create();
        $virtualEvent = factory(OrganisationEvent::class)->create();

        $response = $this->json('POST', '/core/v1/search/events', [
            'is_virtual' => false,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonMissing(['id' => $virtualEvent->id]);
        $response->assertJsonFragment(['id' => $locatedEvent->id]);
    }

    /**
     * @test
     */
    public function searchEventsFilterByHasWheelchair()
    {
        $locatedEvent = factory(OrganisationEvent::class)->states('notVirtual')->create();
        $virtualEvent = factory(OrganisationEvent::class)->create();
        $locatedEventWheelchairAccess = factory(OrganisationEvent::class)->create([
            'is_virtual' => false,
            'location_id' => function () {
                return factory(Location::class)->create([
                    'has_wheelchair_access' => true,
                ])->id;
            },
        ]);

        $response = $this->json('POST', '/core/v1/search/events', [
            'has_wheelchair_access' => true,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $locatedEventWheelchairAccess->id]);
        $response->assertJsonMissing(['id' => $virtualEvent->id]);
        $response->assertJsonMissing(['id' => $locatedEvent->id]);
    }

    /**
     * @test
     */
    public function searchEventsFilterByHasInductionLoop()
    {
        $locatedEvent = factory(OrganisationEvent::class)->states('notVirtual')->create();
        $virtualEvent = factory(OrganisationEvent::class)->create();
        $locatedEventInductionLoop = factory(OrganisationEvent::class)->create([
            'is_virtual' => false,
            'location_id' => function () {
                return factory(Location::class)->create([
                    'has_induction_loop' => true,
                ])->id;
            },
        ]);

        $response = $this->json('POST', '/core/v1/search/events', [
            'has_induction_loop' => true,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $locatedEventInductionLoop->id]);
        $response->assertJsonMissing(['id' => $virtualEvent->id]);
        $response->assertJsonMissing(['id' => $locatedEvent->id]);
    }

    /**
     * @test
     */
    public function searchEventsOnlyFutureDatesReturned()
    {
        $futureEvent = factory(OrganisationEvent::class)->create([
            'title' => 'Testing Dates',
            'start_date' => $this->faker->dateTimeBetween('+1 week', '+2 weeks'),
            'end_date' => $this->faker->dateTimeBetween('+2 week', '+3 weeks'),
        ]);
        $pastEvent = factory(OrganisationEvent::class)->create([
            'title' => 'Testing Dates',
            'start_date' => $this->faker->dateTimeBetween('-3 week', '-2 weeks'),
            'end_date' => $this->faker->dateTimeBetween('-2 week', '-1 weeks'),
        ]);

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'Testing Dates',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $futureEvent->id]);
        $response->assertJsonMissing(['id' => $pastEvent->id]);
    }

    /**
     * @test
     */
    public function searchEventsFilterByStartsAfter()
    {
        $date1 = $this->faker->dateTimeBetween('+3 days', '+1 weeks');
        $date2 = $this->faker->dateTimeBetween('+2 week', '+3 weeks');
        $date3 = $this->faker->dateTimeBetween('+1 days', '+2 days');
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

        $from = clone $date1;
        $from->modify('-1 day');

        $response = $this->json('POST', '/core/v1/search/events', [
            'starts_after' => $from->format('Y-m-d'),
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonFragment(['id' => $organisationEvent2->id]);
        $response->assertJsonMissing(['id' => $organisationEvent3->id]);
    }

    /**
     * @test
     */
    public function searchEventsFilterByEndsBefore()
    {
        $date1 = $this->faker->dateTimeBetween('+3 days', '+1 weeks');
        $date2 = $this->faker->dateTimeBetween('+2 week', '+3 weeks');
        $date3 = $this->faker->dateTimeBetween('+1 days', '+2 days');
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

        $to = clone $date1;
        $to->modify('+1 day');

        $response = $this->json('POST', '/core/v1/search/events', [
            'ends_before' => $to->format('Y-m-d'),
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonFragment(['id' => $organisationEvent3->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
    }

    /**
     * @test
     */
    public function searchEventsFilterByDateRange()
    {
        $date1 = $this->faker->dateTimeBetween('+4 days', '+1 weeks');
        $date2 = $this->faker->dateTimeBetween('+2 week', '+3 weeks');
        $date3 = $this->faker->dateTimeBetween('+1 days', '+2 days');
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

        $from = clone $date1;
        $to = clone $date1;
        $from->modify('-1 day');
        $to->modify('+1 day');

        $response = $this->json('POST', '/core/v1/search/events', [
            'starts_after' => $from->format('Y-m-d'),
            'ends_before' => $to->format('Y-m-d'),
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonMissing(['id' => $organisationEvent3->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
    }

    /**
     * @test
     */
    public function searchEventsOrderByLocationReturnEventsLessThan5MilesAway()
    {
        $event1 = factory(OrganisationEvent::class)->create([
            'is_virtual' => false,
            'location_id' => function () {
                return factory(Location::class)->create([
                    'lat' => 0,
                    'lon' => 0,
                ])->id;
            },
        ]);

        $event2 = factory(OrganisationEvent::class)->create([
            'is_virtual' => false,
            'location_id' => function () {
                return factory(Location::class)->create([
                    'lat' => 45,
                    'lon' => 90,
                ])->id;
            },
        ]);

        $event3 = factory(OrganisationEvent::class)->create([
            'is_virtual' => false,
            'location_id' => function () {
                return factory(Location::class)->create([
                    'lat' => 90,
                    'lon' => 180,
                ])->id;
            },
        ]);

        $response = $this->json('POST', '/core/v1/search/events', [
            'order' => 'distance',
            'location' => [
                'lat' => 45,
                'lon' => 90,
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $event2->id]);
        $response->assertJsonMissing(['id' => $event1->id]);
        $response->assertJsonMissing(['id' => $event3->id]);
    }

    /**
     * @test
     */
    public function searchEventsOrderByLocationReturnServicesLessThan1MileAway()
    {
        // > 1 mile
        $event1 = factory(OrganisationEvent::class)->create([
            'is_virtual' => false,
            'location_id' => function () {
                return factory(Location::class)->create([
                    'lat' => 51.469954129107016, 'lon' => -0.3973609967291171,
                ])->id;
            },
        ]);

        // < 1 mile
        $event2 = factory(OrganisationEvent::class)->create([
            'is_virtual' => false,
            'location_id' => function () {
                return factory(Location::class)->create([
                    'lat' => 51.46813624630186, 'lon' => -0.38543053111827796,
                ])->id;
            },
        ]);

        // > 1 mile
        $event3 = factory(OrganisationEvent::class)->create([
            'is_virtual' => false,
            'location_id' => function () {
                return factory(Location::class)->create([
                    'lat' => 51.47591520714541, 'lon' => -0.41139431461981674,
                ])->id;
            },
        ]);

        $response = $this->json('POST', '/core/v1/search/events', [
            'order' => 'distance',
            'distance' => 1,
            'location' => [
                'lat' => 51.46843366223185,
                'lon' => -0.3674811879751439,
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $event2->id]);
        $response->assertJsonMissing(['id' => $event1->id]);
        $response->assertJsonMissing(['id' => $event3->id]);
    }

    /**
     * @test
     */
    public function searchEventsOrderByRelevanceWithLocationReturnEventsLessThan5MilesAway()
    {
        $event1 = factory(OrganisationEvent::class)->create([
            'is_virtual' => false,
            'location_id' => function () {
                return factory(Location::class)->create([
                    'lat' => 0,
                    'lon' => 0,
                ])->id;
            },
        ]);

        $event2 = factory(OrganisationEvent::class)->create([
            'title' => 'Test Name',
            'is_virtual' => false,
            'location_id' => function () {
                return factory(Location::class)->create([
                    'lat' => 45.001,
                    'lon' => 90.001,
                ])->id;
            },
        ]);

        $event3 = factory(OrganisationEvent::class)->create([
            'is_virtual' => false,
            'location_id' => function () {
                return factory(Location::class)->create([
                    'lat' => 45,
                    'lon' => 90,
                ])->id;
            },
            'organisation_id' => function () {
                return factory(Organisation::class)->create(['name' => 'Test Name'])->id;
            },
        ]);

        $event4 = factory(OrganisationEvent::class)->create([
            'is_virtual' => false,
            'location_id' => function () {
                return factory(Location::class)->create([
                    'lat' => 90,
                    'lon' => 180,
                ])->id;
            },
        ]);

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'Test Name',
            'order' => 'relevance',
            'location' => [
                'lat' => 45,
                'lon' => 90,
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $event2->id]);
        $response->assertJsonFragment(['id' => $event3->id]);
        $response->assertJsonMissing(['id' => $event1->id]);
        $response->assertJsonMissing(['id' => $event4->id]);

        $data = $this->getResponseContent($response)['data'];
        $this->assertEquals(2, count($data));
        $this->assertTrue(in_array($event2->id, [$data[0]['id'], $data[1]['id']]));
        $this->assertTrue(in_array($event3->id, [$data[0]['id'], $data[1]['id']]));
    }

    /**
     * @test
     */
    public function searchEventsOrderByRelevanceWithLocationReturnEventsLessThan1MileAway()
    {
        // Not relevant > 1 mile
        $event1 = factory(OrganisationEvent::class)->create([
            'is_virtual' => false,
            'location_id' => function () {
                return factory(Location::class)->create([
                    'lat' => 51.469954129107016,
                    'lon' => -0.3973609967291171,
                ])->id;
            },
        ]);

        // Relevant < 1 mile
        $event2 = factory(OrganisationEvent::class)->create([
            'intro' => 'Thisisatest',
            'is_virtual' => false,
            'location_id' => function () {
                return factory(Location::class)->create([
                    'lat' => 51.46813624630186,
                    'lon' => -0.38543053111827796,
                ])->id;
            },
        ]);

        // Relevant < 1 mile
        $event3 = factory(OrganisationEvent::class)->create([
            'title' => 'Thisisatest',
            'is_virtual' => false,
            'location_id' => function () {
                return factory(Location::class)->create([
                    'lat' => 51.46933926508632,
                    'lon' => -0.3745729484111921,
                ])->id;
            },
        ]);

        // Relevant > 1 mile
        $event4 = factory(OrganisationEvent::class)->create([
            'title' => 'Thisisatest',
            'is_virtual' => false,
            'location_id' => function () {
                return factory(Location::class)->create([
                    'lat' => 51.46741441979822,
                    'lon' => -0.40152378521657234,
                ])->id;
            },
        ]);

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'Thisisatest',
            'order' => 'relevance',
            'distance' => 1,
            'location' => [
                'lat' => 51.46843366223185,
                'lon' => -0.3674811879751439,
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $event2->id]);
        $response->assertJsonFragment(['id' => $event3->id]);
        $response->assertJsonMissing(['id' => $event1->id]);
        $response->assertJsonMissing(['id' => $event4->id]);

        $data = $this->getResponseContent($response)['data'];
        $this->assertEquals(2, count($data));
        $this->assertEquals($event3->id, $data[0]['id']);
        $this->assertEquals($event2->id, $data[1]['id']);
    }

    /**
     * @test
     */
    public function searchEventsMoreTaxonomiesInACategoryCollectionAreMoreRelevant()
    {
        // Create 3 taxonomies
        $taxonomy1 = Taxonomy::category()->children()->create([
            'name' => 'Red',
            'order' => 1,
            'depth' => 1,
        ]);
        $taxonomy2 = Taxonomy::category()->children()->create([
            'name' => 'Blue',
            'order' => 2,
            'depth' => 1,
        ]);
        $taxonomy3 = Taxonomy::category()->children()->create([
            'name' => 'Green',
            'order' => 3,
            'depth' => 1,
        ]);

        // Create a collection
        $collection = Collection::create([
            'type' => Collection::TYPE_ORGANISATION_EVENT,
            'name' => 'Self Help',
            'meta' => [],
            'order' => 1,
        ]);

        // Link the taxonomies to the collection
        $collection->collectionTaxonomies()->create(['taxonomy_id' => $taxonomy1->id]);
        $collection->collectionTaxonomies()->create(['taxonomy_id' => $taxonomy2->id]);
        $collection->collectionTaxonomies()->create(['taxonomy_id' => $taxonomy3->id]);

        // Create 3 events
        $event1 = factory(OrganisationEvent::class)->create([
            'title' => 'Gold Co.',
        ]);
        $event2 = factory(OrganisationEvent::class)->create([
            'title' => 'Silver Co.',
        ]);
        $event3 = factory(OrganisationEvent::class)->create([
            'title' => 'Bronze Co.',
        ]);

        // Link the events to 1, 2 and 3 taxonomies respectively.
        $event1->organisationEventTaxonomies()->create(['taxonomy_id' => $taxonomy1->id]);
        $event1->organisationEventTaxonomies()->create(['taxonomy_id' => $taxonomy2->id]);
        $event1->organisationEventTaxonomies()->create(['taxonomy_id' => $taxonomy3->id]);
        $event1->save(); // Update the Elasticsearch index.

        $event2->organisationEventTaxonomies()->create(['taxonomy_id' => $taxonomy1->id]);
        $event2->organisationEventTaxonomies()->create(['taxonomy_id' => $taxonomy2->id]);
        $event2->save(); // Update the Elasticsearch index.

        $event3->organisationEventTaxonomies()->create(['taxonomy_id' => $taxonomy1->id]);
        $event3->save(); // Update the Elasticsearch index.

        // Assert that when searching by collection, the events with more taxonomies are ranked higher.
        $response = $this->json('POST', '/core/v1/search/events', [
            'category' => $collection->name,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        $content = $this->getResponseContent($response)['data'];
        $this->assertEquals($event1->id, $content[0]['id']);
        $this->assertEquals($event2->id, $content[1]['id']);
        $this->assertEquals($event3->id, $content[2]['id']);
    }

    /**
     * @test
     */
    public function searchEventsReturnsPaginatedResultSet()
    {
        $events = factory(OrganisationEvent::class, 30)->create([
            'title' => 'Testing Page',
        ]);

        $response = $this->json('POST', '/core/v1/search/events', [
            'query' => 'Testing',
            'page' => 1,
            'per_page' => 20,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonStructure([
            "data" => [],
            "links" => [],
            "meta" => [
                "current_page",
                "from",
                "last_page",
                "path",
                "per_page",
                "to",
                "total",
            ],

        ]);
    }
}
