<?php namespace Clockwork\Support\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class ClockworkBundle extends Bundle
{
	protected function getContainerExtensionClass()
	{
		return ClockworkExtension::class;
	}
}
