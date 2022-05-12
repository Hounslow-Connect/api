<?php

namespace App\Models;

use App\Models\Mutators\OrganisationEventTaxonomyMutators;
use App\Models\Relationships\OrganisationEventTaxonomyRelationships;
use App\Models\Scopes\OrganisationEventTaxonomyScopes;

class OrganisationEventTaxonomy extends Model
{
    use OrganisationEventTaxonomyMutators;
    use OrganisationEventTaxonomyRelationships;
    use OrganisationEventTaxonomyScopes;

    /**
     * @return \App\Models\OrganisationEventTaxonomy
     */
    public function touchOrganisationEvent(): OrganisationEventTaxonomy
    {
        $this->organisationEvent->save();

        return $this;
    }
}
