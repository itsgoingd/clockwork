<?php

namespace Clockwork\Support\CodeIgniter;

use Clockwork\Clockwork;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\DataSource\CodeIgniterDataSource;
use Clockwork\Storage\FileStorage;

class Hook
{
    private static $clockwork = null;
    private static $datasource = null;
    private static $storagePath = '/tmp/clockwork/';

    private $clockwork;
    private $datasource;

    private static $disabled = false;

    public static function disable()
    {
        self::$disabled = true;
    }

    public function setStoragePath($storagePath = null)
    {
        if ($storagePath == null) {
            return;
        }

        if (self::$clockwork != null) {
            trigger_error("Clockwork Storage has already been initialized");
            return;
        }

        self::$storagePath = $storagePath;
    }

    public function __construct()
    {
        // Instantiate Clockwork
        if (self::$clockwork == null) {
            self::$clockwork = new Clockwork;
            self::$clockwork->addDataSource(new PhpDataSource());

            self::$datasource = new CodeIgniterDataSource();
            self::$clockwork->addDataSource(self::$datasource);

            $clockworkStorage = new FileStorage(self::$storagePath);
            self::$clockwork->setStorage($clockworkStorage);

            header('X-Clockwork-Id: '.self::$clockwork->getRequest()->id);
            header('X-Clockwork-Version: '.Clockwork::VERSION);
        }

        $this->clockwork = self::$clockwork;
        $this->datasource = self::$datasource;
    }

    // Called very early during system execution. Only the benchmark and
    // hooks class have been loaded at this point. No routing or other
    // processes have happened.
    public function pre_system()
    {
        $this->datasource->startEvent('boot', 'Framework booting.');
        $this->datasource->startEvent('run', 'Framework running.');
    }

    // Called immediately prior to any of your controllers being called.
    // All base classes, routing, and security checks have been done.
    public function pre_controller()
    {
        $this->datasource->endEvent('boot');
        $this->datasource->startEvent('dispatch', 'Router dispatch.');
    }

    // Called immediately before your controller's constructor.
    public function pre_controller_constructor()
    {
        $CI = &get_instance();
        $CI->clockwork = self::$clockwork;
    }

    // Called immediately after your controller is instantiated, but prior
    // to any method calls happening.
    public function post_controller_constructor()
    {
        $this->datasource->endEvent('dispatch');
        $this->datasource->startEvent('controller', 'Controller running');
    }

    // Called immediately after your controller is fully executed.
    public function post_controller()
    {
        $this->datasource->endEvent('controller');
    }

    public function post_system()
    {
        $this->datasource->endEvent('run');
        if (!self::$disabled) {
            $this->clockwork->resolveRequest();
            $this->clockwork->storeRequest();
        }
    }

    public static function startEvent($event, $description)
    {
        self::$datasource->startEvent($event, $description);
    }

    public static function endEvent($event)
    {
        self::$datasource->endEvent($event);
    }

    public static function getStorage()
    {
        return self::$clockwork->getStorage();
    }

    public static function addDataSource($dataSource)
    {
        self::$clockwork->addDataSource($dataSource);
    }
}
