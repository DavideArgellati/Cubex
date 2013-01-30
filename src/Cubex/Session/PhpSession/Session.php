<?php
/**
 * @author  brooke.bryan
 */
namespace Cubex\Session\PhpSession;

use Cubex\ServiceManager\ServiceConfig;
use Cubex\Session\SessionService;

class Session implements SessionService
{
  /**
   * @param \Cubex\ServiceManager\ServiceConfig $config
   *
   * @return mixed|void
   */
  public function configure(ServiceConfig $config)
  {
  }

  public function init()
  {
    session_start();
    if(!isset($_SESSION['cubex'])) $_SESSION['cubex'] = array();
  }

  public function id()
  {
    return session_id();
  }

  /**
   * @param $key
   *
   * @return mixed
   */
  public function get($key)
  {
    return $this->exists($key) ? $_SESSION['cubex'][$key] : null;
  }

  /**
   * @param $key
   * @param $data
   *
   * @return mixed|void
   */
  public function set($key, $data)
  {
    $_SESSION['cubex'][$key] = $data;
    return true;
  }

  public function delete($key)
  {
    unset($_SESSION['cubex'][$key]);
    return true;
  }

  public function exists($key)
  {
    return isset($_SESSION['cubex'][$key]);
  }

  /**
   * @return bool
   */
  public function destroy()
  {
    unset($_SESSION['cubex']);
  }
}
