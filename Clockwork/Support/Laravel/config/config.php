<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | Enable Clockwork
    |--------------------------------------------------------------------------
    |
    | You can explicitly enable or disable Clockwork here. When enabled, special
    | headers for communication with the Clockwork Chrome extension will be
    | included in your application responses and requests data will be available
    | at /__clockwork url.
    | When set to null, Clockwork behavior is controlled by app.debug setting.
    | Default: null
    |
    */

    'enable' => null,

    /*
    |--------------------------------------------------------------------------
    | Enable data collection, when Clockwork is disabled
    |--------------------------------------------------------------------------
    |
    | This setting controls, whether data about application requests will be
    | recorded even when Clockwork is disabled (useful for later analysis).
    | Default: false
    |
    */

    'collect_data_always' => false,

    /*
    |--------------------------------------------------------------------------
    | Filter collected data
    |--------------------------------------------------------------------------
    |
    | You can filter collected data by specifying what you don't want to collect
    | here.
    |
    */

    'filter' => array(
        'routes',    // collecting routes data on every request might use a lot of disk space
        'viewsData', // collecting views data, including all variables passed to the view on every request might use a lot of disk space
    ),

    /*
    |--------------------------------------------------------------------------
    | Disable data collection for certain URIs
    |--------------------------------------------------------------------------
    |
    | You can disable data collection for specific URIs by adding matching
    | regular expressions here.
    |
    */

    'filter_uris' => array(
        '/__clockwork/.*', // disable collecting data for clockwork-web assets
    ),

    /*
    |--------------------------------------------------------------------------
    | Send Headers for AJAX request
    |--------------------------------------------------------------------------
    |
    | When trying to collect data the AJAX method can sometimes fail if it is 
    | missing required headers. For example, an API might require a version 
    | number using Accept headers to route the HTTP request to the correct 
    | codebase.
    |
    */

    'headers' => array(
        // 'Accept' => 'application/vnd.com.whatever.v1+json',
    ),

);
