<?php

class UsersController extends \BaseController {

	/**
	 * Register / Update an email address with the current user
	 *
	 * @return Response
	 */
	public function register_email() {
		$request = Request::instance();
		$data = (array)json_decode($request->getContent(), true);

		$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
		$user = $token->user;

		if (!isset($data['email_address']) || (isset($data['email_address']) && trim($data['email_address']) == '')) {
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'),
				'message' => Lang::get('messages.missing_email')
			], 400);
		}

		if (isset($data['email_address']) && trim($data['email_address']) != '') {
			if (!preg_match('/[_A-Za-z0-9-\+]+(\.[_A-Za-z0-9-]+)*@[A-Za-z0-9-]+(\.[A-Za-z0-9]+)*(\.[A-Za-z]{2,})/', strtolower(Utils::formPrep($data['email_address'])))) {
				return Response::json([
					'status' => Config::get('constants.ERR_GENERAL'),
					'message' => Lang::get('messages.invalid_email')
				], 400);
			};
		}
		$email_address = strtolower(Utils::formPrep($data['email_address']));

		$dupe_check = User::where('id', '!=', $user->id)->where('email_address', '=', $email_address);
		if ($dupe_check->count() > 0) {
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'),
				'message' => Lang::get('messages.dupe_email')
			], 400);
		}

		$user->email_address = $email_address;
		$user->update();
		return Response::json([
			'status' => Config::get('constants.SUCCESS')
		], 200);
	}

	/**
	 * Register a push notification token with the current user
	 *
	 * @return Response
	 */
	public function register_token()
	{
		$request = Request::instance();
		$data = (array)json_decode($request->getContent(), true);

		$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
		$user = $token->user;

		if (!isset($data['token']) || (isset($data['token']) && trim($data['token']) == '')) {
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'),
				'message' => Lang::get('messages.missing_token')
			], 400);
		}

		if (!isset($data['type']) || (isset($data['type']) && trim($data['type']) == '')) {
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'),
				'message' => Lang::get('messages.missing_token_type')
			], 400);
		}

		$push_token = Utils::formPrep($data['token']);
		$push_token = str_replace(['&lt;', '&gt;'], ['<', '>'], $push_token);
		$tt = Utils::formPrep($data['type']);
		switch($tt) {
			case "ios":
				$token_type = 'apns';
				break;
			case "android":
				$token_type = 'gcm';
				break;
			default:
				$token_type = '';
		}
		if ($token_type == '') {
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'),
				'message' => Lang::get('messages.invalid_token_type')
			], 400);
		}

		$dupe_check = User::where('id', '!=', $user->id)->where('push_token', '=', $push_token)->where('token_type', '=', $token_type);
		if ($dupe_check->count() > 0) {
			foreach($dupe_check->get() as $dupe) {
				$dupe->push_token = null;
				$dupe->update();
			}
//			return Response::json([
//				'status' => Config::get('constants.ERR_GENERAL'),
//				'message' => Lang::get('messages.dupe_token')
//			], 400);
		}

		$user->push_token = $push_token;
		$user->token_type = $token_type;
		$user->update();
		return Response::json([
			'status' => Config::get('constants.SUCCESS')
		], 200);
	}

	/**
	 * Send a push notification
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function send_push()
	{
		$request = Request::instance();
		$data = (array)json_decode($request->getContent(), true);

		$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
		$user = $token->user;

		if (!isset($data['message']) || (isset($data['message']) && trim($data['message']) == '')) {
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'),
				'message' => Lang::get('messages.missing_message')
			], 400);
		}

		if ($user->push_token == '' || $user->token_type == '') {
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'),
				'message' => Lang::get('messages.device_not_registered')
			], 400);
		}

		$app_type = 'peepsout.';
		if ($user->token_type == 'apns') {
			$app_type .= 'ios';
		} else {
			$app_type .= 'android';
		}
		if (Config::get('app.debug')) {
			$app_type .= '.dev';
		} else {
			$app_type .= '.prd';
		}

		PushNotification::app($app_type)
			->to($user->push_token)
			->send($data['message']);
		return Response::json([
			'status' => Config::get('constants.SUCCESS')
		], 200);
	}

	/**
	 * Reset user's badge count
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function reset_pushes()
	{
		$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
		$user = $token->user;

		$user->pushes = 0;
		$user->update();
		return Response::json([
			'status' => Config::get('constants.SUCCESS')
		], 200);
	}

	public function update_location()
	{
		$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
		$device_id = $token->device_id;
		$user = $token->user;
		if ($user->push_token != '') {
			$poll_anchor = Utils::purify(microtime());
			$timezone = Input::get('timezone', -18000);
			$timestamp = Input::get('timestamp', strtotime('now'));
			$lat = Input::get('lat', 40.758895);
			$lon = Input::get('lon', -73.985131);
			if ($lat == '') {
				$lat = 40.758895;
			}
			if ($lon == '') {
				$lon = -73.985131;
			}
			$low_count = 99999999999;
			$start_count = 0;

			$today_day = date('l', $timestamp);
			$user_gmt_time = intval($timestamp) - intval($timezone);
			$all_venues = $returned_venues = $distances = [];

			$fence_setting = Setting::where('key', '=', 'fence')->first();
			$feet_fence = $fence_setting->value;

			$user_favourites = Favourite::getFavourites($user->user_id);

			// Clean older pushed venues
			$old_pushed_venues = PushedAnchors::where('created_at', '<', strtotime("-8 hours"));
			if ($old_pushed_venues->count() > 0) {
				foreach ($old_pushed_venues->get() as $old_venue) {
					$old_venue->delete();
				}
			}

			// Get active pushed venues for user
			$pushed_venues = [];
			$existing_pushed_venues = PushedAnchors::where('device_id', '=', $device_id);
			if ($existing_pushed_venues->count() > 0) {
				foreach ($existing_pushed_venues->get() as $pushed_venue) {
					$found_venues = json_decode($pushed_venue['response_data'], true);
					foreach($found_venues as $k => $v) {
						$pushed_venues[$v['venue_id']] = 1;
					}
				}
			}

			$existing_venues = Venue::where('soft_delete', '=', 0);
			foreach ($existing_venues->get() as $venue) {
				if (!isset($pushed_venues[$venue->venue_id])) {
					list($distance, $venue_key) = Venue::getDistance($lat, $lon, $venue->location_lat, $venue->location_lon, $start_count, $low_count);
					$feet_distance = $distance * 5280;

					if ($feet_distance < $feet_fence) {
						// Venue inside fence
						list($is_streaming, $next_stream_in, $sched_frames) = Venue::checkStream($venue, $today_day, $user_gmt_time);

						if ($is_streaming == 1) {

							$distances[$venue_key] = $distance;
							$venue_categories = Venue::getCategories($venue->category_id);

							$feed_schedule = [];
							$schedule = (array)json_decode($venue->feed_schedule, true);
							foreach ($schedule as $k => $v) {
								if (isset($v['days']) && count($v['days']) > 0) {
									$feed_schedule[] = ['days' => $v['days'], 'hours' => $v['hours']];
								}
							}

							$city = City::where('id', '=', $venue->city)->first();
							$city_name = $city->name;

							$all_venues[$venue_key] = [
								'venue_id' => $venue->venue_id,
								'name' => $venue->name,
								'categories' => $venue_categories,
								'city' => $city_name,
								'feed' => $venue->feed,
								'favourite' => (isset($user_favourites[$venue->venue_id]) ? 1 : 0),
								'streaming' => $is_streaming,
								'stream_in' => $next_stream_in,
								'location' => [
									'lat' => $venue->location_lat,
									'lon' => $venue->location_lon,
									'distance' => number_format($distance, 2, '.', '')
								],
								'sched_frames' => $sched_frames
							];
							$start_count++;
						}
					}
				}
			}
			@asort($distances);
			foreach ($distances as $key => $distance) {
				$returned_venues[] = $all_venues[$key];
			}

			if (count($returned_venues) > 0) {
				$existing_anchors = PushedAnchors::where('device_id', '=', $device_id);
				if ($existing_anchors->count() > 0) {
					foreach ($existing_anchors->get() as $anchor) {
						$anchor->delete();
					}
				}
				$new_anchor = new PushedAnchors();
				$new_anchor->anchor = $poll_anchor;
				$new_anchor->device_id = $device_id;
				$new_anchor->response_data = json_encode($returned_venues);
				$new_anchor->save();

				$text_message = 'You are near a few streaming PeepsOut venues with specials.';
				if (count($returned_venues) == 1) {
					$text_message = 'You are near one of our streaming PeepsOut venue with specials.';
				}

				$badge = $user->pushes;
				$badge++;
				Queue::push('PNDSender', ['token' => $user->push_token, 'message' => $text_message, 'badge' => $badge, 'anchor' => $poll_anchor]);
				$user->pushes = $badge;
				$user->update();
			}

			Metric::registerCall('users/location', Request::getClientIp(), Config::get('constants.SUCCESS'), '');
			return Response::json(['status' => Config::get('constants.SUCCESS')], 200);
		}

		Metric::registerCall('users/location', Request::getClientIp(), Config::get('constants.ERR_GENERAL'), '');
		return Response::json([
			'status' => Config::get('constants.ERR_GENERAL'),
			'message' => Lang::get('messages.device_not_registered')
		], 400);
	}

	public function get_pushed($anchor_id)
	{
		$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
		$device_id = $token->device_id;
		$user = $token->user;

		$existing_anchor = PushedAnchors::where('device_id', '=', $device_id)->where('anchor', '=', $anchor_id);
		if ($existing_anchor->count() > 0) {
			$anchor_data = $existing_anchor->first();
			$venues = json_decode($anchor_data->response_data, true);
			$response = [
				'status' => Config::get('constants.SUCCESS'),
				'total_results' => count($venues),
				'venues' => $venues
			];
			Metric::registerCall('users/pushed/' . $anchor_id, Request::getClientIp(), Config::get('constants.SUCCESS'), '');
			return Response::json($response, 200);
		}
		Metric::registerCall('users/pushed/' . $anchor_id, Request::getClientIp(), Config::get('constants.ERR_GENERAL'), '');
		return Response::json([
			'status' => Config::get('constants.ERR_GENERAL'),
			'message' => Lang::get('messages.anchor_not_found')
		], 400);
	}
}




























