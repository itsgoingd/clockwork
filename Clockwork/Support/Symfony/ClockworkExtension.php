<?php namespace Clockwork\Support\Symfony;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class ClockworkExtension extends ConfigurableExtension
{
	public function loadInternal(array $config, ContainerBuilder $container): void
	{
		$loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/Resources/config'));
		$loader->load('clockwork.php');

		$container->getDefinition(ClockworkSupport::class)->replaceArgument('$config', $config);
	}

	public function getConfiguration(array $config, ContainerBuilder $container)
	{
		return new ClockworkConfiguration($container->getParameter('kernel.debug'));
	}
}
