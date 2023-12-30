<p align="center">
	<img width="300px" src="https://github.com/itsgoingd/clockwork/raw/master/.github/assets/title.png">
	<img width="100%" src="https://github.com/itsgoingd/clockwork/raw/master/.github/assets/screenshot.png">
</p>

> Clockwork is a development tool for PHP available right in your browser. Clockwork gives you an insight into your application runtime - including request data, performance metrics, log entries, database queries, cache queries, redis commands, dispatched events, queued jobs, rendered views and more - for HTTP requests, commands, queue jobs and tests.

> *This repository contains the server-side component of Clockwork.*

> Check out on the [Clockwork website](https://underground.works/clockwork) for details.

<p align="center">
	<a href="https://underground.works/clockwork">
		<img width="100%" src="https://github.com/itsgoingd/clockwork/raw/master/.github/assets/features-1.png">
	</a>
	<a href="https://underground.works/clockwork">
		<img width="100%" src="https://github.com/itsgoingd/clockwork/raw/master/.github/assets/features-2.png">
	</a>
	<a href="https://underground.works/clockwork">
		<img width="100%" src="https://github.com/itsgoingd/clockwork/raw/master/.github/assets/features-3.png">
	</a>
	<a href="https://underground.works/clockwork">
		<img width="100%" src="https://github.com/itsgoingd/clockwork/raw/master/.github/assets/features-4.png">
	</a>
	<a href="https://underground.works/clockwork">
		<img width="100%" src="https://github.com/itsgoingd/clockwork/raw/master/.github/assets/features-5.png">
	</a>
	<a href="https://underground.works/clockwork">
		<img width="100%" src="https://github.com/itsgoingd/clockwork/raw/master/.github/assets/features-6.png">
	</a>
	<a href="https://underground.works/clockwork">
		<img width="100%" src="https://github.com/itsgoingd/clockwork/raw/master/.github/assets/features-7.png">
	</a>
	<a href="https://underground.works/clockwork">
		<img width="100%" src="https://github.com/itsgoingd/clockwork/raw/master/.github/assets/features-8.png">
	</a>
</p>

### Installation

Install the Clockwork library via [Composer](https://getcomposer.org/).

```
composer require itsgoingd/clockwork
```

Congratulations, you are done! To enable more features like commands or queue jobs profiling, publish the configuration file via the `vendor:publish` Artisan command.

**Note:** If you are using the Laravel route cache, you will need to refresh it using the route:cache Artisan command.

Read [full installation instructions](https://underground.works/clockwork/#docs-installation) on the Clockwork website.

### Features

#### Collecting data

The Clockwork server-side component collects and stores data about your application.

Clockwork is only active when your app is in debug mode by default. You can choose to explicitly enable or disable Clockwork, or even set Clockwork to always collect data without exposing them for further analysis.

We collect a whole bunch of useful data by default, but you can enable more features or disable features you don't need in the config file.

Some features might allow for advanced options, eg. for database queries you can set a slow query threshold or enable detecting of duplicate (N+1) queries. Check out the config file to see all what Clockwork can do.

There are several options that allow you to choose for which requests Clockwork is active.

On-demand mode will collect data only when Clockwork app is open. You can even specify a secret to be set in the app settings to collect request. Errors only will record only requests ending with 4xx and 5xx responses. Slow only will collect only requests with responses above the set slow threshold. You can also filter the collected and recorded requests by a custom closure. CORS pre-flight requests will not be collected by default.

New in Clockwork 4.1, artisan commands, queue jobs and tests can now also be collected, you need to enable this in the config file.

Clockwork also collects stack traces for data like log messages or database queries. Last 10 frames of the trace are collected by default. You can change the frames limit or disable this feature in the configuration file.

#### Viewing data

##### Web interface

Visit `/clockwork` route to view and interact with the collected data.

The app will show all executed requests, which is useful when the request is not made by browser, but for example a mobile application you are developing an API for.

##### Browser extension

A browser dev tools extension is also available for Chrome and Firefox:

- [Chrome Web Store](https://chrome.google.com/webstore/detail/clockwork/dmggabnehkmmfmdffgajcflpdjlnoemp)
- [Firefox Addons](https://addons.mozilla.org/en-US/firefox/addon/clockwork-dev-tools/)

##### Toolbar

Clockwork now gives you an option to show basic request information in the form of a toolbar in your app.

The toolbar is fully rendered client-side and requires installing a tiny javascript library.

[Learn more](https://underground.works/clockwork/#docs-viewing-data) on the Clockwork website.

#### Logging

You can log any variable via the clock() helper, from a simple string to an array or object, even multiple values:

```php
clock(User::first(), auth()->user(), $username)
```

The `clock()` helper function returns it's first argument, so you can easily add inline debugging statements to your code:

```php
User::create(clock($request->all()))
```

If you want to specify a log level, you can use the long-form call:

```php
clock()->info("User {$username} logged in!")
```

#### Timeline

Timeline gives you a visual representation of your application runtime.

To add an event to the timeline - start it with a description, execute the tracked code and finish the event. A fluent api is available to further configure the event.

```php
// using timeline api with begin/end and fluent configuration
clock()->event('Importing tweets')->color('purple')->begin();
    ...
clock()->event('Importing tweets')->end();
```

Alternatively you can execute the tracked code block as a closure. You can also choose to use an array based configuration instead of the fluent api.

```php
// using timeline api with run and array-based configuration
clock()->event('Updating cache', [ 'color' => 'green' ])->run(function () {
    ...
});
```

Read more about available features on the [Clockwork website](https://underground.works/clockwork).

<p align="center">
	<a href="https://underground.works">
		<img width="150px" src="https://github.com/itsgoingd/clockwork/raw/master/.github/assets/footer.png">
	</a>
</p>
