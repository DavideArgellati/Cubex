<?php
/**
 * @author  gareth.evans
 */
namespace Cubex\Facade;

class PlatformDetection extends BaseFacade
{
  /**
   * @return \Cubex\Platform\Detection\DetectionService
   */
  public static function getAccessor()
  {
    $serviceManager = static::getServiceManager();

    return $serviceManager->get("platform.detection");
  }

  /**
   * @return bool
   */
  public static function isMobile()
  {
    $accessor = static::getAccessor();

    return $accessor->isMobile();
  }

  /**
   * @return bool
   */
  public static function isTablet()
  {
    $accessor = static::getAccessor();

    return $accessor->isTablet();
  }

  /**
   * @return bool
   */
  public static function isDesktop()
  {
    $accessor = static::getAccessor();

    return !$accessor->isMobile();
  }

  /**
   * @return bool
   */
  public static function canSetUserAgent()
  {
    $accessor = static::getAccessor();

    return $accessor->canSetUserAgent();
  }

  /**
   * @param array $userAgent
   *
   * @return mixed
   */
  public static function setUserAgent(array $userAgent)
  {
    $accessor = static::getAccessor();

    return $accessor->setUserAgent($userAgent);
  }
}
