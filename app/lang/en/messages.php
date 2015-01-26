<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | Messages Language Lines
    |--------------------------------------------------------------------------
    |
    | The following messages lines are used by the API to respond to calls.
    |
    */

    'auth_error' => 'Authentification error!',

    'missing_call_params'       => 'Missing call parameters',
    'missing_device_id'         => 'Missing [device_id] parameter',
    'missing_phone_number'      => 'Missing [phone_number] parameter',
    'missing_user_id'           => 'Missing [user_id] parameter',
    'invalid_user_id'           => 'Invalid [user_id] parameter',

    // Categories
    'missing_name_param'        => 'Missing call parameter [name]',
    'duplicate_category'        => 'Duplicate category name',
    'category_not_found'        => 'Specified category not found',

    // Search
    'missing_search_params'     => 'Missing search params [kw, category]',
    'missing_kw_param'          => 'Missing keywords parameter',
    'missing_category_param'    => 'Missing category parameter',

    // Venues
    'venue_not_found'           => 'Venue not found',

    // Users
    'missing_email'             => 'Email address is missing',
    'invalid_email'             => 'Email address is invalid',
    'dupe_email'                => 'Email address already in use',
    'missing_token'             => 'Push notification token is missing',
    'missing_token_type'        => 'Push notification token type is missing',
    'invalid_token_type'        => 'Invalid token type.',
    'dupe_token'                => 'Push notification token already exists',
    'missing_message'           => 'The push notification message is missing',
    'device_not_registered'     => 'The user\'s device is not registered for push notifications',
    'anchor_not_found'          => 'The specified anchor was not found',

    // Content
    'content_not_found'         => 'Specified content was not found',

);
