<?php

class UsersController extends \BaseController {

	/**
	 * Register / Update an email address with the current user
	 *
	 * @return Response
	 */
	public function register_email() {
		$request = Request::instance();
		$data = (array)json_decode($request->getContent(), true);

		$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
		$user = $token->user;

		if (!isset($data['email_address']) || (isset($data['email_address']) && trim($data['email_address']) == '')) {
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'),
				'message' => Lang::get('messages.missing_email')
			], 400);
		}

		if (isset($data['email_address']) && trim($data['email_address']) != '') {
			if (!preg_match('/[_A-Za-z0-9-\+]+(\.[_A-Za-z0-9-]+)*@[A-Za-z0-9-]+(\.[A-Za-z0-9]+)*(\.[A-Za-z]{2,})/', strtolower(Utils::formPrep($data['email_address'])))) {
				return Response::json([
					'status' => Config::get('constants.ERR_GENERAL'),
					'message' => Lang::get('messages.invalid_email')
				], 400);
			};
		}
		$email_address = strtolower(Utils::formPrep($data['email_address']));

		$dupe_check = User::where('id', '!=', $user->id)->where('email_address', '=', $email_address);
		if ($dupe_check->count() > 0) {
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'),
				'message' => Lang::get('messages.dupe_email')
			], 400);
		}

		$user->email_address = $email_address;
		$user->update();
		return Response::json([
			'status' => Config::get('constants.SUCCESS')
		], 200);
	}

	/**
	 * Register a push notification token with the current user
	 *
	 * @return Response
	 */
	public function register_token()
	{
		$request = Request::instance();
		$data = (array)json_decode($request->getContent(), true);

		$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
		$user = $token->user;

		if (!isset($data['token']) || (isset($data['token']) && trim($data['token']) == '')) {
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'),
				'message' => Lang::get('messages.missing_token')
			], 400);
		}

		if (!isset($data['type']) || (isset($data['type']) && trim($data['type']) == '')) {
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'),
				'message' => Lang::get('messages.missing_token_type')
			], 400);
		}

		$push_token = Utils::formPrep($data['token']);
		$push_token = str_replace(['&lt;', '&gt;'], ['<', '>'], $push_token);
		$tt = Utils::formPrep($data['type']);
		switch($tt) {
			case "ios":
				$token_type = 'apns';
				break;
			case "android":
				$token_type = 'gcm';
				break;
			default:
				$token_type = '';
		}
		if ($token_type == '') {
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'),
				'message' => Lang::get('messages.invalid_token_type')
			], 400);
		}

		$dupe_check = User::where('id', '!=', $user->id)->where('push_token', '=', $push_token)->where('token_type', '=', $token_type);
		if ($dupe_check->count() > 0) {
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'),
				'message' => Lang::get('messages.dupe_token')
			], 400);
		}

		$user->push_token = $push_token;
		$user->token_type = $token_type;
		$user->update();
		return Response::json([
			'status' => Config::get('constants.SUCCESS')
		], 200);
	}

	/**
	 * Send a push notification
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function send_push()
	{
		$request = Request::instance();
		$data = (array)json_decode($request->getContent(), true);

		$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
		$user = $token->user;

		if (!isset($data['message']) || (isset($data['message']) && trim($data['message']) == '')) {
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'),
				'message' => Lang::get('messages.missing_message')
			], 400);
		}

		if ($user->push_token == '' || $user->token_type == '') {
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'),
				'message' => Lang::get('messages.device_not_registered')
			], 400);
		}

		$app_type = 'peepsout.';
		if ($user->token_type == 'apns') {
			$app_type .= 'ios';
		} else {
			$app_type .= 'android';
		}
		if (Config::get('app.debug')) {
			$app_type .= '.dev';
		} else {
			$app_type .= '.prd';
		}

		PushNotification::app($app_type)
			->to($user->push_token)
			->send($data['message']);
		return Response::json([
			'status' => Config::get('constants.SUCCESS')
		], 200);
	}

	public function reset_pushes()
	{
		$token = Token::where('auth_token', '=', Request::header('Authorization'))->first();
		$user = $token->user;

		$user->pushes = 0;
		$user->update();
		return Response::json([
			'status' => Config::get('constants.SUCCESS')
		], 200);
	}
}
