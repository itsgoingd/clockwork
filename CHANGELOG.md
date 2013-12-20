dev
NOTE: Clockwork\Request\Log::log method arguments have been changed from log($message, $level) to log($level, $message), levels are now specified via Psr\Log\LogLevel class, it's recommended to use shortcut methods for various levels (emergency, alert, critical,  error, warning, notice, info and debug($message))
- clockwork log class now implements PSR logger interface, updated Laravel and Monolog support to use all available log levels
- clockwork log now accepts objects and arrays as input and logs their json representation

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
