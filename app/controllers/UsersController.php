<?php

class UsersController extends \BaseController {


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

}
