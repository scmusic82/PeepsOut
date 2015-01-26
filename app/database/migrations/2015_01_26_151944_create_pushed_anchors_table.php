<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePushedAnchorsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('pushed_anchors', function(Blueprint $table)
		{
			$table->increments('id');
			$table->string('anchor', 128);
			$table->string('device_id', 128);
			$table->text('response_data')->nullable();
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
		Schema::drop('pushed_anchors');
	}
}
