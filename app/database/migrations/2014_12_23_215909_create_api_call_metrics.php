<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateApiCallMetrics extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('_call_metrics', function(Blueprint $table)
		{
			$table->increments('id');
			$table->string('endpoint', 128);
			$table->integer('times_called')->unsigned();
			$table->timestamp('called_at');
			$table->string('called_from', 16);
			$table->tinyInteger('status')->unsigned();
			$table->text('message')->nullable();
			$table->timestamps();
			$table->index('endpoint', 'endpoint_idx');
			$table->index('called_at', 'called_at_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('_call_metrics');
	}

}
