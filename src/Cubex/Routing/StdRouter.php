<?php
/**
 * @author  brooke.bryan
 */
namespace Cubex\Routing;

class StdRouter implements Router
{
  protected $_routes;
  protected $_verbMatch;

  /**
   * Initiate Router
   *
   * @param Route[] $routes
   * @param         $httpVerb
   */
  public function __construct(array $routes, $httpVerb = null)
  {
    $this->_routes    = $routes;
    $this->_verbMatch = $httpVerb;
  }

  /**
   * Add an array of routes
   *
   * @param Route[] $route
   */
  public function addRoutes(array $route)
  {
    $this->_routes = $this->_routes + $route;
  }

  /**
   * @param $pattern
   *
   * @return StdRoute|null
   */
  public function getRoute($pattern)
  {
    foreach($this->_routes as $route)
    {
      $result = $this->_tryRoute($route, $pattern);
      if($result instanceof StdRoute)
      {
        return $result;
      }
    }
    return null;
  }

  /**
   * @param StdRoute $route
   * @param          $pattern
   *
   * @return bool|StdRoute
   */
  protected function _tryRoute(StdRoute $route, $pattern)
  {
    if($this->_verbMatch !== null)
    {
      if(!$route->matchesVerb($this->_verbMatch))
      {
        return false;
      }
    }
    $routePattern = $route->pattern();
    if(\substr($pattern, -1) != '/') $pattern = $pattern . '/';
    if(\substr($routePattern, -1) != '/') $routePattern = $routePattern . '/';

    $routePattern = $this->convertSimpleRoute($routePattern);

    $matches = array();
    $match   = \preg_match("#^$routePattern#", $pattern, $matches);

    if($match)
    {
      foreach($matches as $k => $v)
      {
        //Strip out all non declared matches
        if(!\is_numeric($k))
        {
          $route->addRouteData($k, $v);
        }
      }
      return $route;
    }
    else if($route->hasSubRoutes())
    {
      $subRoutes = $route->subRoutes();
      foreach($subRoutes as $subRoute)
      {
        if($subRoute instanceof StdRoute)
        {
          $result = $this->_tryRoute($subRoute, $pattern);
          if($result instanceof StdRoute)
          {
            return $subRoute;
          }
        }
      }
    }

    return false;
  }

  /**
   * @param $route
   *
   * @return mixed
   */
  public function convertSimpleRoute($route)
  {
    /* Allow Simple Routes */
    if(stristr($route, ':'))
    {
      $route = \preg_replace(
        "/\:(_?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\@alpha/",
        "(?P<$1>\w+)/",
        $route
      );
      $route = \preg_replace(
        "/\:(_?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\@all/",
        "(?P<$1>.*)/",
        $route
      );
      $route = \preg_replace(
        "/\:(_?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\@num/",
        "(?P<$1>[1-9]\d*)/",
        $route
      );
      $route = \preg_replace(
        "/\:(_?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/",
        "(?P<$1>[^\/]+)/",
        $route
      );

      $route = str_replace('//', '/', $route);
    }
    return $route;
  }
}