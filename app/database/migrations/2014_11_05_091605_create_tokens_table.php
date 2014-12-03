<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTokensTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('_tokens', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('auth_token', 128)->unique();
            $table->string('device_id', 128)->unique();
            $table->string('role', 50);
            $table->timestamps();
            $table->timestamp('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('_tokens');
    }

}
