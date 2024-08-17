<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWebsitesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->increments('id');
            $table->string('cid')->nullable();
            $table->string('template_id');
            $table->string('site_name')->unique();
            $table->string('domain')->unique();
            $table->string('db_port')->unique();
            $table->string('site_port')->unique();
            $table->string('state')->default('creating'); // creating/burn-up/running/stop/error/shutdown
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('websites');
    }
}
