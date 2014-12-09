<?php

class CategoriesController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		
		$existing = Category::where('id', '>', 0)->orderBy('name');
		$categories = [
			['category_id' => '', 'name' => 'All Venues', 'stub' => '']
		];
		if ($existing->count() > 0) {
			$result = $existing->get();
			foreach($result as $category) {
				$categories[] = [
					'category_id' => $category->category_id, 
					'name' => $category->name, 
					'stub' => $category->stub
				];
				$category->impressions++;
				$category->update();
			}
		}
		return Response::json([
			'status' => Config::get('constants.SUCCESS'), 
			'categories' => $categories
			], 200);
	}

	
	public static function testout()
	{
		return Utils::testdb();
	}
}
