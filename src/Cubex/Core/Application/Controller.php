<?php
/**
 * @author  brooke.bryan
 */
namespace Cubex\Core\Application;

use Cubex\Core\Http\Dispatchable;

interface Controller extends Dispatchable
{
  /**
   * @param \Cubex\Core\Application\Application f$app
   *
   * @return mixed
   */
  public function setApplication(Application $app);

  /**
   * @return \Cubex\Core\Application\Application
   */
  public function application();
}
