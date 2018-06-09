<?php

use Symfony\Component\DependencyInjection\Reference;

$container->register(Clockwork\Clockwork::class)
	->addMethodCall('setStorage', [ new Reference(Clockwork\Storage\SymfonyStorage::class) ])
	->setPublic(true);

$container->register(Clockwork\Support\Symfony\ClockworkSupport::class)
	->setArgument('$container', new Reference('service_container'))
	->setArgument('$config', [])
	->setPublic(true);

$container->register(Clockwork\Support\Symfony\ClockworkListener::class)
	->addTag('kernel.event_subscriber');

$container->autowire(Clockwork\Storage\SymfonyStorage::class)
	->setArgument('$profiler', new Reference('profiler'));

$container->autowire(Clockwork\Support\Symfony\ClockworkController::class)
	->setAutoconfigured(true)
	->setArgument('$profiler', new Reference('profiler'));

$container->setAlias('clockwork', Clockwork\Clockwork::class)->setPublic('true');
$container->setAlias('clockwork.support', Clockwork\Support\Symfony\ClockworkSupport::class)->setPublic('true');
