<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('device_id', 128);
            $table->foreign('device_id')->references('device_id')->on('_tokens')->onDelete('cascade');
            $table->string('user_id', 20)->unique();
            $table->string('email_address', 128)->unique();
            $table->string('push_token', 128)->unique();
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
        Schema::drop('users');
    }

}
