<?php

namespace AWP\IO;

defined('ABSPATH') || exit;

/**
 * Base class for singleton objects.
 * Class Singleton.
 * @package AWP\IO
 * @abstract
 */
abstract class Singleton
{

	private static $instances = array();
	protected function __construct()
	{
	}
	protected function __clone()
	{
	}
	public function __wakeup()
	{
		throw new \Exception('Cannot unserialize singleton');
	}

	public static function get_instance(...$params) {
        $class = static::class;
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new $class(...$params); // Parameters used only once
        } else if (!empty($params)) {
            throw new \Exception("Singleton already initialized. Additional parameters are not allowed.");
        }
        return self::$instances[$class];
    }


	/**
	 * Get instance object.
	 **/
	public static function get_instance_old()
	{
		$cls = get_called_class(); // late-static-bound class name
		if (!isset(self::$instances[$cls])) {
			self::$instances[$cls] = new static;
			(self::$instances[$cls])->on_construct();
		}
		return self::$instances[$cls];
	}

	function on_construct()
	{
	}

	/**
	 * Returns the singleton instance.
	 *
	 * @return $this
	 */
	public static function instance()
	{
		return static::get_instance();
	}
}
