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
				$utils = App::make('Utils');
				$device_id = $utils::purify($request_data['device_id']);

				$user_exists = User::where('device_id', '=', $device_id);
				if ($user_exists->count() == 0) {
					// New user
					$user = new User();

					$token = Token::generateToken($device_id);
					Token::saveToken($token, $device_id);

					$user_id = User::generateCode();
					$user->device_id = $device_id;
					$user->user_id = $user_id;
					$user->save();

					return Response::json(['status' => 2, 'auth_token' => $token, 'user_id' => $user_id], 200);
				} else {
					// Existing user
					$now = date('Y-m-d H:i:s');
					$db_user = $user_exists->first();
					if (strtotime($db_user->token->expires_at) < strtotime($now)) {
						// Token expired - generate new one and return
						
						$token = Token::renewToken($db_user->token->auth_token, $device_id);
						if ($token == '') {
							return Response::json(['status' => 2, 'message' => Lang::get('messages.auth_error')], 401);
						}

						$returnData = ['status' => 1, 'auth_token' => $token, 'user_id' => $db_user->user_id, 'name' => $db_user->name, 'photo' => $db_user->photo];
					} else {
						// Token still alive and kicking - return it
						$returnData = ['status' => 1, 'auth_token' => $db_user->token->auth_token, 'user_id' => $db_user->user_id, 'name' => $db_user->name, 'photo' => $db_user->photo];
					}
					return Response::json($returnData, 200);
				}
			} else {
				return Response::json(['status' => 0, 'message' => Lang::get('messages.missing_device_id')], 400);
			}
		} else {
			return Response::json(['status' => 0, 'message' => Lang::get('messages.missing_call_params')], 400);
		}
	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		//
	}


	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		//
	}


	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		//
	}


	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		//
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($device_id)
	{
		if (Request::header('Authorization')) {
			$request = Request::instance();
			$data = (array)json_decode($request->getContent());
			$auth_token = Request::header('Authorization');
			$now = date('Y-m-d H:i:s');
			if (Token::checkToken(Request::header('Authorization'))) {
				$new_token = Token::renewToken(Request::header('Authorization'), $device_id);
				if ($new_token == '') {
					$new_token = Request::header('Authorization');
				}
				return Response::json(['status' => 1, 'auth_token' => $new_token], 200);
			} else {
				$new_token = Token::renewToken(Request::header('Authorization'), $device_id);
				return Response::json(['status' => 1, 'auth_token' => $new_token], 200);
			}
		}
		return Response::json(['status' => 2, 'message' => Lang::get('messages.auth_error')], 401);
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($device_id)
	{
		if (Request::header('Authorization')) {
			$request = Request::instance();
			$data = (array)json_decode($request->getContent());
			$auth_token = Request::header('Authorization');
			$now = date('Y-m-d H:i:s');
			if (Token::checkToken(Request::header('Authorization'))) {
				$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
				Token::invalidateToken(Request::header('Authorization'), $token->device_id);
				return Response::json(['status' => 1], 200);
			} else {
				return Response::json(['status' => 2, 'message' => Lang::get('messages.auth_error')], 401);
			}
		}
		return Response::json(['status' => 2, 'message' => Lang::get('messages.auth_error')], 401);
	}


}
