<?php
class Venue extends Eloquent {

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'venues';

	/**
	* Calculate the distance from the user to the venue based on two sets of coordinates
	*
	* @param 	float 	user's latitude
	* @param 	float 	user's longitude
	* @param 	float 	venue's latitude
	* @param 	float 	venue's longitude
	* @param 	int 	key driver for the venues array
	* @param 	int 	key driver for the venues with no coordinates, used to place them at the bottom
	*
	* @return 	array
	*/
	public static function getDistance($lat1, $lon1, $lat2, $lon2, $start_count = 0, $low_count = 99999999999)
	{
		if ($lat2 != '' && $lon2 != '') {
			$earth_radius = 3960;
			$delta_lat = $lat2 - $lat1;
			$delta_lon = $lon2 - $lon1;
			$distance  = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($delta_lon)) ;
			$distance  = acos($distance);
			$distance  = rad2deg($distance);
			$distance  = $distance * 60 * 1.1515;
			$distance  = round($distance, 4);
			$venue_key = $start_count;
		} else {
			$distance = $low_count - $start_count;
			$venue_key = $low_count - $start_count;
		}
		return [$distance, $venue_key];
	}

	/**
	* Return an array of categories with their name, id and stub
	*
	* @param 	string 		JSON formatted string extracted from the venue entry 
	* @return 	array
	*/
	public static function getCategories($categories_json = '')
	{
		$categories = Category::getCategories();
		$venue_categories = [];
		$vc = (array)json_decode($categories_json, true);
		foreach($vc as $venue_category) {
			if (isset($categories[$venue_category])) {
				$venue_categories[] = [
					'name' => $categories[$venue_category]['name'],
					'id' => $venue_category,
					'stub' => $categories[$venue_category]['stub']
				];
			}
		}
		return $venue_categories;
	}

	public static function grabStreamData($url)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
	}

	/**
	* Return an array containing if the venue is streaming or not and when it should start next
	*
	* @param 	object 		The venue object
	* @param 	int 		The timezone of the venue
	* @param 	string 		The user's literal day name
	* @param 	int 		The user's date and time reported to GTM
	* @return 	array
	*/
	public static function checkStream($venue, $today_day, $user_gmt_time)
	{
		$venue_feed_schedule = $venue->feed_schedule;
		$venue_feed_timezone = $venue->feed_timezone;
		$schedule = (array)json_decode($venue_feed_schedule, true);
		$is_streaming = 0;
		$next_stream_in = '';
		$venue_timezone = 0;

		if ($venue_feed_timezone > 0) {
			$vtz = Timezone::find($venue_feed_timezone);
			$tz_hours = $vtz->amount;
			$venue_timezone = $tz_hours * 3600;
		}

		$sched_frames = [];
		$sched_frames[$today_day]['b'] = $user_gmt_time;
		foreach($schedule as $sched_key => $sched_val) {
			foreach($sched_val['days'] as $day_key => $day_val) {
				$check_hours = [];
				foreach($sched_val['hours'] as $hour_key => $hour_val) {
					if ($hour_val == '00:00') { $hour_val = '23:59'; }

					if ($hour_key == 0) {
						$sched_frames[$day_val]['a'] = intval(strtotime(date('Y-m-d ' . $hour_val . ':00'))) - intval($venue_timezone);
					}
					if ($hour_key == 1) {
						$sched_frames[$day_val]['c'] = intval(strtotime(date('Y-m-d ' . $hour_val . ':00'))) - intval($venue_timezone);
					}
				}

				// Check if it is passed midnight
				/*
				 * a: 26.01.2015 17:00    1700
				 * b:
				 * c: 27.01.2015 03:00		300
				 */
				if ($sched_frames[$day_val]['a'] > $sched_frames[$day_val]['c']) {
					$sched_frames[$day_val]['c'] += 86400;
					$sched_frames[$day_val]['d'] = 86400;
				}

				//@asort($sched_frames[$day_val]);
			}
		}
		
		$all_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
		foreach ($sched_frames as $day => $hours) {
			if ($next_stream_in == '') {
				if (isset($hours['a']) && isset($hours['b']) && isset($hours['c'])) {
					if ($hours['b'] < $hours['a']) {
						// Streams today
						$is_streaming = 0;
						$gmt_difference_sec = $hours['a'] - $hours['b'];
						if ($gmt_difference_sec > 86400) {
							foreach($all_days as $k => $d) {
								if ($d == $day) {
									if ($k == count($all_days)-1) {
										$next_stream_in = $all_days[0];
									} else {
										$i = $k+1;
										while(!isset($sched_frames[$all_days[$i]])) {
											$i++;
											if ($i == count($all_days)) { $i = 0; }
										}
										$next_stream_in = $all_days[$i];
										if ($i == $k+1) {
											$next_stream_in = 'Tomorrow';
										}
									}
								}
							}
						} else if ($gmt_difference_sec > 3600) {
			 				$next_stream_in = ceil($gmt_difference_sec / 3600).' hrs';
			 			} else if ($gmt_difference_sec > 60) {
			 				$next_stream_in = ceil($gmt_difference_sec / 60).' min';
			 			}
					} else if ($hours['b'] > $hours['a'] && $hours['b'] < $hours['c']) {
						// Streams NOW
						$is_streaming = 1;
						$next_stream_in = 'LIVE';
					} else if ($hours['b'] > $hours['c']) {
						// Streams tomorrow or any other day of the week...
						$is_streaming = 0;
						foreach($all_days as $k => $d) {
							if ($d == $day) {
								if ($k == count($all_days)-1) {
									$next_day = $all_days[0];
								} else {
									$i = $k+1;
									while(!isset($sched_frames[$all_days[$i]])) {
										$i++;
										if ($i == count($all_days)) { $i = 0; }
									}
									$next_day = $all_days[$i];
								}
							}
						}
						$gmt_difference_sec = $sched_frames[$next_day]['a'] - $hours['b'];
						if ($gmt_difference_sec > 86400) {
							foreach($all_days as $k => $d) {
								if ($d == $day) {
									if ($k == count($all_days)-1) {
										$next_stream_in = $all_days[0];
										if ($day == 'Sunday' && $next_stream_in == 'Monday') {
											$next_stream_in = 'Tomorrow';
										}
									} else {
										$i = $k+1;
										while(!isset($sched_frames[$all_days[$i]])) {
											$i++;
											if ($i == count($all_days)) { $i = 0; }
										}
										$next_stream_in = $all_days[$i];
										if ($i == $k+1) {
											$next_stream_in = 'Tomorrow';
										}
									}
								}
							}
						} else if ($gmt_difference_sec > 3600) {
			 				$next_stream_in = ceil($gmt_difference_sec / 3600).' hrs';
			 			} else if ($gmt_difference_sec > 60) {
			 				$next_stream_in = ceil($gmt_difference_sec / 60).' min';
			 			}
					}
				}
			}
		}

		if (isset($venue->feed) && $venue->feed != '' && preg_match('/http/', $venue->feed) && $is_streaming == 1) {
			//$contents = file_get_contents($venue->feed);
			$contents = self::grabStreamData($venue->feed);
			if (!preg_match('/RESOLUTION/', $contents)) {
				$is_streaming = 2;
				$next_stream_in = 'LIVE';
			}
		}

		return [$is_streaming, $next_stream_in, $sched_frames];
	}

    public static function getVenueSpecials($specials = [])
    {
        $venue_specials = [];
        $today = strtotime('now');
        foreach($specials as $k => $v) {
            $special = [];
            if (isset($v['description'])) {
				$special['event'] = $v['event'];
				$special['description'] = Utils::restoreTags($v['description']);
				$special['day'] = $v['day'];
				if (trim($v['from']) != '' && trim($v['until']) != '' && $v['from'] == $v['until']) {
					$special['starts'] = 'Only on ' . date("F jS, Y", strtotime($v['from'])) . '.';
					$special['ends'] = '';
				} else if (trim($v['from']) != '' && trim($v['until']) != '') {
                    // Timed
                    if ($today > strtotime($v['from'] . ' 00:00:00') && $today < strtotime($v['until'] . ' 23:59:59')) {
						$special['starts'] = 'From '. date("F jS, Y", strtotime($v['from']));
						$special['ends'] = 'until ' . date("F jS, Y", strtotime($v['until'])) . '.';
                    }
                } else if (trim($v['from']) != '' && trim($v['forever']) == '1') {
                    // Never finishes
                    //if ($today > strtotime($v['from'] . ' 00:00:00')) {
                        // Is happening
						$special['starts'] = 'Starting on ' . date("F jS, Y", strtotime($v['from'])) . '.';
						$special['ends'] = '';
                    //}
                } else {
					$special['starts'] = $special['ends'] = '';
				}
            }
            if (count($special) > 0) { $venue_specials[$v['group']][] = $special; }
        }
        return $venue_specials;
    }
}
