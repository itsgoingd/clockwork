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
- CodeIgniter - no longer supported, you can continue using the lastest 1.x version
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
- fixed compatibilty with latest Laravel 4.1

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
