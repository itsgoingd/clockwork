<?php namespace Clockwork\Support\Laravel\Tests;

use Clockwork\Helpers\Serializer;
use Clockwork\Helpers\StackFilter;
use Clockwork\Helpers\StackTrace;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Runner\BaseTestRunner;

trait UsesClockwork
{
	protected static $clockwork = [
		'assertsRan' => [],
		'testStart'  => null
	];

	protected function setUp()
	{
		parent::setUp();
		$this->setUpClockwork();
	}

	protected function setUpClockwork()
	{
		$this->beforeApplicationDestroyed(function () {
			$this->app->make('clockwork')
				->resolveTest(
					$this->toString(),
					$this->resolveClockworkStatus(),
					$this->getStatusMessage(),
					$this->resolveClockworkAssertsRan()
				)
				->storeRequest();
		});
	}

	protected function resolveClockworkStatus()
	{
		$status = $this->getStatus();

		$statuses = [
			BaseTestRunner::STATUS_UNKNOWN    => 'unknown',
			BaseTestRunner::STATUS_PASSED     => 'passed',
			BaseTestRunner::STATUS_SKIPPED    => 'skipped',
			BaseTestRunner::STATUS_INCOMPLETE => 'incomplete',
			BaseTestRunner::STATUS_FAILURE    => 'failed',
			BaseTestRunner::STATUS_ERROR      => 'error',
			BaseTestRunner::STATUS_RISKY      => 'passed',
			BaseTestRunner::STATUS_WARNING    => 'warning'
		];

		return isset($statuses[$status]) ? $statuses[$status] : null;
	}

	protected function resolveClockworkAssertsRan()
	{
		$assertsRan = static::$clockwork['assertsRan'];

		if ($this->getStatus() == BaseTestRunner::STATUS_FAILURE) {
			$assertsRan[count($assertsRan) - 1]['passed'] = false;
		}

		static::$clockwork['assertsRan'] = [];

		return $assertsRan;
	}

	protected static function logClockworkAssertRan($assert, $arguments)
	{
	}

	public static function assertThat($value, Constraint $constraint, string $message = ''): void
	{
		$trace = StackTrace::get([ 'arguments' => true, 'limit' => 10 ]);

		$assertFrame = $trace->filter(function ($frame) { return strpos($frame->function, 'assert') === 0; })->last();

		static::$clockwork['assertsRan'][] = [
			'name'      => $assertFrame->function,
			'arguments' => $assertFrame->args,
			'trace'     => (new Serializer)->trace($trace),
			'passed'    => true
		];

		parent::assertThat($value, $constraint, $message);
	}
}
