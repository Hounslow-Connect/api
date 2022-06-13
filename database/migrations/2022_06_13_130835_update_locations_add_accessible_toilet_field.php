<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateLocationsAddAccessibleToiletField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('has_accessible_toilet')->after('has_induction_loop')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('has_accessible_toilet');
        });
    }
}
