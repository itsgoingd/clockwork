<?php namespace Clockwork\Support\Laravel\Tests;

use Clockwork\Helpers\{Serializer, StackFilter, StackTrace};

use PHPUnit\{Event, Runner, TextUI};

// Extension for collecting executed tests, compatible with PHPUnit 10+
class ClockworkExtension implements Runner\Extension\Extension
{
	public static $asserts = [];

	public function bootstrap(
		TextUI\Configuration\Configuration $configuration,
		Runner\Extension\Facade $facade,
		Runner\Extension\ParameterCollection $parameters
	): void {
		$subscribers = array_filter([
			new class implements Event\Test\PreparedSubscriber {
				public function notify($event): void { ClockworkExtension::$asserts = []; }
			},
			new class implements Event\Test\ErroredSubscriber {
				public function notify($event): void { ClockworkExtension::recordTest('error', $event->throwable()->message()); }
			},
			new class implements Event\Test\FailedSubscriber {
				public function notify($event): void { ClockworkExtension::recordTest('failed', $event->throwable()->message()); }
			},
			new class implements Event\Test\MarkedIncompleteSubscriber {
				public function notify($event): void { ClockworkExtension::recordTest('incomplete', $event->throwable()->message()); }
			},
			new class implements Event\Test\PassedSubscriber {
				public function notify($event): void { ClockworkExtension::recordTest('passed'); }
			},
			new class implements Event\Test\SkippedSubscriber {
				public function notify($event): void { ClockworkExtension::recordTest('skipped', $event->message()); }
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

	public static function recordTest($status, $message = null)
	{
		$testCase = static::resolveTestCase();

		if (! $testCase) return;

		$app = static::resolveApp($testCase);

		if (! $app) return;

		if (! $app->make('clockwork.support')->isCollectingTests()) return;
		if ($app->make('clockwork.support')->isTestFiltered($testCase->toString())) return;

		$app->make('clockwork')
			->resolveAsTest(
				str_replace('__pest_evaluable_', '', $testCase->toString()),
				$status,
				$message,
				static::$asserts
			)
			->storeRequest();
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
		$reflectionClass = new \ReflectionClass($testCase);

		if ($reflectionClass->hasProperty('app')) {
			$reflectionProperty = $reflectionClass->getProperty('app');
			$reflectionProperty->setAccessible(true);

			if ($reflectionProperty->getValue($testCase)) {
				return $reflectionProperty->getValue($testCase);
			}
		} elseif (method_exists($testCase, 'createApplication')) {
			return $testCase->createApplication();
		}
	}
}
