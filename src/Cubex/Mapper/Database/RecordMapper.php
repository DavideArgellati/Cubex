<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Mapper\Database;

use Cubex\Container\Container;
use Cubex\Data\Attribute;
use Cubex\Data\CompositeAttribute;
use Cubex\Data\Ephemeral\EphemeralCache;
use Cubex\Database\ConnectionMode;
use Cubex\Database\DatabaseService;
use Cubex\Helpers\Strings;
use Cubex\Log\Debug;
use Cubex\Mapper\DataMapper;
use Cubex\Sprintf\ParseQuery;

abstract class RecordMapper extends DataMapper
{
  const CONFIG_IDS    = 'id-mechanism';
  const CONFIG_SCHEMA = 'schema-type';

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


  const SCHEMA_UNDERSCORE = 'underscore';
  const SCHEMA_CAMELCASE  = 'camel';
  const SCHEMA_PASCALCASE = 'pascal';
  const SCHEMA_AS_IS      = 'asis';

  const RELATIONSHIP_BELONGSTO = 'belongsto';
  const RELATIONSHIP_HASONE    = 'hasone';
  const RELATIONSHIP_HASMANY   = 'hasmany';

  protected $_dbServiceName = 'db';
  protected $_dbTableName;
  protected $_idType = self::ID_AUTOINCREMENT;
  protected $_schemaType = self::SCHEMA_UNDERSCORE;
  protected $_underscoreTable = true;

  protected $_loadPending;
  protected $_loadDetails;

  protected $_fromRelationship;
  protected $_newOnFailedRelationship;
  protected $_recentRelationKey;

  protected $_handledError;
  protected $_changes;

  public function __construct($id = null, $columns = ['*'])
  {
    parent::__construct();
    $this->_addIdAttribute();
    if($id !== null)
    {
      $this->load($id, $columns);
    }
  }

  public function remoteIdKey()
  {
    $foreignKey = strtolower(class_shortname($this)) . '_id';
    return $this->stringToColumnName($foreignKey);
  }

  public function disableLoading()
  {
    $this->_loadPending = null;
    return $this;
  }

  public function createsNewInstanceOnFailedRelation()
  {
    return (bool)$this->_newOnFailedRelationship;
  }

  public function newInstanceOnFailedRelation($bool)
  {
    $this->_newOnFailedRelationship = (bool)$bool;
    return $this;
  }

  public function recentRelationKey()
  {
    return $this->_recentRelationKey;
  }

  public function setRecentRelationKey($key)
  {
    $this->_recentRelationKey = $key;
    return $this;
  }

  public function setFromRelationshipType($rel)
  {
    $this->_fromRelationship = $rel;
    return $this;
  }

  public function fromRelationshipType()
  {
    return $this->_fromRelationship;
  }

  protected function _checkAttributes()
  {
    $this->_load();
  }

  public function forceLoad()
  {
    $this->_load();
    return $this;
  }

  protected function _addIdAttribute()
  {
    if(!$this->attributeExists($this->getIdKey()))
    {
      $this->_addAttribute(new Attribute($this->getIdKey()));
    }
    return $this;
  }

  /**
   * @return array
   */
  public function getConfiguration()
  {
    return array(
      static::CONFIG_IDS    => $this->_idType,
      static::CONFIG_SCHEMA => $this->_schemaType,
    );
  }

  /**
   * @return string
   */
  public function idPattern()
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
   * @return string
   */
  public function schemaType()
  {
    $config = $this->getConfiguration();
    if(!isset($config[static::CONFIG_SCHEMA]))
    {
      return self::SCHEMA_AS_IS;
    }
    else
    {
      return $config[static::CONFIG_SCHEMA];
    }
  }

  public function setExists($bool = true)
  {
    if($bool)
    {
      $this->_loadPending = null;
    }
    parent::setExists($bool);
  }

  public function deleteEphemeralCache()
  {
    EphemeralCache::deleteCache($this->id(), $this);
    return $this;
  }

  public function reload()
  {
    $this->deleteEphemeralCache();
    $this->load($this->id());
    $this->forceLoad();
    return $this;
  }

  protected function _load()
  {
    if($this->_loadPending === null)
    {
      return false;
    }
    $this->_loadPending = null;

    $id                 = $this->_loadDetails['id'];
    $columns            = $this->_loadDetails['columns'];
    $this->_loadPending = null;

    if(EphemeralCache::inCache($id, $this))
    {
      $row = EphemeralCache::getCache($id, $this);
      $this->_fromRow($row);
      return $this;
    }

    $connection = $this->connection(
      new ConnectionMode(ConnectionMode::READ)
    );

    $idAttr = $this->getAttribute($this->getIdKey());
    if($idAttr instanceof CompositeAttribute)
    {
      $idAttr->setData($id);
    }

    /**
     * @var $this self
     */
    $this->setExists(false);
    $pattern = $this->idPattern();
    $pattern = 'SELECT %LC FROM %T WHERE ' . $pattern;

    if($idAttr instanceof CompositeAttribute)
    {
      $args = array(
        $pattern,
        $columns,
        $this->getTableName(),
      );

      $named = $idAttr->getNamedArray();
      foreach($named as $k => $v)
      {
        $args[] = $k;
        $args[] = $v;
      }
    }
    else
    {
      $args = array(
        $pattern,
        $columns,
        $this->getTableName(),
        $this->getIdKey(),
        $id,
      );
    }

    $query = ParseQuery::parse($connection, $args);

    $rows = $connection->getRows($query);
    if(!$rows)
    {
      $this->setData($this->getIdKey(), $id);
    }
    else
    {
      if(count($rows) == 1)
      {
        $row = $rows[0];
        if($columns == ['*'])
        {
          EphemeralCache::storeCache($id, $row, $this);
        }
        $this->_fromRow($row);
      }
      else
      {
        throw new \Exception("The provided key returned more than one result.");
      }
    }

    return true;
  }

  protected function _fromRow($row)
  {
    $this->hydrate((array)$row);
    $this->setExists(true);
    $this->_unmodifyAttributes();
    return $this;
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
    $this->_loadPending = true;
    $this->_loadDetails = ['id' => $id, 'columns' => $columns];
    return $this;
  }

  public function exists()
  {
    $this->_load();
    return $this->_exists;
  }

  /**
   * Before Delete from DB
   */
  protected function _preDelete()
  {
  }

  public function delete()
  {
    $this->_load();
    $this->_preDelete();
    $this->deleteEphemeralCache();
    if($this->exists())
    {
      $connection = $this->connection(
        new ConnectionMode(ConnectionMode::WRITE)
      );

      $pattern = $this->idPattern();
      $pattern = 'DELETE FROM %T WHERE ' . $pattern;

      $args = array(
        $pattern,
        $this->getTableName(),
        $this->getIdKey(),
        $this->id(),
      );

      $query = ParseQuery::parse($connection, $args);

      $connection->query($query);
      $this->setExists(false);
    }
    return $this;
  }

  /**
   * @param \Cubex\Database\ConnectionMode $mode
   *
   * @return \Cubex\Database\DatabaseService
   */
  public static function conn(ConnectionMode $mode = null)
  {
    $a = new static;
    /**
     * @var $a self
     */
    if($mode === null)
    {
      return $a->connection();
    }
    else
    {
      return $a->connection($mode);
    }
  }

  /**
   * @param \Cubex\Database\ConnectionMode $mode
   *
   * @return \Cubex\Database\DatabaseService
   */
  public function connection(ConnectionMode $mode = null)
  {
    if($mode === null)
    {
      $mode = new ConnectionMode(ConnectionMode::READ);
    }

    $sm = Container::servicemanager();
    return $sm->db($this->_dbServiceName, $mode);
  }

  /**
   * @return mixed
   */
  public function getTableName()
  {
    if($this->_dbTableName === null)
    {
      $excludeParts = [
        'mappers',
        'applications',
        'modules',
        'components'
      ];
      $nsparts      = explode('\\', $this->getTableClass());

      foreach($nsparts as $i => $part)
      {
        if($i == 0 || in_array(strtolower($part), $excludeParts))
        {
          unset($nsparts[$i]);
        }
      }

      $table = implode('_', $nsparts);
      if($this->_underscoreTable)
      {
        $table = Strings::variableToUnderScore($table);
      }

      $table              = strtolower(str_replace('\\', '_', $table));
      $this->_dbTableName = $table . 's';
    }
    return $this->_dbTableName;
  }

  public function getTableClass()
  {
    return get_class($this);
  }

  public function id()
  {
    $this->_load();
    if($this->isCompositeId())
    {
      return $this->_getCompositeId();
    }
    else
    {
      $attr = $this->_attribute($this->getIdKey());
      if($attr !== null)
      {
        $id = $attr->data();
        if(is_array($id))
        {
          return implode(',', $id);
        }
        else
        {
          return $id;
        }
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
  public function isCompositeId()
  {
    $config = $this->getConfiguration();
    if(isset($config[self::CONFIG_IDS]))
    {
      return in_array(
        $config[self::CONFIG_IDS],
        [
        self::ID_COMPOSITE,
        self::ID_COMPOSITE_SPLIT
        ]
      );
    }

    return false;
  }

  /**
   * @return string
   */
  protected function _getCompositeId()
  {
    $this->_load();
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
  public function composeId( /*$key1,$key2*/)
  {
    return implode("|", func_get_args());
  }


  public function getDateFormat($attribute = null)
  {
    return "Y-m-d H:i:s";
  }

  /**
   * Before object creation within DB
   */
  protected function _prePersist()
  {
  }

  /**
   * Before object update within DB
   */
  protected function _preUpdate()
  {
  }

  /**
   * @return bool|mixed
   * @throws \Exception
   */
  public function saveChanges()
  {
    if(!$this->exists())
    {
      $this->_prePersist();
    }
    else
    {
      $this->_preUpdate();
    }

    $this->_changes = [];

    $connection = $this->connection(new ConnectionMode(ConnectionMode::WRITE));
    $modified   = $this->getModifiedAttributes();
    $updates    = $inserts = array();
    $cache      = EphemeralCache::getCache($this->id(), $this, null);
    $idAttr     = $this->getAttribute($this->getIdKey());
    $idFields   = [];

    if($idAttr instanceof CompositeAttribute)
    {
      $idFields = array_keys($idAttr->getNamedArray());
    }

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
          if(in_array($attr->name(), $idFields) && $this->exists())
          {
            throw new \Exception("You cannot update IDs on a pivot table");
          }

          if(
            $this->_autoTimestamp
            && $attr->name() != $this->createdAttribute()
            && $attr->name() != $this->updatedAttribute()
          )
          {
            $this->_changes[$attr->name()] = [
              'before' => $attr->originalData(),
              'after'  => $attr->data()
            ];
          }

          $val = $attr->rawData();
          if($val instanceof \DateTime)
          {
            $val = $val->format($this->getDateFormat($attr->name()));
          }
          else
          {
            $val = $attr->serialize();
          }

          if($cache !== null)
          {
            $keyname         = $attr->name();
            $cache->$keyname = $val;
          }

          $inserts[$this->stringToColumnName($attr->name())] = $val;

          if($attr->name() != $this->createdAttribute())
          {
            $updates[] = ParseQuery::parse(
              $connection,
              [
              "%C = %ns",
              $this->stringToColumnName($attr->name()),
              $val
              ]
            );
          }
        }
      }
    }

    if(empty($updates))
    {
      return true;
    }

    if($cache !== null)
    {
      EphemeralCache::storeCache($this->id(), $cache, $this);
    }

    if(!$this->exists())
    {
      $pattern = 'INSERT INTO %T (%LC) VALUES(%Ls)';

      $args = array(
        $this->getTableName(),
        array_keys($inserts),
        array_values($inserts),
      );

      array_unshift($args, $pattern);
      $query = ParseQuery::parse($connection, $args);

      if($this->id() !== null)
      {
        $query .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
      }
    }
    else
    {
      $pattern = 'UPDATE %T SET ' . implode(', ', $updates);
      $pattern .= ' WHERE ' . $this->idPattern();

      if($idAttr instanceof CompositeAttribute)
      {
        $args = array(
          $pattern,
          $this->getTableName(),
        );

        $named = $idAttr->getNamedArray();
        foreach($named as $k => $v)
        {
          $args[] = $k;
          $args[] = $v;
        }
      }
      else
      {
        $args = array(
          $pattern,
          $this->getTableName(),
          $this->getIdKey(),
          $this->id(),
        );
      }

      $query = ParseQuery::parse($connection, $args);
    }

    $result = $connection->query($query);

    if($this->_handledError !== true && !$result)
    {
      $this->_handleError($connection);
    }
    else
    {
      if(!$this->exists())
      {
        $newId = $connection->insertId();
        if($newId !== null && $newId !== 0)
        {
          $this->setId($newId);
        }
      }
      foreach($this->_attributes as $attr)
      {
        $attr->unsetModified();
      }
    }

    $this->_handledError = null;

    return $result;
  }

  public function getSavedChanges()
  {
    return $this->_changes;
  }

  public function hasOne(RecordMapper $entity, $foreignKey = null)
  {
    $this->_load();
    $rel = new Relationship($this);
    return $rel->hasOne($entity, $foreignKey);
  }

  public function hasMany(RecordMapper $entity, $foreignKey = null)
  {
    $this->_load();
    $rel = new Relationship($this);
    return $rel->hasMany($entity, $foreignKey);
  }

  public function belongsTo(
    RecordMapper $entity, $foreignKey = null,
    $localKey = null
  )
  {
    $this->_load();
    $rel = new Relationship($this);
    return $rel->belongsTo($entity, $foreignKey, $localKey);
  }

  public function hasAndBelongsToMany(
    RecordMapper $entity, $localKey = null, $foreignKey = null, $table = null
  )
  {
    $this->_load();
    $rel = new Relationship($this);
    return $rel->hasAndBelongsToMany($entity, $localKey, $foreignKey, $table);
  }

  public function stringToColumnName($string)
  {
    switch($this->schemaType())
    {
      case self::SCHEMA_UNDERSCORE:
        return Strings::variableToUnderScore($string);
      case self::SCHEMA_PASCALCASE:
        return Strings::variableToPascalCase($string);
      case self::SCHEMA_CAMELCASE:
        return Strings::variableToCamelCase($string);
      case self::SCHEMA_AS_IS:
        return $string;
    }
    return $string;
  }

  public static function schema()
  {
    /**
     * @var $source self
     */
    $source = new static;
    $schema = $source->conn()->getKeyedRows(
      "DESCRIBE `" . $source->getTableName() . '`'
    );
    return $schema;
  }

  public static function min($key = 'id')
  {
    $a = new Aggregate((new static));
    if(func_num_args() > 1)
    {
      $args = func_get_args();
      array_shift($args);
      call_user_func_array(
        [
        $a,
        'where'
        ],
        $args
      );
    }
    return $a->min($key);
  }

  public static function max($key = 'id')
  {
    $a = new Aggregate((new static));
    if(func_num_args() > 1)
    {
      $args = func_get_args();
      array_shift($args);
      call_user_func_array(
        [
        $a,
        'where'
        ],
        $args
      );
    }
    return $a->max($key);
  }

  public static function avg($key = 'id')
  {
    $a = new Aggregate((new static));
    if(func_num_args() > 1)
    {
      $args = func_get_args();
      array_shift($args);
      call_user_func_array(
        [
        $a,
        'where'
        ],
        $args
      );
    }
    return $a->avg($key);
  }

  public static function sum($key = 'id')
  {
    $a = new Aggregate((new static));
    if(func_num_args() > 1)
    {
      $args = func_get_args();
      array_shift($args);
      call_user_func_array(
        [
        $a,
        'where'
        ],
        $args
      );
    }
    return $a->sum($key);
  }

  public static function count($key = 'id')
  {
    $a = new Aggregate((new static));
    if(func_num_args() > 1)
    {
      $args = func_get_args();
      array_shift($args);
      call_user_func_array(
        [
        $a,
        'where'
        ],
        $args
      );
    }
    return $a->count($key);
  }

  /**
   * @return static|null
   * @throws \Exception
   */
  public static function loadWhere()
  {
    $collection = new RecordCollection(new static());
    return call_user_func_array(
      [
      $collection,
      'loadOneWhere'
      ],
      func_get_args()
    );
  }

  /**
   * @return static|null
   * @throws \Exception
   */
  public static function loadWhereOrNew()
  {
    $resp = call_user_func_array(
      [get_called_class(), "loadWhere"],
      func_get_args()
    );
    if($resp === null)
    {
      $resp = new static;
    }
    return $resp;
  }

  /**
   * @return RecordCollection
   */
  public static function collection()
  {
    return new RecordCollection(new static);
  }

  protected function _handleError(DatabaseService $connection)
  {
    switch($connection->errorNo())
    {
      case 1146:
      case 1054:
        if(Container::config()->get("devtools")->getBool("creations", false))
        {
          $builder = new DBBuilder($connection, $this);
          if($builder->success())
          {
            $this->_handledError = true;
            $this->saveChanges();
          }
          return;
        }
      default:
        throw new \Exception($connection->errorMsg(), $connection->errorNo());
    }
  }


  public function __clone()
  {
    $this->_loadPending = $this->_loadDetails = null;
    parent::__clone();
  }
}
