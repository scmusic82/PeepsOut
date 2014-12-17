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
	* @param 	lat1 		float 	user's latitude
	* @param 	lon1		float 	user's longitude
	* @param 	lat2		float 	venue's latitude
	* @param 	lon2 		float 	venue's longitude
	* @param 	start_count	int 	key driver for the venues array
	* @param 	low_count 	int 	key driver for the venues with no coordinates, used to place them at the bottom
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

	/**
	* Return an array containing if the venue is streaming or not and when it should start next
	*
	* @param 	string 		JSON formatted string extracted from the venue entry 
	* @param 	int 		The timezone of the venue
	* @param 	string 		The user's literal day name
	* @param 	int 		The user's date and time reported to GTM
	* @return 	array
	*/
	public static function checkStream($venue_feed_schedule, $venue_feed_timezone, $today_day, $user_gmt_time)
	{
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
				foreach($sched_val['hours'] as $hour_key => $hour_val) {
					if ($hour_val == '00:00') { $hour_val = '23:59'; }
					if ($hour_key == 0) {
						$sched_frames[$day_val]['a'] = intval(strtotime(date('Y-m-d ' . $hour_val . ':00'))) - intval($venue_timezone);
					}
					if ($hour_key == 1) {
						$sched_frames[$day_val]['c'] = intval(strtotime(date('Y-m-d ' . $hour_val . ':00'))) - intval($venue_timezone);
					}
				}
				@asort($sched_frames[$day_val]);
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

		return [$is_streaming, $next_stream_in];
	}

    public static function getVenueSpecials($specials = [])
    {
        $venue_specials = $venue_events = [];
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
        return [$venue_specials, $venue_events];
    }
}
