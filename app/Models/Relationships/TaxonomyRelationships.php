<?php

namespace App\Models\Relationships;

use App\Models\CollectionTaxonomy;
use App\Models\OrganisationEvent;
use App\Models\OrganisationEventTaxonomy;
use App\Models\Referral;
use App\Models\Service;
use App\Models\ServiceTaxonomy;
use App\Models\Taxonomy;

trait TaxonomyRelationships
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(Taxonomy::class, 'parent_id')->orderBy('order');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function collectionTaxonomies()
    {
        return $this->hasMany(CollectionTaxonomy::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function serviceTaxonomies()
    {
        return $this->hasMany(ServiceTaxonomy::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function referrals()
    {
        return $this->hasMany(Referral::class, 'organisation_taxonomy_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function services()
    {
        return $this->belongsToMany(Service::class, (new ServiceTaxonomy())->getTable());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function organisationEvents()
    {
        return $this->belongsToMany(OrganisationEvent::class, (new OrganisationEventTaxonomy())->getTable());
    }
}
