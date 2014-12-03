<?php

class VenuesController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		if (Request::header('Authorization')) {
			$auth_token = Request::header('Authorization');
			if (Token::checkToken($auth_token)) {
				
				$kw = trim(Utils::formPrep(Input::get('kw', '')));
				$search_category = trim(Utils::formPrep(Input::get('category', '')));
				$kw_condition = $category_condition = "";

				if ($kw != '') {
					$kw = Utils::stopWords($kw);
					$kw_condition = " AND (search_field LIKE '%" . implode("%' OR search_field LIKE '%", explode(' ', $kw)) . "%')";
				}

				if ($search_category != '') {
					$category_condition = " AND category_id LIKE '%" . $search_category . "%'";
				}

				$existing_venues = Venue::whereRaw("id > 0" . $kw_condition . $category_condition);
				//select("SELECT * FROM venues WHERE id > 0" . $kw_condition . $category_condition . " ORDER BY name");
				$returned_venues = [];
				if ($existing_venues->count() > 0) {
					$token = Token::where('auth_token', '=', $auth_token)->first();
					$user = $token->user;
					$user_favourites = Favourite::getFavourites($user->user_id);
					$categories = Category::getCategories();

					$venues = $existing_venues->get();
					foreach($venues as $venue) {
						$venue_categories = [];
						$vc = (array)json_decode($venue->category_id, true);
						foreach($vc as $venue_category) {
							if (isset($categories[$venue_category])) {
								$venue_categories[] = [
									'name' => $categories[$venue_category]['name'],
									'id' => $venue_category,
									'stub' => $categories[$venue_category]['stub']
								];
							}
						}
						$returned_venues[] = [
							'venue_id' 		=> $venue->venue_id,
							'name' 			=> $venue->name,
							'categories'	=> $venue_categories,
							'feed' 			=> $venue->feed, 
							'favourite' 	=> (isset($user_favourites[$venue->venue_id]) ? 1 : 0), 
							'location' => [
								'lat' 		=> $venue->location_lat, 
								'lon' 		=> $venue->location_lon
							]
						];
						$venue->impressions++;
						$venue->update();
					}
				}
				return Response::json(['status' => 1, 'total_results' => count($returned_venues), 'venues' => $returned_venues], 200);
			}
		}
		return Response::json(['status' => 2, 'message' => Lang::get('messages.auth_error')], 401);
	}

	public function show($venue_id)
	{
		if (Request::header('Authorization')) {
			$auth_token = Request::header('Authorization');
			if (Token::checkToken($auth_token)) {
				$token = Token::where('auth_token', '=', $auth_token)->first();
				$user = $token->user;

				$existing_venue = Venue::where('venue_id', '=', $venue_id);
				if ($existing_venue->count() == 0) {
					return Response::json(['status' => 0, 'message' => Lang::get('messages.venue_not_found')], 401);
				}
				$venue = $existing_venue->first();

				$categories = Category::getCategories();
				$venue_categories = [];
				$vc = (array)json_decode($venue->category_id, true);
				foreach($vc as $venue_category) {
					if (isset($categories[$venue_category])) {
						$venue_categories[] = [
							'name' => $categories[$venue_category]['name'],
							'id' => $venue_category,
							'stub' => $categories[$venue_category]['stub']
						];
					}
				}
				
				$feed_schedule = [];
				$schedule = (array)json_decode($venue->feed_schedule, true);
				foreach($schedule as $k => $v) {
					$hrs = explode('-', $v['hours']);
					if (!isset($hrs[0])) { $hrs[0] = ''; }
					if (!isset($hrs[1])) { $hrs[1] = ''; }
					if (isset($v['days']) && trim($v['days']) != '') {
						$feed_schedule[] = ['day' => $v['days'], 'hours' => [trim($hrs[0]), trim($hrs[1])]];
					}
				}

				$venue_specials = [];
				$specials = (array)json_decode($venue->specials, true);
				foreach($specials as $k => $v) {
					if (isset($v['day']) && isset($v['name']) && isset($v['description']) && isset($v['timing']) && isset($v['price'])) {
						$venue_specials[] = [
							"day" 			=> trim($v['day']),
							"name" 			=> trim($v['name']),
							"description" 	=> trim($v['description']),
							"timing" 		=> trim($v['timing']),
							"price" 		=> number_format(trim($v['price']), 2)
						];
					}
				}

				$venue_images = [];
				$images = (array)json_decode($venue->images, true);
				foreach ($images as $image) {
					$venue_images[] = asset($image);
				}
				$response = [
					"status"			=> 1,
					"venue_id" 			=> $venue->venue_id,
					"name" 				=> $venue->name,
					"categories" 		=> $venue_categories,
					"feed" 				=> $venue->feed,
					"feed_schedule" 	=> $feed_schedule,
					"location" 			=> [
						"lat" 			=> $venue->location_lat,
						"lon" 			=> $venue->location_lon
					],
					"details" 			=> [
						"description" 	=> $venue->description,
						"web_address" 	=> $venue->web_address,
						"email_address" => $venue->email_address,
						"phone_numbers" => array_values((array)json_decode($venue->phone_numbers, true))
					],
					"specials" 			=> $venue_specials,
					"images" 			=> $venue_images
				];
				return Response::json($response, 200);
			}
		}
		return Response::json(['status' => 2, 'message' => Lang::get('messages.auth_error')], 401);
	}

	public function markFavourite($venue_id)
	{
		if (Request::header('Authorization')) {
			$auth_token = Request::header('Authorization');
			if (Token::checkToken($auth_token)) {
				$token = Token::where('auth_token', '=', $auth_token)->first();
				$user = $token->user;
				$existing_favourites = Favourite::where('venue_id', '=', $venue_id)->where('user_id', '=', $user->user_id);
				if ($existing_favourites->count() == 0) {
					$favourite = new Favourite();
					$favourite->venue_id = $venue_id;
					$favourite->user_id = $user->user_id;
					$favourite->save();
				} else {
					$favourite = $existing_favourites->first();
					$favourite->delete();
				}
				return Response::make('', 200);
			}
		}
		return Response::json(['status' => 2, 'message' => Lang::get('messages.auth_error')], 401);
	}


	public function listSpecials()
	{
		if (Request::header('Authorization')) {
			$auth_token = Request::header('Authorization');
			if (Token::checkToken($auth_token)) {
				$token = Token::where('auth_token', '=', $auth_token)->first();
				$user = $token->user;

				$returned_venues = [];

				$existing_venues = Venue::where('specials', '!=', '')->where('specials', '!=', '[]');
				if ($existing_venues->count() > 0) {

					$categories = Category::getCategories();
					$user_favourites = Favourite::getFavourites($user->user_id);
					$found_venues = $existing_venues->get();
					
					foreach($found_venues as $venue) {
						$venue_categories = [];
						$vc = (array)json_decode($venue->category_id, true);
						foreach($vc as $venue_category) {
							if (isset($categories[$venue_category])) {
								$venue_categories[] = [
									'name' => $categories[$venue_category]['name'],
									'id' => $venue_category,
									'stub' => $categories[$venue_category]['stub']
								];
							}
						}
						$returned_venues[] = [
							'venue_id' 		=> $venue->venue_id,
							'name' 			=> $venue->name,
							'categories'	=> $venue_categories,
							'feed' 			=> $venue->feed, 
							'favourite' 	=> (isset($user_favourites[$venue->venue_id]) ? 1 : 0), 
							'location' => [
								'lat' 		=> $venue->location_lat, 
								'lon' 		=> $venue->location_lon
							]
						];
					}
				}
				return Response::json(['status' => 1, 'total_results' => count($returned_venues), 'venues' => $returned_venues], 200);
			}
		}
		return Response::json(['status' => 2, 'message' => Lang::get('messages.auth_error')], 401);
	}

	public function listFavourites()
	{
		if (Request::header('Authorization')) {
			$auth_token = Request::header('Authorization');
			if (Token::checkToken($auth_token)) {
				$token = Token::where('auth_token', '=', $auth_token)->first();
				$user = $token->user;

				$returned_venues = [];
				$all_favourites = [];

				$existing_favourites = Favourite::where('user_id', '=', $user->user_id);
				if ($existing_favourites->count() > 0) {
					$found_favourites = $existing_favourites->get();
					foreach($found_favourites as $favourite) {
						$all_favourites[] = $favourite->venue_id;
					}
					$all_favourites = array_unique($all_favourites);
				}

				if (count($all_favourites) > 0) {
					$existing_venues = Venue::whereIn('venue_id', $all_favourites)->orderBy('name');

					if ($existing_venues->count() > 0) {
						$categories = Category::getCategories();
						$found_venues = $existing_venues->get();
						foreach($found_venues as $venue) {
							$venue_categories = [];
							$vc = (array)json_decode($venue->category_id, true);
							foreach($vc as $venue_category) {
								if (isset($categories[$venue_category])) {
									$venue_categories[] = [
										'name' => $categories[$venue_category]['name'],
										'id' => $venue_category,
										'stub' => $categories[$venue_category]['stub']
									];
								}
							}
							$returned_venues[] = [
								'venue_id' 		=> $venue->venue_id,
								'name' 			=> $venue->name,
								'categories'	=> $venue_categories,
								'feed' 			=> $venue->feed, 
								'location' => [
									'lat' 		=> $venue->location_lat, 
									'lon' 		=> $venue->location_lon
								]
							];
							$venue->impressions++;
							$venue->update();
						}
					}
				}
				return Response::json(['status' => 1, 'total_results' => count($returned_venues), 'venues' => $returned_venues], 200);
			}
		}
		return Response::json(['status' => 2, 'message' => Lang::get('messages.auth_error')], 401);
	}

}






































