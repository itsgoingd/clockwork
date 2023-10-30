<?php

use Clockwork\Support\Symfony\ClockworkFactory;

use Symfony\Component\DependencyInjection\Reference;

$container->register(Clockwork\Support\Symfony\ClockworkFactory::class)
	->setArgument('$container', new Reference('service_container'))
	->setArgument('$profiler', new Reference('profiler'));

$container->register(Clockwork\Clockwork::class)
	->setFactory([ new Reference(ClockworkFactory::class), 'clockwork' ])
	->setPublic(true);

$container->register(Clockwork\Authentication\AuthenticatorInterface::class)
	->setFactory([ new Reference(ClockworkFactory::class), 'clockworkAuthenticator' ])
	->setPublic(true);

$container->register(Clockwork\Storage\StorageInterface::class)
	->setFactory([ new Reference(ClockworkFactory::class), 'clockworkStorage' ])
	->setPublic(true);

$container->register(Clockwork\Support\Symfony\ClockworkSupport::class)
	->setArgument('$config', [])
	->setFactory([ new Reference(ClockworkFactory::class), 'clockworkSupport' ])
	->setPublic(true);

$container->autowire(Clockwork\Support\Symfony\ClockworkController::class)
	->setAutoconfigured(true);

$container->autowire(Clockwork\Support\Symfony\ClockworkListener::class)
	->setArgument('$profiler', new Reference('profiler'))
	->addTag('kernel.event_subscriber');

$container->autowire(Clockwork\Support\Symfony\ClockworkLoader::class)
	->addTag('routing.loader');

$container->setAlias('clockwork', Clockwork\Clockwork::class)->setPublic('true');
$container->setAlias('clockwork.authenticator', Clockwork\Authentication\AuthenticatorInterface::class)->setPublic('true');
$container->setAlias('clockwork.storage', Clockwork\Storage\StorageInterface::class)->setPublic('true');
$container->setAlias('clockwork.support', Clockwork\Support\Symfony\ClockworkSupport::class)->setPublic('true');
