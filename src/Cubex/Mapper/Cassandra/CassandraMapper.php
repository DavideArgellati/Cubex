<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Mapper\Cassandra;

use Cubex\Data\Attribute;
use Cubex\Facade\Cassandra;
use Cubex\Mapper\KeyValue\KvMapper;

class CassandraMapper extends KvMapper
{
  protected $_cassandraConnection = 'cassandra';
  protected $_autoTimestamp = false;

  public function connection()
  {
    return Cassandra::getAccessor($this->_cassandraConnection);
  }

  public function setData($attribute, $value)
  {
    if(!$this->attributeExists($attribute))
    {
      $this->_addAttribute(new Attribute($attribute));
    }
    return parent::setData($attribute, $value);
  }

  /**
   * @param $name
   *
   * @return \Cubex\KvStore\Cassandra\ColumnAttribute
   */
  public function getAttribute($name)
  {
    return $this->_attribute($name);
  }

  /**
   * @return CassandraCollection
   */
  public static function collection()
  {
    return new CassandraCollection(new static);
  }

  public function columnUpdated($columnName)
  {
    if($this->attributeExists($columnName))
    {
      return $this->getAttribute($columnName)->updatedTime();
    }
    else
    {
      return false;
    }
  }

  public function columnExpiry($columnName)
  {
    if($this->attributeExists($columnName))
    {
      return $this->getAttribute($columnName)->expiryTime();
    }
    else
    {
      return false;
    }
  }
}
