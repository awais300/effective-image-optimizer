<?php

namespace AWP\IO;

defined('ABSPATH') || exit;

/**
 * Base class for singleton objects.
 * 
 * Provides a foundation for implementing the Singleton pattern in WordPress plugins.
 * Ensures only one instance of a class exists and provides global access to it.
 * 
 * @package AWP\IO
 * @since 1.0.0
 * @abstract
 */
abstract class Singleton
{
    /**
     * Holds the instances of singleton classes
     * 
     * @var array<string, Singleton>
     * @access private
     * @static
     */
    private static $instances = array();

    /**
     * Protected constructor to prevent creating a new instance of the
     * Singleton via the `new` operator.
     * 
     * @access protected
     */
    protected function __construct() {}

    /**
     * Protected clone method to prevent cloning of the instance.
     * 
     * @access protected
     */
    protected function __clone() {}

    /**
     * Protected wakeup method to prevent unserializing of the instance.
     * 
     * @access public
     * @throws \Exception If attempting to unserialize singleton
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Returns the singleton instance of the class.
     * 
     * @access public
     * @static
     * @return static The singleton instance
     */
    public static function get_instance()
    {
        $cls = get_called_class(); // late-static-bound class name
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static;
            (self::$instances[$cls])->on_construct();
        }
        return self::$instances[$cls];
    }

    /**
     * Hook method called after instance construction in get_instance().
     * 
     * @deprecated since 1.0.0 Used only by get_instance()
     * @access public
     */
    public function on_construct() {}

    /**
     * Alias for get_instance().
     * 
     * Provides a more intuitive method name for getting the singleton instance.
     * 
     * @since 1.0.0
     * @access public
     * @static
     * @return static The singleton instance
     */
    public static function instance()
    {
        return static::get_instance();
    }
}
