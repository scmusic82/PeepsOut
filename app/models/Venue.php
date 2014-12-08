<?php
class Venue extends Eloquent {

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'venues';

	public static function getDistance($lat1, $lon1, $lat2, $lon2)
	{
		$earth_radius = 3960;
		$delta_lat = $lat2 - $lat1;
		$delta_lon = $lon2 - $lon1;
		$distance  = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($delta_lon)) ;
		$distance  = acos($distance);
		$distance  = rad2deg($distance);
		$distance  = $distance * 60 * 1.1515;
		$distance  = round($distance, 4);
		return $distance;
	}

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
}
