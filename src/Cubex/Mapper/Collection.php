<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Mapper;

/**
 * @var Collection DataMapper[]
 */
class Collection
  implements \ArrayAccess, \Countable, \Iterator,
             \JsonSerializable, \Serializable
{
  /**
   * @var DataMapper[]
   */
  protected $_mappers = [];
  protected $_dictionary = [];
  protected $_position = 0;

  /**
   * @param array $mappers
   */
  public function __construct(array $mappers = [])
  {
    $this->hydrate($mappers);
  }

  public function clear()
  {
    $this->_mappers    = [];
    $this->_dictionary = [];
    $this->_position   = 0;
    return $this;
  }

  public function createArray($keyField, $valueField)
  {
    $result = [];
    foreach($this->_mappers as $mapper)
    {
      $result[$mapper->$keyField] = $mapper->$valueField;
    }
    return $result;
  }

  public function hydrate(array $mappers = [])
  {
    foreach($mappers as $mapper)
    {
      if($mapper instanceof DataMapper)
      {
        $this->addMapper($mapper);
      }
    }
    return $this;
  }

  public function addMapper(DataMapper $mapper)
  {
    $this->_mappers[] = $mapper;
    if($mapper->id() !== null)
    {
      $this->_dictionary[$mapper->id()] = true;
    }
    return $this;
  }

  public function __isset($id)
  {
    return $this->contains($id);
  }

  public function contains($id)
  {
    return isset($this->_dictionary[$id]);
  }

  /**
   * @return DataMapper[]
   */
  public function all()
  {
    return $this->_mappers;
  }

  /**
   * Get a selection from the entire result set
   *
   * @param int $offset
   * @param int $length
   *
   * @return DataMapper[]
   */
  public function limit($offset = 0, $length = 1)
  {
    return array_slice($this->_mappers, $offset, $length);
  }

  /**
   * Return the current element
   *
   * @link http://php.net/manual/en/iterator.current.php
   * @return DataMapper
   */
  public function current()
  {
    return $this->_mappers[$this->_position];
  }

  /**
   * Move forward to next element
   *
   * @link http://php.net/manual/en/iterator.next.php
   * @return void Any returned value is ignored.
   */
  public function next()
  {
    ++$this->_position;
  }

  /**
   * Return the key of the current element
   *
   * @link http://php.net/manual/en/iterator.key.php
   * @return mixed scalar on success, or null on failure.
   */
  public function key()
  {
    return $this->_position;
  }

  /**
   * Checks if current position is valid
   *
   * @link http://php.net/manual/en/iterator.valid.php
   * @return boolean The return value will be casted to boolean
   *       and then evaluated.
   */
  public function valid()
  {
    return isset($this->_mappers[$this->_position]);
  }

  /**
   * Rewind the Iterator to the first element
   *
   * @link http://php.net/manual/en/iterator.rewind.php
   * @return void Any returned value is ignored.
   */
  public function rewind()
  {
    $this->_position = 0;
  }

  /**
   * Whether a offset exists
   *
   * @link http://php.net/manual/en/arrayaccess.offsetexists.php
   *
   * @param mixed $offset An offset to check for.
   *
   * @return boolean true on success or false on failure.
   *
   * The return value will be casted to boolean if non-boolean was returned.
   */
  public function offsetExists($offset)
  {
    return array_key_exists($offset, $this->_mappers);
  }

  /**
   * Offset to retrieve
   *
   * @link http://php.net/manual/en/arrayaccess.offsetget.php
   *
   * @param mixed $offset The offset to retrieve.
   *
   * @return DataMapper
   */
  public function offsetGet($offset)
  {
    return $this->_mappers[$offset];
  }

  /**
   * Offset to set
   *
   * @link http://php.net/manual/en/arrayaccess.offsetset.php
   *
   * @param mixed $offset The offset to assign the value to.
   * @param mixed $value  The value to set.
   *
   * @return void
   */
  public function offsetSet($offset, $value)
  {
    $this->_mappers[$offset] = $value;
  }

  /**
   * Offset to unset
   *
   * @link http://php.net/manual/en/arrayaccess.offsetunset.php
   *
   * @param mixed $offset The offset to unset.
   *
   * @return void
   */
  public function offsetUnset($offset)
  {
    unset($this->_mappers[$offset]);
  }

  /**
   * Count elements of an object
   *
   * @link http://php.net/manual/en/countable.count.php
   * @return int The custom count as an integer.
   */
  public function count()
  {
    return count($this->_mappers);
  }

  /**
   * Serializes the object to a value that can be serialized
   * natively by json_encode().
   *
   * @link http://docs.php.net/manual/en/jsonserializable.jsonserialize.php
   * @return mixed Returns data which can be serialized by json_encode(),
   *       which is a value of any type other than a resource.
   */
  public function jsonSerialize()
  {
    return $this->_mappers;
  }

  /**
   * String representation of object
   *
   * @link http://php.net/manual/en/serializable.serialize.php
   * @return string the string representation of the object or null
   */
  public function serialize()
  {
    return serialize($this->_mappers);
  }

  /**
   * Constructs the object
   *
   * @link http://php.net/manual/en/serializable.unserialize.php
   *
   * @param string $serialized The string representation of the object.
   *
   * @return void
   */
  public function unserialize($serialized)
  {
    $mappers = unserialize($serialized);
    $this->hydrate($mappers);
  }


  public function __toString()
  {
    return json_encode($this);
  }
}
