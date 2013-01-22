<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Auth;

class StdAuthedUser implements AuthedUser
{
  protected $_id;
  protected $_username;
  protected $_details;

  public function __construct($id = null, $username = null, $details = null)
  {
    $this->_id       = $id;
    $this->_username = $username;
    $this->_details  = $details;
  }

  /**
   * @return mixed
   */
  public function id()
  {
    return $this->_id;
  }

  /**
   * @return string
   */
  public function username()
  {
    return $this->_username;
  }

  /**
   * @return mixed
   */
  public function details()
  {
    return $this->_details;
  }
}
