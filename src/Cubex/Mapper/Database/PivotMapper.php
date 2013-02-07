<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Mapper\Database;

use Cubex\Data\Attribute;
use Cubex\Data\Validator\Validator;

class PivotMapper extends RecordMapper
{
  protected $_idType = self::ID_COMPOSITE;
  protected $_autoTimestamp = false;

  protected $_pivotOn;

  protected $_pivotaKey;
  protected $_pivotbKey;

  public function __construct($ida = null, $idb = null, $columns = ['*'])
  {
    parent::__construct(null, $columns);
    if($ida !== null && $idb !== null)
    {
      $this->load($ida, $idb, $columns);
    }
  }

  public function id()
  {
    $id = $this->getCompAttribute("id");
    if($id !== null)
    {
      $ids = $id->getValueArray();
      return implode(',', $ids);
    }
    return null;
  }

  public function pivotAKey()
  {
    return $this->_pivotaKey;
  }

  public function pivotBKey()
  {
    return $this->_pivotaKey;
  }

  public function setPivotAKey($key)
  {
    $this->_pivotaKey = $key;
    return $this;
  }

  public function setPivotBKey($key)
  {
    $this->_pivotbKey = $key;
    return $this;
  }

  public function pivotOn(RecordMapper $pivota, RecordMapper $pivotb)
  {
    $this->_pivotOn = func_get_args();

    if($this->_dbTableName === null)
    {
      $class  = strtolower(class_shortname($pivota));
      $eClass = strtolower(class_shortname($pivotb));

      $sT     = $pivota->getTableName();
      $prefix = str_replace($class . 's', '', $sT);
      $prefix = trim($prefix, '_');

      if($eClass > $class)
      {
        $table = implode('_', [$prefix, $class . 's', $eClass . 's']);
      }
      else
      {
        $table = implode('_', [$prefix, $eClass . 's', $class . 's']);
      }

      $table = ltrim($table, '_');

      $this->setTableName($table);
    }

    $foreignKey = $pivotb->stringToColumnName($pivotb->remoteIdKey());
    $this->setPivotAKey($foreignKey);

    $localKey = $pivota->stringToColumnName($pivota->remoteIdKey());
    $this->setPivotBKey($localKey);

    $this->addAttribute($foreignKey);
    $this->addAttribute($localKey);

    $this->addCompositeAttribute("id", [$localKey, $foreignKey]);
  }

  public function idPattern()
  {
    $patterns = [];
    $comp     = $this->getCompAttribute($this->getIdKey());
    foreach($comp->availableAttributes() as $k)
    {
      try
      {
        Validator::int($comp->attributeValue($k));
        $patterns[] = "%C = %d";
      }
      catch(\Exception $e)
      {
        echo $e->getMessage();
        $patterns[] = "%C = %s";
      }
    }

    return implode(" AND ", $patterns);
  }

  public function setTableName($table)
  {
    $this->_dbTableName = $table;
    return $this;
  }

  public function addAttribute($key)
  {
    $this->_addAttribute(new Attribute($key));
    return $this;
  }

  public function addCompositeAttribute(
    $name, array $attributes, $createSubs = true
  )
  {
    $attrs = [];
    foreach($attributes as $attr)
    {
      if(is_scalar($attr))
      {
        $attrs[] = $this->getAttribute($attr);
      }
      else
      {
        $attrs[] = $attr;
      }
    }

    return $this->_addCompositeAttribute($name, $attrs, $createSubs);
  }

  public function setLink($ida, $idb)
  {
    $this->setId(func_get_args());
    return $this;
  }


  public function load($ida, $idb, $columns = ['*'])
  {
    $id = [$ida, $idb];
    return parent::load($id, $columns);
  }

  public function loadCollection($key, $value = null)
  {
    if($key instanceof RecordMapper)
    {
      if($value === null)
      {
        $value = $key->id();
      }
      $key = $this->stringToColumnName($key->remoteIdKey());
    }
    $collection = $this::collection();
    $map        = $collection->getMapperType();
    if($map instanceof PivotMapper)
    {
      call_user_func_array([$map, "pivotOn"], $this->_pivotOn);
      $map->setTableName($this->getTableName());
      $map->setPivotAKey($this->pivotAKey());
      $map->setPivotBKey($this->pivotBKey());
    }
    try
    {
      Validator::int($value);
      $collection->loadWhere("%C = %d", $key, $value);
    }
    catch(\Exception $e)
    {
      $collection->loadWhere("%C = %s", $key, $value);
    }

    return $collection;
  }
}
