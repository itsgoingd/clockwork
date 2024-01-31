<?php

namespace Clockwork\Support\Mezzio;

use Clockwork\Support\Vanilla\Clockwork as VanillaClockwork;

class Clockwork extends VanillaClockwork
{
    public function __construct($config = [])
    {
        /**
         *  Use custom config which use getenv instead of $_ENV,
         *  the latter does not retrieve correctly the environment variables
         */
        $config = array_merge(include __DIR__ . '/config.php', $config);
        parent::__construct($config);
    }

    public function getWebHost()
    {
        return $this->config['web']['host'];
    }
}
