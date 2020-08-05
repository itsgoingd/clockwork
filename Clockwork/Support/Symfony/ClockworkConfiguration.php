<?php namespace Clockwork\Support\Symfony;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpKernel\Kernel;

class ClockworkConfiguration implements ConfigurationInterface
{
	protected $debug;

	public function __construct($debug)
	{
		$this->debug = $debug;
	}

	public function getConfigTreeBuilder()
	{
		return $this->getConfigRoot()
			->children()
				->booleanNode('enable')->defaultValue($this->debug)->end()
				->variableNode('web')->defaultValue(true)->end()
				->booleanNode('authentication')->defaultValue(false)->end()
				->scalarNode('authentication_password')->defaultValue('VerySecretPassword')->end()
				->end()
			->end();
	}

	protected function getConfigRoot()
	{
		if (Kernel::VERSION_ID < 40300) {
			return (new TreeBuilder)->root('clockwork');
		}

		return (new TreeBuilder('clockwork'))->getRootNode();
	}
}
