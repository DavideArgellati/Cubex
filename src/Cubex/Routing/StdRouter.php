<?php
/**
 * @author  brooke.bryan
 */
namespace Cubex\Routing;

class StdRouter implements Router
{
  protected $_routes;
  protected $_verbMatch;
  protected $_matchedRoute;

  /**
   * Initiate Router
   *
   * @param StdRoute[] $routes
   * @param            $httpVerb
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
   * @return StdRoute
   */
  public function getMatchedRoute()
  {
    return $this->_matchedRoute;
  }

  /**
   * @param $pattern
   *
   * @return StdRoute|null
   */
  public function getRoute($pattern)
  {
    $routeMatches = [];
    foreach($this->_routes as $route)
    {
      $result = $this->_tryRoute($route, $pattern);
      if($result instanceof StdRoute)
      {
        $routeMatches[] = $result;
      }
    }

    if(empty($routeMatches))
    {
      return null;
    }
    else
    {
      usort(
        $routeMatches,
        [
        $this,
        '_sortRoutes'
        ]
      );
      $this->_matchedRoute = array_shift($routeMatches);
    }

    return $this->_matchedRoute;
  }

  protected function _sortRoutes(StdRoute $a, StdRoute $b)
  {
    $aparts = (int)substr_count($a->pattern(), "/");
    $bparts = (int)substr_count($b->pattern(), "/");

    if($aparts == $bparts)
    {
      $aparts = $a->matchedOn();
      $bparts = $b->matchedOn();

      if($aparts == $bparts)
      {
        if($a->matchedOn() < 2)
        {
          $aparts = strlen($a->pattern());
          $bparts = strlen($b->pattern());
          if($aparts == $bparts)
          {
            return 0;
          }
        }
      }
      else
      {
        return ($aparts < $bparts) ? -1 : 1;
      }
    }
    return ($aparts < $bparts) ? 1 : -1;
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
    $appendEnd    = substr($routePattern, -1) == '$';
    if($routePattern == '$')
    {
      $routePattern = '/$';
    }

    if(!$appendEnd && substr($pattern, -1) !== '/')
    {
      $pattern = $pattern . '/';
    }
    if(!$appendEnd && substr($routePattern, -1) !== '/')
    {
      $routePattern = $routePattern . '/?';
    }
    if(substr($routePattern, 0, 1) !== '/')
    {
      $routePattern = '/' . $routePattern;
    }

    $matchedOn = 1;
    $matches   = array();
    $match     = preg_match("#^$routePattern#", $pattern, $matches);

    if(!$match)
    {
      $routePattern = $this->convertSimpleRoute($routePattern);
      $match        = preg_match("#^$routePattern#", $pattern, $matches);
      $matchedOn    = 2;
    }

    if($match)
    {
      $route->setMatchedOn($matchedOn);
      if($route->hasSubRoutes())
      {
        $subRoutes = $route->subRoutes();
        foreach($subRoutes as $subRoute)
        {
          if($subRoute instanceof StdRoute)
          {
            $subPattern = $route->pattern() . '/' . $subRoute->pattern();
            $subPattern = str_replace('//', '/', $subPattern);
            $subRoute->setPattern($subPattern);
            $result = $this->_tryRoute($subRoute, $pattern);
            if($result instanceof StdRoute)
            {
              return $subRoute;
            }
          }
        }
      }
      else
      {
        foreach($matches as $k => $v)
        {
          //Strip out all non declared matches
          if(!\is_numeric($k))
          {
            $route->addRouteData($k, $v);
          }
        }
      }
      return $route;
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
