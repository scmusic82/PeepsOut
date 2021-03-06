<?php

class AuthController extends BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$request = Request::instance();
        $content = $request->getContent();
		if ($content != '') {
			$request_data = (array)json_decode($content, true);
			if (isset($request_data['device_id'])) {
				Metric::registerCall('auth', Request::header("x-forwarded-for"), 1, '');
				$device_id = Utils::purify($request_data['device_id']);

				$user_exists = User::where('device_id', '=', $device_id);
				if ($user_exists->count() == 0) {

					$user = new User();
					$token = Tkn::createToken(['device_id' => $device_id]);
					$user_id = Utils::generateCode(['table' => 'users', 'field' => 'user_id', 'prefix' => 'U']);
					$user->device_id = $device_id;
					$user->user_id = $user_id;
					$user->save();

					return Response::json([
						'status' => Config::get('constants.ERR_AUTH'), 
						'auth_token' => $token, 
						'user_id' => $user_id
						], 200);

				} else {
					
					$db_user = $user_exists->first();
					if (Tkn::expiredToken($device_id)) {
						$token = Tkn::renewToken($db_user->token->auth_token, $device_id);
						$return_data = [
							'status' => Config::get('constants.SUCCESS'), 
							'auth_token' => $token,
							'expires_at' => strtotime($db_user->token->expires_at),
							'user_id' => $db_user->user_id, 
							'name' => $db_user->name, 
							'photo' => $db_user->photo
						];
					} else {
						$return_data = [
							'status' => Config::get('constants.SUCCESS'), 
							'auth_token' => $db_user->token->auth_token,
							'expires_at' => strtotime($db_user->token->expires_at),
							'user_id' => $db_user->user_id, 
							'name' => $db_user->name, 
							'photo' => $db_user->photo
						];
					}
					return Response::json($return_data, 200);
				}
			} else {
				Metric::registerCall('auth', Request::header("x-forwarded-for"), Config::get('constants.ERR_GENERAL'), Lang::get('messages.missing_device_id'));
				return Response::json([
					'status' => Config::get('constants.ERR_GENERAL'), 
					'message' => Lang::get('messages.missing_device_id')
					], 401);
			}
		} else {
			Metric::registerCall('auth', Request::header("x-forwarded-for"), Config::get('constants.ERR_GENERAL'), Lang::get('messages.missing_call_params'));
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'), 
				'message' => Lang::get('messages.missing_call_params')
				], 401);
		}
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($device_id)
	{
		$auth_token = Tkn::renewToken(Request::header('x-authorization'), $device_id);
		Metric::registerCall('auth/renew', Request::header("x-forwarded-for"), Config::get('constants.SUCCESS'), '');
		return Response::json([
			'status' => Config::get('constants.SUCCESS'), 
			'auth_token' => $auth_token
			], 200);
	}
}
