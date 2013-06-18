<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Auth;

use Cubex\Foundation\Container;
use Cubex\Cookie\Cookies;
use Cubex\Cookie\EncryptedCookie;

abstract class BaseAuthService implements IAuthService
{
  /**
   * @var int|string|\DateTime
   */
  protected $_loginExpiry = 0;

  /**
   * @param IAuthedUser $user
   *
   * @return bool
   */
  public function storeLogin(IAuthedUser $user)
  {
    $security = $this->cookieHash($user);
    Container::bind(Container::AUTHED_USER, $user);

    $cookieData = implode(
      "|",
      [
      $user->getId(),
      $user->getUsername(),
      $security,
      json_encode($user->getDetails())
      ]
    );

    $cookie     = new EncryptedCookie(
      "CUBEXLOGIN",
      $cookieData,
      $this->_loginExpiry,
      "/",
      url(".%d.%t"),
      false,
      true
    );
    Cookies::set($cookie);

    return true;
  }

  /**
   * @return null|IAuthedUser
   */
  public function retrieveLogin()
  {
    try
    {
      $cookie = Cookies::get("CUBEXLOGIN");
      $data   = $cookie->getValue(true);
      list($id, $username, $security, $details) = explode('|', $data, 4);
      $details = json_decode($details);
      $user    = $this->buildUser($id, $username, $details);
      if($security == $this->cookieHash($user))
      {
        return $user;
      }
    }
    catch(\Exception $e)
    {
    }
    return null;
  }

  public function logout()
  {
    Cookies::delete("CUBEXLOGIN", "/", url(".%d.%t"));
  }
}
