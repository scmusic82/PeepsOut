<?php
class Token extends Eloquent {

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = '_tokens';
	
	/**
	 * One-to-One relation to the user's table
	 *
	 * @var string
	 */
	public function user()
    {
        return $this->belongsTo('User', 'device_id', 'device_id');
    }
}