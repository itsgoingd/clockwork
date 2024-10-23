<?php namespace Clockwork\Support\Doctrine;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;

// Clockwork logging middleware for Doctrine 3+
class Middleware implements MiddlewareInterface
{
	// On-query callback
	protected $onQuery;

	// Create a new middleware instance, takes an on-query callback as argument
	public function __construct($onQuery)
	{
		$this->onQuery = $onQuery;
	}

	public function wrap(DriverInterface $driver): DriverInterface
	{
		return new Driver($driver, $this->onQuery);
	}
}
