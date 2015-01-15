<?php

class VenuesController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$kw = trim(Utils::formPrep(Input::get('kw', '')));
		$search_category = trim(Utils::formPrep(Input::get('category', '')));

		$timestamp = Input::get('timestamp', strtotime('now'));
		$timezone = Input::get('timezone', '-18000');
		$lat = Input::get('lat', 40.758895);
        $lon = Input::get('lon', -73.985131);
        if ($lat == '') { $lat = 40.758895; }
        if ($lon == '') { $lon = -73.985131; }
		$low_count = 99999999999;
		$start_count = 0;

		$kw_condition = $category_condition = "";

		$today_day = date('l', $timestamp);
		$user_gmt_time = intval($timestamp) - intval($timezone);

		if ($kw != '') {
			$kw = Utils::stopWords($kw);
			$kw_condition = " AND (search_field LIKE '%" . implode("%' OR search_field LIKE '%", explode(' ', $kw)) . "%')";
		}

		if ($search_category != '') {
			$category_condition = " AND category_id LIKE '%" . $search_category . "%'";
		}

		$existing_venues = Venue::whereRaw("id > 0 AND soft_delete = 0" . $kw_condition . $category_condition);
		$all_venues = $returned_venues = $distances = [];
		if ($existing_venues->count() > 0) {
			
			$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
			$user = $token->user;

			$user_favourites = Favourite::getFavourites($user->user_id);

			$venues = $existing_venues->get();
			foreach($venues as $venue) {

				list($is_streaming, $next_stream_in) = Venue::checkStream($venue->feed_schedule, $venue->feed_timezone, $today_day, $user_gmt_time);
				list($distance, $venue_key) = Venue::getDistance($lat, $lon, $venue->location_lat, $venue->location_lon, $start_count, $low_count);
				
				$distances[$venue_key] = $distance;
				$venue_categories = Venue::getCategories($venue->category_id);

				$feed_schedule = [];
				$schedule = (array)json_decode($venue->feed_schedule, true);
				foreach($schedule as $k => $v) {
					if (isset($v['days']) && count($v['days']) > 0) {
						$feed_schedule[] = ['days' => $v['days'], 'hours' => $v['hours']];
					}
				}

				$city = City::where('id', '=', $venue->city)->first();
				$city_name = $city->name;

				$all_venues[$venue_key] = [
					'venue_id' 		=> $venue->venue_id,
					'name' 			=> $venue->name,
					'categories'	=> $venue_categories,
					'city'			=> $city_name,
					'feed' 			=> $venue->feed,
					'favourite' 	=> (isset($user_favourites[$venue->venue_id]) ? 1 : 0),
					'streaming'		=> $is_streaming,
					'stream_in'		=> $next_stream_in,
					'location' => [
						'lat' 		=> $venue->location_lat, 
						'lon' 		=> $venue->location_lon,
						'distance'	=> number_format($distance, 2, '.', '')
					]
				];
				$venue->impressions++;
				$venue->update();
				$start_count++;

			}
		}
		@asort($distances);
		foreach($distances as $key => $distance) {
			$returned_venues[] = $all_venues[$key];
		}
		$response = [
			'status' => Config::get('constants.SUCCESS'),
			'total_results' => count($returned_venues),
			'venues' => $returned_venues
		];
		Metric::registerCall('venues', Request::getClientIp(), Config::get('constants.SUCCESS'), '');
		return Response::json($response, 200);
	}

	/**
	 * Display venue details
	 *
	 * @param $venue_id
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function show($venue_id)
	{
		$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
		$user = $token->user;

		$lat = Input::get('lat', 40.758895);
        $lon = Input::get('lon', -73.985131);
        if ($lat == '') { $lat = 40.758895; }
        if ($lon == '') { $lon = -73.985131; }
		$timestamp = Input::get('timestamp', strtotime('now'));
		$timezone = Input::get('timezone', '-18000');
		$today_day = date('l', $timestamp);
		$user_gmt_time = intval($timestamp) - intval($timezone);

		$existing_venue = Venue::where('venue_id', '=', $venue_id);
		if ($existing_venue->count() == 0) {
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'), 
				'message' => Lang::get('messages.venue_not_found')
				], 401);
		}
		$venue = $existing_venue->first();

		list($is_streaming, $next_stream_in) = Venue::checkStream($venue->feed_schedule, $venue->feed_timezone, $today_day, $user_gmt_time);
		list($distance, $venue_key) = Venue::getDistance($lat, $lon, $venue->location_lat, $venue->location_lon);

		$venue_categories = Venue::getCategories($venue->category_id);
		$user_favourites = Favourite::getFavourites($user->user_id);
		
		$feed_schedule = [];
		$schedule = (array)json_decode($venue->feed_schedule, true);
		foreach($schedule as $k => $v) {
			if (isset($v['days']) && count($v['days']) > 0) {
				if (is_array($v['hours']) && count($v['hours']) > 0) {
					foreach($v['hours'] as $kh => $vh) {
						$h = explode(':', $vh);
						if (intval($h[0]) <= 11) {
							$v['hours'][$kh] = intval($h[0]) . (intval($h[1])  > 0 ? ':' . $h[1] : '') . ' AM';
						} else if (intval($h[0]) == 12) {
							$v['hours'][$kh] = $h[0] . (intval($h[1])  > 0 ? ':' . $h[1] : '') . ' PM';
						} else if (intval($h[0]) > 12) {
							$new_h = $h[0] - 12;
							$v['hours'][$kh] = $new_h . (intval($h[1]) > 0 ? ':' . $h[1] : '') . ' PM';
						}
					}
				}
				$feed_schedule[] = ['days' => $v['days'], 'hours' => $v['hours']];
			}
		}

        $specials = (array)json_decode($venue->specials, true);
        $venue_specials = Venue::getVenueSpecials($specials);
		$specials_order = [];
		if (count($venue_specials) > 0) {
			foreach($venue_specials as $k => $v) {
				$specials_order[] = $k;
			}
		}
		$venue_images = [];
		$images = (array)json_decode($venue->images, true);
		foreach ($images as $image) {
			$venue_images[] = Config::get('constants.IMG_HOST') . trim($image, '/');
		}
		$response = [
			"status"			=> Config::get('constants.SUCCESS'),
			"venue_id" 			=> $venue->venue_id,
			"name" 				=> $venue->name,
			"categories" 		=> $venue_categories,
			"feed" 				=> $venue->feed,
			"feed_schedule" 	=> $feed_schedule,
			'favourite' 		=> (isset($user_favourites[$venue->venue_id]) ? 1 : 0),
			"location" 			=> [
				"lat" 			=> $venue->location_lat,
				"lon" 			=> $venue->location_lon,
				"distance"		=> number_format($distance, 2, '.', '')
			],
			"details" 			=> [
				"description" 	=> strip_tags($venue->description),
				"geo_address"	=> $venue->geo_address,
				"web_address" 	=> $venue->web_address,
				"email_address" => $venue->email_address,
				"phone_numbers" => array_values((array)json_decode($venue->phone_numbers, true))
			],
			"specials" 			=> $venue_specials,
			"specials_order"	=> $specials_order,
			"images" 			=> $venue_images,
			"streaming"			=> $is_streaming,
			"stream_in"			=> $next_stream_in
		];
		$venue->visits++;
		$venue->update();

		Metric::registerCall('venues/' . $venue_id, Request::getClientIp(), Config::get('constants.SUCCESS'), '');
		return Response::json($response, 200);
	}

	public function markFavourite($venue_id)
	{
		$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
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
		Metric::registerCall('venues/' . $venue_id . '/fav', Request::getClientIp(), Config::get('constants.SUCCESS'), '');
		return Response::make('', 200);
	}


	public function listSpecials()
	{
		$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
		$user = $token->user;

		$returned_venues = $all_venues = $distances = [];

		$timestamp = Input::get('timestamp', strtotime('now'));
		$timezone = Input::get('timezone', '-18000');
		$lat = Input::get('lat', 40.758895);
		$lon = Input::get('lon', -73.985131);
        if ($lat == '') { $lat = 40.758895; }
        if ($lon == '') { $lon = -73.985131; }
		$low_count = 99999999999;
		$start_count = 0;

		$today_day = date('l', $timestamp);
		$user_gmt_time = intval($timestamp) - intval($timezone);

		$existing_venues = Venue::where('soft_delete', '=', '0')
			->where('specials', '!=', '')
			->where('specials', '!=', '[]');
		if ($existing_venues->count() > 0) {

			$categories = Category::getCategories();
			$user_favourites = Favourite::getFavourites($user->user_id);
			$found_venues = $existing_venues->get();
			
			foreach($found_venues as $venue) {
				$specials = json_decode($venue->specials, true);
                foreach($specials as $special) {
                    $add_venue = false;
    				if (isset($special['event']) && $special['event'] == '0') {
    				    $add_venue = true;
                        break;
                    }
                }
                if ($add_venue) {
					list($is_streaming, $next_stream_in) = Venue::checkStream($venue->feed_schedule, $venue->feed_timezone, $today_day, $user_gmt_time);
					list($distance, $venue_key) = Venue::getDistance($lat, $lon, $venue->location_lat, $venue->location_lon, $start_count, $low_count);
					$distances[$venue_key] = $distance;
					$venue_categories = Venue::getCategories($venue->category_id);

                    $specials = (array)json_decode($venue->specials, true);
                    $venue_specials = Venue::getVenueSpecials($specials);

					$specials_order = [];
					if (count($venue_specials) > 0) {
						foreach($venue_specials as $k => $v) {
							$specials_order[] = $k;
						}
					}

					$city = City::where('id', '=', $venue->city)->first();
					$city_name = $city->name;

					$all_venues[$venue_key] = [
						'venue_id' 		=> $venue->venue_id,
						'name' 			=> $venue->name,
						'categories'	=> $venue_categories,
						'city'			=> $city_name,
						'feed' 			=> $venue->feed, 
						'favourite' 	=> (isset($user_favourites[$venue->venue_id]) ? 1 : 0), 
						'streaming'		=> $is_streaming,
						'stream_in'		=> $next_stream_in,
						'location' => [
							'lat' 		=> $venue->location_lat, 
							'lon' 		=> $venue->location_lon,
							'distance'	=> number_format($distance, 2, '.', '')
						],
                        'specials'      => $venue_specials,
						'specials_order'=> $specials_order
					];

					$venue->impressions++;
					$venue->update();
					$start_count++;
    			}
			}
		}
		@asort($distances);
		foreach($distances as $key => $distance) {
			$returned_venues[] = $all_venues[$key];
		}

		Metric::registerCall('venues/specials', Request::getClientIp(), Config::get('constants.SUCCESS'), '');
		return Response::json([
			'status' => Config::get('constants.SUCCESS'), 
			'total_results' => count($returned_venues), 
			'venues' => $returned_venues
			], 200);
	}

	public function listFavourites()
	{
		$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
		$user = $token->user;

		$returned_venues = $all_favourites = $all_venues = $distances = [];

		$timestamp = Input::get('timestamp', strtotime('now'));
		$timezone = Input::get('timezone', '-18000');
		$lat = Input::get('lat', 40.758895);
        $lon = Input::get('lon', -73.985131);
        if ($lat == '') { $lat = 40.758895; }
        if ($lon == '') { $lon = -73.985131; }
		$low_count = 99999999999;
		$start_count = 0;

		$today_day = date('l', $timestamp);
		$user_gmt_time = intval($timestamp) - intval($timezone);

		$existing_favourites = Favourite::where('user_id', '=', $user->user_id);
		if ($existing_favourites->count() > 0) {
			$found_favourites = $existing_favourites->get();
			foreach($found_favourites as $favourite) {
				$all_favourites[] = $favourite->venue_id;
			}
			$all_favourites = array_unique($all_favourites);
		}

		if (count($all_favourites) > 0) {
			$existing_venues = Venue::where('soft_delete', '=', '0')
				->whereIn('venue_id', $all_favourites);
			if ($existing_venues->count() > 0) {
				$categories = Category::getCategories();
				$found_venues = $existing_venues->get();
				foreach($found_venues as $venue) {

					list($is_streaming, $next_stream_in) = Venue::checkStream($venue->feed_schedule, $venue->feed_timezone, $today_day, $user_gmt_time);
					list($distance, $venue_key) = Venue::getDistance($lat, $lon, $venue->location_lat, $venue->location_lon, $start_count, $low_count);

					$distances[$venue_key] = $distance;
					$venue_categories = Venue::getCategories($venue->category_id);

					$all_venues[$venue_key] = [
						'venue_id' 		=> $venue->venue_id,
						'name' 			=> $venue->name,
						'categories'	=> $venue_categories,
						'feed' 			=> $venue->feed, 
						'distance'		=> $venue->distance,
						'streaming'		=> $is_streaming,
						'stream_in'		=> $next_stream_in,
						'location' => [
							'lat' 		=> $venue->location_lat, 
							'lon' 		=> $venue->location_lon,
							'distance'	=> number_format($distance, 2, '.', '')
						]
					];
					$venue->impressions++;
					$venue->update();
					$start_count++;
				}
			}
		}
		@asort($distances);
		foreach($distances as $key => $distance) {
			$returned_venues[] = $all_venues[$key];
		}
		Metric::registerCall('venues/favourites', Request::getClientIp(), Config::get('constants.SUCCESS'), '');
		return Response::json([
			'status' => Config::get('constants.SUCCESS'), 
			'total_results' => count($returned_venues), 
			'venues' => $returned_venues
			], 200);
	}

	public function showSuggestions()
	{
		$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
		$user = $token->user;

		$kw = Input::get('kw', '');
		$suggestions = [];

		if ($kw != '') {
			$kw = Utils::stopWords(Utils::purify($kw));
			$venues = Venue::where('soft_delete', '=', '0')
				->where('search_field', 'like', '%' . $kw . '%');
			if ($venues->count() > 0) {
				foreach($venues->get() as $venue) {
					$suggestions[] = $venue->name;
				}
			}
		}
		$suggestions = array_unique($suggestions);
		@asort($suggestions);
		Metric::registerCall('venues/suggestions', Request::getClientIp(), Config::get('constants.SUCCESS'), '');
		return Response::json([
			'status' => Config::get('constants.SUCCESS'), 
			'suggestions' => $suggestions
			], 200);
	}
} 