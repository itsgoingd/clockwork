<?php namespace Clockwork\Support\Laravel\Tests;

use Clockwork\Helpers\{Serializer, StackFilter, StackTrace};

use PHPUnit\{Event, Runner, TextUI};

// Extension for collecting executed tests, compatible with PHPUnit 10+
class ClockworkExtension implements Runner\Extension\Extension
{
	public static $asserts = [];
	public static $tests = [];

	public function bootstrap(
		TextUI\Configuration\Configuration $configuration,
		Runner\Extension\Facade $facade,
		Runner\Extension\ParameterCollection $parameters
	): void {
		$subscribers = array_filter([

			new class implements Event\Test\PreparedSubscriber {
				public function notify($event): void { ClockworkExtension::$asserts = []; ClockworkExtension::prepareTest($event->test()->id()); }
			},
			new class implements Event\Test\ErroredSubscriber {
				public function notify($event): void { ClockworkExtension::finishTest($event->test()->id(), 'error', $event->throwable()->message()); }
			},
			new class implements Event\Test\FailedSubscriber {
				public function notify($event): void { ClockworkExtension::finishTest($event->test()->id(), 'failed', $event->throwable()->message()); }
			},
			new class implements Event\Test\MarkedIncompleteSubscriber {
				public function notify($event): void { ClockworkExtension::finishTest($event->test()->id(), 'incomplete', $event->throwable()->message()); }
			},
			new class implements Event\Test\PassedSubscriber {
				public function notify($event): void { ClockworkExtension::finishTest($event->test()->id(), 'passed'); }
			},
			new class implements Event\Test\SkippedSubscriber {
				public function notify($event): void { ClockworkExtension::finishTest($event->test()->id(), 'skipped', $event->message()); }
			},
			new class implements Event\Test\FinishedSubscriber {
				public function notify($event): void { ClockworkExtension::storeTest($event->test()->id()); }
			},
			interface_exists(Event\Test\AssertionSucceededSubscriber::class) ? new class implements Event\Test\AssertionSucceededSubscriber {
				public function notify($event): void { ClockworkExtension::recordAssertion(true); }
			} : null,
			interface_exists(Event\Test\AssertionFailedSubscriber::class) ? new class implements Event\Test\AssertionFailedSubscriber {
				public function notify($event): void { ClockworkExtension::recordAssertion(false); }
			} : null
		]);

		$facade->registerSubscribers(...$subscribers);
	}

	public static function prepareTest($id)
	{
		if (static::isPrepared($id)) return;

		$testCase = static::resolveTestCase();

		if (! $testCase) return;

		$app = static::resolveApp($testCase);

		if (! $app) return;

		$support = $app->make('clockwork.support');

		if (! $support->isCollectingTests()) return;
		if ($support->isTestFiltered($testCase->toString())) return;

		static::$tests[$id] = [
			'clockwork' => $app->make('clockwork'),
			'name'      => str_replace('__pest_evaluable_', '', $testCase->toString()),
			'status'    => 'passed',
			'message'   => null
		];

		static::beforeApplicationDestroyed($testCase, function () use ($id) {
			static::resolveTest($id);
		});
	}

	public static function finishTest($id, $status, $message = null)
	{
		static::prepareTest($id);

		if (! static::isPrepared($id)) return;

		static::$tests[$id]['status'] = $status;
		static::$tests[$id]['message'] = $message;
	}

	public static function resolveTest($id)
	{
		if (! static::isPrepared($id) || static::isResolved($id)) return;

		static::$tests[$id]['clockwork']->resolveRequest();
		static::$tests[$id]['resolved'] = true;
	}

	public static function storeTest($id)
	{
		if (! static::isPrepared($id)) return;

		static::resolveTest($id);

		$test = static::$tests[$id];

		unset(static::$tests[$id]);

		$test['clockwork']
			->asTest($test['name'], $test['status'], $test['message'], static::$asserts)
			->storeRequest();
	}

	protected static function isPrepared($id)
	{
		return isset(static::$tests[$id]);
	}

	protected static function isResolved($id)
	{
		return isset(static::$tests[$id]['resolved']);
	}

	protected static function beforeApplicationDestroyed($testCase, $callback)
	{
		// Call the protected beforeApplicationDestroyed method by binding a closure to the object
		(function ($callback) {
			$this->beforeApplicationDestroyed($callback);
		})->call($testCase, $callback);
	}

	public static function recordAssertion($passed = true)
	{
		$trace = StackTrace::get([ 'arguments' => true, 'limit' => 10 ]);
		$assertFrame = $trace->filter(function ($frame) { return strpos($frame->function, 'assert') === 0; })->last();

		$trace = $trace->skip(StackFilter::make()->isNotVendor([ 'itsgoingd', 'phpunit' ]))->limit(3);

		static::$asserts[] = [
			'name'      => $assertFrame->function,
			'arguments' => $assertFrame->args,
			'trace'     => (new Serializer)->trace($trace),
			'passed'    => $passed
		];
	}

	protected static function resolveTestCase()
	{
		$trace = StackTrace::get([ 'arguments' => false, 'limit' => 10 ]);

		$testFrame = $trace->filter(function ($frame) { return $frame->object instanceof \PHPUnit\Framework\TestCase; })->last();

		return $testFrame?->object;
	}

	protected static function resolveApp($testCase)
	{
		if (! property_exists($testCase, 'app')) return;

		// Retrieve the protected app property by binding a closure to the object
		return (function () {
			return $this->app;
		})->call($testCase);
	}
}
