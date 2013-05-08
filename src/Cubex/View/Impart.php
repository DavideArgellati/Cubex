<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\View;

/**
 * Basic render object
 */
use Cubex\Foundation\IRenderable;

class Impart implements IRenderable
{
  protected $_content = '';

  /**
   * @param $content
   */
  public function __construct($content = '')
  {
    $this->setContent($content);
  }

  /**
   * @return string
   */
  public function __tostring()
  {
    return $this->render();
  }

  /**
   * @param $content
   *
   * @return $this
   */
  public function setContent($content)
  {
    $this->_content = $content;
    return $this;
  }

  /**
   * @return string
   */
  public function render()
  {
    return (string)$this->_content;
  }
}
