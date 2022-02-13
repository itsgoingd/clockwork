5.1.5

- removed support for psr/log
- fixed some typos (implemented by fridzema, thanks!)

*BREAKING*

- `Clockwork\Request\Log` no longer implements the PSR log interface, it is unlikely you are using this class directly

5.1.4

- added Laravel 9 support
- added support for manually registering Clockwork middleware in Laravel
- fixed some failing tests might not been collected in Laravel (reported by ajcastro, thanks!)
- fixed not respecting the collect tests setting in Laravel (reported by SimBig, thanks!)
- fixed some deprecation warnings on PHP 8.1 (implemented by usernotnull, thanks!)

5.1.3

- added PSR to the default filtered namespaces from stack traces in the Laravel integration
- fixed not being able to log non-string values when using psr/log >=2.0 (reported by Wit3, thanks!)
- fixed some deprecation warnings on PHP 8.1 (reported by Pinnokkio, thanks!)
- fixed wrong redirect when accessing web ui with an url including a trailing slash (implemented by ssnepenthe, thanks!)
- fixed update-token could be leaked via the Clockwork rest api (implemented by ssnepenthe, thanks!)

5.1.2

- fixed some deprecation warnings on PHP 8.1 (reported by Codomic, thanks!)

5.1.1

- added support for psr/log 2.0 (used in recent Laravel versions) (implemented by byGits, thanks!)
- improved timeline api event run method to return the return value of passed closure
- improved collecting Laravel database queries to not quote integers (implemented by thisiskj, thanks!)
- improved toolbar details link to always be absolute and work with subdirectories (reported by superDuperCyberTechno, thanks!)
- fixed some deprecation warnings on PHP 8.1 (implemented by gharlan, thanks!)
- fixed collecting Laravel database queries to produce correct queries when bindings contain question marks (reported by woshixiaobai, thanks!)
- fixed filtering collected and recorded requests by closure (implemented by ssnepenthe, thanks!)
- fixed some inconsistencies in the Clockwork metadata api
- fixed some web UI assets being server with wrong mime-types (implemented by ssnepenthe, thanks!)
- fixed missing method on storage interface and missing default parameter value in sql storage (implemented by ssnepenthe, thanks!)

*BREAKING*

- timeline api event run method now returns the return value of passed closure instead of the event instance

5.1

- added initial support for Laravel Octane
- added support for Web UI in the vanilla integration
- added support for collecting Laravel cache queries without values (implemented by akalongman, thanks!)
- added ability to filter Laravel routes from particular namespaces (idea by hailwood, thanks!)
- improved collecting of request URL to include full URL including the query string
- improved Clockwork Browser payload to include Web UI path
- updated Clockwork App (5.1)
- fixed logging falsy values via Clockwork::log (reported by Karmalakas, thanks!)
- fixed PHP 8 incompatibility when processing some Laravel notifications (implemented by nanaya, thanks!)
- fixed request body being collected even when already parsed into POST data
- fixed collecting request URLs with non-standard ports

5.0.8

- fixed crash when collecting Laravel mailables built via MailMessage (implemented by cbl, thanks!)
- fixed crash when collecting artisan command in Lumen (reported by 2Attack, thanks!)
- fixed crash when collecting database queries in Laravel with connection implementation not using PDO (implemented by lenssoft, thanks!)
- fixed crash when HTTP request body contains valid json which does not contain array (eg. a number) (reported by Mradxz, thanks!)
- fixed collected jobs dispatched from other jobs not having a correct parent job set (implemented by josvar, thanks!)

5.0.7

- changed delay listening to events until the app is booted (improves comatibility with some other packages)
- changed default settings to enable toolbar (separately installed component)
- changed default except requests filter to include debugbar api (implemented by edgardmessias, thanks!)
- fixed wrong type-hint for the timeline event run method (reported by hferradj, thanks!)
- fixed on-demand mode not working in Laravel (reported by yemenifree, thanks!)
- fixed crash when collecting Laravel notifications with recipient names (reported by iainheng, thanks!)
- fixed possible crashes and other issues when collecting Laravel notifications (reported by beekmanbv, thanks!)
- fixed crash when creating runnable queries in DBAL data source (implemented by N-M, thanks!)

5.0.6

- fixed vanilla integration overriding other cookies when used with a PSR-7 response (reported by leemason, thanks!)

5.0.5

- added support for toolbar in the vanilla integration (idea by reeslo, thanks!)
- added support for client metrics in the vanilla integration
- improved PSR-7 support in the vanilla integration
- fixed toolbar might not work when not collecting database models
- fixed crash collecting Slack and Nexmo notifications (reported by abalozz, thanks!)
- fixed timeline api usage not being updated in the Slim integration leading to crash (reported by jiaojie1989, implemented by seanhamlin, thanks!)
- fixed api path being interpreted as regex in the vanilla integration (implemented by pqr, thanks!)
- fixed Symfony storage not being updated for latest storage api (implemented by auchanhub, thanks!)

5.0.4

- fixed Lumen integration crash (implemented by alexbegoon, thanks!)
- fixed PHP 5.6 incompatibility (implemented by sanis, thanks!)

5.0.3

- fixed PHP 8.0 incompatibility in log (implemented by mtorromeo, thanks!)

5.0.2

- fixed data sources not being initialized for extended data requests (reported by tmishutin, thanks!)
- fixed inconsistent handling of time and duration arguments in various Request::add* methods (reported by mahagr, thanks!)
- updated Clockwork App (5.0.2)

5.0.1

- fixed performance issues related to collecting stack traces for Eloquent models actions (reported by mstaack, thanks!)
- fixed collecting database and unsupported Laravel notifications (implemented by YannikFirre, thanks!)
- fixed log and timeline sorting leading to invalid metadata format
- updated Clockwork App (5.0.1)

5.0

- added collecting of client-metrics and web-vitals
- added collecting of Eloquent models actions and retrieved, created, updated and deleted models counts
- added collecting of Laravel notifications
- added reworked timeline api
- added configurable web ui path (default changed to /clockwork)
- added toolbar support
- added on-demand mode (with optional secret)
- added option to collect error requests only (requests with 4xx and 5xx responses)
- added option to specify slow threshold and collect slow requests only
- added option to sample collected requests (collect only 1 in x requests)
- added option to collect only specified urls
- added option to not collect pre-flight requests (enabled by default)
- added option to filter collected and recorded requests by closure
- added Laravel controller timeline event
- added support for updating existing requests
- added Slim 4 support
- updated to Clockwork App 5.0
- improved reworked the central Clockwork class api
- improved requests recording to use a terminate callback
- improved global log instance to live on the request instance
- improved global timeline instance to live on the request instance
- improved Symfony routes registration to register web ui paths only when enabled
- improved SQL storage to be more compatible with different PDO error modes
- improved Clockwork rest api with only/except filters
- improved handling of corrupted index records in file storage
- improved cleaned up the code-base, added and improved comments, use modern php features
- removed Laravel total, initialization, boot and run timeline events
- removed legacy clockwork.controller events
- removed duplicate file/line information from collected metadata
- fixed authentication route not being registered when web ui is disabled
- fixed database queries not being collected for queue jobs
- fixed multi-line database queries not being counted properly (implemented by edgardmessias, thanks!)
- fixed StackFrame not processing Windows paths correctly

*BREAKING*

- multiple changes to the Laravel config file, please review and re-publish
- minimal required PHP version is now 5.6 (previously 5.5)
- the timeline api was reworked, please see documentation for details
- the global log instance was moved to request instance, please see documentation for details
- the central Clockwork class api was reworked, old api is available but deprecated
- changed Slim middleware namespaces

4.1.8

- fixed handling of index file locking failures in file storage (reported by mahagr, thanks!)

4.1.7

- fixed a rare crash in Eloquent duplicate queries detection (reported by mstaack, thanks!)
- fixed code-style in the Laravel config (implemented by fgilio, thanks!)

4.1.6

- added support for filtering collected requests by method to Laravel integration (options requests filtered by default) (idea by mortenscheel, thanks!)
- added support for filtering collected requests by uri and method to vanilla integration
- fixed handling of failed file operations on index file in file storage (reported by staabm, thanks!)

4.1.5

- fixed crash on initialization in Lumen apps using queue (reported by gramparallelo, thanks!)

4.1.4

- added support for a time property to the Request:add* apis, defaults to "current time - duration"
- fixed crash when collecting console commands with array arguments or options in the Laravel integration (implemented by mortenscheel, thanks!)
- fixed default storage directory being one level too deep in vanilla integration

4.1.3

- fixed file storage not unlocking index when cleanup has nothing to clean (implemented by Nacoma, thanks!)

4.1.2

- fixed interaction when making HTTP requests in feature tests when collecting tests in Laravel
- updated to Clockwork App 4.1.1

4.1.1

- added ext-json to composer.json require section (idea by staabm, thanks!)
- fixed Clockwork being initialized too soon in Laravel integration leading to possible crashes (reported by tminich, thanks!)

4.1

- added support for command type requests with command specific metadata (commandName, commandArguments, commandArgumentsDefaults, commandOptions, commandOptionsDefaults, commandExitCode, commandOutput)
- added support for collecting executed artisan commands in Laravel integration
- added support for queue-job type requests with queue-job specific metadata (jobName, jobDescription, jobStatus, jobPayload, jobQueue, jobConnection, jobOptions)
- added support for collecting executed queue-jobs in Laravel integration (also supports Laravel Horizon)
- added support for test type requests with test specific metadata (testName, testStatus, testStatusMessage, testAsserts)
- added support for collecting test runs in Laravel integration using PHPunit
- added support for disabling collection of view data when collecting rendered views (new default is to collect views without data)
- added Twig data source using the built-in Twig profiler to collect more precise Twig profiling data
- added support for setting parent requests on requests
- improved collecting of database queries, cache queries, dispatched queue jobs and redis commands to also collect time
- improved the data sources filters api to allow multiple filter types
- improved collecting of Laravel views to use a separate data source
- improved Eloquent data source to have an additional "early" filter applied before the query is added to query counts
- improved Eloquent data source now passes raw stack trace as second argument to filters
- improved Laravel data source to work when response is not provided
- improved Laravel events data source to include Laravel namespace in the default ignored events
- improved Laravel views data source to strip view data prefixed with __
- improved PHP data source to not set request time for cli commands
- improved serializer to omit data below depth limit, support debugInfo, jsonSerialize and toArray methods (partially implemented by mahagr, thanks!)
- improved log to allow overriding serializer settings via context, no longer enabled toString by default
- improved Request class now has pre-populated request time on creation
- improved StackTrace helper with limit option, last method, fixed filter output keys
- improved Lumen queue and redis feature detection
- improved vanilla integration to allow manually sending the headers early (implemented by tminich, thanks!)
- fixed Symfony support, added support for latest Symfony 5.x and 4.x (reported by llaville, thanks!)
- removed dark theme for the web UI setting (now configurable in the Clockwork app itself)
- updated to Clockwork App 4.1

*BREAKING*

- multiple new settings were added to the Laravel config file
- DataSourceInterface::reset method was added, default empty implementation is provided in the base DataSource class
- LaravelDataSource constructor arguments changed to reflect removing the views collecting support

4.0.17

- improved performance and memory usage when doing file storage cleanup (reported by ikkez, thanks!)
- fixed crash after running file storage cleanup
- fixed typo in clockwork:clean argument description

4.0.16

- fixed Laravel middleware being registered too late, causing "collect data always" setting to not work (reported by Youniteus, thanks!)

4.0.15

- fixed cleanup not working with file storage (implemented by LucidTaZ, thanks!)

4.0.14

- fixed compatibility with Laravel 5.4 and earlier when resolving authenticated user

4.0.13

- fixed stack traces processing not handling call_user_func frames properly leading to wrong traces (reported by marcus-at-localhost, thanks!)
- fixed wrong stack traces skip namespaces defaults leading to wrong traces
- fixed vanilla integration config file missing and no longer used settings

4.0.12

- added a simple index file locking to the file storage
- improved handling of invalid index data in the file storage (reported by nsbucky and tkaven, thanks!)
- fixed Laravel data source crash when running without auth service (implemented by DrBenton, thanks!)

4.0.11

- updated web UI (Clockwork App 4.0.6)

4.0.10

- fixed wrong file:line for log messages (requires enabled stack traces atm)

4.0.9

- fixed duplicate queries detection reporting all relationship queries instead of only duplicates (reported by robclancy, thanks!)
- improved the default .gitignore for metadata storage to ignore compressed metadata as well (implemented by clugg, thanks!)

4.0.8

- updated web UI (Clockwork App 4.0.5)

4.0.7

- updated web UI (Clockwork App 4.0.4)

4.0.6

- fixed possible crash in LaravelDataSource when resolving authenticated user in non-standard auth implementations (4.0 regression) (implemented by zarunet, thanks!)
- fixed StackTrace::filter calling array_filter with swapped arguments (implemented by villermen, thanks!)
- fixed PHP 5.x incompatibility tenaming the Storage\Search empty and notEmpty methods to isEmpty and isNotEmpty (reported by eduardodgarciac, thanks!)
- updated web UI (Clockwork App 4.0.3)

4.0.5

- fixed multiple issues causing FileStorage cleanup to not delete old metadata or crash (partially implemented by jaumesala, reported by SerafimArts, thanks!)
- updated web UI (Clockwork App 4.0.2)

4.0.4

- fixed web UI not working (4.0.2 regression) (reported by williamqian and lachlankrautz, thanks!)

4.0.3

- fixed crash when using SQL storage (reported by sebastiaanluca, thanks!)

4.0.2

- updated web UI (Clockwork App 4.0.1)

4.0.1

- fixed Lumen support (reported by Owlnofeathers, thanks!)

4.0

- added "features" configuration
- added requests search (extended storage api)
- added collecting request body data (idea by lkloon123, thanks!)
- added collecting of dispatched queue jobs
- added collecting Redis commands (idea by tillkruss, thanks!)
- added collecting of database query stats separate from queries
- added collecting of executed middleware
- added ability to specify slow database query threshold
- added ability to collect only slow database queries
- added ability to disable collecting of database queries keeping database stats
- added ability to disable collecting of cache queries keeping cache stats
- added duplicate (N+1) database query detection (inspired by beyondcode/laravel-query-detector, thanks!)
- added configuration to limit number of collected frames for stack traces (defaults to 10)
- added configuration to specify skipped vendors, namespaces and files for stack traces
- added index file to file storage
- added support for compression in file storage
- added new filters api to data sources
- improved file and sql storage to support search api
- improved symfony storage to work with file storage changes
- improved log api to allow passing custom stack traces in context
- improved refactored and cleaned up Laravel service provider
- improved Lumen integration to share more code with Laravel integration
- improved refactored sql storage a bit
- improved timeline api, description is now optional and defaults to event name when calling startEvent (idea by robclancy, thanks!)
- updated web UI
- fixed regexp in vanilla integration Clockwork REST api processing
- removed storage filter support (replaced by features configuration)
- BREAKING configuration format changes, please re-deploy if using customized Clockwork config
- NOTE metadata files from previous versions will need to be manually removed on upgrade

3.1.4

- improved DBALDataSource to work with custom types (thanks villermen)

3.1.3

- updated LaravelCacheDataSource to support Laravel 5.8

3.1.2

- fixed missing use statement in vanilla integration (thanks micc83)

3.1.1

- exposed the Request::setAuthenticatedUser method on the main Clockwork class
- fixed possible crash in LaravelDataSource when resolving authenticated user in non-standard auth implementations (thanks freshleafmedia, motia)

3.1

- added new integration for vanilla PHP (thanks martbean)
- added support for collecting authenticated user info
- added bunch of helper methods for adding data like database queries or events to Clockwork
- added serializer options to the config files
- updated web UI to match latest Chrome version
- improved collecting of exceptions
- improved filtered uris implementation in Laravel to no longer have any performance overhead (thanks marcusbetts)
- improved compatibility with Laravel Telescope
- fixed numeric keys being lost on serialization of arrays (thanks ametad)
- fixed serialization of parent class private properties
- fixed a possible crash when resolving stack traces (thanks mbardelmeijer)
- deprecated Clockwork::subrequest method in favor of Clockwork::addSubrequest

3.0.2

- fixed infinite redirect if dark web theme is enabled on Laravel or Lumen <5.5 (thanks pixelskribe)

3.0.1

- improved LaravelDataSource to not collect views data if it is filtered (by default)

3.0

- updated web UI to match latest Chrome version
- added new api for user-data (custom tabs in Clockwork app)
- added support for authentication (thanks xiaohuilam)
- added support for collecting stack traces for log messages, queries, etc. (thanks sisve)
- added new api for recording subrequests (thanks L3o-pold)
- added Symfony integration beta
- added Xdebug profiler support
- added collecting of full URLs for requests
- added collecting of peak memory usage
- added ability to use dark theme for the web UI
- added new extend-api to data soruces for extending data when it's being sent to the application
- improved data serialization implementation - handles recursion, unlimited depth, type metadata, clear marking for protected and private properties
- improved data serialization with configurable defaults, limit and blackboxing of classes
- improved handling of binary bindings in EloquentDataSource (thanks sergio91pt and coderNeos)
- improved stack traces collection to resolve original view names
- BREAKING improved Laravel integration to type-hint contracts instead of concrete implementations (thanks robclancy)
- improved default configuration to not collect data for Laravel Horizon requests (thanks fgilio)
- improved LaravelDataSource view data collecting to remove Laravel Twigbridge metadata
- changed Laravel integration to register middleware in the boot method instead of register (thanks dionysiosarvanitis)
- changed Laravel and Lumen integrations to use a single shared Log instance
- fixed Clockwork HTTP API returning empty object instead of null if request was not found
- fixed Clockwork routes not returning 404 when disabled on runtime with route cache enabled (thanks joskfg)
- BREAKING dropped Laravel 4 support
- BREAKING dropped PHP 5.4 support, requires PHP 5.5

2.2.5

- changed SQL storage schema URI column type from VARCHAR to TEXT (thanks sumidatx)
- fixed possible crash in file storage cleanup if the file was already deleted (thanks bcalik)
- fixed event handling in Eloquent data source compatibility with some 3rd party packages (thanks erikgaal)

2.2.4

- drop support for collecting Laravel controller middleware (as this can have unexpected side-effects) (thanks phh)

2.2.3

- improved Server-Timing now uses the new header format (thanks kohenkatz)
- fixed Laravel crash when gathering middleware if the controller class doesn't exist

2.2.2

- fixed compatibility with Laravel 5.2 (thanks peppeocchi)

2.2.1

- fixed Laravel 4.x support once again (thanks bcalik)

2.2

- added support for collecting route middleware (thanks Vercoutere)
- added support for collecting routes and middleware in newer Lumen versions
- updated Web UI to match Clockwork Chrome 2.2
- improved Laravel support to register most event handlers only when collecting data
- fixed Lumen middleware not being registered automatically (thanks lucian-dragomir)
- fixed published Lumen config not being loaded

2.1.1

- fixed Laravel 4.x support (added legacy version of the config file) (thanks bcalik)

2.1

- updated Web UI to match Clockwork Chrome 2.1
- improved Laravel support to load the default config and use env variables in the default config
- improved Lumen support to use the standard config subsystem instead of directly accessing env variables (thanks davoaust, SunMar)
- improved reliability of storing metadata in some cases (by using JSON_PARTIAL_OUTPUT_ON_ERROR when supported)
- fixed wrong mime-type for javascript assets in Web UI causing it to not work in some browsers (thanks sleavitt)
- fixed path checking in Web UI causing it to not work on Windows (thanks Malezha)
- fixed parameters conversion in DBALDataSource (thanks andrzejenne)

2.0.4

- improved mkdir error handling in FileStorage (thanks FBnil)
- fixed crash in LaravelEventsDataSource when firing events with associative array as payload

2.0.3

- fixed Clockwork now working when used with Laravel route cache

2.0.2

- fixed crash on attempt to clean up file storage if the project contains Clockwork 1.x metadata

2.0.1

- fixed Web UI not working in Firefox

2.0

- added Web UI
- added new Laravel cache data source
- added new Laravel events data source
- added new more robust metadata storage API
- added automatic metadata cleanup (defaults to 1 week)
- added better metadata serialization including class names for objects
- added PostgreSQL compatibility for the SQL storage (thanks oldskool73)
- added Slim 3 middleware (thanks sperrichon)
- added PSR message data source (thanks sperrichon)
- added Doctrine DBAL data source (thanks sperrichon)
- changed Clockwork request ids now use dashes instead of dots (thanks Tibbelit)
- changed Laravel and Lumen integrations to no longer log data for console commands
- changed simplified the clock Laravel helper (thanks Jergus Lejko)
- fixed wrong version data logged in SQL storage
- removed PHP 5.3 support, code style changes
- removed CodeIgniter support
- removed ability to register additional data sources via Clockwork config

UPGRADING

- update the required Clockwork version to ^2.0 in your composer.json
- PHP 5.3 - no longer supported, you can continue using the latest 1.x version
- CodeIgniter - no longer supported, you can continue using the latest 1.x version
- Slim 2 - update the imported namespace from Clockwork\Support\Slim to Clockwork\Support\Slim\Legacy
- ability to register additional data sources via Clockwork config was removed, please call app('clockwork')->addDataSource(...) in your own service provider

1.14.5

- fixed incompatibility with Laravel 4.1 an 4.2 (introduced in 1.14.3)

1.14.4

- added support for Lumen 5.5 (thanks nebez)

1.14.3

- added support for Laravel 5.5 package auto-discovery (thanks Omranic)
- added automatic registration of the Laravel middleware (no need to edit your Http/Kernel.php anymore, existing installations don't need to be changed)
- updated Laravel artisan clockwork:clean command for Laravel 5.5 (thanks rosswilson252)
- fixed crash when retrieving all requests from Sql storage (thanks pies)

1.14.2

- fixed missing imports in Doctrine data source (thanks jenssegers)

1.14.1
- fixed collecting Eloquent queries when using PDO_ODBC driver for real (thanks abhimanyu003)

1.14
- added support for Server-Timing headers (thanks Garbee)
- fixed compatibility with Lumen 5.4 (thanks Dimasdanz)
- fixed collecting Eloquent queries with bindings containing backslashes (thanks fitztrev)
- fixed collecting Eloquent queries when using PDO_ODBC driver (thanks abhimanyu003)
- fixed collecting Doctrine queries with array bindings (thanks RolfJanssen)
- replaced Doctrine bindings preparation code with more complete version from laravel-doctrine
- fixed PHP 5.3 compatibility

1.13.1
- fixed compatibility with Lumen 5.4 (thanks meanevo)

1.13
- added support for Laravel 5.4 (thanks KKSzymanowski)
- improved Laravel "clock" helper function now takes multiple arguments to be logged at once (eg. `clock($foo, $bar, $baz)`)

1.12
- added collecting of caller file name and line number for queries and model name (Laravel 4.2+) for ORM queries to the Eloquent data source (thanks OmarMakled and fitztrev for the idea)
- added collecting of context, caller file name and line number to the logger (thanks crissi for the idea)
- fixed crash in Lumen data source when running unit tests with simulated requests on Lumen
- fixed compatibility with Laravel 4.0

1.11.2
- switched to PSR-4 autoloading
- fixed Swift data source crash when sending email with no from/to address specified (thanks marksecurelogin)

1.11.1
- added support for DateTimeImmutable in Doctrine data source (thanks morfin)
- fixed not being able to log null values via the "clock" helper function
- fixed Laravel 4.2-dev not being properly detected as 4.2 release (thanks DemianD)

1.11
- added support for Lumen 5.2 (thanks lukeed)
- added "clock" helper function
- fixed data sources being initialized too late (thanks morfin)
- fixed code style in Doctrine data source
- removed Laravel log dependency from Doctrine data source
- NOTE laravel-doctrine provides ootb support for Clockwork, you should use this instead of included Doctrine data source with Laravel

1.10.1
- fixed collecting of database queries in Laravel 5.2 (thanks sebastiandedeyne)

1.10
- added Laravel 5.2 support (thanks jonphipps)
- improved file storage to allow configuring directory permissions (thanks patrick-radius)
- fixed interaction with PHPUnit in Lumen (thanks troyharvey)
- removed "router dispatch" timeline event for now (due to Laravel 5.2 changes)

1.9
- added Lumen support (thanks dawiyo)
- added aliases for all Clockwork parts so they can be resolved by the IoC container in Laravel and Lumen
- fixed Laravel framework initialisation, booting and running timeline events not being recorded properly (thanks HipsterJazzbo, sisve)
- fixed how Laravel clockwork:clean artisan command is registered (thanks freekmurze)
- removed Lumen framework initialisation, booting and running timeline events as they are not supported by Lumen

1.8.1
- fixed SQL data storage initialization if PDO is set to throw exception on error (thanks YOzaz)

1.8
- added SQL data storage implementation
- added new config options for data storage for Laravel (please re-publish the config file)
- fixed not being able to use the Larvel route caching when using Clockwork (thanks Garbee, kylestev, cbakker86)

1.7
- added support for Laravel 5 (thanks Garbee, slovenianGooner)
- improved support for Laravel 4.1 and 4.2, Clockwork data is now available for error responses
- added Doctrine data source (thanks matiux)
- fixed compatibility with some old PHP 5.3 versions (thanks hailwood)
- updated Laravel data source to capture the context for log messages (thanks hermanzhu)

1.6
- improved Eloquent data source to support multiple databases (thanks ingro)
- improved compatibility with Laravel apps not using database
- improved compatibility with various CodeIngiter installations
- fixed a bug where log messages and timeline data might not be sorted correctly
- fixed missing static keyword in CodeIgniter hook (thanks noevidenz)
- changed Timeline::endEvent behavior to return false instead of throwing exception when called for non-existing event

1.5
- improved Slim support to use DI container to share Clockwork instance instead of config
- improved Slim support now adds all messages logged via Slim's log interface to Clockwork log as well
- improved CodeIgniter support to make Clockwork available through the CI app (tnx BradEstey)
- fixed Laravel support breaking flash messages (tnx hannesvdvreken)
- fixed CodeIgniter support PSR-0 autoloading and other improvements (tnx pwhelan)
- fixed file storage warning when recursive data is collected

1.4.4
- changed Laravel support to disable permanent data collection by default (tnx jenssegers)
- improved Laravel support to return Clockwork data with proper Content-Type (tnx maximebeaudoin)
- fixed CodeIgniter support compatibility with PHP 5.3 (tnx BradEstey)

1.4.3
- fixed incorrect requests ids being generated depending on set locale

1.4.2
- fixed Laravel support compatibility with PHP 5.3

1.4.1
- fixed Laravel support compatibility with PHP 5.3

1.4
- added support for collecting emails and views data
- added support for CodeIgniter 2.1 (tnx pwhelan)
- added data source and plugin for collecting emails data from Swift mailer
- added support for collecting emails and views data from Laravel
- added --age argument to Laravel artisan clockwork::clean command, specifies how old the request data must be to be deleted (in hours)
- improved Laravel service provider
- fixed compatibility with latest Laravel 4.1

1.3
NOTE: Clockwork\Request\Log::log method arguments have been changed from log($message, $level) to log($level, $message), levels are now specified via Psr\Log\LogLevel class, it's recommended to use shortcut methods for various levels (emergency, alert, critical,  error, warning, notice, info and debug($message))
- clockwork log class now implements PSR logger interface, updated Laravel and Monolog support to use all available log levels
- clockwork log now accepts objects and arrays as input and logs their json representation
- added support for specifying additional headers on metadata requests (Laravel) (tnx philsturgeon)

1.2
- added support for Laravel 4.1
- added facade for Laravel
- added ability to disable collecting data about requests to specified URIs in Laravel
- added clockwork:clean artisan command for cleaning request metadata for Laravel
- added an easy way to add timeline events and log records via main Clockwork class
- added support for Slim apps running in subdirs (requires Clockwork Chrome 1.1+)
- file storage now creates default gitignore file for the request data when creating the storage dir
- fixed a few bugs which might cause request data to not appear in Chrome extension
- fixed a few bugs that could lead to PHP errors/exceptions

1.1
- added support for Laravel 4 apps running in subdirs (requires Clockwork Chrome 1.1+)
- added data-protocol version to the request data
- updated Laravel 4 service provider to work with Clockwork Web
- fixed a bug where Clockwork would break Laravel 4 apps not using database
- fixed a bug where calling Timeline::endEvent after Timeline::finalize caused exception to be thrown
- fixed a bug where using certain filters would store incorrect data

0.9.1
- added support for application routes (ootb support for Laravel 4 only atm)
- added configuration file for Laravel 4
- added support for filtering stored data in Storage
- added library version constant Clockwork::VERSION
