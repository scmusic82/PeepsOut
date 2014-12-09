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
		$lat = Input::get('lat', 0);
		$lon = Input::get('lon', 0);
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

		$existing_venues = Venue::whereRaw("id > 0" . $kw_condition . $category_condition);
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

				$all_venues[$venue_key] = [
					'venue_id' 		=> $venue->venue_id,
					'name' 			=> $venue->name,
					'categories'	=> $venue_categories,
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
		return Response::json($response, 200);
	}

	public function show($venue_id)
	{
		$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
		$user = $token->user;

		$lat = Input::get('lat', 0);
		$lon = Input::get('lon', 0);
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
		
		$feed_schedule = [];
		$schedule = (array)json_decode($venue->feed_schedule, true);
		foreach($schedule as $k => $v) {
			if (isset($v['days']) && count($v['days']) > 0) {
				$feed_schedule[] = ['days' => $v['days'], 'hours' => $v['hours']];
			}
		}

		$venue_specials = $venue_events = [];
		$specials = (array)json_decode($venue->specials, true);
		$today = strtotime('now');
		foreach($specials as $k => $v) {
			$special = $event = [];
			if (isset($v['description'])) {
				$is_event = $v['event'] == '1' ? true : false;
				if (trim($v['from']) == '' && trim($v['until']) == '') {
					// No time limit
					if ($is_event) {
						// It's an event
						$event['description'] = $v['description'];
						$event['day'] = $v['day'];
					} else {
						// It's a special
						$special['description'] = $v['description'];
						$special['day'] = $v['day'];
					}
				} else if (trim($v['from']) != '' && trim($v['until']) != '') {
					// Timed
					if ($today > strtotime($v['from'] . ' 00:00:00') && $today < strtotime($v['until'] . ' 23:59:59')) {
						// Is happening
						if ($is_event) {
							// It's an event
							$event['description'] = $v['description'];
							$event['day'] = $v['day'];
							$event['starts'] = $v['from'];
							$event['ends'] = $v['until'];
						} else {
							// It's a special
							$special['description'] = $v['description'];
							$special['day'] = $v['day'];
							$special['starts'] = $v['from'];
							$special['ends'] = $v['until'];
						}
					}
				} else if (trim($v['from']) != '' && trim($v['forever']) == '1') {
					// Never finishes
					if ($today > strtotime($v['from'] . ' 00:00:00')) {
						// Is happening
						if ($is_event) {
							// It's an event
							$event['description'] = $v['description'];
							$event['day'] = $v['day'];
							$event['starts'] = $v['from'];
						} else {
							// It's a special
							$special['description'] = $v['description'];
							$special['day'] = $v['day'];
							$special['starts'] = $v['from'];
						}
					}
				}
			}
			if (count($special) > 0) { $venue_specials[$v['group']][] = $special; }
			if (count($event) > 0) { $venue_events[$v['group']][] = $event; }
		}


		$venue_images = [];
		$images = (array)json_decode($venue->images, true);
		foreach ($images as $image) {
			$venue_images[] = asset($image);
		}
		$response = [
			"status"			=> Config::get('constants.SUCCESS'),
			"venue_id" 			=> $venue->venue_id,
			"name" 				=> $venue->name,
			"categories" 		=> $venue_categories,
			"feed" 				=> $venue->feed,
			"feed_schedule" 	=> $feed_schedule,
			"location" 			=> [
				"lat" 			=> $venue->location_lat,
				"lon" 			=> $venue->location_lon,
				"distance"		=> number_format($distance, 2, '.', '')
			],
			"details" 			=> [
				"description" 	=> $venue->description,
				"web_address" 	=> $venue->web_address,
				"email_address" => $venue->email_address,
				"phone_numbers" => array_values((array)json_decode($venue->phone_numbers, true))
			],
			"specials" 			=> $venue_specials,
			"events" 			=> $venue_events,
			"images" 			=> $venue_images,
			"streaming"			=> $is_streaming,
			"stream_in"			=> $next_stream_in
		];
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
		return Response::make('', 200);
	}


	public function listSpecials()
	{
		$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
		$user = $token->user;

		$returned_venues = $all_venues = $distances = [];

		$timestamp = Input::get('timestamp', strtotime('now'));
		$timezone = Input::get('timezone', '-18000');
		$lat = Input::get('lat', 0);
		$lon = Input::get('lon', 0);
		$low_count = 99999999999;
		$start_count = 0;

		$today_day = date('l', $timestamp);
		$user_gmt_time = intval($timestamp) - intval($timezone);

		$existing_venues = Venue::where('specials', '!=', '')->where('specials', '!=', '[]');
		if ($existing_venues->count() > 0) {

			$categories = Category::getCategories();
			$user_favourites = Favourite::getFavourites($user->user_id);
			$found_venues = $existing_venues->get();
			
			foreach($found_venues as $venue) {
				$specials = json_decode($venue->specials, true);
				if (isset($specials['event']) && $specials['event'] == '0') {
					
					list($is_streaming, $next_stream_in) = Venue::checkStream($venue->feed_schedule, $venue->feed_timezone, $today_day, $user_gmt_time);
					list($distance, $venue_key) = Venue::getDistance($lat, $lon, $venue->location_lat, $venue->location_lon, $start_count, $low_count);
					$distances[$venue_key] = $distance;
					$venue_categories = Venue::getCategories($venue->category_id);

					$all_venues[$venue_key] = [
						'venue_id' 		=> $venue->venue_id,
						'name' 			=> $venue->name,
						'categories'	=> $venue_categories,
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
		}
		@asort($distances);
		foreach($distances as $key => $distance) {
			$returned_venues[] = $all_venues[$key];
		}

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
		$lat = Input::get('lat', 0);
		$lon = Input::get('lon', 0);
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
			$existing_venues = Venue::whereIn('venue_id', $all_favourites)->orderBy('name');
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
			$venues = Venue::where('search_field', 'like', '%' . $kw . '%');
			if ($venues->count() > 0) {
				foreach($venues->get() as $venue) {
					$suggestions[] = $venue->name;
				}
			}
		}
		$suggestions = array_unique($suggestions);
		@asort($suggestions);
		return Response::json([
			'status' => Config::get('constants.SUCCESS'), 
			'suggestions' => $suggestions
			], 200);
	}
} 