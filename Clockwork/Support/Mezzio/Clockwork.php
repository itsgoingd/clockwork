<?php

namespace Clockwork\Support\Mezzio;

use Clockwork\Support\Vanilla\Clockwork as VanillaClockwork;
use Clockwork\Clockwork as BaseClockwork;
use Clockwork\DataSource\PhpDataSource;

class Clockwork extends VanillaClockwork
{
    public function __construct($config = [])
    {
        /**
         *  Use custom config which use getenv instead of $_ENV,
         *  the latter does not retrieve correctly the environment variables
         */
        $this->config = array_merge(include __DIR__ . '/config.php', $config);

        $this->clockwork = new BaseClockwork();

        $this->clockwork->addDataSource(new PhpDataSource());
        $this->clockwork->storage($this->makeStorage());
        $this->clockwork->authenticator($this->makeAuthenticator());

        $this->configureSerializer();
        $this->configureShouldCollect();
        $this->configureShouldRecord();

        if ($this->config['register_helpers']) {
            include __DIR__ . '/helpers.php';
        }
    }

    public function getApiPath()
    {
        return $this->config['api'];
    }

    public function getWebHost()
    {
        return $this->config['web']['host'];
    }

    public function isWebEnabled()
    {
        return $this->config['web']['enable'];
    }

    public function isEnabled()
    {
        return $this->config['enable'];
    }
}
