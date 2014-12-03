<?php
class Category extends Eloquent {

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'categories';

    public static function generateCode($size = 8, $prefix = 'C', $suffix = '')
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

		$duplicates = Category::where('category_id', '=', $code);
		while($duplicates->count() > 0) {
			$code = self::generateCode($size, $suffix);
			$duplicates = Category::where('category_id', '=', $code);
		}

		return $code;
	}

	public static function getCategories()
	{
		$returned = [];
		$existing = Category::where('id', '>', 0);
		if ($existing->count() > 0) {
			$categories = $existing->get();
			foreach($categories as $category) {
				$returned[$category->category_id] = ['name' => $category->name, 'stub' => $category->stub];
			}
		}
		return $returned;
	}
}
