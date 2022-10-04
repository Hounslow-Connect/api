<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateServiceGalleryItemsAddCascadeDelete extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('service_gallery_items', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->dropForeign(['file_id']);
            $table->foreign('file_id')->references('id')->on('files')->onDelete('cascade');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('service_gallery_items', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->foreign('service_id')->references('id')->on('services');
            $table->dropForeign(['file_id']);
            $table->foreign('file_id')->references('id')->on('files');
        });

        Schema::enableForeignKeyConstraints();
    }
}
