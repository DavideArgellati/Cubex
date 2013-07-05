<?php
/**
 * @author gareth.evans
 */

namespace Cubex\Detection;

use Cubex\ServiceManager\IService;

interface IDetection extends IService
{
  /**
   * @return bool
   */
  public function canSetUserAgent();

  /**
   * @param string $userAgent
   *
   * @return mixed
   */
  public function setUserAgent($userAgent);
}
