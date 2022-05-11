<?php

use App\Models\Taxonomy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrganisationEventTaxonomiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('organisation_event_taxonomies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_event_id', 'organisation_events');
            $table->foreignUuid('taxonomy_id', 'taxonomies');
            $table->timestamps();
        });

        Taxonomy::create([
            'name' => Taxonomy::NAME_ORGANISATION_EVENT,
            'order' => 0,
            'depth' => 0,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('organisation_event_taxonomies');
    }
}
