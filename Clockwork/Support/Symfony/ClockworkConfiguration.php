<?php namespace Clockwork\Support\Symfony;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ClockworkConfiguration implements ConfigurationInterface
{
	public function getConfigTreeBuilder()
	{
		$treeBuilder = new TreeBuilder();
		$rootNode = $treeBuilder->root('clockwork');

		$rootNode
			->children()
				->booleanNode('enabled')->defaultTrue()->end()
			->end()
		;

		return $treeBuilder;
	}
}
