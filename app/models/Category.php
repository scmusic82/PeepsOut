<?php
class Category extends \BaseModel {

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'categories';

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
