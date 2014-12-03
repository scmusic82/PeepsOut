<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVenuesTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('venues', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('venue_id', 10)->unique();
            $table->string('name', 250);
            $table->text('category_id');
            $table->text('feed')->nullable();
            $table->text('feed_schedule')->nullable();
            $table->text('geo_address')->nullable();
            $table->string('location_lat', 32)->nullable();
            $table->string('location_lon', 32)->nullable();
            $table->text('description')->nullable();
            $table->string('web_address', 200)->nullable();
            $table->string('email_address', 100)->nullable();
            $table->text('phone_numbers')->nullable();
            $table->text('images')->nullable();
            $table->text('search_field')->nullable();
            $table->integer('impressions');
            $table->integer('visits');
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
        Schema::drop('venues');
    }

}
