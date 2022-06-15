<?php

use App\Models\Location;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateLocationsAddAccessibleToiletField extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('has_accessible_toilet')->after('has_induction_loop');
        });

        Schema::table('locations', function (Blueprint $table) {
            DB::table((new Location())->getTable())
                ->update(['has_accessible_toilet' => false]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('has_accessible_toilet');
        });
    }
}
