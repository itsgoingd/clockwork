<?php namespace Clockwork\Support\Symfony;

use Clockwork\Clockwork;
use Clockwork\Storage\SymfonyStorage;

use Symfony\Component\DependencyInjection\ContainerInterface;

class ClockworkFactory
{
	public function __construct(ContainerInterface $container)
	{
		$this->container = $container;
	}

	public function clockwork()
	{
		return (new Clockwork)
			->authenticator($this->container->get('clockwork.authenticator'))
			->storage($this->container->get('clockwork.storage'));
	}

	public function clockworkAuthenticator()
	{
		return $this->container->get('clockwork.support')->makeAuthenticator();
	}

	public function clockworkStorage()
	{
		return new SymfonyStorage(
			$this->container->get('profiler'), substr($this->container->getParameter('profiler.storage.dsn'), 5)
		);
	}

	public function clockworkSupport($config)
	{
		return new ClockworkSupport($this->container, $config);
	}
}
