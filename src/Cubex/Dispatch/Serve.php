<?php
/**
 * @author: gareth.evans
 */
namespace Cubex\Dispatch;

use Cubex\Core\Http\Dispatchable;
use Cubex\Core\Http\Request;
use Cubex\Core\Http\Response;
use Cubex\View\Templates\Errors\Error404;

class Serve extends Dispatcher implements Dispatchable
{
  // 60 * 60 * 24 * 30
  protected $_cacheTime = 2592000;
  protected $_useMap = true;

  protected $_dispatchPath;
  protected $_domainMap = [];

  protected $_domainHash;
  protected $_entityHash;
  protected $_typeDescriptor;
  protected $_debugString;
  protected $_relativePath;

  protected static $_nocacheDebugString = "nocache";

  public function __construct($dispatchPath,
                              array $entityMap = array(),
                              array $domainMap = array())
  {
    $this->_entityMap = $entityMap;
    $this->_domainMap = $domainMap;
    $this->_dispatchPath = $dispatchPath;

    $dispatchPathParts = explode("/", $this->_dispatchPath, 4);

    if(count($dispatchPathParts) !== 4)
    {
      throw new \UnexpectedValueException(
        "The dispatch path should include at least four directory seperator ".
          "seperated sections"
      );
    }

    $this->_domainHash = $dispatchPathParts[0];
    $this->_entityHash = $dispatchPathParts[1];

    $this->_typeDescriptor = $dispatchPathParts[2];
    if(strstr($dispatchPathParts[2], ";") !== false)
    {
      list($this->_typeDescriptor, $this->_debugString) = explode(
        ";", $dispatchPathParts[2], 2
      );
    }

    $this->_relativePath = $dispatchPathParts[3];

    $this->setUseMap($this->_typeDescriptor !== self::getNomapDescriptor());
  }

  /**
   * @param $useMap
   *
   * @return Serve
   */
  public function setUseMap($useMap)
  {
    $this->_useMap = $useMap;

    return $this;
  }

  /**
   * @return bool
   */
  public function getUseMap()
  {
    return $this->_useMap;
  }

  /**
   * @param $nocacheDebugString
   */
  public static function setNocacheDebugString($nocacheDebugString)
  {
    self::$_nocacheDebugString = $nocacheDebugString;
  }

  /**
   * @return string
   */
  public static function getNocacheDebugString()
  {
    return self::$_nocacheDebugString;
  }

  public function dispatch(Request $request, Response $response)
  {
    $response->addHeader("Vary", "Accept-Encoding");

    return $this->getResponse($response, $request);
  }

  public function getResponse(Response $response, Request $request)
  {
    if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
      && $this->_debugString !== self::getNocacheDebugString()
      && $this->_typeDescriptor != self::getNomapDescriptor())
    {
      return $this->_setCacheHeaders($response);
    }

    $domain = $this->getDomainByHash($this->_domainHash);

    if($domain === false)
    {
      $domain = $request->domain() . "." . $request->tld();
    }

    $entityPath = $this->getEntityPathByHash($this->_entityHash);
    $explode = explode(".", $this->_relativePath);
    $resourceType = end($explode);

    $type = $this->_typeDescriptor !== "pkg" ?
      "static" : $this->_typeDescriptor;

    // Stop possible hacks for disk paths, e.g. /js/../../../etc/passwd
    if(preg_match("@(//|\.\.)@", $this->_relativePath))
    {
      return $response->fromRenderable(new Error404())->setStatusCode(404);
    }

    // Either hack attempt or a dev needs a slapped wrist
    if(!array_key_exists($resourceType, $this->supportedTypes()))
    {
      return $response->fromRenderable(new Error404())->setStatusCode(404);
    }

    $fabricate = new Fabricate($this->getConfig());
    if($type === "pkg")
    {
      $data = $fabricate->getPackageData(
        $entityPath,
        $this->_relativePath,
        $domain,
        $this->getUseMap()
      );
    }
    else
    {
      $data = $fabricate->getData(
        $entityPath,
        $this->_relativePath,
        $domain
      );
    }

    // No data found, assume 404
    if(empty($data))
    {
      return $response->fromRenderable(new Error404())->setStatusCode(404);
    }

    $response->from($data)
      ->addHeader("Content-Type", $this->supportedTypes()[$resourceType])
      ->addHeader("X-Powered-By", "Cubex:Dispatch")
      ->setStatusCode(200);

    if($this->_debugString === $this->getNocacheDebugString())
    {
      $response->disbleCache();
    }
    else if($this->getUseMap() === false)
    {
      $response->disbleCache();
    }
    else
    {
      $response->cacheFor($this->_cacheTime)
        ->lastModified(time());
    }

    return $response;
  }

  protected function _setCacheHeaders(Response $response)
  {
    return $response->addHeader("X-Powere-By", "Cubex:Dispatch")
      ->setStatusCode(304)
      ->cacheFor($this->_cacheTime)
      ->lastModified(time());
  }

  public function getDomainByHash($domainHash)
  {
    if(array_key_exists($domainHash, $this->_domainMap))
    {
      return $this->_domainMap[$domainHash];
    }

    return false;
  }

  /**
   * Supported file types that can be processed using dispatch
   *
   * @return array
   */
  public function supportedTypes()
  {
    return array(
      'ico' => 'image/x-icon',
      'css' => 'text/css; charset=utf-8',
      'js'  => 'text/javascript; charset=utf-8',
      'png' => 'image/png',
      'jpg' => 'image/jpg',
      'gif' => 'image/gif',
      'swf' => 'application/x-shockwave-flash',
    );
  }
}
