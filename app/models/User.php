<?php

class User extends \BaseModel {

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'users';

	public function token()
    {
        return $this->hasOne('Token', 'device_id', 'device_id');
    }

}
