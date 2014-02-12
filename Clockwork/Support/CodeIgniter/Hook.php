<?php

namespace Clockwork\Support\CodeIgniter;

use Clockwork\Clockwork;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\DataSource\CodeIgniterDataSource;
use Clockwork\Storage\FileStorage;

class Hook
{
	private static $__clockwork = null;
	private static $__datasource = null;
	private static $__storagePath = '/tmp/clockwork/';
	
	private $_clockwork;
	private $_datasource;	
	
	private static $__disabled = false;
	
	public static function disable()
	{
		self::$__disabled = true;
	}
	
	public function setStoragePath($storagePath = null)
	{
		if ($storagePath == null) {
			return;
		}
		
		if (self::$__clockwork != null) {
			trigger_error("Clockwork Storage has already been initialized");
			return;
		}
		
		self::$__storagePath = $storagePath;
	}
	
	public function __construct()
	{
		// Instantiate Clockwork
		if (self::$__clockwork == null) {
			self::$__clockwork = new Clockwork;
			self::$__clockwork->addDataSource(new PhpDataSource());
			
			self::$__datasource = new CodeIgniterDataSource();
			self::$__clockwork->addDataSource(self::$__datasource);
			
			$clockworkStorage = new FileStorage(self::$__storagePath);
			self::$__clockwork->setStorage($clockworkStorage);
			
			header('X-Clockwork-Id: '.self::$__clockwork->getRequest()->id);
			header('X-Clockwork-Version: '.Clockwork::VERSION);
		}
		
		$this->_clockwork = self::$__clockwork;
		$this->_datasource = self::$__datasource;
	}
	
	// Called very early during system execution. Only the benchmark and
	// hooks class have been loaded at this point. No routing or other 
	// processes have happened.
	public function pre_system()
	{
		$this->_datasource->startEvent('boot', 'Framework booting.');
		$this->_datasource->startEvent('run', 'Framework running.');
	}
	
	// Called immediately prior to any of your controllers being called. 
	// All base classes, routing, and security checks have been done.
	public function pre_controller()
	{
		$this->_datasource->endEvent('boot');
		$this->_datasource->startEvent('dispatch', 'Router dispatch.');
	}

	// Called immediately before your controller's constructor.
	public function pre_controller_constructor()
	{
		$CI = &get_instance();
		$CI->clockwork = self::$__clockwork;
	}
	
	// Called immediately after your controller is instantiated, but prior 
	// to any method calls happening.
	public function post_controller_constructor()
	{
		$this->_datasource->endEvent('dispatch');
		$this->_datasource->startEvent('controller', 'Controller running');
	}
	
	// Called immediately after your controller is fully executed.
	public function post_controller()
	{
		$this->_datasource->endEvent('controller');
	}
	
	public function post_system()
	{
		$this->_datasource->endEvent('run');
		if (!self::$__disabled) {
			$this->_clockwork->resolveRequest();
			$this->_clockwork->storeRequest();
		}
	}
	
	public static function startEvent($event, $description)
	{
		self::$__datasource->startEvent($event, $description);
	}
	
	public static function endEvent($event)
	{
		self::$__datasource->endEvent($event);
	}
	
	public static function getStorage()
	{
		return self::$__clockwork->getStorage();
	}
	
	public static function addDataSource($dataSource)
	{
		self::$__clockwork->addDataSource($dataSource);
	}
}
