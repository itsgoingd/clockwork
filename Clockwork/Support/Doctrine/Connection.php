<?php namespace Clockwork\Support\Doctrine;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;

// Part of the Clockwork logging middleware, should not be used directly
class Connection extends AbstractConnectionMiddleware
{
	protected $onQuery;

	public function __construct(ConnectionInterface $connection, $onQuery)
	{
		parent::__construct($connection);

		$this->onQuery = $onQuery;
	}

	public function query(string $sql): Result
	{
		$time = microtime(true);

		$result = parent::query($sql);

		($this->onQuery)([ 'query' => $sql, 'time' => $time ]);

		return $result;
	}

	public function exec(string $sql): int
	{
		$time = microtime(true);

		$result = parent::exec($sql);

		($this->onQuery)([ 'query' => $sql, 'time' => $time ]);

		return $result;
	}
}
