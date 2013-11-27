<?php
/**
 * @author  brooke.bryan
 */
namespace Cubex\View;

use Cubex\Core\Traits\OnDomainTrait;
use Cubex\Foundation\Container;
use Cubex\Core\Application\IController;
use Cubex\Dispatch\Utils\RequireTrait;
use Cubex\Events\EventManager;
use Cubex\Data\Handler\HandlerTrait;
use Cubex\Foundation\IRenderable;
use Cubex\I18n\ITranslatable;
use Cubex\I18n\TranslateTraits;

abstract class ViewModel implements IRenderable, ITranslatable
{
  use TranslateTraits;
  use RequireTrait;
  use HandlerTrait;
  use OnDomainTrait;

  protected $_hostController;

  abstract public function render();

  public function __toString()
  {
    try
    {
      return (string)$this->render();
    }
    catch(\Exception $e)
    {
      return $e->getMessage();
    }
  }

  public function request()
  {
    return Container::request();
  }

  /**
   * Attempt to set page title
   *
   * @param string $title
   *
   * @return static
   */
  public function setTitle($title = '')
  {
    EventManager::trigger(
      EventManager::CUBEX_PAGE_TITLE,
      ['title' => $title],
      $this
    );
    return $this;
  }

  public function addMeta($name, $content)
  {
    EventManager::trigger(
      EventManager::CUBEX_PAGE_META,
      ['name' => $name, 'content' => $content],
      $this
    );
    return $this;
  }

  public function addDynamicMeta($data)
  {
    EventManager::trigger(EventManager::CUBEX_PAGE_META, $data, $this);
    return $this;
  }

  /**
   * Set the controller hosting this view
   *
   * @param IController $controller
   *
   * @return $this
   */
  public function setHostController(IController $controller)
  {
    $this->_hostController = $controller;
    return $this;
  }

  /**
   * Get the controller hosting this view
   *
   * @return IController
   * @throws \Exception
   */
  public function getHostController()
  {
    if($this->_hostController === null)
    {
      throw new \Exception("No host controller has been specified");
    }
    if($this->_hostController instanceof IController)
    {
      return $this->_hostController;
    }
    else
    {
      throw new \Exception("Incorrectly configured host controller");
    }
  }

  public function baseUri()
  {
    return $this->getHostController()->baseUri();
  }

  public function appBaseUri()
  {
    return $this->getHostController()->application()->baseUri();
  }
}
