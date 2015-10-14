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
