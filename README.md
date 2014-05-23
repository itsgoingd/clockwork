Clockwork
=========

**[Clockwork](http://github.com/itsgoingd/clockwork-chrome) is a Chrome extension for PHP development**, extending Developer Tools with a new panel providing all kinds of information useful for debugging and profiling your PHP applications, including information about request, headers, get and post data, cookies, session data, database queries, routes, visualisation of application runtime and more.

**Not a Chrome user?** Check out [embeddable web app version of Clockwork](http://github.com/itsgoingd/clockwork-web), supporting many modern browsers along Chrome with out of the box support for Laravel and Slim.

**This repository contains server-side component of Clockwork** that gathers all the data, stores them in JSON format and serves them for displaying in Chrome Developer Tools extension.

## Installation

This extension provides out of the box support for Laravel 4, Slim 2 and CodeIgniter 2.1 frameworks, you can add support for any other or custom framework via an extensible API.

To install latest version simply add it to your `composer.json`:

```javascript
"itsgoingd/clockwork": "1.*"
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

Clockwork also comes with a facade, which provides an easy way to add records to the Clockwork log and events to the timeline. You can register the facade in your `app/config/app.php`:

```php
'aliases' => array(
	...
	'Clockwork' => 'Clockwork\Support\Laravel\Facade',
)
```

Now you can use the following commands:

```php
Clockwork::startEvent('event_name', 'Event description.'); // event called 'Event description.' appears in Clockwork timeline tab

Clockwork::info('Message text.'); // 'Message text.' appears in Clockwork log tab
Log::info('Message text.'); // 'Message text.' appears in Clockwork log tab as well as application log file

Clockwork::info(array('hello' => 'world')); // logs json representation of the array
Clockwork::info(new Object()); // logs string representation of the objects if the object implements __toString magic method, logs json representation of output of toArray method if the object implements it, if neither is the case, logs json representation of the object cast to array

Clockwork::endEvent('event_name');
```

### Slim 2

Once Clockwork is installed, you need to add Slim middleware to your app:

```php
$app = new Slim(...);
$app->add(new Clockwork\Support\Slim\ClockworkMiddleware('/requests/storage/path'));
```

Clockwork is now available in Slim's DI container and can be used like this:

```php
$app = Slim::getInstance();

$app->clockwork->startEvent('event_name', 'Event description.'); // event called 'Event description.' appears in Clockwork timeline tab

$app->clockwork->info('Message text.'); // 'Message text.' appears in Clockwork log tab
$app->log->info('Message text.'); // 'Message text.' appears in Clockwork log tab as well as application log file

$app->clockwork->endEvent('event_name');
```

### CodeIgniter 2.1

Once Clockwork is installed, you need to copy the Clockwork controller from `vendor/itsgoingd/clockwork/Clockwork/Support/CodeIgniter/Clockwork.php` to your controllers directory and set up the following route:

```php
$route['__clockwork/(.*)'] = 'clockwork/$1';
```

Finally, you need to set up the Clockwork hooks by adding following to your `application/config/hooks.php` file:

```php
Clockwork\Support\CodeIgniter\Register::registerHooks($hook);
```

To use Clockwork within your controllers/models/etc. you will need to extend your `CI_Controller` class. (If you haven't done so already) Create a new file at `application/core/MY_Controller.php`. 

```php
class MY_Controller extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $GLOBALS['EXT']->_call_hook('pre_controller_constructor');
     } 
}
```

Now you can use the following commands in your CodeIgniter app:

```php
$this->clockwork->startEvent('event_name', 'Event description.'); // event called 'Event description.' appears in Clockwork timeline tab

$this->clockwork->info('Message text.'); // 'Message text.' appears in Clockwork log tab

$this->clockwork->endEvent('event_name');
```

### Other frameworks

There is a [brief architecture overview](https://github.com/itsgoingd/clockwork/wiki/Development-notes) available, that should provide some help when implementing support for new frameworks or custom applications.

If you would like to see or are working on a support for yet unsupported framework feel free to open a new issue on github.

## Addons

- [clockwork-cli](https://github.com/ptrofimov/clockwork-cli) - Command-line interface to Clockwork by [ptrofimov](https://github.com/ptrofimov)
- [guzzle-clockwork](https://github.com/hannesvdvreken/guzzle-clockwork) - Plugin for logging Guzzle requests to Clockwork by [hannesvdvreken](https://github.com/hannesvdvreken)

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
