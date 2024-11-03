<?php namespace Clockwork\DataSource;

use Doctrine\ORM\EntityManager;

// Data source for Doctrine, provides database queries
class DoctrineDataSource extends DBALDataSource
{
	public function __construct(?EntityManager $enm = null)
	{
		parent::__construct($enm ? $enm->getConnection() : null);
	}
}
