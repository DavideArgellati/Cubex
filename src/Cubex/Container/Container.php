<?php
/**
 * @author  brooke.bryan
 */
namespace Cubex\Container;

class Container
{
  protected static $_bound;

  public static function bind($name, $object)
  {
    static::$_bound[$name] = $object;
  }

  public static function bindIf($name, $object)
  {
    if(!static::bound($name))
    {
      static::bind($name, $object);
    }
  }

  public static function bound($name)
  {
    return isset(static::$_bound[$name]);
  }

  public static function get($name, $default = null)
  {
    if(static::bound($name))
    {
      return static::$_bound[$name];
    }
    else
    {
      return $default;
    }
  }
}
