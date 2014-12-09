<?php

class CitiesController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$response = [
			'status' => Config::get('constants.SUCCESS'), 
			'cities' => []
		];

		$existing_cities = City::where('id', '>', 0)->orderBy('name');
		if ($existing_cities->count() > 0) {
			foreach($existing_cities->get() as $key => $val) {
				$city = [];
				$city['name'] = $val->name;
				$city['neighbourhoods'] = [];
				if ($val->neighbourhoods->count() > 0) {
					foreach($val->neighbourhoods as $neighbourhood) {
						$city['neighbourhoods'][] = $neighbourhood->name;
					}
				}
				$response['cities'][] = $city;
			}
		}
		return Response::json($response, 200);
	}

}