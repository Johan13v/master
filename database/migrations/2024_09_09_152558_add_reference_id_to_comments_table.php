<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReferenceIdToCommentsTable extends Migration
{
    public function up()
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->string('reference_id')->unique()->nullable(); // Add a unique reference for WordPress comment ID
        });
    }

    public function down()
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn('reference_id');
        });
    }
}
