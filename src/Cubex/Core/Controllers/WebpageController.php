<?php
/**
 * @author  brooke.bryan
 */
namespace Cubex\Core\Controllers;

use Cubex\Core\Http\Request;
use Cubex\Core\Http\Response;
use Cubex\Core\Response\Webpage;
use Cubex\Core\Traits\OnDomainTrait;
use Cubex\Dispatch\Utils\RequireTrait;
use Cubex\Events\EventManager;
use Cubex\Foundation\IRenderable;
use Cubex\View\Impart;
use Cubex\View\Layout;
use Cubex\View\TemplatedView;
use Cubex\View\Templates\Exceptions\ExceptionView;

class WebpageController extends BaseController
{
  use RequireTrait;
  use OnDomainTrait;

  /**
   * @var \Cubex\Core\Response\Webpage
   */
  protected $_webpage;
  protected $_actionNest = 'content';
  /**
   * @var string
   */
  protected $_layout;

  protected $_renderingEsiAction;

  protected $_surrogateCapability = false;

  protected function _getActionNestName()
  {
    return $this->_actionNest;
  }

  /**
   * @return \Cubex\Core\Response\Webpage
   */
  public function webpage()
  {
    return $this->_webpage;
  }

  public function setTitle($title)
  {
    $this->_webpage->setTitle($title);
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

  public function layout()
  {
    return $this->_webpage->layout();
  }

  /**
   * @return string
   */
  public function layoutName()
  {
    if($this->_layout === null)
    {
      $this->setLayoutName($this->_application->layout());
    }
    return $this->_layout;
  }

  /**
   * @param $layout
   *
   * @return static
   */
  public function setLayoutName($layout)
  {
    $this->_layout = $layout;
  }

  public function nest($name, IRenderable $content)
  {
    $this->_webpage->layout()->nest($name, $content);
    return $this;
  }

  public function unNest($name)
  {
    $this->_webpage->layout()->unNest($name);
    return $this;
  }

  public function tryNest($name, $content)
  {
    if($content instanceof IRenderable)
    {
      return $this->nest($name, $content);
    }
    return false;
  }

  public function renderBefore($name, IRenderable $item)
  {
    $this->_webpage->layout()->renderBefore($name, $item);
    return $this;
  }

  public function renderAfter($name, IRenderable $item)
  {
    $this->_webpage->layout()->renderAfter($name, $item);
    return $this;
  }

  public function isNested($name)
  {
    return $this->_webpage->layout()->isNested($name);
  }

  /**
   * @param \Cubex\Core\Http\Request  $request
   * @param \Cubex\Core\Http\Response $response
   *
   * @return \Cubex\Core\Http\Response
   * @throws \Exception
   */
  public function dispatch(Request $request, Response $response)
  {
    $this->_webpage = new Webpage($request, $response);

    if($this->config("response")->getBool("minify_html", true))
    {
      EventManager::listen(
        EventManager::CUBEX_WEBPAGE_RENDER_BODY,
        [
        $this->_webpage,
        "minifyHtml"
        ]
      );
    }

    $theme = $this->_application->getTheme();
    $theme->initiate();

    $layout = new Layout($theme, $this->_application);
    $layout->setTemplate($this->layoutName());
    $this->_webpage->setLayout($layout);
    $this->_webpage->renderableNest($this->_getActionNestName());

    return parent::dispatch($request, $response);
  }

  protected function _getResponseFromActionResponse($actionResponse)
  {
    if($actionResponse instanceof Response)
    {
      return $actionResponse;
    }
    else if($actionResponse instanceof IRenderable)
    {
      $this->_webpage->addRenderable($actionResponse);
    }
    else if(is_scalar($actionResponse))
    {
      $this->_webpage->addRenderable(new Impart($actionResponse));
    }
    else
    {
      return $this->_response->from($actionResponse);
    }

    return $this->_response->fromRenderable($this->_webpage);
  }

  public function esiRender($action, $path, $params = [])
  {
    $surrogateCapability = strpos(
      $this->request()->header("surrogate_capability", ''),
      'ESI/1.0'
    ) !== false;

    if($surrogateCapability)
    {
      return new Impart('<esi:include src="' . $path . '" />');
    }
    else
    {
      $this->_renderingEsiAction = true;
      $resp                      = $this->_processAction($action, $params);
      if(!($resp instanceof IRenderable))
      {
        if(is_scalar($resp))
        {
          $resp = new Impart($resp);
        }
        else
        {
          throw new \Exception("Invalid ESI response on $action");
        }
      }
      $this->_renderingEsiAction = false;
      return $resp;
    }
  }

  public function isEsiSubAction()
  {
    return (bool)$this->_renderingEsiAction;
  }

  /**
   * @param $action
   * @param $params
   *
   * @return mixed
   * @throws \BadMethodCallException
   * @throws \Exception
   */
  public function runAction($action, $params)
  {
    try
    {
      return parent::runAction($action, $params);
    }
    catch(\BadMethodCallException $e)
    {
      $path   = substr($this->request()->path(), strlen($this->baseUri()) + 1);
      $return = (new TemplatedView($path, $this->application()))->render();
      if($return === '')
      {
        throw $e;
      }
      return $return;
    }
    catch(\Exception $e)
    {
      throw $e;
    }
  }
}
