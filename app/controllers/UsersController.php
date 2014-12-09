<?php

class UsersController extends \BaseController {


	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($user_id)
	{
		$response_data = [
			'status' => Config::get('constants.SUCCESS')
		];

		$existing_user = User::where('user_id', '=', $user_id);
		if ($existing_user->count() == 1) {
			$user = $existing_user->first();
			if ($user->user_id == $user_id) {
				$response_data = array_merge($response_data, $user->toArray());
			}
		}
		
		return Response::json($response_data, 200);
	}

}
