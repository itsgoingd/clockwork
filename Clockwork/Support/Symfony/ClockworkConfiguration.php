<?php namespace Clockwork\Support\Symfony;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ClockworkConfiguration implements ConfigurationInterface
{
	protected $debug;

	public function __construct($debug)
	{
		$this->debug = $debug;
	}

	public function getConfigTreeBuilder()
	{
		return (new TreeBuilder)->root('clockwork')
			->children()
				->booleanNode('enable')->defaultValue($this->debug)->end()
                ->end()
			->end();
	}
}
