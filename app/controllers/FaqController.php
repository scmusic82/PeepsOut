<?php

class FaqController extends \BaseController {

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

				$response = ['status' => 1, 'faqs' => []];

				$existing_faqs = Faq::where('id', '>', 0)->orderBy('question');
				if ($existing_faqs->count() > 0) {
					foreach($existing_faqs->get() as $faq) {
						$response['faqs'][] = [
							"Q" => $faq->question,
							"A" => $faq->answer
						];
					}
				}

				return Response::json($response, 200);
			}
		}
		return Response::json(['status' => 2, 'message' => Lang::get('messages.auth_error')], 401);
	}

}
