<?php
/**
 * @author  brooke.bryan
 */
namespace Cubex\ServiceManager;

interface ServiceManagerAware
{
  /**
   * Set the service manager
   *
   * @param ServiceManager $serviceManager
   *
   * @return mixed
   */
  public function setServiceManager(ServiceManager $serviceManager);

  /**
   * @return ServiceManager
   */
  public function getServiceManager();
}
