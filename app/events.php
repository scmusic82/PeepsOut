<?php

Event::listen('log.call', function($endpoint, $ip, $request, $response, $response_code) {
    if (isset($request['q'])) {
        unset($request['q']);
    }

    DB::table('_api_log')->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-8 days')))->delete();
    DB::table('_api_log')->insert([
        'endpoint'      => $endpoint,
        'ip'            => $ip,
        'request'       => json_encode($request),
        'response'      => json_encode($response),
        'response_code' => $response_code,
        'created_at'    => date('Y-m-d H:i:s'),
        'updated_at'    => date('Y-m-d H:i:s')
    ]);
});