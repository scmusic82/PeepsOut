<?php
class Neighbourhood extends Eloquent {

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'neighbourhoods';
	
    public function city()
    {
        return $this->belongsTo('City', 'parent', 'id');
    }
}
