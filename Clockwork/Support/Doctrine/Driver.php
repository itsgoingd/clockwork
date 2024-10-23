<?php namespace Clockwork\Support\Doctrine;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

// Part of the Clockwork logging middleware, should not be used directly
class Driver extends AbstractDriverMiddleware
{
	protected $onQuery;

	public function __construct(DriverInterface $driver, $onQuery)
	{
		parent::__construct($driver);

		$this->onQuery = $onQuery;
	}

	public function connect(array $params): Connection
	{
		return new Connection(parent::connect($params), $this->onQuery);
	}
}
