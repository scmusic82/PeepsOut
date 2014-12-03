<?php
class City extends Eloquent {

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'cities';

    public static function generateCode($size = 8, $prefix = 'T', $suffix = '')
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

		$duplicates = City::where('city_id', '=', $code);
		while($duplicates->count() > 0) {
			$code = self::generateCode($size, $suffix);
			$duplicates = City::where('city_id', '=', $code);
		}

		return $code;
	}
	
}
