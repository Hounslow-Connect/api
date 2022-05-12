<?php

namespace App\Models\Relationships;

use App\Models\OrganisationEvent;
use App\Models\Taxonomy;

trait OrganisationEventTaxonomyRelationships
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organisationEvent()
    {
        return $this->belongsTo(OrganisationEvent::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function taxonomy()
    {
        return $this->belongsTo(Taxonomy::class);
    }
}
