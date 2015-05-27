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

    /**
     * Get live stream data
     *
     * @param $url
     * @return mixed
     */
	public static function grabStreamData($url)
	{
		$url_port = 80;
		$components = parse_url($url);
		if (isset($components['port'])) {
			$url_port = $components['port'];
		}
		$url = str_replace(':' . $url_port, '', $url);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_PORT, $url_port);
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
	public static function checkStream($venue, $timestamp, $timezone)
	{
        $is_streaming = 0;
        $next_stream_in = "";
        $grab_next = false;

        $schedule = json_decode($venue->feed_schedule, true);
        if (count($schedule) == 0) {
            return [0, ''];
        }

        if ($timestamp == 0) {
            $timestamp = intval(strtotime("now"));
        }
        if ($timezone == 0) {
            $timezone = intval(date("Z"));
        }

        $venue_timezone = Config::get('app.timezone');
        $existing_timezone = Timezone::where('id', '=', $venue->timezone_id);
        if ($existing_timezone->count() > 0) {
            $v_timezone = $existing_timezone->first();
            $venue_timezone = $v_timezone->tz_literal;
        }

        $user_literal_timezone = '';
        $tz_hours = $timezone / 3600;
        $existing_user_timezone = Timezone::where('amount', '=', $tz_hours);
        if ($existing_user_timezone->count() > 0) {
            $user_timezone = $existing_user_timezone->first();
            $user_literal_timezone = $user_timezone->tz_literal;
        }

        if ($user_literal_timezone == '') {
            $user_time = $timestamp - $timezone;
        } else {
            $new_user_date = new DateTime('now', new DateTimeZone($user_literal_timezone));
            $new_user_date->setTimezone(new DateTimeZone("UTC"));
            $user_time = $new_user_date->getTimestamp();
        }
        $today = date('l');
        $time_frames = [];
        $debug = [];
        $days_offset = ["Monday" => 1, "Tuesday" => 2, "Wednesday" => 3, "Thursday" => 4, "Friday" => 5, "Saturday" => 6, "Sunday" => 7];
        foreach($schedule as $k => $v) {
            if (isset($v['days'])) {
                foreach($v['days'] as $k2 => $day) {

                    $day_offset = ($days_offset[$today] - $days_offset[$day]) * 86400;

                    $tmp = [];
                    $tmp['b'] = $user_time;

                    $h1 = $h2 = 0;
                    foreach ($v['hours'] as $k3 => $hour) {
                        if ($k3 == 0) {
                            $key = 'a';
                            if (strlen($hour) != 5) {
                                $h1 = '0001';
                                $hour = '00:01';
                            } else {
                                if ($hour == '00:00' || $hour == '24:00') {
                                    $hour = '00:01';
                                }
                                $h1 = intval(str_replace(':', '', $hour));
                            }
                        } else if ($k3 == 1) {
                            $key = 'c';
                            if (strlen($hour) != 5) {
                                $h1 = '2359';
                                $hour = '23:59';
                            } else {
                                if ($hour == '00:00' || $hour == '24:00') {
                                    $hour = '23:59';
                                }
                                $h2 = intval(str_replace(':', '', $hour));
                            }
                        }
                        $new_date = new DateTime(date("Y-m-d") . " " . $hour . ":00", new DateTimeZone($venue_timezone));
                        $new_date->setTimezone(new DateTimeZone("UTC"));
                        $tmp[$key] = intval($new_date->getTimestamp()) - $day_offset;
                        $debug['dates'][$key] = $new_date->format('Y-m-d H:i:sP');
                    }
                    if ($h1 > 0 && $h2 > 0) {
                        if ($h1 > $h2) {
                            $tmp['c'] += 86400;
                        }
                    }
                    @asort($tmp);
                    $time_frames[] = $tmp;
                }
            }
        }

        foreach($time_frames as $time_frame) {
            $cards = implode('', array_keys($time_frame));
            if ($grab_next) {
                $date_time = new DateTime();
                $date_time->setTimestamp($time_frame['a']);
                $date_time->setTimezone(new DateTimeZone($venue_timezone));
                return [0, $date_time->format("l")];
            }
            if ($cards == 'abc' || $cards == 'cba') {
                $next_stream_in = 'LIVE';
                return [1, $next_stream_in];
            }
            if ($cards == 'bac') {
                // It'll open soon
                if ($time_frame['a'] - $time_frame['b'] > 3600) {
                    $next_stream_in = ceil(($time_frame['a'] - $time_frame['b']) / 3600);
                    if ($next_stream_in > 24) {
                        $date_time = new DateTime();
                        $date_time->setTimestamp($time_frame['a']);
                        $date_time->setTimezone(new DateTimeZone($venue_timezone));
                        $next_stream_in = $date_time->format("l");
                    } else {
                        $next_stream_in .= ' hrs';
                    }
                } else if ($time_frame['a'] - $time_frame['b'] > 60) {
                    $next_stream_in = ceil(($time_frame['a'] - $time_frame['b']) / 60).' min';
                }
                return [0, $next_stream_in];
            }
        }
        foreach($time_frames as $time_frame) {
            $date_time = new DateTime();
            $date_time->setTimestamp($time_frame['a']);
            $date_time->setTimezone(new DateTimeZone($venue_timezone));
            return [0, $date_time->format("l")];
        }
        return [0, ''];

//		$venue_feed_schedule = $venue->feed_schedule;
//		$venue_feed_timezone = $venue->feed_timezone;
//		$schedule = (array)json_decode($venue_feed_schedule, true);
//		$is_streaming = 0;
//		$next_stream_in = '';
//		$venue_timezone = 0;
//
//		if ($venue_feed_timezone > 0) {
//			$vtz = Timezone::find($venue_feed_timezone);
//			$tz_hours = $vtz->amount;
//			$venue_timezone = $tz_hours * 3600;
//		}
//
//		$sched_frames = [];
//		$sched_frames[$today_day]['b'] = $user_gmt_time;
//		foreach($schedule as $sched_key => $sched_val) {
//			foreach($sched_val['days'] as $day_key => $day_val) {
//				$check_hours = [];
//				foreach($sched_val['hours'] as $hour_key => $hour_val) {
//					if ($hour_val == '00:00') { $hour_val = '23:59'; }
//
//					if ($hour_key == 0) {
//						$sched_frames[$day_val]['a'] = intval(strtotime(date('Y-m-d ' . $hour_val . ':00'))) - intval($venue_timezone);
//					}
//					if ($hour_key == 1) {
//						$sched_frames[$day_val]['c'] = intval(strtotime(date('Y-m-d ' . $hour_val . ':00'))) - intval($venue_timezone);
//					}
//				}
//
//				// Check if it is passed midnight
//				/*
//				 * a: 26.01.2015 17:00    1700
//				 * b:
//				 * c: 27.01.2015 03:00		300
//				 */
//				if ($sched_frames[$day_val]['a'] > $sched_frames[$day_val]['c']) {
//					$sched_frames[$day_val]['c'] += 86400;
//					$sched_frames[$day_val]['d'] = 86400;
//				}
//
//				//@asort($sched_frames[$day_val]);
//			}
//		}
//
//		$all_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
//		foreach ($sched_frames as $day => $hours) {
//			if ($next_stream_in == '') {
//				if (isset($hours['a']) && isset($hours['b']) && isset($hours['c'])) {
//					if ($hours['b'] < $hours['a']) {
//						// Streams today
//						$is_streaming = 0;
//						$gmt_difference_sec = $hours['a'] - $hours['b'];
//						if ($gmt_difference_sec > 86400) {
//							foreach($all_days as $k => $d) {
//								if ($d == $day) {
//									if ($k == count($all_days)-1) {
//										$next_stream_in = $all_days[0];
//									} else {
//										$i = $k+1;
//										while(!isset($sched_frames[$all_days[$i]])) {
//											$i++;
//											if ($i == count($all_days)) { $i = 0; }
//										}
//										$next_stream_in = $all_days[$i];
//										if ($i == $k+1) {
//											$next_stream_in = 'Tomorrow';
//										}
//									}
//								}
//							}
//						} else if ($gmt_difference_sec > 3600) {
//			 				$next_stream_in = ceil($gmt_difference_sec / 3600).' hrs';
//			 			} else if ($gmt_difference_sec > 60) {
//			 				$next_stream_in = ceil($gmt_difference_sec / 60).' min';
//			 			}
//					} else if ($hours['b'] > $hours['a'] && $hours['b'] < $hours['c']) {
//						// Streams NOW
//						$is_streaming = 1;
//						$next_stream_in = 'LIVE';
//					} else if ($hours['b'] > $hours['c']) {
//						// Streams tomorrow or any other day of the week...
//						$is_streaming = 0;
//						foreach($all_days as $k => $d) {
//							if ($d == $day) {
//								if ($k == count($all_days)-1) {
//									$next_day = $all_days[0];
//								} else {
//									$i = $k+1;
//									while(!isset($sched_frames[$all_days[$i]])) {
//										$i++;
//										if ($i == count($all_days)) { $i = 0; }
//									}
//									$next_day = $all_days[$i];
//								}
//							}
//						}
//						$gmt_difference_sec = $sched_frames[$next_day]['a'] - $hours['b'];
//						if ($gmt_difference_sec > 86400) {
//							foreach($all_days as $k => $d) {
//								if ($d == $day) {
//									if ($k == count($all_days)-1) {
//										$next_stream_in = $all_days[0];
//										if ($day == 'Sunday' && $next_stream_in == 'Monday') {
//											$next_stream_in = 'Tomorrow';
//										}
//									} else {
//										$i = $k+1;
//										while(!isset($sched_frames[$all_days[$i]])) {
//											$i++;
//											if ($i == count($all_days)) { $i = 0; }
//										}
//										$next_stream_in = $all_days[$i];
//										if ($i == $k+1) {
//											$next_stream_in = 'Tomorrow';
//										}
//									}
//								}
//							}
//						} else if ($gmt_difference_sec > 3600) {
//			 				$next_stream_in = ceil($gmt_difference_sec / 3600).' hrs';
//			 			} else if ($gmt_difference_sec > 60) {
//			 				$next_stream_in = ceil($gmt_difference_sec / 60).' min';
//			 			}
//					}
//				}
//			}
//		}
//
//		if (isset($venue->feed) && $venue->feed != '' && preg_match('/http/', $venue->feed) && $is_streaming == 1) {
//			$contents = self::grabStreamData($venue->feed);
//			if ($contents !== false && !preg_match('/RESOLUTION/', $contents)) {
//				$is_streaming = 2;
//				$next_stream_in = 'LIVE';
//			}
//		}

		return [$is_streaming, $next_stream_in];
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
