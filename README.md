Clockwork
=========

[Clockwork](http://github.com/itsgoingd/clockwork-chrome) is a Chrome extension for PHP development, extending Developer Tools with a new panel providing all kinds of information useful for debugging and profilling your PHP applications, including information about request, headers, get and post data, cookies, session data, database queries, routes, visualisation of application runtime and more.

This repository contains server-side component of Clockwork that gathers all the data, stores them in JSON format and serves them for displaying in Chrome Developer Tools extension.

## Installation

This extension provides out of the box support for Laravel 4 and Slim 2 frameworks, you can add support for any other or custom framework via an extensible API.

To install latest version simply add it to your `composer.json`:

```javascript
"itsgoingd/clockwork": "dev-master"
```

### Laravel 4

Once Clockwork is installed, you need to register Laravel service provider, in your `app/config/app.php`:

```php
'providers' => array(
	...    
    'Clockwork\Support\Laravel\ClockworkServiceProvider'
)
```

By default, Clockwork will only be available in debug mode, you can change this and other settings in the configuration file. Use the following Artisan command to publish the configuration file into your config directory:

```
$ php artisan config:publish itsgoingd/clockwork --path vendor/itsgoingd/clockwork/Clockwork/Support/Laravel/config/
```

To add your controller's runtime to timeline, add following to your base controller's constructor:

```php
$this->beforeFilter(function()
{
	Event::fire('clockwork.controller.start');
});

$this->afterFilter(function()
{
	Event::fire('clockwork.controller.end');
});
```

### Slim 2

Once Clockwork is installed, you need to add Slim middleware to your app:

```php
$app = new Slim(...);
$app->add(new Clockwork\Support\Slim\ClockworkMiddleware('/requests/storage/path'));
```

### Other frameworks

There is a [brief architecture overview](https://github.com/itsgoingd/clockwork/wiki/Development-notes) available, that should provide some help when implementing support for new frameworks or custom applications.

If you would like to see or are working on a support for yet unsupported framework feel free to open a new issue on github.

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
