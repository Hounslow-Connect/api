<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateOrganisationEventsAddHomepageField extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('organisation_events', function (Blueprint $table) {
            $table->boolean('homepage')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('organisation_events', function (Blueprint $table) {
            $table->dropColumn('homepage');
        });
    }
}
