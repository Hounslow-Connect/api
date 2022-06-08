<?php

namespace Tests\Feature;

use App\Models\Collection;
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
