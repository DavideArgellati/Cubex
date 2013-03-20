<?php
/**
 * @author  gareth.evans
 */
namespace Cubex\Cookie;

use Cubex\Events\EventManager;

class Cookies
{
  /**
   * @var CookieInterface[]
   */
  private static $_cookies;
  private static $_initialised = false;
  private static $_written     = false;

  private static function _init()
  {
    if(self::$_initialised === false)
    {
      self::$_initialised = true;
      self::_readCookies();
      self::_listen();
    }
  }

  private static function _readCookies()
  {
    if(self::$_cookies === null)
    {
      self::$_cookies = [];

      if(is_array($_SERVER))
      {
        foreach($_SERVER as $kk => $vv)
        {
          if($kk === "HTTP_COOKIE")
          {
            $flatCookieArr = explode(";", $vv);
            foreach($flatCookieArr as $flatCookie)
            {
              if(strstr($flatCookie, "=") === false)
              {
                $flatCookie .= "=";
              }

              list($name, $value) = explode("=", $flatCookie);

              $name  = urldecode(trim($name));
              $value = urldecode(trim($value));

              if(EncryptedCookie::isEncrypted($value))
              {
                $cookie = new EncryptedCookie($name, $value);
              }
              else
              {
                $cookie = new StandardCookie($name, $value);
              }

              $cookie->setMode(StandardCookie::MODE_READ);
              self::$_cookies[$name] = $cookie;
            }
          }
        }
      }
    }
  }

  protected static function _listen()
  {
    EventManager::listen(
      EventManager::CUBEX_RESPONSE_PREPARE,
      [__NAMESPACE__ . "\\Cookies", "write"]
    );

    // If the page stops execution before the default event triggers the cookies
    // to write then we try and send them now. If the headers are already gone
    // though, it's far too late!
    register_shutdown_function([__NAMESPACE__ . "\\Cookies", "write"]);
  }

  /**
   * This can only get called once per request. It will be called automatically
   * when the CUBEX_RESPONSE_PREPARE event is triggered. It can be called
   * manually but is not recommended.
   */
  public static function write()
  {
    if(self::$_written === false && headers_sent() === false)
    {
      foreach(self::$_cookies as $cookie)
      {
        if($cookie->isWrite())
        {
          header("Set-Cookie: " . (string)$cookie, false);
        }
      }
    }

    self::$_written = true;
  }

  /**
   * @param string $name
   *
   * @return standardCookie|EncryptedCookie
   * @throws \InvalidArgumentException
   */
  public static function get($name)
  {
    self::_init();
    if(!array_key_exists($name, self::$_cookies))
    {
      throw new \InvalidArgumentException(
        sprintf("The cookie '%s' does not exist.", $name)
      );
    }

    return self::$_cookies[$name];
  }

  /**
   * @param string $name
   *
   * @throws \InvalidArgumentException
   */
  public static function delete($name)
  {
    self::_init();
    if(!array_key_exists($name, self::$_cookies))
    {
      throw new \InvalidArgumentException(
        sprintf("The cookie '%s' does not exist.", $name)
      );
    }

    self::$_cookies[$name]->delete();
  }

  /**
   * This will delete all active cookies
   */
  public static function flush()
  {
    self::_init();
    foreach(self::$_cookies as $cookie)
    {
      $cookie->delete();
    }
  }

  /**
   * @param string $name
   *
   * @return bool
   */
  public static function exists($name)
  {
    self::_init();
    return array_key_exists($name, self::$_cookies);
  }

  /**
   * @param CookieInterface $cookie
   *
   * @return bool
   */
  public static function set(CookieInterface $cookie)
  {
    self::_init();
    self::$_cookies[$cookie->getName()] = $cookie;

    return true;
  }
}
