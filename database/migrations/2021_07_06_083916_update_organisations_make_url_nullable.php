<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateOrganisationsMakeUrlNullable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('url')->nullable(false)->change();
        });
    }
}
