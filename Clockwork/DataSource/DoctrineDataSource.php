<?php namespace Clockwork\DataSource;

use Doctrine\ORM\EntityManager;

// Data source for Doctrine, provides database queries
class DoctrineDataSource extends DBALDataSource
{
	public function __construct(EntityManager $enm)
	{
		parent::__construct($enm->getConnection());
	}
}
