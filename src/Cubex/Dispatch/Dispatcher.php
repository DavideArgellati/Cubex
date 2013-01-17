<?php
/**
 * @author: gareth.evans
 */
 namespace Cubex\Dispatch;

use Cubex\Foundation\Config\ConfigGroup;
use Cubex\Foundation\Config\ConfigTrait;

class Dispatcher
{
  use ConfigTrait;

  private $_domainMap;
  private $_entityMap;
  private $_dispatchIniFilename;
  private $_resourceDirectory;
  private $_projectNamespace;
  private $_projectBase;
  private $_baseHash = "esabot";
  private $_nomapHash = "pamon";
  private $_supportedTypes = [
    'ico' => 'image/x-icon',
    'css' => 'text/css; charset=utf-8',
    'js'  => 'text/javascript; charset=utf-8',
    'png' => 'image/png',
    'jpg' => 'image/jpg',
    'gif' => 'image/gif',
    'swf' => 'application/x-shockwave-flash',
  ];

  /**
   * @param \Cubex\Foundation\Config\ConfigGroup $configGroup
   */
  public function __construct(ConfigGroup $configGroup)
  {
    if(!defined("DS")) define("DS", DIRECTORY_SEPARATOR);

    $this->configure($configGroup);

    $dispatchConfig = $this->config("dispatch");
    $projectConfig  = $this->config("project");
    $cubexConfig    = $this->config("_cubex_");

    $this->_domainMap = $dispatchConfig->getArr("domain_map", []);
    $this->_entityMap = $dispatchConfig->getArr("entity_map", []);

    $this->_dispatchIniFilename = $dispatchConfig->getStr(
      "dispatch_ini_filename", "dispatch.ini"
    );
    $this->_resourceDirectory   = $dispatchConfig->getStr(
      "resource_directory", "res"
    );
    $this->_projectNamespace    = $projectConfig->getStr(
      "namespace", "Project"
    );
    $this->_projectBase         = FileSystem::resolvePath(
      $cubexConfig->getStr("project_base", "..")
    );
  }

  /**
   * @return array
   */
  public function getDomainMap()
  {
    return $this->_domainMap;
  }

  /**
   * @return array
   */
  public function getEntityMap()
  {
    return $this->_entityMap;
  }

  /**
   * @return string
   */
  public function getDispatchIniFilename()
  {
    return $this->_dispatchIniFilename;
  }

  /**
   * @return string
   */
  public function getResourceDirectory()
  {
    return $this->_resourceDirectory;
  }

  /**
   * @return string
   */
  public function getProjectNamespace()
  {
    return $this->_projectNamespace;
  }

  /**
   * @return string
   */
  public function getProjectBase()
  {
    return $this->_projectBase;
  }

  /**
   * @return string
   */
  public function getProjectPath()
  {
    return $this->getProjectBase() . DS . $this->getProjectNamespace();
  }

  /**
   * @return string
   */
  public function getBaseHash()
  {
    return $this->_baseHash;
  }

  /**
   * @return string
   */
  public function getNomapHash()
  {
    return $this->_nomapHash;
  }

  /**
   * @return array
   */
  public function getSupportedTypes()
  {
    return $this->_supportedTypes;
  }

  /**
   * @param $entityHash
   *
   * @return string
   */
  public function getEntityPathByHash($entityHash)
  {
    if($entityHash === $this->getBaseHash())
    {
      return $this->getProjectNamespace(). "/" . $this->getResourceDirectory();
    }
    else if(array_key_exists($entityHash, $this->getEntityMap()))
    {
      return $this->getEntityMap()[$entityHash];
    }
    else
    {
      $path = $this->getEntityPathByHash($entityHash);

      if($path === null)
      {
        return rawurldecode($entityHash);
      }

      return $path;
    }
  }

  /**
   * This will expand a filename and return an array of filenames that may get
   * included. This is for rendering resources before and after the main file
   *
   * @param $filename
   *
   * @return array
   */
  public function getRelatedFilenamesOrdered($filename)
  {
    $fileParts = explode(".", $filename);
    $fileExtension = array_pop($fileParts);
    $filename = implode(".", $fileParts);

    return array(
      "pre"  => "{$filename}.pre.{$fileExtension}",
      "main" => "{$filename}.{$fileExtension}",
      "post" => "{$filename}.post.{$fileExtension}"
    );
  }

  /*****************************************************************************
   * The methods below do a little more than the mass of getters above
   */

  /**
   * @param string $entity
   * @param int    $length
   *
   * @return string
   */
  public function generateEntityHash($entity, $length = 6)
  {
    return substr(md5($entity), 0, $length);
  }

  /**
   * @param string $domain
   * @param int    $length
   *
   * @return string
   */
  public function generateDomainHash($domain, $length = 6)
  {
    return substr(md5($domain), 0, $length);
  }

  /**
   * @param object $source
   *
   * @return string
   */
  public static function getNamespaceFromSource($source)
  {
    $sourceObjectRefelction = new \ReflectionClass($source);

    return $sourceObjectRefelction->getNamespaceName();
  }

  /**
   * Will read the filename, and all pre/post/related files from the direcotry
   * returning as a concatonated string
   *
   * @param string $directory
   * @param string $filename
   *
   * @return string
   */
  public function getFileMerge($directory, $filename)
  {
    $contents = "";

    foreach($this->getRelatedFilenamesOrdered($filename) as $relatedFilename)
    {
      $relatedFilePath = $directory . DS . $relatedFilename;
      if(file_exists($relatedFilePath))
      {
        try
        {
          $content = FileSystem::readFile($relatedFilePath);
        }
        catch(\Exception $e)
        {
          // We don't bubble this at the moment, might log it if the logger is
          // available
          $content = "";
        }

        $contents .= $content;
      }
    }

    return $contents;
  }

  /**
   * We don't really want to do this, but it's for times that an entityHash has
   * come through that isn't in our map and we want to try and find it on the
   * fly. Once this request is over it should get cached seeing as it thinks
   * it's in a map so no big deal.
   *
   * When you call it we only really want the hash, the path and depth are for
   * the method to call it's self recursively.
   *
   * @param string $hash
   *
   * @return null|string
   */
  public function findEntityFromHash($hash)
  {
    $entities = (new Mapper($this->getConfig()))->findEntities();

    foreach($entities as $entity)
    {
      if($this->generateEntityHash($entity, strlen($hash)) === $hash)
      {
        return $entity;
      }
    }

    return null;
  }
}
