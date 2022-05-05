<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrganisationEventsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('organisation_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('intro');
            $table->text('description');
            $table->boolean('is_free')->default(true);
            $table->string('fees_text')->nullable();
            $table->string('fees_url')->nullable();
            $table->string('organiser_name')->nullable();
            $table->string('organiser_phone')->nullable();
            $table->string('organiser_email')->nullable();
            $table->string('organiser_url')->nullable();
            $table->string('booking_title')->nullable();
            $table->string('booking_summary')->nullable();
            $table->string('booking_url')->nullable();
            $table->string('booking_cta')->nullable();
            $table->boolean('is_virtual')->default(true);
            $table->nullableForeignUuid('location_id', 'locations');
            $table->nullableForeignUuid('image_file_id', 'files');
            $table->foreignUuid('organisation_id', 'organisations');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('organisation_events');
    }
}
