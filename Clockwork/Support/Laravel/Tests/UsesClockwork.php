<?php namespace Clockwork\Support\Laravel\Tests;

use Clockwork\Helpers\Serializer;
use Clockwork\Helpers\StackFilter;
use Clockwork\Helpers\StackTrace;

use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Runner\BaseTestRunner;

// Trait to include in PHPUnit tests to collect executed tests
trait UsesClockwork
{
	// Clockwork phpunit metadata collected while executing tests
	protected static $clockwork = [
		'asserts' => []
	];

	// Set up Clockwork in this test case, should be called from the PHPUnit setUp method
	protected function setUpClockwork()
	{
		$this->beforeApplicationDestroyed(function () {
			if ($this->app->make('clockwork.support')->isTestFiltered($this->toString())) return;

			$this->app->make('clockwork')
				->resolveAsTest(
					$this->toString(),
					$this->resolveClockworkStatus(),
					$this->getStatusMessage(),
					$this->resolveClockworkAsserts()
				)
				->storeRequest();
		});
	}

	// Resolve Clockwork test status
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

	// Resolve executed asserts
	protected function resolveClockworkAsserts()
	{
		$asserts = static::$clockwork['asserts'];

		if ($this->getStatus() == BaseTestRunner::STATUS_FAILURE) {
			$asserts[count($asserts) - 1]['passed'] = false;
		}

		static::$clockwork['asserts'] = [];

		return $asserts;
	}

	// Overload the main PHPUnit assert method to collect executed asserts
	public static function assertThat($value, Constraint $constraint, string $message = ''): void
	{
		$trace = StackTrace::get([ 'arguments' => true, 'limit' => 10 ]);

		$assertFrame = $trace->filter(function ($frame) { return strpos($frame->function, 'assert') === 0; })->last();
		$trace = $trace->skip(StackFilter::make()->isNotVendor([ 'itsgoingd', 'phpunit' ]))->limit(3);

		static::$clockwork['asserts'][] = [
			'name'      => $assertFrame->function,
			'arguments' => $assertFrame->args,
			'trace'     => (new Serializer)->trace($trace),
			'passed'    => true
		];

		parent::assertThat($value, $constraint, $message);
	}
}
