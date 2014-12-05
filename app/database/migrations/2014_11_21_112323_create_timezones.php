<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTimezones extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('_timezones', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('timezone', 150);
            $table->integer('amount');
            $table->string('difference', 10)->default('0000');
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
        Schema::drop('_timezones');
    }

}
