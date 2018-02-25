<?php

return [

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

	'enable' => env('CLOCKWORK_ENABLE', null),

	/*
	|--------------------------------------------------------------------------
	| Enable web UI
	|--------------------------------------------------------------------------
	|
	| Enable or disable the Clockwork web UI available at  http://your.app/__clockwork
	| Default: true
	|
	*/

	'web' => env('CLOCKWORK_WEB', true),

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

	'collect_data_always' => env('CLOCKWORK_COLLECT_DATA_ALWAYS', false),

	/*
	|--------------------------------------------------------------------------
	| Metadata storage
	|--------------------------------------------------------------------------
	|
	| You can configure how are the metadata collected by Clockwork stored.
	| Valid options are: files or sql.
	| Files storage stores the metadata in one-per-request files in a specified
	| directory.
	| Sql storage stores the metadata as rows in a sql database. You can specify
	| the database by name if defined in database.php or by path to Sqlite
	| database. Database table will be automatically created.
	| Sql storage requires PDO.
	|
	*/

	'storage' => env('CLOCKWORK_STORAGE', 'files'),

	'storage_files_path' => env('CLOCKWORK_STORAGE_FILES_PATH', storage_path('clockwork')),

	'storage_sql_database' => env('CLOCKWORK_STORAGE_SQL_DATABASE', storage_path('clockwork.sqlite')),
	'storage_sql_table'    => env('CLOCKWORK_STORAGE_SQL_TABLE', 'clockwork'),

	/*
	|--------------------------------------------------------------------------
	| Metadata expiration
	|--------------------------------------------------------------------------
	|
	| Maximum lifetime of the metadata in seconds, metadata for older requests
	| will automatically be deleted when storing new requests.
	| When set to false, metadata will never be deleted.
	| Default: 1 week
	|
	*/

	'storage_expiration' => env('CLOCKWORK_STORAGE_EXPIRATION', 60 * 24 * 7),

	/*
	|--------------------------------------------------------------------------
	| Filter collected data
	|--------------------------------------------------------------------------
	|
	| You can filter collected data by specifying what you don't want to collect
	| here.
	|
	*/

	'filter' => [
		'cacheQueries', // collecting cache queries in cache-heavy might have a negative performance impact and use a lot of disk space
		'routes', // collecting routes data on every request might use a lot of disk space
		'viewsData', // collecting views data, including all variables passed to the view on every request might use a lot of disk space
	],

	/*
	|--------------------------------------------------------------------------
	| Disable data collection for certain URIs
	|--------------------------------------------------------------------------
	|
	| You can disable data collection for specific URIs by adding matching
	| regular expressions here.
	|
	*/

	'filter_uris' => [
		'/__clockwork/.*', // disable collecting data for clockwork-web assets
	],

	/*
	|--------------------------------------------------------------------------
	| Ignored events
	|--------------------------------------------------------------------------
	|
	| Array of event names that will be ignored when collecting data for the "events" tab.
	| By default all framework-specific events are also ignored, set to false to log
	| all possible fired events.
	|
	*/

	'ignored_events' => [
	],

	/*
	|--------------------------------------------------------------------------
	| Register helpers
	|--------------------------------------------------------------------------
	|
	| This setting controls whether the "clock" helper function will be registered. You can use the "clock" function to
	| quickly log something to Clockwork or access the Clockwork instance.
	|
	*/

	'register_helpers' => env('CLOCKWORK_REGISTER_HELPERS', true),

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

	'headers' => [
		// 'Accept' => 'application/vnd.com.whatever.v1+json',
	],

	/*
	|--------------------------------------------------------------------------
	| Server-Timing
	|--------------------------------------------------------------------------
	|
	| Clockwork supports the W3C Server Timing specification, which allows for
	/ collecting a simple performance metrics in a cross-browser way. Eg. in
	/ Chrome, your app, database and timeline event timings will be shown
	/ in the Dev Tools network tab.
	/ This setting specifies the max number of timeline events that will be sent.
	| When set to false, Server-Timing headers will not be set.
	| Default: 10
	|
	*/

	'server_timing' => env('CLOCKWORK_SERVER_TIMING', 10)

];
