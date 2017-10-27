<p align="center">
	<img width="412px" src="https://underground.works/clockwork/images/github/title.png">
	<img src="https://underground.works/clockwork/images/github/clockwork-intro.png">
</p>


### What is Clockwork?

Clockwork is a browser extension, providing tools for debugging and profiling your PHP applications, including request data, application log, database queries, routes, visualisation of application runtime and more.

Clockwork uses a server-side component, that gathers all the data and easily integrates with any PHP project, including out-of-the-box support for major frameworks.

Read more and try it out on the [Clockwork website](https://underground.works/clockwork).

*This repository contains the server-side component of Clockwork.*

### Installation

*This readme contains installation and usage instructions for the Laravel framework, for other integrations check out the [Clockwork website](https://underground.works/clockwork).*

Install the Clockwork library via Composer.

```shell
$ composer require itsgoingd/clockwork
```

If you are running the latest Laravel version, congratulations you are done!

For Laravel versions older than 5.5, you'll need to register the service provider, in your `config/app.php`:

```php
'providers' => [
	...
	Clockwork\Support\Laravel\ClockworkServiceProvider::class
]
```

By default, Clockwork will only be available in debug mode, you can change this and other settings in the configuration file. Use the `vendor:publish` Artisan command to publish the configuration file into your config directory.

Clockwork comes with a `clock()` helper function, which provides an easy way to add records to the Clockwork log and events to the timeline.

If you prefer to use a Facade, add following to your `config/app.php`:

```php
'aliases' => [
	...
	'Clockwork' => Clockwork\Support\Laravel\Facade::class,
]
```

**Note:** If you are using Laravel route cache, you will need to refresh it using the `route:cache` Artisan command.

### Usage

To interact with the data collected by Clockwork, you will need to

- install the [Chrome extension](https://chrome.google.com/webstore/detail/clockwork/dmggabnehkmmfmdffgajcflpdjlnoemp)
- or the [Firefox add-on](https://addons.mozilla.org/en-US/firefox/addon/clockwork-dev-tools/)
- or use the web UI `http://your.app/__clockwork`

Clockwork comes with a `clock()` helper function, which provides an easy way to add records to the Clockwork log or events to the timeline.

You can also access Clockwork using the `Clockwork` facade, resolving from the container `app('clockwork')` or typehinting `Clockwork\Clockwork`.

#### Logging

All data logged using the Laravel log methods will also appear in the Clockwork log tab for the request.

You can also use the Clockwork log directly, with the benefit of rich logging capabilities. You can safely log any variable, from a simple string to an object.

Logging data to Clockwork can be done using the helper function, which even supports logging multiple values at once

```php
clock(User::first(), auth()->user(), $username)
```

If you want to specify a log level, you can use the long-form call

```php
clock()->info("User {$username} logged in!")
```

#### Timeline

Clockwork adds some general application runtime timeline events for you by default.

To add a custom event to the timeline, you'll need to start an event with an unique name and description first.

```php
clock()->startEvent('twitter-api-call', "Loading users latest tweets via Twitter API")
```

After executing the tracked block of code, you can end the event, using it's unique name.

```php
clock()->endEvent('twitter-api-call')
```

Events that are not stopped explicitly will simply finish when the application runtime ends.

#### Configuration

By default, Clockwork will only be available in debug mode, you can change this and more settings in the configuration file.

You can publish the configuration file using the `vendor:publish` artisan command to

- set when Clockwork should be enabled
- enable or disable the web UI
- configure how the request metadata is stored
- set what data should be collected

### Licence

Copyright (c) 2013 Miroslav Rigler

MIT License

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
