<?php

class CategoriesController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		if (Request::header('Authorization')) {
			$auth_token = Request::header('Authorization');
			if (Token::checkToken($auth_token)) {
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
				return Response::json(['status' => 1, 'categories' => $categories], 200);
			}
		}
		return Response::json(['status' => 2, 'message' => Lang::get('messages.auth_error')], 401);
	}

}
