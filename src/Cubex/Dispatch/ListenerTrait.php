<?php
/**
 * @author: gareth.evans
 */
namespace Cubex\Dispatch;

use Cubex\Events\EventManager;

trait ListenerTrait
{
  /**
   * @return string
   */
  abstract public function getNamespace();

  protected function _listen()
  {
    EventManager::listen(
      EventManager::DISPATCH_RESOURCE_REQUIRE,
      function(Event $event)
      {
        $event->setNamespace($this->getNamespace());

        $prop = new Prop($this->getConfig());
        $prop->requireResource($event);
      },
      $this->getNamespace()
    );

    EventManager::listen(
      EventManager::DISPATCH_PACKAGE_REQUIRE,
      function(Event $event)
      {
        $event->setNamespace($this->getNamespace());

        $prop = new Prop($this->getConfig());
        $prop->requirePackage($event);
      },
      $this->getNamespace()
    );

    EventManager::listen(
      EventManager::DISPATCH_IMG_URL,
      function(Event $event)
      {
        $event->setNamespace($this->getNamespace());

        $dispatchImage = new Image($this->getConfig());

        return $dispatchImage->getUri($event);
      },
      $this->getNamespace()
    );
  }
}
