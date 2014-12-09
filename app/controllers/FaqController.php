<?php

class FaqController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$response = [
			'status' => Config::get('constants.SUCCESS'), 
			'faqs' => []
		];

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
