<?php

namespace Clockwork\Support\CodeIgniter;

/**
 * Singleton class for easy loading of the Clockwork Hooks. Add the following to
 * your application/config/hooks.php file:
 * 
 *     Clockwork\Support\CodeIgniter\Register::registerHooks($hook);
 * 
 */
class Register
{
	public static function registerHooks(&$hook, $storagePath = null)
	{
		$hooknames = array(
			'pre_system', 
			'pre_controller',
			'pre_controller_constructor',
			'post_controller_constructor',
			'post_controller',
			'post_system'
		);
		
		// Force Autoload and set Storage Path for the Hook_Clockwork
		// class. Also force it to auto-load so that CodeIgniter does
		// not make the attempt.
		Hook::setStoragePath($storagePath);
		
		// Hook into all the necessary hooks for Hook_Clockwork to do
		// it's job. Make sure that other previous hooks are not
		// overwritten.
		foreach ($hooknames as $hookname) {
			if (!isset($hook[$hookname])) {
				$hook[$hookname] = array();
			}
			if (isset($hook[$hookname]) && isset($hook[$hookname]['class'])) {
				$hook[$hookname] = array(0 => $hook[$hookname]);
			}
			
			$hook[$hookname][] = array(
				'class'		=> 'Clockwork\\Support\\CodeIgniter\\Hook',
				'function'	=> $hookname,
				'filename'	=> 'Hook.php',
				'filepath'	=> self::resolveFilepath(),
				'params'	=> array()
			);
		}
	}
	
	/**
	 * Resolves the path used for the hook, relative to APPPATH
	 */
	private static function resolveFilepath()
	{
		$path = realpath(APPPATH);
		$steps = 0;
	 
		while (strstr(__DIR__, $path) === false) {
			$path = dirname($path);
			$steps++;
		}
	 
		return str_repeat('..' . DIRECTORY_SEPARATOR, $steps) . preg_replace('#^' . preg_quote($path, '#') . '#', '', __DIR__);
	}
}
