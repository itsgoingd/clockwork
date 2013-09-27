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
- added library version constant Cloclwork::VERSION
