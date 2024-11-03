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
		$facade->registerSubscribers(
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
			new class implements Event\Test\AssertionSucceededSubscriber {
				public function notify($event): void { ClockworkExtension::recordAssertion(true); }
			},
			new class implements Event\Test\AssertionFailedSubscriber {
				public function notify($event): void { ClockworkExtension::recordAssertion(false); }
			}
		);
	}

	public static function recordTest($status, $message = null)
	{
		$trace = StackTrace::get([ 'arguments' => false, 'limit' => 10 ]);
		$testFrame = $trace->filter(function ($frame) { return $frame->object instanceof \PHPUnit\Framework\TestCase; })->last();

		if (! $testFrame) return;

		$testInstance = $testFrame->object;

		$reflectionClass = new \ReflectionClass($testInstance);

		if (! $reflectionClass->hasProperty('app')) return;

		$reflectionProperty = $reflectionClass->getProperty('app');
		$reflectionProperty->setAccessible(true);

		$app = $reflectionProperty->getValue($testInstance);

		if (! $app->make('clockwork.support')->isCollectingTests()) return;
		if ($app->make('clockwork.support')->isTestFiltered($testInstance->toString())) return;

		$app->make('clockwork')
			->resolveAsTest(
				str_replace('__pest_evaluable_', '', $testInstance->toString()),
				$status,
				$message,
				ClockworkExtension::$asserts
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
}
