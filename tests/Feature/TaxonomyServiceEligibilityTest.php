<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\SocialMedia;
use App\Models\Taxonomy;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaxonomyServiceEligibilityTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->generateServiceEligibilityTaxonomy();
    }

    public function test_criteria_property_returns_comma_separated_service_eligibilities()
    {
        $service = $this->createService();
        $response = $this->get('/core/v1/services/' . $service->id);

        $response->assertJsonFragment([
            'criteria' => [
                'age_group' => 'Age Group taxonomy child,custom age group',
                'disability' => 'Disability taxonomy child,custom disability',
                'employment' => 'Employment taxonomy child,custom employment',
                'gender' => 'Gender taxonomy child,custom gender',
                'housing' => 'Housing taxonomy child,custom housing',
                'income' => 'Income taxonomy child,custom income',
                'language' => 'Language taxonomy child,custom language',
            ]
        ]);
    }

    private function  createService()
    {
        $service = factory(Service::class)->create();

        // @FIXME: move to factory states for improved code reuse
        $service->usefulInfos()->create([
            'title' => 'Did You Know?',
            'description' => 'This is a test description',
            'order' => 1,
        ]);
        
        $service->offerings()->create([
            'offering' => 'Weekly club',
            'order' => 1,
        ]);
        
        $service->socialMedias()->create([
            'type' => SocialMedia::TYPE_INSTAGRAM,
            'url' => 'https://www.instagram.com/ayupdigital/',
        ]);

        // Loop through each top level child of service eligibility taxonomy
        Taxonomy::serviceEligibility()->children->each((function($topLevelChild) use ($service) {
            // And for each top level child, attach one of its children to the service
            $topLevelChild->children->each(function($serviceEligibilityTaxonomyParent) use ($service) {
                $service->serviceEligibilities()->create([
                    'id' => (string) Str::uuid(),
                    'taxonomy_id' => $serviceEligibilityTaxonomyParent->id,
                ]);
            });
        }));

        $service->eligibility_age_group_custom = 'custom age group';
        $service->eligibility_disability_custom = 'custom disability';
        $service->eligibility_employment_custom = 'custom employment';
        $service->eligibility_gender_custom = 'custom gender';
        $service->eligibility_housing_custom = 'custom housing';
        $service->eligibility_income_custom = 'custom income';
        $service->eligibility_language_custom = 'custom language';


        $service->save();
        return $service;
    }

    private function generateServiceEligibilityTaxonomy(): void
    {
        $firstLevelChildren = [
            [
                'name' => 'Age Group',
                'order' => 0,
                'depth' => 1,
            ],
            [
                'name' => 'Disability',
                'order' => 1,
                'depth' => 1,
            ],
            [
                'name' => 'Employment',
                'order' => 2,
                'depth' => 1,
            ],
            [
                'name' => 'Gender',
                'order' => 3,
                'depth' => 1,
            ],
            [
                'name' => 'Housing',
                'order' => 4,
                'depth' => 1,
            ],
            [
                'name' => 'Income',
                'order' => 5,
                'depth' => 1,
            ],
            [
                'name' => 'Language',
                'order' => 6,
                'depth' => 1,
            ],
            [
                'name' => 'Ethnicity',
                'order' => 7,
                'depth' => 1,
            ]
        ];

        Taxonomy::serviceEligibility()
            ->children()
            ->createMany($firstLevelChildren);

        $count = 0;

        Taxonomy::serviceEligibility()
            ->children
            ->each(function ($item) use ($count) {
                $item->children()->create([
                    'name' => $item->name . ' taxonomy child',
                    'order' => $count,
                    'depth' => $item->depth + 1,
                ]);

                $count++;
            });
    }
}
