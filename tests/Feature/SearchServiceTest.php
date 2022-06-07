<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Organisation;
use App\Models\Service;
use App\Models\ServiceLocation;
use App\Models\Taxonomy;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\UsesElasticsearch;

class SearchServiceTest extends TestCase implements UsesElasticsearch
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
     * Perform a search for services.
     */

    public function test_guest_can_search()
    {
        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'test',
        ]);

        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_query_matches_service_name()
    {
        $service1 = factory(Service::class)->create(['name' => 'Thisisatest']);
        $service2 = factory(Service::class)->create(['name' => 'Should not match']);

        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'Thisisatest',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $service1->id,
        ]);

        // Fuzzy match
        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'Thsiisatst',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $service1->id,
        ]);
    }

    public function test_query_matches_service_description()
    {
        $service1 = factory(Service::class)->create(['description' => 'Thisisatest']);
        $service2 = factory(Service::class)->create(['description' => 'Should not match']);

        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'Thisisatest',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $service1->id,
        ]);

        // Fuzzy match
        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'Thsiisatst',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $service1->id,
        ]);
    }

    public function test_query_matches_taxonomy_name()
    {
        $service1 = factory(Service::class)->create();
        $taxonomy = Taxonomy::category()->children()->create([
            'name' => 'Thisisatest',
            'order' => 1,
            'depth' => 1,
        ]);
        $service1->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy->id]);

        $service2 = factory(Service::class)->create();
        $taxonomy = Taxonomy::category()->children()->create([
            'name' => 'Should not match',
            'order' => 1,
            'depth' => 1,
        ]);
        $service2->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy->id]);

        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'Thisisatest',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $service1->id]);

        // Fuzzy
        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'Thsiisatst',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $service1->id]);
    }

    public function test_query_matches_partial_taxonomy_name()
    {
        $service1 = factory(Service::class)->create();
        $taxonomy = Taxonomy::category()->children()->create([
            'name' => 'PHPUnit Taxonomy',
            'order' => 1,
            'depth' => 1,
        ]);
        $service1->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy->id]);

        $service2 = factory(Service::class)->create();
        $taxonomy = Taxonomy::category()->children()->create([
            'name' => 'Should not match',
            'order' => 1,
            'depth' => 1,
        ]);
        $service2->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy->id]);

        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'PHPUnit',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $service1->id]);

        // Fuzzy
        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'HPHUnit',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $service1->id]);
    }

    public function test_query_matches_organisation_name()
    {
        $organisation1 = factory(Organisation::class)->create(['name' => 'Thisisatest']);
        $organisation2 = factory(Organisation::class)->create(['name' => 'Should not match']);
        $service1 = factory(Service::class)->create(['organisation_id' => $organisation1->id]);
        $service2 = factory(Service::class)->create(['organisation_id' => $organisation2->id]);

        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'Thisisatest',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $service1->id,
        ]);

        // Fuzzy
        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'Thsiisatst',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $service1->id]);
    }

    public function test_query_ranks_service_name_equivalent_to_organisation_name()
    {
        $organisation = factory(Organisation::class)->create(['name' => 'Thisisatest']);
        $serviceWithRelevantOrganisationName = factory(Service::class)->create([
            'name' => 'Relevant Organisation',
            'intro' => 'Service Intro',
            'description' => 'Service description',
            'organisation_id' => $organisation->id,
        ]);
        $serviceWithRelevantOrganisationName->save();
        $serviceWithRelevantServiceName = factory(Service::class)->create([
            'name' => 'Thisisatest',
            'intro' => 'Service Intro',
            'description' => 'Service description',
        ]);
        $serviceWithRelevantServiceName->save();
        $serviceWithRelevantIntro = factory(Service::class)->create([
            'name' => 'Relevant Intro',
            'intro' => 'Thisisatest',
            'description' => 'Service description',
        ]);
        $serviceWithRelevantIntro->save();
        $serviceWithRelevantDescription = factory(Service::class)->create([
            'name' => 'Relevant Description',
            'intro' => 'Service Intro',
            'description' => 'Thisisatest',
        ]);
        $serviceWithRelevantDescription->save();
        $serviceWithRelevantTaxonomy = factory(Service::class)->create([
            'name' => 'Relevant Taxonomy',
            'intro' => 'Service Intro',
            'description' => 'Service description',
        ]);
        $taxonomy = Taxonomy::category()->children()->create([
            'name' => 'Thisisatest',
            'order' => 1,
            'depth' => 1,
        ]);
        $serviceWithRelevantTaxonomy->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy->id]);
        $serviceWithRelevantTaxonomy->save();

        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'Thisisatest',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(5, 'data');
        $data = $this->getResponseContent($response)['data'];

        $this->assertTrue(in_array($serviceWithRelevantServiceName->id, [$data[0]['id'], $data[1]['id']]));
        $this->assertTrue(in_array($serviceWithRelevantOrganisationName->id, [$data[0]['id'], $data[1]['id']]));
        $this->assertTrue(in_array($serviceWithRelevantIntro->id, [$data[2]['id'], $data[3]['id']]));
        $this->assertTrue(in_array($serviceWithRelevantDescription->id, [$data[2]['id'], $data[3]['id']]));
        $this->assertEquals($serviceWithRelevantTaxonomy->id, $data[4]['id']);
    }

    public function test_query_ranks_perfect_match_above_fuzzy_match()
    {
        $service1 = factory(Service::class)->create(['name' => 'Thisisatest']);
        $service2 = factory(Service::class)->create(['name' => 'Thsiisatst']);

        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'Thisisatest',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $service1->id,
        ]);

        $response->assertJsonFragment([
            'id' => $service2->id,
        ]);

        $data = $this->getResponseContent($response)['data'];
        $this->assertEquals($service1->id, $data[0]['id']);
        $this->assertEquals($service2->id, $data[1]['id']);
    }

    public function test_query_matches_service_intro()
    {
        $service1 = factory(Service::class)->create([
            'intro' => 'This is a service that helps the homeless find temporary housing.',
        ]);

        $service2 = factory(Service::class)->create([
            'intro' => 'This is a service that helps provide food.',
        ]);

        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'housing',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $service1->id,
        ]);

        // Fuzzy
        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'housign',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $service1->id,
        ]);
    }

    public function test_query_matches_single_word_from_service_description()
    {
        $service = factory(Service::class)->create([
            'description' => 'This is a service that helps to homeless find temporary housing.',
        ]);

        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'homeless',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $service->id]);
    }

    public function test_query_matches_multiple_words_from_service_description()
    {
        $service1 = factory(Service::class)->create([
            'description' => 'This is a service that helps to homeless find temporary housing.',
        ]);

        $service2 = factory(Service::class)->create([
            'intro' => 'This is a service that helps provide food.',
        ]);

        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'temporary housing',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $service1->id]);

        // Fuzzy
        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'temprary housign',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $service1->id]);
    }

    public function test_filter_by_category_works()
    {
        $service = factory(Service::class)->create();
        $collection = Collection::create([
            'type' => Collection::TYPE_CATEGORY,
            'name' => 'Self Help',
            'meta' => [],
            'order' => 1,
        ]);
        $taxonomy = Taxonomy::category()->children()->create([
            'name' => 'PHPUnit Taxonomy',
            'order' => 1,
            'depth' => 1,
        ]);
        $collection->collectionTaxonomies()->create(['taxonomy_id' => $taxonomy->id]);
        $service->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy->id]);
        $service->save();

        $response = $this->json('POST', '/core/v1/search', [
            'category' => $collection->name,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $service->id]);
    }

    public function test_filter_by_persona_works()
    {
        $service = factory(Service::class)->create();
        $collection = Collection::create([
            'type' => Collection::TYPE_PERSONA,
            'name' => 'Refugees',
            'meta' => [],
            'order' => 1,
        ]);
        $taxonomy = Taxonomy::category()->children()->create([
            'name' => 'PHPUnit Taxonomy',
            'order' => 1,
            'depth' => 1,
        ]);
        $collection->collectionTaxonomies()->create(['taxonomy_id' => $taxonomy->id]);
        $service->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy->id]);
        $service->save();

        $response = $this->json('POST', '/core/v1/search', [
            'persona' => $collection->name,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $service->id]);
    }

    public function test_filter_by_wait_time_works()
    {
        $oneMonthWaitTimeService = factory(Service::class)->create(['wait_time' => Service::WAIT_TIME_MONTH]);
        $twoWeeksWaitTimeService = factory(Service::class)->create(['wait_time' => Service::WAIT_TIME_TWO_WEEKS]);
        $oneWeekWaitTimeService = factory(Service::class)->create(['wait_time' => Service::WAIT_TIME_ONE_WEEK]);

        $response = $this->json('POST', '/core/v1/search', [
            'wait_time' => Service::WAIT_TIME_TWO_WEEKS,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $oneWeekWaitTimeService->id]);
        $response->assertJsonFragment(['id' => $twoWeeksWaitTimeService->id]);
        $response->assertJsonMissing(['id' => $oneMonthWaitTimeService->id]);
    }

    public function test_filter_by_is_free_works()
    {
        $paidService = factory(Service::class)->create(['is_free' => false]);
        $freeService = factory(Service::class)->create(['is_free' => true]);

        $response = $this->json('POST', '/core/v1/search', [
            'is_free' => true,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $freeService->id]);
        $response->assertJsonMissing(['id' => $paidService->id]);
    }

    public function test_order_by_location_works()
    {
        $service = factory(Service::class)->create();
        $serviceLocation = factory(ServiceLocation::class)->create(['service_id' => $service->id]);
        DB::table('locations')->where('id', $serviceLocation->location->id)->update(['lat' => 19.9, 'lon' => 19.9]);
        $service->save();

        $service2 = factory(Service::class)->create();
        $serviceLocation2 = factory(ServiceLocation::class)->create(['service_id' => $service2->id]);
        DB::table('locations')->where('id', $serviceLocation2->location->id)->update(['lat' => 20, 'lon' => 20]);
        $service2->save();

        $service3 = factory(Service::class)->create();
        $serviceLocation3 = factory(ServiceLocation::class)->create(['service_id' => $service3->id]);
        DB::table('locations')->where('id', $serviceLocation3->location->id)->update(['lat' => 20.15, 'lon' => 20.15]);
        $service3->save();

        $response = $this->json('POST', '/core/v1/search', [
            'order' => 'distance',
            'location' => [
                'lat' => 20,
                'lon' => 20,
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $service2->id]);
        $hits = json_decode($response->getContent(), true)['data'];
        $this->assertEquals($service2->id, $hits[0]['id']);
        $this->assertEquals($service->id, $hits[1]['id']);
        $this->assertEquals($service3->id, $hits[2]['id']);
    }

    public function test_query_and_filter_works()
    {
        $service = factory(Service::class)->create(['name' => 'Ayup Digital']);
        $collection = Collection::create([
            'type' => Collection::TYPE_CATEGORY,
            'name' => 'Self Help',
            'meta' => [],
            'order' => 1,
        ]);
        $taxonomy = Taxonomy::category()->children()->create([
            'name' => 'Collection',
            'order' => 1,
            'depth' => 1,
        ]);
        $collectionTaxonomy = $collection->collectionTaxonomies()->create(['taxonomy_id' => $taxonomy->id]);
        $service->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy->id]);
        $service->save();

        $differentService = factory(Service::class)->create(['name' => 'Ayup Digital']);
        $differentCollection = Collection::create([
            'type' => Collection::TYPE_PERSONA,
            'name' => 'Refugees',
            'meta' => [],
            'order' => 1,
        ]);
        $differentTaxonomy = Taxonomy::category()->children()->create([
            'name' => 'Persona',
            'order' => 2,
            'depth' => 1,
        ]);
        $differentCollection->collectionTaxonomies()->create(['taxonomy_id' => $differentTaxonomy->id]);
        $differentService->serviceTaxonomies()->create(['taxonomy_id' => $differentTaxonomy->id]);
        $differentService->save();

        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'Ayup Digital',
            'category' => $collectionTaxonomy->collection->name,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $service->id]);
        $response->assertJsonMissing(['id' => $differentService->id]);
    }

    public function test_query_and_filter_works_when_query_does_not_match()
    {
        $service = factory(Service::class)->create(['name' => 'Ayup Digital']);
        $collection = Collection::create([
            'type' => Collection::TYPE_CATEGORY,
            'name' => 'Self Help',
            'meta' => [],
            'order' => 1,
        ]);
        $taxonomy = Taxonomy::category()->children()->create([
            'name' => 'Collection',
            'order' => 1,
            'depth' => 1,
        ]);
        $collectionTaxonomy = $collection->collectionTaxonomies()->create(['taxonomy_id' => $taxonomy->id]);
        $service->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy->id]);
        $service->save();

        $differentService = factory(Service::class)->create(['name' => 'Ayup Digital']);
        $differentCollection = Collection::create([
            'type' => Collection::TYPE_PERSONA,
            'name' => 'Refugees',
            'meta' => [],
            'order' => 1,
        ]);
        $differentTaxonomy = Taxonomy::category()->children()->create([
            'name' => 'Persona',
            'order' => 2,
            'depth' => 1,
        ]);
        $differentCollection->collectionTaxonomies()->create(['taxonomy_id' => $differentTaxonomy->id]);
        $differentService->serviceTaxonomies()->create(['taxonomy_id' => $differentTaxonomy->id]);
        $differentService->save();

        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'asfkjbadsflksbdafklhasdbflkbs',
            'category' => $collectionTaxonomy->collection->name,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $service->id]);
        $response->assertJsonMissing(['id' => $differentService->id]);
    }

    public function test_only_active_services_returned()
    {
        $activeService = factory(Service::class)->create([
            'name' => 'Testing Service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $inactiveService = factory(Service::class)->create([
            'name' => 'Testing Service',
            'status' => Service::STATUS_INACTIVE,
        ]);

        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'Testing Service',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $activeService->id]);
        $response->assertJsonMissing(['id' => $inactiveService->id]);
    }

    public function test_order_by_location_return_services_less_than_15_miles_away()
    {
        $service1 = factory(Service::class)->create();
        $serviceLocation = factory(ServiceLocation::class)->create(['service_id' => $service1->id]);
        DB::table('locations')->where('id', $serviceLocation->location->id)->update(['lat' => 0, 'lon' => 0]);
        $service1->save();

        $service2 = factory(Service::class)->create();
        $serviceLocation2 = factory(ServiceLocation::class)->create(['service_id' => $service2->id]);
        DB::table('locations')->where('id', $serviceLocation2->location->id)->update(['lat' => 45, 'lon' => 90]);
        $service2->save();

        $service3 = factory(Service::class)->create();
        $serviceLocation3 = factory(ServiceLocation::class)->create(['service_id' => $service3->id]);
        DB::table('locations')->where('id', $serviceLocation3->location->id)->update(['lat' => 90, 'lon' => 180]);
        $service3->save();

        $response = $this->json('POST', '/core/v1/search', [
            'order' => 'distance',
            'location' => [
                'lat' => 45,
                'lon' => 90,
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $service2->id]);
        $response->assertJsonMissing(['id' => $service1->id]);
        $response->assertJsonMissing(['id' => $service3->id]);
    }

    public function test_order_by_location_return_services_less_than_1_mile_away()
    {
        // > 1 mile
        $service1 = factory(Service::class)->create();
        $serviceLocation = factory(ServiceLocation::class)->create(['service_id' => $service1->id]);
        DB::table('locations')->where('id', $serviceLocation->location->id)->update(['lat' => 51.469954129107016, 'lon' => -0.3973609967291171]);
        $service1->save();

        // < 1 mile
        $service2 = factory(Service::class)->create();
        $serviceLocation2 = factory(ServiceLocation::class)->create(['service_id' => $service2->id]);
        DB::table('locations')->where('id', $serviceLocation2->location->id)->update(['lat' => 51.46813624630186, 'lon' => -0.38543053111827796]);
        $service2->save();

        // > 1 mile
        $service3 = factory(Service::class)->create();
        $serviceLocation3 = factory(ServiceLocation::class)->create(['service_id' => $service3->id]);
        DB::table('locations')->where('id', $serviceLocation3->location->id)->update(['lat' => 51.47591520714541, 'lon' => -0.41139431461981674]);
        $service3->save();

        $response = $this->json('POST', '/core/v1/search', [
            'order' => 'distance',
            'distance' => 1,
            'location' => [
                'lat' => 51.46843366223185,
                'lon' => -0.3674811879751439,
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $service2->id]);
        $response->assertJsonMissing(['id' => $service1->id]);
        $response->assertJsonMissing(['id' => $service3->id]);
    }

    public function test_order_by_relevance_with_location_return_services_less_than_15_miles_away()
    {
        $service1 = factory(Service::class)->create();
        $serviceLocation = factory(ServiceLocation::class)->create(['service_id' => $service1->id]);
        DB::table('locations')->where('id', $serviceLocation->location->id)->update(['lat' => 0, 'lon' => 0]);
        $service1->save();

        $service2 = factory(Service::class)->create(['name' => 'Test Name']);
        $serviceLocation2 = factory(ServiceLocation::class)->create(['service_id' => $service2->id]);
        DB::table('locations')->where('id', $serviceLocation2->location->id)->update(['lat' => 45.01, 'lon' => 90.01]);
        $service2->save();

        $organisation3 = factory(Organisation::class)->create(['name' => 'Test Name']);
        $service3 = factory(Service::class)->create(['organisation_id' => $organisation3->id]);
        $serviceLocation3 = factory(ServiceLocation::class)->create(['service_id' => $service3->id]);
        DB::table('locations')->where('id', $serviceLocation3->location->id)->update(['lat' => 45, 'lon' => 90]);
        $service3->save();

        $service4 = factory(Service::class)->create();
        $serviceLocation4 = factory(ServiceLocation::class)->create(['service_id' => $service4->id]);
        DB::table('locations')->where('id', $serviceLocation4->location->id)->update(['lat' => 90, 'lon' => 180]);
        $service4->save();

        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'Test Name',
            'order' => 'relevance',
            'location' => [
                'lat' => 45,
                'lon' => 90,
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $service2->id]);
        $response->assertJsonFragment(['id' => $service3->id]);
        $response->assertJsonMissing(['id' => $service1->id]);
        $response->assertJsonMissing(['id' => $service4->id]);

        $data = $this->getResponseContent($response)['data'];
        $this->assertEquals(2, count($data));
        $this->assertTrue(in_array($service2->id, [$data[0]['id'], $data[1]['id']]));
        $this->assertTrue(in_array($service3->id, [$data[0]['id'], $data[1]['id']]));
    }

    public function test_order_by_relevance_with_location_return_services_less_than_1_mile_away()
    {
        // Not relevant > 1 mile
        $service1 = factory(Service::class)->create();
        $serviceLocation = factory(ServiceLocation::class)->create(['service_id' => $service1->id]);
        DB::table('locations')->where('id', $serviceLocation->location->id)->update(['lat' => 51.469954129107016, 'lon' => -0.3973609967291171]);
        $service1->save();

        // Relevant < 1 mile
        $service2 = factory(Service::class)->create(['intro' => 'Thisisatest']);
        $serviceLocation2 = factory(ServiceLocation::class)->create(['service_id' => $service2->id]);
        DB::table('locations')->where('id', $serviceLocation2->location->id)->update(['lat' => 51.46813624630186, 'lon' => -0.38543053111827796]);
        $service2->save();

        // Relevant < 1 mile
        $organisation3 = factory(Organisation::class)->create(['name' => 'Thisisatest']);
        $service3 = factory(Service::class)->create(['organisation_id' => $organisation3->id]);
        $serviceLocation3 = factory(ServiceLocation::class)->create(['service_id' => $service3->id]);
        DB::table('locations')->where('id', $serviceLocation3->location->id)->update(['lat' => 51.46933926508632, 'lon' => -0.3745729484111921]);
        $service3->save();

        // Relevant > 1 mile
        $service4 = factory(Service::class)->create(['name' => 'Thisisatest']);
        $serviceLocation4 = factory(ServiceLocation::class)->create(['service_id' => $service4->id]);
        DB::table('locations')->where('id', $serviceLocation4->location->id)->update(['lat' => 51.46741441979822, 'lon' => -0.40152378521657234]);
        $service4->save();

        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'Thisisatest',
            'order' => 'relevance',
            'distance' => 1,
            'location' => [
                'lat' => 51.46843366223185,
                'lon' => -0.3674811879751439,
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $service2->id]);
        $response->assertJsonFragment(['id' => $service3->id]);
        $response->assertJsonMissing(['id' => $service1->id]);
        $response->assertJsonMissing(['id' => $service4->id]);

        $data = $this->getResponseContent($response)['data'];
        $this->assertEquals(2, count($data));
        $this->assertEquals($service3->id, $data[0]['id']);
        $this->assertEquals($service2->id, $data[1]['id']);
    }

    public function test_services_with_more_taxonomies_in_a_category_collection_are_more_relevant()
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
            'type' => Collection::TYPE_CATEGORY,
            'name' => 'Self Help',
            'meta' => [],
            'order' => 1,
        ]);

        // Link the taxonomies to the collection
        $collection->collectionTaxonomies()->create(['taxonomy_id' => $taxonomy1->id]);
        $collection->collectionTaxonomies()->create(['taxonomy_id' => $taxonomy2->id]);
        $collection->collectionTaxonomies()->create(['taxonomy_id' => $taxonomy3->id]);

        // Create 3 services
        $service1 = factory(Service::class)->create(['name' => 'Gold Co.']);
        $service2 = factory(Service::class)->create(['name' => 'Silver Co.']);
        $service3 = factory(Service::class)->create(['name' => 'Bronze Co.']);

        // Link the services to 1, 2 and 3 taxonomies respectively.
        $service1->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy1->id]);
        $service1->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy2->id]);
        $service1->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy3->id]);
        $service1->save(); // Update the Elasticsearch index.

        $service2->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy1->id]);
        $service2->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy2->id]);
        $service2->save(); // Update the Elasticsearch index.

        $service3->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy1->id]);
        $service3->save(); // Update the Elasticsearch index.

        // Assert that when searching by collection, the services with more taxonomies are ranked higher.
        $response = $this->json('POST', '/core/v1/search', [
            'category' => $collection->name,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        $content = $this->getResponseContent($response)['data'];
        $this->assertEquals($service1->id, $content[0]['id']);
        $this->assertEquals($service2->id, $content[1]['id']);
        $this->assertEquals($service3->id, $content[2]['id']);
    }

    public function test_services_with_more_taxonomies_in_a_persona_collection_are_more_relevant()
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
            'type' => Collection::TYPE_PERSONA,
            'name' => 'Self Help',
            'meta' => [],
            'order' => 1,
        ]);

        // Link the taxonomies to the collection
        $collection->collectionTaxonomies()->create(['taxonomy_id' => $taxonomy1->id]);
        $collection->collectionTaxonomies()->create(['taxonomy_id' => $taxonomy2->id]);
        $collection->collectionTaxonomies()->create(['taxonomy_id' => $taxonomy3->id]);

        // Create 3 services
        $service1 = factory(Service::class)->create(['name' => 'Gold Co.']);
        $service2 = factory(Service::class)->create(['name' => 'Silver Co.']);
        $service3 = factory(Service::class)->create(['name' => 'Bronze Co.']);

        // Link the services to 1, 2 and 3 taxonomies respectively.
        $service1->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy1->id]);
        $service1->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy2->id]);
        $service1->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy3->id]);
        $service1->save(); // Update the Elasticsearch index.

        $service2->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy1->id]);
        $service2->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy2->id]);
        $service2->save(); // Update the Elasticsearch index.

        $service3->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy1->id]);
        $service3->save(); // Update the Elasticsearch index.

        // Assert that when searching by collection, the services with more taxonomies are ranked higher.
        $response = $this->json('POST', '/core/v1/search', [
            'persona' => $collection->name,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        $content = $this->getResponseContent($response)['data'];
        $this->assertEquals($service1->id, $content[0]['id']);
        $this->assertEquals($service2->id, $content[1]['id']);
        $this->assertEquals($service3->id, $content[2]['id']);
    }

    public function test_query_matches_eligibility_name()
    {
        // Given a service has an eligibility age group taxonomy of 12 - 15 years
        $service = factory(Service::class)
            ->create();

        $parentTaxonomy = Taxonomy::serviceEligibility()
            ->children()
            ->where(['name' => 'Age Group'])
            ->first();

        $taxonomy = $parentTaxonomy->children()
            ->where(['name' => '12 - 15 years'])
            ->first();

        $service->serviceEligibilities()->create(['taxonomy_id' => $taxonomy->id]);

        // Trigger a reindex
        $service->save();

        // When a search is performed with the age group taxonomies of 12 - 15 years and 16 - 18 years
        $response = $this->json('POST', '/core/v1/search', [
            'eligibilities' => [
                '12 - 15 years',
                '16 - 18 years',
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);

        // Then the results should include the service
        $response->assertJsonFragment(['id' => $service->id]);
    }

    public function test_no_results_when_query_does_not_match_eligibility_name()
    {
        // Given a service has an eligibility age group taxonomy of 12 - 15 years
        $service = factory(Service::class)
            ->create();

        $parentTaxonomy = Taxonomy::serviceEligibility()
            ->children()
            ->where(['name' => 'Age Group'])
            ->first();

        $taxonomy = $parentTaxonomy->children()
            ->where(['name' => '12 - 15 years'])
            ->first();

        $service->serviceEligibilities()->create(['taxonomy_id' => $taxonomy->id]);

        // Trigger a reindex
        $service->save();

        // When a search is performed with the age group taxonomies of 16 - 18 years and 19 - 20 years
        $response = $this->json('POST', '/core/v1/search', [
            'eligibilities' => [
                '16 - 18 years',
                '19 - 20 years',
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonMissing(['id' => $service->id]);
    }

    public function test_service_returned_in_result_if_it_has_no_eligibility_taxonomies_related_to_parent_of_searched_eligibility()
    {
        $service = factory(Service::class)
            ->create();

        $service->save();

        $response = $this->json('POST', '/core/v1/search', [
            'eligibilities' => [
                '16 - 18 years',
                '19 - 20 years',
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $service->id]);
    }

    public function test_query_results_filtered_when_eligibity_filter_applied()
    {
        $serviceEligibility1 = Taxonomy::where('name', '12 - 15 years')->firstOrFail();
        $serviceEligibility2 = Taxonomy::where('name', '16 - 18 years')->firstOrFail();
        $service1 = factory(Service::class)->create(['name' => 'Thisisatest']);
        $service2 = factory(Service::class)->create(['intro' => 'Thisisatest']);
        $service3 = factory(Service::class)->create(['description' => 'Thisisatest']);
        $service4 = factory(Service::class)->create();
        $taxonomy = Taxonomy::category()->children()->create([
            'name' => 'Thisisatest',
            'order' => 1,
            'depth' => 1,
        ]);
        $service4->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy->id]);

        $service1->serviceEligibilities()->create([
            'taxonomy_id' => $serviceEligibility1->id,
        ]);
        $service4->serviceEligibilities()->create([
            'taxonomy_id' => $serviceEligibility1->id,
        ]);
        $service2->serviceEligibilities()->create([
            'taxonomy_id' => $serviceEligibility2->id,
        ]);

        // Reindex
        $service1->save();
        $service2->save();
        $service3->save();
        $service4->save();

        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'Thisisatest',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(4, 'data');

        $response = $this->json('POST', '/core/v1/search', [
            'query' => 'Thisisatest',
            'eligibilities' => [
                '12 - 15 years'],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment(['id' => $service1->id]);
        $response->assertJsonFragment(['id' => $service3->id]);
        $response->assertJsonFragment(['id' => $service4->id]);
        $response->assertJsonMissing(['id' => $service2->id]);
    }

    public function test_service_ranks_higher_if_eligibility_match()
    {
        // Given a service called Alpha Ltd has no eligibility age group taxonomies specified
        $serviceA = factory(Service::class)
            ->create([
                'name' => 'Alpha Ltd',
            ]);

        // And a service called Beta Ltd has an eligibility age group taxonomy of 12 - 15 years
        $serviceB = factory(Service::class)
            ->create([
                'name' => 'Beta Ltd',
            ]);

        $parentTaxonomy = Taxonomy::serviceEligibility()
            ->children()
            ->where(['name' => 'Age Group'])
            ->first();

        $taxonomyB = $parentTaxonomy->children()
            ->where(['name' => '12 - 15 years'])
            ->first();

        $serviceB->serviceEligibilities()->create([
            'taxonomy_id' => $taxonomyB->id,
        ]);

        // Trigger a reindex
        $serviceA->save();
        $serviceB->save();

        // When a search is performed with the age group taxonomies of 12 - 15 years and 16 - 18 years
        $response = $this->json('POST', '/core/v1/search', [
            'eligibilities' => [
                '12 - 15 years',
                '16 - 18 years',
            ],
        ]);

        $data = $this->getResponseContent($response)['data'];

        // Then the results should include both services
        $this->assertEquals(2, count($data));

        // And Beta Ltd should rank higher in the results
        $this->assertEquals($serviceB->id, $data[0]['id']);
        $this->assertEquals($serviceA->id, $data[1]['id']);
    }

    public function test_ranking_for_eligibility_higher_for_single_matching_eligibility_over_multiple_or_all()
    {
        $parentTaxonomy = Taxonomy::serviceEligibility()
            ->children()
            ->where(['name' => 'Age Group'])
            ->first();

        $taxonomyA = $parentTaxonomy->children()
            ->where(['name' => '12 - 15 years'])
            ->first();

        $taxonomyB = $parentTaxonomy->children()
            ->where(['name' => '16 - 18 years'])
            ->first();

        $taxonomyC = $parentTaxonomy->children()
            ->where(['name' => '19 - 20 years'])
            ->first();

        $serviceA = factory(Service::class)
            ->create([
                'name' => 'Single Eligibility',
            ]);

        $serviceA->serviceEligibilities()->create([
            'taxonomy_id' => $taxonomyA->id,
        ]);

        $serviceB = factory(Service::class)
            ->create([
                'name' => 'Double Eligibility',
            ]);

        $serviceB->serviceEligibilities()->createMany([
            ['taxonomy_id' => $taxonomyA->id],
            ['taxonomy_id' => $taxonomyB->id],
        ]);

        $serviceC = factory(Service::class)
            ->create([
                'name' => 'Multiple Eligibility',
            ]);

        $serviceC->serviceEligibilities()->createMany([
            ['taxonomy_id' => $taxonomyA->id],
            ['taxonomy_id' => $taxonomyB->id],
            ['taxonomy_id' => $taxonomyC->id],
        ]);

        $serviceD = factory(Service::class)
            ->create([
                'name' => 'All Eligibilities by default',
            ]);

        $serviceA->save();
        $serviceB->save();
        $serviceC->save();
        $serviceD->save();

        $response = $this->json('POST', '/core/v1/search', [
            'eligibilities' => [
                '12 - 15 years',
            ],
        ]);

        $data = $this->getResponseContent($response)['data'];

        $this->assertEquals(4, count($data));

        $this->assertEquals($serviceA->id, $data[0]['id']);
        $this->assertEquals($serviceB->id, $data[1]['id']);
        $this->assertEquals($serviceC->id, $data[2]['id']);
        $this->assertEquals($serviceD->id, $data[3]['id']);
    }

    public function test_search_ranking_given_more_relevant_matches_versus_less_hits_or_no_eligibilities_attached()
    {
        $parentTaxonomy = Taxonomy::serviceEligibility()
            ->children()
            ->where(['name' => 'Disability'])
            ->first();

        // Given a service called Alpha Ltd has no eligibility taxonomies specified
        $serviceA = factory(Service::class)
            ->create([
                'name' => 'Alpha Ltd',
            ]);

        // And a service called Bravo Ltd has matches one of the eligibility taxonomy terms that we will search for
        $serviceB = factory(Service::class)
            ->create([
                'name' => 'Bravo Ltd',
            ]);

        $taxonomyIdsB = $parentTaxonomy
            ->children()
            ->where(['name' => 'Bipolar disorder'])
            ->first()
            ->id;

        $serviceB->serviceEligibilities()->create(['taxonomy_id' => $taxonomyIdsB]);

        // And a service called Charlie Ltd matches two of the eligibility taxonomy terms that we will search for
        $serviceC = factory(Service::class)
            ->create([
                'name' => 'Charlie Ltd',
            ]);

        $taxonomyIdsC = $parentTaxonomy->children()
            ->whereIn('name', ['Bipolar disorder', 'Multiple sclerosis'])
            ->get()
            ->map(function ($taxonomy) {
                return ['taxonomy_id' => $taxonomy->id];
            });

        $serviceC->serviceEligibilities()->createMany($taxonomyIdsC->toArray());

        // And a service called Delta Ltd matches all of the eligibility taxonomy terms that we will search for
        $serviceD = factory(Service::class)
            ->create([
                'name' => 'Delta Ltd',
            ]);

        $taxonomyIdsD = $parentTaxonomy->children()
            ->whereIn('name', ['Bipolar disorder', 'Multiple sclerosis', 'Schizophrenia'])
            ->get()
            ->map(function ($taxonomy) {
                return ['taxonomy_id' => $taxonomy->id];
            });

        $serviceD->serviceEligibilities()->createMany($taxonomyIdsD->toArray());

        // And a Service called Inactive Service that matches all of the eligiblity taxonomies but is inactive
        $serviceIA = factory(Service::class)
            ->create([
                'name' => 'Inactive Service',
                'status' => Service::STATUS_INACTIVE,
            ]);

        $serviceIA->serviceEligibilities()->createMany($taxonomyIdsD->toArray());

        // Trigger reindex in a different order to ensure it's not just sorted by updated_at or something
        $serviceB->save();
        $serviceA->save();
        $serviceD->save();
        $serviceC->save();
        $serviceIA->save();

        $response = $this->json('POST', '/core/v1/search', [
            'eligibilities' => ['Bipolar disorder', 'Multiple sclerosis', 'Schizophrenia'],
        ]);

        $data = $this->getResponseContent($response)['data'];

        // There should be 4 results
        $this->assertEquals(4, count($data));

        // In this order
        $this->assertEquals($serviceD->id, $data[0]['id']);
        $this->assertEquals($serviceC->id, $data[1]['id']);
        $this->assertEquals($serviceB->id, $data[2]['id']);
        $this->assertEquals($serviceA->id, $data[3]['id']);

        // And the inactive one is filtered out
        $response->assertJsonMissing(['id' => $serviceIA->id]);
    }
}
