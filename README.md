Clockwork
=========

**[Clockwork](http://github.com/itsgoingd/clockwork-chrome) is a Chrome extension for PHP development**, extending Developer Tools with a new panel providing all kinds of information useful for debugging and profiling your PHP applications, including information about request, headers, get and post data, cookies, session data, database queries, routes, visualisation of application runtime and more.

**Not a Chrome user?** Check out [embeddable web app version of Clockwork](http://github.com/itsgoingd/clockwork-web), supporting many modern browsers along Chrome with out of the box support for Laravel and Slim.
There are also a third-party [Firebug extension](https://github.com/sidorovich/clockwork-firebug) and a [CLI client app](https://github.com/ptrofimov/clockwork-cli) available.

**This repository contains server-side component of Clockwork** that gathers all the data, stores them in JSON format and serves them for displaying in Chrome Developer Tools extension.

## Installation

This extension provides out of the box support for Laravel, Slim 2 and CodeIgniter 2.1 frameworks, you can add support for any other or custom framework via an extensible API.

To install latest version simply add it to your `composer.json`:

```javascript
"itsgoingd/clockwork": "~1.11.1"
```

### Laravel

Once Clockwork is installed, you need to register Laravel service provider, in your `config/app.php`:

```php
'providers' => [
	...
	'Clockwork\Support\Laravel\ClockworkServiceProvider'
]
```

You also need to add Clockwork middleware, in your `app/Http/Kernel.php`:

```php
protected $middleware = [
	'Clockwork\Support\Laravel\ClockworkMiddleware',
	...
]
```

By default, Clockwork will only be available in debug mode, you can change this and other settings in the configuration file. Use the following Artisan command to publish the configuration file into your config directory:

```
$ php artisan vendor:publish --provider='Clockwork\Support\Laravel\ClockworkServiceProvider'
```

Clockwork also comes with a `clock()` helper function, which provides an easy way to add records to the Clockwork log and events to the timeline.

```php
clock()->startEvent('event_name', 'Event description.'); // event called 'Event description.' appears in Clockwork timeline tab

clock('Message text.'); // 'Message text.' appears in Clockwork log tab
logger('Message text.'); // 'Message text.' appears in Clockwork log tab as well as application log file

clock(array('hello' => 'world')); // logs json representation of the array
clock(new Object()); // logs string representation of the objects if the object implements __toString magic method, logs json representation of output of toArray method if the object implements it, if neither is the case, logs json representation of the object cast to array

clock()->endEvent('event_name');
```

If you prefer using Facades, add following to your `app/config/app.php`:

```php
'aliases' => [
	...
	'Clockwork' => 'Clockwork\Support\Laravel\Facade',
]
```

### Lumen

Once Clockwork is installed, you need to register the Clockwork service provider, in your `bootstrap/app.php`:

```php
$app->register(Clockwork\Support\Lumen\ClockworkServiceProvider::class);
```

You also need to add the Clockwork middleware, in the same file:

```php
$app->middleware([
	...
	Clockwork\Support\Lumen\ClockworkMiddleware::class
]);
```

By default, Clockwork will only be available in debug mode (`APP_DEBUG` set to true), you can change this and other settings via environment variables.
Simply specify the setting as environment variable prefixed with `CLOCKWORK_`, eg. `CLOCKWORK_ENABLE`, [full list of available settings](https://raw.githubusercontent.com/itsgoingd/clockwork/v1/Clockwork/Support/Laravel/config/clockwork.php).

Clockwork also comes with a `clock()` helper function (see examples above) and a facade thats automatically registered when you enable facades in your `bootstrap/app.php`.

### Other frameworks

There is a [brief architecture overview](https://github.com/itsgoingd/clockwork/wiki/Development-notes) available, that should provide some help when implementing support for new frameworks or custom applications.

If you would like to see or are working on a support for yet unsupported framework feel free to open a new issue on github.

## Addons

- [clockwork-cli](https://github.com/ptrofimov/clockwork-cli) - Command-line interface to Clockwork by [ptrofimov](https://github.com/ptrofimov)
- [guzzle-clockwork](https://github.com/hannesvdvreken/guzzle-clockwork) - Plugin for logging Guzzle requests to Clockwork by [hannesvdvreken](https://github.com/hannesvdvreken)
- [silverstripe-clockwork](https://github.com/markguinn/silverstripe-clockwork) - Integration for SilverStripe CMS/framework by [markguinn](https://github.com/markguinn)
- [clockwork-firebug](https://github.com/sidorovich/clockwork-firebug) - Extension for Firebug (like for Chrome) by [Pavel Sidorovich](https://github.com/sidorovich)

- [laravel-doctrine](http://www.laraveldoctrine.org) - Doctrine support for Laravel, contains ootb Clockwork support

## Licence

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
