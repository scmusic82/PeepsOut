<?php
class Venue extends Eloquent {

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'venues';

    public static function generateCode($size = 8, $prefix = 'V', $suffix = '')
	{
		$code = '';
		$seed = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
		srand(time());
		shuffle($seed);
		for($a = 1; $a <= $size; $a++) {
			$char = array_shift($seed);
			$code .= $char;
			$seed[] = $char;
			shuffle($seed);
		}

		$code = $prefix . $code . $suffix;

		$duplicates = Venue::where('venue_id', '=', $code);
		while($duplicates->count() > 0) {
			$code = self::generateCode($size, $suffix);
			$duplicates = Venue::where('venue_id', '=', $code);
		}

		return $code;
	}

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

		foreach($schedule as $sched_key => $sched_val) {
			if (in_array($today_day, $sched_val['days']) && isset($sched_val['hours'][0]) && isset($sched_val['hours'][1])) {
				$venue_gmt_start = intval(strtotime(date('Y-m-d ' . $sched_val['hours'][0] . ':00'))) - intval($venue_timezone);
				$venue_gmt_end = intval(strtotime(date('Y-m-d ' . $sched_val['hours'][1] . ':00'))) - intval($venue_timezone);
				if ($user_gmt_time > $venue_gmt_start && $user_gmt_time < $venue_gmt_end) {
					$is_streaming = 1;
					$next_stream_in = 'now';
				}
			}
			if (in_array($today_day, $sched_val['days']) && isset($sched_val['hours'][0]) && isset($sched_val['hours'][1]) && $is_streaming == 0) {
				$venue_gmt_start = intval(strtotime(date('Y-m-d ' . $sched_val['hours'][0] . ':00'))) - intval($venue_timezone);
				if ($user_gmt_time < $venue_gmt_start) {
					$gmt_difference_sec = $venue_gmt_start - $user_gmt_time;
					if ($gmt_difference_sec > 3600) {
						$next_stream_in = ceil($gmt_difference_sec / 3600).'hrs';
					} else if ($gmt_difference_sec > 60) {
						$next_stream_in = ceil($gmt_difference_sec / 60).'min';
					}
				}
			}
		}

		return [$is_streaming, $next_stream_in];
	}
}
