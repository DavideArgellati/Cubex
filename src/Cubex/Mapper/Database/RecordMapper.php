<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Mapper\Database;

use Cubex\Container\Container;
use Cubex\Data\Attribute;
use Cubex\Database\ConnectionMode;
use Cubex\Log\Debug;
use Cubex\Mapper\DataMapper;
use Cubex\Sprintf\ParseQuery;

class RecordMapper extends DataMapper
{
  const CONFIG_IDS = 'id-mechanism';

  /**
   * Auto Incrementing ID
   */
  const ID_AUTOINCREMENT = 'auto';
  /**
   * Manual ID Assignment
   */
  const ID_MANUAL = 'manual';
  /**
   * Combine multiple keys to a single key for store
   */
  const ID_COMPOSITE = 'composite';
  /**
   * Base ID on multiple keys
   */
  const ID_COMPOSITE_SPLIT = 'compositesplit';

  protected $_dbServiceName = 'db';
  protected $_dbTableName;

  public function __construct($id = null, $columns = ['*'])
  {
    parent::__construct();
    $this->_addIdAttribute();
    if($id !== null)
    {
      $this->load($id, $columns);
    }
  }

  protected function _addIdAttribute()
  {
    if(!$this->_attributeExists($this->getIDKey()))
    {
      $this->_addAttribute(new Attribute($this->getIDKey()));
    }
  }

  /**
   * @return array
   */
  public function getConfiguration()
  {
    return array(static::CONFIG_IDS => static::ID_AUTOINCREMENT);
  }

  /**
   * @return string
   */
  protected function _idPattern()
  {
    $config = $this->getConfiguration();
    if(!isset($config[static::CONFIG_IDS]))
    {
      $config[static::CONFIG_IDS] = static::ID_MANUAL;
    }
    if($config[static::CONFIG_IDS] == static::ID_AUTOINCREMENT)
    {
      return "%C = %d";
    }
    else
    {
      return "%C = %s";
    }
  }

  /**
   * @param       $id
   * @param array $columns
   *
   * @return static
   * @throws \Exception
   */
  public function load($id, $columns = ['*'])
  {
    /**
     * @var $this self
     */
    $this->setExists(false);
    $pattern = $this->_idPattern();
    $pattern = 'SELECT %LC FROM %T WHERE ' . $pattern;

    $connection = $this->connection(
      new ConnectionMode(ConnectionMode::READ)
    );

    $args = array(
      $pattern,
      $columns,
      $this->getTableName(),
      $this->getIDKey(),
      $id,
    );

    $query = ParseQuery::parse($connection, $args);

    $rows = $connection->getRows($query);
    if(!$rows)
    {
      $set = "set" . $this->getIDKey();
      $this->$set($id);
    }
    else
    {
      if(count($rows) == 1)
      {
        $row = $rows[0];
        $this->hydrate((array)$row);
        $this->setExists(true);
        $this->_unmodifyAttributes();
      }
      else
      {
        throw new \Exception("The provided key returned more than one result.");
      }
    }

    return $this;
  }

  public function delete()
  {
    if($this->exists())
    {
      $connection = $this->connection(
        new ConnectionMode(ConnectionMode::WRITE)
      );

      $pattern = $this->_idPattern();
      $pattern = 'DELETE FROM %T WHERE ' . $pattern;

      $args = array(
        $pattern,
        $this->getTableName(),
        $this->getIDKey(),
        $this->id(),
      );

      $query = ParseQuery::parse($connection, $args);

      $connection->query($query);
      $this->setExists(false);
    }
    return $this;
  }

  /**
   * Column Name for ID field
   *
   * @return string Name of ID column
   */
  public function getIDKey()
  {
    return 'id';
  }

  /**
   * @param \Cubex\Database\ConnectionMode $mode
   *
   * @return \Cubex\Database\DatabaseService
   */
  public function connection(ConnectionMode $mode)
  {
    /**
     * @var $sm \Cubex\ServiceManager\ServiceManager
     */
    $sm = Container::get(Container::SERVICE_MANAGER);
    return $sm->db($this->_dbServiceName, $mode);
  }

  /**
   * @return mixed
   */
  public function getTableName()
  {
    if($this->_dbTableName === null)
    {
      $excludeParts = ['mappers', 'applications', 'modules', 'components'];
      $nsparts      = explode('\\', strtolower(get_class($this)));

      foreach($nsparts as $i => $part)
      {
        if($i == 0 || in_array($part, $excludeParts))
        {
          unset($nsparts[$i]);
        }
      }

      $table = implode('_', $nsparts);

      $table              = strtolower(str_replace('\\', '_', $table));
      $this->_dbTableName = $table;
    }
    return $this->_dbTableName;
  }

  public function id()
  {
    if($this->isCompositeID())
    {
      return $this->_getCompositeID();
    }
    else
    {
      $attr = $this->_attribute($this->getIDKey());
      if($attr !== null)
      {
        return $attr->rawData();
      }
      else
      {
        return null;
      }
    }
  }

  /**
   * @return bool
   */
  public function isCompositeID()
  {
    $config = $this->getConfiguration();
    if(isset($config[self::CONFIG_IDS]))
    {
      return in_array(
        $config[self::CONFIG_IDS],
        [self::ID_COMPOSITE, self::ID_COMPOSITE_SPLIT]
      );
    }

    return false;
  }

  /**
   * @return string
   */
  protected function _getCompositeID()
  {
    $result = array();
    foreach($this->_getCompositeKeys() as $key)
    {
      $result[] = $this->_attribute($key)->rawData();
    }

    return implode('|', $result);
  }

  /**
   * @return array
   */
  protected function _getCompositeKeys()
  {
    return array();
  }

  /**
   * @return string
   */
  public function composeID( /*$key1,$key2*/)
  {
    return implode("|", func_get_args());
  }


  public function getDateFormat($attribute = null)
  {
    return "Y-m-d H:i:s";
  }

  /**
   * @return mixed
   */
  public function saveChanges()
  {
    $connection = $this->connection(new ConnectionMode(ConnectionMode::WRITE));
    $modified   = $this->getModifiedAttributes();
    $updates    = $inserts = array();

    if(!empty($modified))
    {
      $this->_updateTimestamps();
      $modified = $this->getModifiedAttributes();
    }

    foreach($modified as $attr)
    {
      if($attr instanceof Attribute)
      {
        if($attr->isModified())
        {
          $val = $attr->rawData();
          if($val instanceof \DateTime)
          {
            $val = $val->format($this->getDateFormat($attr->name()));
          }
          else
          {
            $val = $attr->serialize();
          }

          $inserts[$attr->name()] = $val;
          $updates[]              = ParseQuery::parse(
            $connection,
            array("%C = %ns", $attr->name(), $val)
          );
          $attr->unsetModified();
        }
      }
    }

    if(empty($updates))
    {
      return true;
    }

    if(!$this->exists())
    {
      $pattern = 'INSERT INTO %T (%LC) VALUES(%Ls)';

      $args = array(
        $this->getTableName(),
        array_keys($inserts),
        array_values($inserts),
      );

      if($this->id() !== null)
      {
        $pattern .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
        $pattern .= ' WHERE ' . $this->_idPattern();
        $args[] = $this->getIDKey();
        $args[] = $this->id();
      }

      array_unshift($args, $pattern);
    }
    else
    {
      $pattern = 'UPDATE %T SET ' .
      implode(', ', $updates) .
      ' WHERE ' . $this->_idPattern();

      $args = array(
        $pattern,
        $this->getTableName(),
        $this->getIDKey(),
        $this->id(),
      );
    }

    $query = ParseQuery::parse($connection, $args);

    Debug::info("Query Executed: " . $query);

    return $connection->query($query);
  }
}
