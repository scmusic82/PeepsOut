<?php

class SettingsController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$response = [
			'status' => Config::get('constants.SUCCESS')
		];

		$existing_settings = Setting::where('id', '>', 0);
		if ($existing_settings->count() > 0) {
			foreach($existing_settings->get() as $setting) {
				if ($setting->key == 'logo' && $setting->value != '') {
					$response[$setting->key] = Config::get('constants.IMG_HOST') . trim($setting->value, '/');
				} else {
					$response[$setting->key] = $setting->value;
				}
			}
		}

		Metric::registerCall('settings', Request::getClientIp(), Config::get('constants.SUCCESS'), '');
		return Response::json($response, 200);
	}
}