<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTitleToImportsTable extends Migration
{
    public function up()
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->string('title')->nullable();
        });
    }

    public function down()
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
}
