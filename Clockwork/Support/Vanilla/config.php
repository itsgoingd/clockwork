<?php

return [

	/*
	|------------------------------------------------------------------------------------------------------------------
	| Enable Clockwork
	|------------------------------------------------------------------------------------------------------------------
	|
	| You can explicitly enable or disable Clockwork here. When disabled,
	| the storeRequest and returnRequest methods will be no-ops. This provides
	| a convenient way to disable Clockwork in production.
	|
	*/

	'enable' => isset($_ENV['CLOCKWORK_ENABLE']) ? $_ENV['CLOCKWORK_ENABLE'] : true,

	/*
	|------------------------------------------------------------------------------------------------------------------
	| Features
	|------------------------------------------------------------------------------------------------------------------
	|
	| You can enable or disable various Clockwork features here. Some features have additional settings (eg. slow query
	| threshold for database queries).
	|
	*/

	'features' => [

		// Performance metrics
		'performance' => [
			// Allow collecting of client metrics. Requires separate clockwork-browser npm package.
			'client_metrics' => isset($_ENV['CLOCKWORK_PERFORMANCE_CLIENT_METRICS']) ? $_ENV['CLOCKWORK_PERFORMANCE_CLIENT_METRICS'] : true
		]

	],

	/*
	|------------------------------------------------------------------------------------------------------------------
	| Enable toolbar
	|------------------------------------------------------------------------------------------------------------------
	|
	| Clockwork can show a toolbar with basic metrics on all responses. Here you can enable or disable this feature.
	| Requires a separate clockwork-browser npm library.
	|
	*/

	'toolbar' => isset($_ENV['CLOCKWORK_TOOLBAR']) ? $_ENV['CLOCKWORK_TOOLBAR'] : true,

	/*
	|------------------------------------------------------------------------------------------------------------------
	| HTTP requests collection
	|------------------------------------------------------------------------------------------------------------------
	|
	| Clockwork collects data about HTTP requests to your app. Here you can choose which requests should be collected.
	|
	*/

	'requests' => [
		// With on-demand mode enabled, Clockwork will only profile requests when the browser extension is open or you
		// manually pass a "clockwork-profile" cookie or get/post data key.
		// Optionally you can specify a "secret" that has to be passed as the value to enable profiling.
		'on_demand' => isset($_ENV['CLOCKWORK_REQUESTS_ON_DEMAND']) ? $_ENV['CLOCKWORK_REQUESTS_ON_DEMAND'] : false,

		// Collect only errors (requests with HTTP 4xx and 5xx responses)
		'errors_only' => isset($_ENV['CLOCKWORK_REQUESTS_ERRORS_ONLY']) ? $_ENV['CLOCKWORK_REQUESTS_ERRORS_ONLY'] : false,

		// Response time threshold in milliseconds after which the request will be marked as slow
		'slow_threshold' => isset($_ENV['CLOCKWORK_REQUESTS_SLOW_THRESHOLD']) ? $_ENV['CLOCKWORK_REQUESTS_SLOW_THRESHOLD'] : null,

		// Collect only slow requests
		'slow_only' => isset($_ENV['CLOCKWORK_REQUESTS_SLOW_ONLY']) ? $_ENV['CLOCKWORK_REQUESTS_SLOW_ONLY'] : false,

		// Sample the collected requests (eg. set to 100 to collect only 1 in 100 requests)
		'sample' => isset($_ENV['CLOCKWORK_REQUESTS_SAMPLE']) ? $_ENV['CLOCKWORK_REQUESTS_SAMPLE'] : false,

		// List of URIs that should not be collected
		'except' => [
			// '/api/.*'
		],

		// List of URIs that should be collected, any other URI will not be collected if not empty
		'only' => [
			// '/api/.*'
		],

		// Don't collect OPTIONS requests, mostly used in the CSRF pre-flight requests and are rarely of interest
		'except_preflight' => isset($_ENV['CLOCKWORK_REQUESTS_EXCEPT_PREFLIGHT']) ? $_ENV['CLOCKWORK_REQUESTS_EXCEPT_PREFLIGHT'] : true
	],

	/*
	|------------------------------------------------------------------------------------------------------------------
	| Enable data collection when Clockwork is disabled
	|------------------------------------------------------------------------------------------------------------------
	|
	| You can enable this setting to collect data even when Clockwork is disabled. Eg. for future analysis.
	|
	*/

	'collect_data_always' => isset($_ENV['CLOCKWORK_COLLECT_DATA_ALWAYS']) ? $_ENV['CLOCKWORK_COLLECT_DATA_ALWAYS'] : false,

	/*
	|------------------------------------------------------------------------------------------------------------------
	| Clockwork API URI
	|------------------------------------------------------------------------------------------------------------------
	|
	| Path of the script calling returnRequest to return Clockwork metadata to the client app. See installation
	| instructions for details.
	|
	*/

	'api' => isset($_ENV['CLOCKWORK_API']) ? $_ENV['CLOCKWORK_API'] : '/__clockwork/',

	/*
	|------------------------------------------------------------------------------------------------------------------
	| Clockwork web UI
	|------------------------------------------------------------------------------------------------------------------
	|
	| Clockwork comes bundled with a full Clockwork App accessible as a Web UI. Here you can enable and configure this
	| feature.
	| Clockwork::returnWeb api is used to expose the Web UI in your vanilla app, see the installation instructions for
	| details.
	|
	*/

	'web' => [
		// Enable or disable the Web UI, set to the public uri where Clockwork Web UI is accessible
		'enable' => isset($_ENV['CLOCKWORK_WEB_ENABLE']) ? $_ENV['CLOCKWORK_WEB_ENABLE'] : true,

		// Path where to install the Web UI assets, should be publicly accessible
		'path' => isset($_ENV['CLOCKWORK_WEB_PATH']) ? $_ENV['CLOCKWORK_WEB_PATH'] : __DIR__ . '/../../../../../public/vendor/clockwork',

		// Public URI where the installed Web UI assets will be accessible
		'uri' => isset($_ENV['CLOCKWORK_WEB_URI']) ? $_ENV['CLOCKWORK_WEB_URI'] : '/vendor/clockwork'
	],

	/*
	|------------------------------------------------------------------------------------------------------------------
	| Metadata storage
	|------------------------------------------------------------------------------------------------------------------
	|
	| Configure how is the metadata collected by Clockwork stored. Three options are available:
	|   - files - A simple fast storage implementation storing data in one-per-request files.
	|   - sql - Stores requests in a sql database. Supports MySQL, Postgresql, Sqlite and requires PDO.
	|   - redis - Stores requests in redis. Requires phpredis.
	*/

	'storage' => isset($_ENV['CLOCKWORK_STORAGE']) ? $_ENV['CLOCKWORK_STORAGE'] : 'files',

	// Path where the Clockwork metadata is stored
	'storage_files_path' => isset($_ENV['CLOCKWORK_STORAGE_FILES_PATH']) ? $_ENV['CLOCKWORK_STORAGE_FILES_PATH'] : __DIR__ . '/../../../../../../clockwork',

	// Compress the metadata files using gzip, trading a little bit of performance for lower disk usage
	'storage_files_compress' => isset($_ENV['CLOCKWORK_STORAGE_FILES_COMPRESS']) ? $_ENV['CLOCKWORK_STORAGE_FILES_COMPRESS'] : false,

	// SQL database to use, can be a PDO connection string or a path to a sqlite file
	'storage_sql_database' => isset($_ENV['CLOCKWORK_STORAGE_SQL_DATABASE']) ? $_ENV['CLOCKWORK_STORAGE_SQL_DATABASE'] : 'sqlite:' . __DIR__ . '/../../../../../clockwork.sqlite',
	'storage_sql_username' => isset($_ENV['CLOCKWORK_STORAGE_SQL_USERNAME']) ? $_ENV['CLOCKWORK_STORAGE_SQL_USERNAME'] : null,
	'storage_sql_password' => isset($_ENV['CLOCKWORK_STORAGE_SQL_PASSWORD']) ? $_ENV['CLOCKWORK_STORAGE_SQL_PASSWORD'] : null,

	// SQL table name to use, the table is automatically created and updated when needed
	'storage_sql_table' => isset($_ENV['CLOCKWORK_STORAGE_SQL_TABLE']) ? $_ENV['CLOCKWORK_STORAGE_SQL_TABLE'] : 'clockwork',

	// Configuration for the Redis storage
	'storage_redis' => [
		'host' => isset($_ENV['CLOCKWORK_STORAGE_REDIS_HOST']) ? $_ENV['CLOCKWORK_STORAGE_REDIS_HOST'] : '127.0.0.1',
		'username' => isset($_ENV['CLOCKWORK_STORAGE_REDIS_USERNAME']) ? $_ENV['CLOCKWORK_STORAGE_REDIS_USERNAME'] : null,
		'password' => isset($_ENV['CLOCKWORK_STORAGE_REDIS_PASSWORD']) ? $_ENV['CLOCKWORK_STORAGE_REDIS_PASSWORD'] : null,
		'port' => isset($_ENV['CLOCKWORK_STORAGE_REDIS_PORT']) ? $_ENV['CLOCKWORK_STORAGE_REDIS_PORT'] : 6379,
		'database' => isset($_ENV['CLOCKWORK_STORAGE_REDIS_DB']) ? $_ENV['CLOCKWORK_STORAGE_REDIS_DB'] : 0
	],

	// Redis prefix for Clockwork keys ("clockwork" if not set)
	'storage_redis_prefix' => isset($_ENV['CLOCKWORK_STORAGE_REDIS_PREFIX']) ? $_ENV['CLOCKWORK_STORAGE_REDIS_PREFIX'] : 'clockwork',

	// Maximum lifetime of collected metadata in minutes, older requests will automatically be deleted, false to disable
	'storage_expiration' => isset($_ENV['CLOCKWORK_STORAGE_EXPIRATION']) ? $_ENV['CLOCKWORK_STORAGE_EXPIRATION'] : 60 * 24 * 7,

	/*
	|------------------------------------------------------------------------------------------------------------------
	| Authentication
	|------------------------------------------------------------------------------------------------------------------
	|
	| Clockwork can be configured to require authentication before allowing access to the collected data. This might be
	| useful when the application is publicly accessible. Setting to true will enable a simple authentication with a
	| pre-configured password. You can also pass a class name of a custom implementation.
	|
	*/

	'authentication' => isset($_ENV['CLOCKWORK_AUTHENTICATION']) ? $_ENV['CLOCKWORK_AUTHENTICATION'] : false,

	// Password for the simple authentication
	'authentication_password' => isset($_ENV['CLOCKWORK_AUTHENTICATION_PASSWORD']) ? $_ENV['CLOCKWORK_AUTHENTICATION_PASSWORD'] : 'VerySecretPassword',

	/*
	|------------------------------------------------------------------------------------------------------------------
	| Stack traces collection
	|------------------------------------------------------------------------------------------------------------------
	|
	| Clockwork can collect stack traces for log messages and certain data like database queries. Here you can set
	| whether to collect stack traces, limit the number of collected frames and set further configuration. Collecting
	| long stack traces considerably increases metadata size.
	|
	*/

	'stack_traces' => [
		// Enable or disable collecting of stack traces
		'enabled' => isset($_ENV['CLOCKWORK_STACK_TRACES_ENABLED']) ? $_ENV['CLOCKWORK_STACK_TRACES_ENABLED'] : true,

		// Limit the number of frames to be collected
		'limit' => isset($_ENV['CLOCKWORK_STACK_TRACES_LIMIT']) ? $_ENV['CLOCKWORK_STACK_TRACES_LIMIT'] : 10,

		// List of vendor names to skip when determining caller, common vendor are automatically added
		'skip_vendors' => [
			// 'phpunit'
		],

		// List of namespaces to skip when determining caller
		'skip_namespaces' => [
			// 'Vendor'
		],

		// List of class names to skip when determining caller
		'skip_classes' => [
			// App\CustomLog::class
		]
	],

	/*
	|------------------------------------------------------------------------------------------------------------------
	| Serialization
	|------------------------------------------------------------------------------------------------------------------
	|
	| Clockwork serializes the collected data to json for storage and transfer. Here you can configure certain aspects
	| of serialization. Serialization has a large effect on the cpu time and memory usage.
	|
	*/

	// Maximum depth of serialized multi-level arrays and objects
	'serialization_depth' => isset($_ENV['CLOCKWORK_SERIALIZATION_DEPTH']) ? $_ENV['CLOCKWORK_SERIALIZATION_DEPTH'] : 10,

	// A list of classes that will never be serialized (eg. a common service container class)
	'serialization_blackbox' => [
		// \App\ServiceContainer::class
	],

	/*
	|------------------------------------------------------------------------------------------------------------------
	| Register helpers
	|------------------------------------------------------------------------------------------------------------------
	|
	| Clockwork comes with a "clock" global helper function. You can use this helper to quickly log something and to
	| access the Clockwork instance.
	|
	*/

	'register_helpers' => isset($_ENV['CLOCKWORK_REGISTER_HELPERS']) ? $_ENV['CLOCKWORK_REGISTER_HELPERS'] : false,

	/*
	|------------------------------------------------------------------------------------------------------------------
	| Send Headers for AJAX request
	|------------------------------------------------------------------------------------------------------------------
	|
	| When trying to collect data the AJAX method can sometimes fail if it is missing required headers. For example, an
	| API might require a version number using Accept headers to route the HTTP request to the correct codebase.
	|
	*/

	'headers' => [
		// 'Accept' => 'application/vnd.com.whatever.v1+json',
	],
	/*
	|------------------------------------------------------------------------------------------------------------------
	| Server-Timing
	|------------------------------------------------------------------------------------------------------------------
	|
	| Clockwork supports the W3C Server Timing specification, which allows for collecting a simple performance metrics
	| in a cross-browser way. Eg. in Chrome, your app, database and timeline event timings will be shown in the Dev
	| Tools network tab. This setting specifies the max number of timeline events that will be sent. Setting to false
	| will disable the feature.
	|
	*/

	'server_timing' => isset($_ENV['CLOCKWORK_SERVER_TIMING']) ? $_ENV['CLOCKWORK_SERVER_TIMING'] : 10

];
