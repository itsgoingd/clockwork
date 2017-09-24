<?php namespace Clockwork\DataSource;

use Doctrine\ORM\EntityManager;

class DoctrineDataSource extends DBALDataSource
{
	public function __construct(EntityManager $enm)
	{
		parent::__construct($enm->getConnection());
	}
}
