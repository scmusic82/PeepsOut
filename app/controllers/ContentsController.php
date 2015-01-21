<?php

class ContentsController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$existing = Content::where('id', '>', 0)->orderBy('id');
		if ($existing->count() > 0) {
			$content = $existing->get();
		} else {
			$content = [];
		}
		return Response::json([
			'status' => Config::get('constants.SUCCESS'),
			'total_count' => $existing->count(),
			'content' => $content
		], 200);
	}

	/**
	 * Show the details for the content_id
	 *
	 * @param int
	 * @return Response
	 */
	public function details($content_id)
	{
		$existing = Content::where('id', '=', $content_id);
		if ($existing->count() == 0) {
			return Response::json([
				'status' => Config::get('constants.ERR_GENERAL'),
				'message' => Lang::get('messages.content_not_found')
			], 400);
		}
		$content = $existing->first();
		return Response::json([
			'status' => Config::get('constants.SUCCESS'),
			'content' => [
				'title' => $content->title,
				'content' => strip_tags($content->content),
				'image' => $content->image
			]
		], 200);
	}

}
