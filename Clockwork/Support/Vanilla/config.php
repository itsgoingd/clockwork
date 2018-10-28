<?php

return [

	/*
	|--------------------------------------------------------------------------
	| Enable Clockwork
	|--------------------------------------------------------------------------
	|
	| You can explicitly enable or disable Clockwork here. When disabled,
	| the storeRequest and returnRequest methods will be no-ops. This provides
	| a convenient way to disable Clockwork in production.
	|
	*/

	'enable' => isset($_ENV['CLOCKWORK_ENABLE']) ? $_ENV['CLOCKWORK_ENABLE'] : true,

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

	'collect_data_always' => isset($_ENV['CLOCKWORK_COLLECT_DATA_ALWAYS']) ? $_ENV['CLOCKWORK_COLLECT_DATA_ALWAYS'] : false,

	/*
	|--------------------------------------------------------------------------
	| Clockwork API URI
	|--------------------------------------------------------------------------
	|
	| URI to the script calling returnRequest to return Clockwork metadata to
	| the client app. See installation instructions for details.
	| Default: '/__clockwork/'
	|
	*/

	'api' => isset($_ENV['CLOCKWORK_API']) ? $_ENV['CLOCKWORK_API'] : '/__clockwork/',

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
	| the database by it's PDO connection string. Database table will be
	| automatically created.
	| Sql storage requires PDO.
	|
	*/

	'storage' => isset($_ENV['CLOCKWORK_STORAGE']) ? $_ENV['CLOCKWORK_STORAGE'] : 'files',

	'storage_files_path' => isset($_ENV['CLOCKWORK_STORAGE_FILES_PATH']) ? $_ENV['CLOCKWORK_STORAGE_FILES_PATH'] : __DIR__ . '/../../../../../clockwork',

	'storage_sql_database' => isset($_ENV['CLOCKWORK_STORAGE_SQL_DATABASE']) ? $_ENV['CLOCKWORK_STORAGE_SQL_DATABASE'] : 'sqlite:' . __DIR__ . '/../../../../../clockwork.sqlite',
	'storage_sql_username' => isset($_ENV['CLOCKWORK_STORAGE_SQL_USERNAME']) ? $_ENV['CLOCKWORK_STORAGE_SQL_USERNAME'] : null,
	'storage_sql_password' => isset($_ENV['CLOCKWORK_STORAGE_SQL_PASSWORD']) ? $_ENV['CLOCKWORK_STORAGE_SQL_PASSWORD'] : null,
	'storage_sql_table'    => isset($_ENV['CLOCKWORK_STORAGE_SQL_TABLE']) ? $_ENV['CLOCKWORK_STORAGE_SQL_TABLE'] : 'clockwork',

	/*
	|--------------------------------------------------------------------------
	| Metadata expiration
	|--------------------------------------------------------------------------
	|
	| Maximum lifetime of the metadata in minutes, metadata for older requests
	| will automatically be deleted when storing new requests.
	| When set to false, metadata will never be deleted.
	| Default: 1 week
	|
	*/

	'storage_expiration' => isset($_ENV['CLOCKWORK_STORAGE_EXPIRATION']) ? $_ENV['CLOCKWORK_STORAGE_EXPIRATION'] : 60 * 24 * 7,

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
	| Enable collecting of stack traces
	|--------------------------------------------------------------------------
	|
	| This setting controls, whether log messages and certain data sources, like
	| the database or cache data sources, should collect stack traces.
	| You might want to disable this if you are collecting 100s of queries or
	| log messages, as the stack traces can considerably increase the metadata size.
	| You can force collecting of stack trace for a single log call by passing
	| [ 'trace' => true ] as $context.
	| Default: true
	|
	*/

	'collect_stack_traces' => isset($_ENV['CLOCKWORK_COLLECT_STACK_TRACES']) ? $_ENV['CLOCKWORK_COLLECT_STACK_TRACES'] : true,

	/*
	|--------------------------------------------------------------------------
	| Serialization
	|--------------------------------------------------------------------------
	|
	| Configure how Clockwork serializes the collected data.
	| Depth limits how many levels of multi-level arrays and objects have
	| extended serialization (rest uses simple serialization).
	| Blackbox allows you to specify classes which contents should be never
	| serialized (eg. a service container class).
	| Lowering depth limit and adding classes to blackbox lowers the memory
	| usage and processing time.
	|
	*/

	'serialization_depth' => isset($_ENV['CLOCKWORK_SERIALIZATION_DEPTH']) ? $_ENV['CLOCKWORK_SERIALIZATION_DEPTH'] : 10,

	'serialization_blackbox' => [
		// \App\ServiceContainer::class
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

	'register_helpers' => isset($_ENV['CLOCKWORK_REGISTER_HELPERS']) ? $_ENV['CLOCKWORK_REGISTER_HELPERS'] : false,

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
	| collecting a simple performance metrics in a cross-browser way. Eg. in
	| Chrome, your app, database and timeline event timings will be shown
	| in the Dev Tools network tab.
	| This setting specifies the max number of timeline events that will be sent.
	| When set to false, Server-Timing headers will not be set.
	| Default: 10
	|
	*/

	'server_timing' => isset($_ENV['CLOCKWORK_SERVER_TIMING']) ? $_ENV['CLOCKWORK_SERVER_TIMING'] : 10

];
