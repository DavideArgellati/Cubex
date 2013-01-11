<?php
/**
 * @author  brooke.bryan
 */
namespace Cubex\Core\Controllers;

use Cubex\Core\Application\Application;
use Cubex\Core\Application\Controller;
use Cubex\Foundation\Config\ConfigGroup;
use Cubex\Foundation\Config\ConfigTrait;
use Cubex\Foundation\DataHandler\HandlerInterface;
use Cubex\Foundation\DataHandler\HandlerTrait;
use Cubex\Core\Http\Request;
use Cubex\Core\Http\Response;

use Cubex\Routing\Route;
use Cubex\Routing\StdRoute;
use Cubex\Routing\StdRouter;

class BaseController implements Controller, HandlerInterface
{
  use HandlerTrait;
  use ConfigTrait;

  /**
   * @var \Cubex\Core\Http\Request
   */
  protected $_request;
  /**
   * @var \Cubex\Core\Http\Response
   */
  protected $_response;

  /**
   * @var \Cubex\Core\Application\Application
   */
  protected $_application;
  /**
   * @var \Cubex\Foundation\Config\ConfigGroup
   */
  protected $_configuration;


  /**
   * @param \Cubex\Core\Application\Application f$app
   *
   * @return mixed
   */
  public function setApplication(Application $app)
  {
    $this->_application = $app;
  }

  /**
   * @return \Cubex\Core\Application\Application
   */
  public function application()
  {
    return $this->_application;
  }

  /**
   * @return \Cubex\Core\Http\Request
   */
  public function request()
  {
    return $this->_request;
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
    $this->_response = $response;
    $this->_request  = $request;

    try
    {
      $canProcess = $this->canProcess();
      if(!$canProcess)
      {
        throw new \Exception("Unable to process request");
      }

      $this->preProcess();
      $actionResponse = $this->processRequest();
      $this->postProcess();
    }
    catch(\Exception $e)
    {
      $actionResponse = $this->failedProcess($e);
    }

    $this->_response = $this->_getResponseFromActionResponse($actionResponse);

    return $this->_response;
  }

  /**
   * @param $actionResponse
   *
   * @return \Cubex\Core\Http\Response
   */
  protected function _getResponseFromActionResponse($actionResponse)
  {
    if($actionResponse instanceof Response)
    {
      $this->_response = $actionResponse;
    }
    else
    {
      $this->_response->from($actionResponse);
    }
    return $this->_response;
  }

  /**
   * Any pre filtering
   */
  public function preProcess()
  {
  }

  /**
   * Any post processing
   */
  public function postProcess()
  {
  }

  /**
   * Should the continue process, or run failedProcess()
   *
   * @return bool
   * @throws \Exception
   */
  public function canProcess()
  {
    return true;
  }

  /**
   * Main method for handling the request
   *
   * @return Response
   */
  public function processRequest()
  {
    return $this->routeRequest();
  }

  /**
   * response when canProcess() returns false
   *
   * @param \Exception $e
   *
   * @return mixed
   *
   * @throws \Exception
   */
  public function failedProcess(\Exception $e)
  {
    throw $e;
  }

  /**
   * @return mixed|string
   */
  public function routeRequest()
  {
    $route = null;

    if($this->request()->isAjax())
    {
      $route = $this->_attemptRoutes($this->_getRoutes($this->getAjaxRoutes()));
    }

    if($route === null && $this->request()->is('POST'))
    {
      $route = $this->_attemptRoutes($this->_getRoutes($this->getPostRoutes()));
    }

    if($route === null)
    {
      $route = $this->_attemptRoutes($this->_getRoutes($this->getRoutes()));
    }

    if($route === null)
    {
      $action = $this->defaultAction();
      $params = [];
    }
    else
    {
      $action = $route->result();
      $params = $route->routeData();
    }
    $this->hydrate($params);

    ob_start(); //Stop any naughty output making a mess of our response
    $result   = $this->runAction($action, $params);
    $buffered = ob_get_clean();

    if($result === null)
    {
      $result = $buffered;
    }

    return $result;
  }

  /**
   * @param $action
   * @param $params
   *
   * @return mixed
   * @throws \BadMethodCallException
   */
  public function runAction($action, $params)
  {
    if($action === null)
    {
      throw new \BadMethodCallException(
        "No action specified on " . $this->_name()
      );
    }

    if($this->request()->isAjax())
    {
      $attempt = 'ajax' . \ucfirst($action);
      if(\method_exists($this, $attempt))
      {
        return $this->$attempt();
      }
    }

    if($this->request()->is('POST'))
    {
      $attempt = 'post' . \ucfirst($action);
      if(\method_exists($this, $attempt))
      {
        return $this->$attempt();
      }
    }

    $attempt = 'render' . \ucfirst($action);
    if(\method_exists($this, $attempt))
    {
      return call_user_func_array([$this, $attempt], $params);
    }

    if(is_callable($action))
    {
      return $action();
    }

    throw new \BadMethodCallException(
      "Invalid action $action specified on " . $this->_name()
    );
  }

  /**
   * @return string
   */
  protected function _name()
  {
    $reflector = new \ReflectionClass(\get_class($this));

    return $reflector->getShortName();
  }

  /**
   * @param array $routes
   *
   * @return \Cubex\Routing\Route|null
   */
  protected function _attemptRoutes(array $routes)
  {
    $router = new StdRouter($routes, $this->request()->requestMethod());
    return $router->getRoute($this->request()->path());
  }

  /**
   * @param \Cubex\Foundation\Config\ConfigGroup $configuration
   *
   * @return \Cubex\Core\Http\Dispatchable
   */
  public function configure(ConfigGroup $configuration)
  {
    $this->_configuration = $configuration;
  }


  /**
   * @param array $routes
   *
   * @return Route[]
   */
  protected function _getRoutes(array $routes)
  {
    $finalRoutes = array();
    if(!empty($routes))
    {
      foreach($routes as $routePattern => $routeResult)
      {
        if($routeResult instanceof Route)
        {
          $finalRoutes[] = $routeResult;
        }
        else if(is_array($routeResult))
        {
          $subRoutes = StdRoute::fromArray($routeResult);
          $route     = new StdRoute($routePattern, '');
          foreach($subRoutes as $sr)
          {
            $route->addSubRoute($sr);
          }
          $finalRoutes[] = $route;
        }
        else
        {
          $finalRoutes[] = new StdRoute($routePattern, $routeResult);
        }
      }
    }
    return $finalRoutes;
  }

  /**
   * @return array|Route[]
   */
  public function getRoutes()
  {
    return [];
  }

  /**
   * @return array|Route[]
   */
  public function getPostRoutes()
  {
    return [];
  }

  /**
   * @return array|Route[]
   */
  public function getAjaxRoutes()
  {
    return [];
  }

  /**
   * @return null
   */
  public function defaultAction()
  {
    return null;
  }
}
