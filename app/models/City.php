<?php
class City extends Eloquent {

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'cities';

	public function neighbourhoods()
    {
        return $this->hasMany('Neighbourhood', 'parent', 'id');
    }
	
}
