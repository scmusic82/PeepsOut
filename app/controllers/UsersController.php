<?php

class UsersController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		//
	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{	
		if (Request::header('Authorization')) {
			$request = Request::instance();
			$data = (array)json_decode($request->getContent());
			$auth_token = Request::header('Authorization');
			
		}
		return Response::json(['status' => 2, 'message' => Lang::get('messages.auth_error')], 401);
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
	public function show($user_id)
	{
		if (Request::header('Authorization')) {
			$auth_token = Request::header('Authorization');
			$now = date('Y-m-d H:i:s');
			if (Token::checkToken($auth_token)) {
				$token = Token::where('auth_token', '=', $auth_token)->first();
				$response = $token->user->toArray();
				unset($response['id']);
				$response['status'] = 1;
				return Response::json($response, 200);
			}
		}
		return Response::json(['status' => 2, 'message' => Lang::get('messages.auth_error')], 401);
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
	public function update($user_id)
	{
		if (Request::header('Authorization')) {
			$request = Request::instance();
			$data = (array)json_decode($request->getContent());
			$auth_token = Request::header('Authorization');
			if (Token::checkToken($auth_token)) {
				
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
	public function destroy($user_id)
	{
		if (Request::header('Authorization')) {
			$request = Request::instance();
			$data = (array)json_decode($request->getContent(), true);
			$auth_token = Request::header('Authorization');
			if (Token::checkToken($auth_token)) {
				if (isset($data['device_id'])) {
					$token = Token::where('auth_token', '=', $auth_token);
					$user = User::where('user_id', '=', $data['user_id'])->where('device_id', '=', $data['device_id']);
					if ($user->count() > 0 && $token->count() > 0) {
						$user_data = $user->first();
						if ($user_data->user_id == $user_id) {
							$token->delete();
							return Response::json(['status' => 1], 200);
						}
					}
				} else {
					return Response::json(['status' => 0, 'message' => Lang::get('messages.missing_call_params')], 400);
				}
			}
		}
		return Response::json(['status' => 2, 'message' => Lang::get('messages.auth_error')], 401);
	}


}
