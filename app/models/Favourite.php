<?php
class Favourite extends Eloquent {

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = '_favourites';

	/**
	 * Gather current user's favourites
	 *
	 * @param string $user_id
	 * @return array
	 */
	public static function getFavourites($user_id = '')
	{
		if ($user_id == '') { return []; }
		$user_favourites = [];
		$existing_favourites = Favourite::where('user_id', '=', $user_id);
		if ($existing_favourites->count() > 0) {
			$favourites = $existing_favourites->get();
			foreach($favourites as $favourite) {
				$user_favourites[$favourite->venue_id] = 1;
			}
		}
		return $user_favourites;
	}
	
}
