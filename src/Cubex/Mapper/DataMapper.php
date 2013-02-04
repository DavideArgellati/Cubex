<?php
/**
 * @author  brooke.bryan
 */
namespace Cubex\Mapper;

use Cubex\Data\Attribute;

abstract class DataMapper implements \JsonSerializable, \IteratorAggregate
{
  protected $_id;
  /**
   * @var \Cubex\Data\Attribute[]
   */
  protected $_attributes;
  protected $_invalidAttributes;
  protected $_exists = false;
  protected $_autoTimestamp = true;

  /**
   * Automatically add all public properties as attributes
   * and unset them for automatic handling of data
   */
  public function __construct($id = null)
  {
    $this->setId($id);
    $this->_buildAttributes();
    $this->_configure();
  }

  protected function _checkAttributes()
  {
  }

  protected function _cleanAttributeName($name)
  {
    $name = strtolower($name);
    $name = str_replace([' ', '_'], '', $name);
    return $name;
  }

  /**
   * Column Name for ID field
   *
   * @return string Name of ID column
   */
  public function getIdKey()
  {
    return 'id';
  }

  public function id()
  {
    return $this->_id;
  }

  public function setId($id)
  {
    if($this->attributeExists($this->getIdKey()))
    {
      $this->setData($this->getIdKey(), $id);
    }
    $this->_id = $id;
    return $this;
  }

  protected function _buildAttributes($type = '\Cubex\Data\Attribute')
  {
    $class = new \ReflectionClass(get_class($this));
    foreach($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $p)
    {
      $property = $p->getName();
      if(!$this->attributeExists($property))
      {
        $this->_addAttribute(
          new $type($property, false, null, $p->getValue($this))
        );
      }
      unset($this->$property);
    }

    if($this->_autoTimestamp)
    {
      if(!$this->attributeExists($this->updatedAttribute()))
      {
        $this->_addAttribute(new $type($this->updatedAttribute()));
      }

      if(!$this->attributeExists($this->createdAttribute()))
      {
        $this->_addAttribute(new $type($this->createdAttribute()));
      }
    }
    return $this;
  }

  protected function _configure()
  {
    //Add Filters & Validators
    return $this;
  }

  /**
   *
   */
  public function __clone()
  {
    $attrs             = $this->_attributes;
    $this->_attributes = array();
    $this->_cloneSetup();

    foreach($attrs as $attr)
    {
      if($attr instanceof Attribute)
      {
        $attr->setData(null);
        $this->_addAttribute(clone $attr);
      }
    }
  }

  /**
   * @return \Cubex\Data\Attribute[]
   */
  public function getRawAttributes()
  {
    $this->_checkAttributes();
    return $this->_attributes;
  }

  protected function _cloneSetup()
  {
  }

  /**
   * @return \ArrayIterator
   */
  public function getIterator()
  {
    return new \ArrayIterator($this->_getRawAttributesArr($this->_attributes));
  }

  /**
   * @return string
   */
  public function __toString()
  {
    return get_class($this) . " " . json_encode($this);
  }

  /**
   * @return array|mixed
   */
  public function jsonSerialize()
  {
    $this->_checkAttributes();
    return $this->_getRawAttributesArr($this->_attributes);
  }

  /**
   * @param array $attributes
   *
   * @return array
   */
  protected function _getRawAttributesArr(array $attributes)
  {
    $this->_checkAttributes();
    $rawAttributes = [];
    foreach($attributes as $attribute)
    {
      if($attribute instanceof Attribute)
      {
        $rawAttributes[$attribute->name()] = $attribute->data();
      }
    }

    return $rawAttributes;
  }

  public function exists()
  {
    return $this->_exists;
  }

  public function setExists($bool = true)
  {
    $this->_exists = $bool;
    return $this;
  }

  /**
   * @param $method
   * @param $args
   *
   * @return bool|DataMapper|mixed
   */
  public function __call($method, $args)
  {
    // NOTE: PHP has a bug that static variables defined in __call() are shared
    // across all children classes. Call a different method to work around this
    // bug.
    return $this->_doCall($method, $args);
  }

  /**
   * @param $method
   * @param $args
   *
   * @return bool|DataMapper|mixed
   * @throws \Exception
   */
  protected function _doCall($method, $args)
  {
    switch(substr($method, 0, 3))
    {
      case 'set':
        $this->setData(substr($method, 3), $args[0]);
        break;
      case 'get':
        $this->getData(substr($method, 3));
        break;
    }
    return true;
  }

  public function hasAttribute($attribute)
  {
    return $this->attributeExists($attribute);
  }

  public function setData($attribute, $value)
  {
    if($this->attributeExists($attribute))
    {
      $this->_attribute($attribute)->setData($value);

      return $this;
    }
    else
    {
      throw new \Exception("Invalid Attribute " . $attribute);
    }
  }

  public function getData($attribute)
  {
    $this->_checkAttributes();
    if($this->attributeExists($attribute))
    {
      return $this->_attribute($attribute)->data();
    }
    else
    {
      throw new \Exception("Invalid Attribute " . $attribute);
    }
  }

  /**
   * @param $name
   *
   * @return bool|DataMapper|mixed
   */
  public function __get($name)
  {
    return $this->getData($name);
  }

  /**
   * @param $name
   * @param $value
   *
   * @return bool|DataMapper|mixed
   */
  public function __set($name, $value)
  {
    return $this->setData($name, $value);
  }


  /**
   * @return array
   */
  public function getConfiguration()
  {
    return array();
  }


  /**
   * @param $name
   *
   * @return Attribute
   */
  protected function _attribute($name)
  {
    $name = $this->_cleanAttributeName($name);
    return isset($this->_attributes[$name]) ? $this->_attributes[$name] : null;
  }

  /**
   * @param \Cubex\Data\Attribute $attribute
   *
   * @return $this
   */
  protected function _addAttribute(Attribute $attribute)
  {
    $name                     = $this->_cleanAttributeName($attribute->name());
    $this->_attributes[$name] = $attribute;
    return $this;
  }

  /**
   * @param $attribute
   *
   * @return bool
   */
  public function attributeExists($attribute)
  {
    $attribute = $this->_cleanAttributeName($attribute);
    return isset($this->_attributes[$attribute]);
  }

  protected function _setRequired($attribute, $required = true)
  {
    if($this->attributeExists($attribute))
    {
      $this->_attribute($attribute)->setRequired($required);
    }
    return $this;
  }

  protected function _addFilter($attribute, $filter, array $options = [])
  {
    $this->_checkAttributes();
    $attribute = $this->_cleanAttributeName($attribute);
    if(!isset($this->_attributes[$attribute]))
    {
      return false;
    }
    $attr = $this->_attributes[$attribute];
    if($attr instanceof Attribute)
    {
      $this->_attributes[$attribute] = $attr->addFilter(
        $filter, $options
      );

      return true;
    }

    return false;
  }

  protected function _addValidator($attribute, $validator, array $options = [])
  {
    $this->_checkAttributes();
    $attribute = $this->_cleanAttributeName($attribute);
    if(!isset($this->_attributes[$attribute]))
    {
      return false;
    }
    $attr = $this->_attributes[$attribute];
    if($attr instanceof Attribute)
    {
      $this->_attributes[$attribute] = $attr->addValidator(
        $validator, $options
      );

      return true;
    }

    return false;
  }

  /**
   * @param $attribute
   * @param $option
   *
   * @return bool
   */
  protected function _addAttributeOption($attribute, $option)
  {
    $this->_checkAttributes();
    $attribute = $this->_cleanAttributeName($attribute);
    if(!isset($this->_attributes[$attribute]))
    {
      return false;
    }
    $attr = $this->_attributes[$attribute];
    if($attr instanceof Attribute)
    {
      $this->_attributes[$attribute] = $attr->addOption($option);

      return true;
    }

    return false;
  }

  /**
   * @param null $attributes
   * @param bool $processAllValidators
   * @param bool $failFirst
   *
   * @return bool
   */
  public function isValid($attributes = null, $processAllValidators = false,
                          $failFirst = false)
  {
    $this->_checkAttributes();
    $valid = true;

    if(is_scalar($attributes))
    {
      $attributes = [$attributes];
    }

    if($attributes === null)
    {
      $attributes = array_keys($this->_attributes);
    }

    if(is_array($attributes))
    {
      foreach($attributes as $attribute)
      {
        $attr = $this->_attribute($attribute);
        if($attr instanceof Attribute)
        {
          unset($this->_invalidAttributes[$attribute]);
          if(!$attr->valid($processAllValidators))
          {
            $valid                                = false;
            $this->_invalidAttributes[$attribute] = $attr->validationErrors();
            if($failFirst)
            {
              return false;
            }
          }
        }
      }
    }

    return $valid;
  }

  public function validationErrors(array $attributes = [])
  {
    if($attributes === [])
    {
      return (array)$this->_invalidAttributes;
    }
    else
    {
      $result = [];
      foreach($attributes as $attr)
      {
        $result[$attr] = $this->_invalidAttributes[$attr];
      }
      return $result;
    }
  }


  protected function _unmodifyAttributes()
  {
    $this->_checkAttributes();
    foreach($this->_attributes as $attr)
    {
      if($attr instanceof Attribute)
      {
        $attr->unsetModified();
      }
    }
    return $this;
  }

  /**
   * @return array
   */
  public function getModifiedAttributes()
  {
    $this->_checkAttributes();
    $modified = array();
    foreach($this->_attributes as $attr)
    {
      if($attr instanceof Attribute)
      {
        if($attr->isModified())
        {
          $modified[] = $attr;
        }
      }
    }

    return $modified;
  }

  /**
   * @param null $name
   *
   * @return DataMapper
   */
  public function revert($name = null)
  {
    $this->_checkAttributes();
    if($name !== null)
    {
      $this->_attribute($name)->revert();
    }
    else
    {
      foreach($this->_attributes as $attr)
      {
        if($attr instanceof Attribute)
        {
          $attr->revert();
        }
      }
    }

    return $this;
  }

  /**
   * @param array $data
   * @param bool  $setUnmodified
   *
   * @return DataMapper
   */
  public function hydrate(array $data, $setUnmodified = false)
  {
    foreach($data as $k => $v)
    {
      $k = strtolower($k);
      if($this->attributeExists($k))
      {
        $set = "set$k";
        $this->$set($this->_attribute($k)->unserialize($v));
        if($setUnmodified)
        {
          $this->_attribute($k)->unsetModified();
        }
      }
    }

    return $this;
  }

  public function hydrateFromMapper(DataMapper $mapper)
  {
    foreach($mapper->getRawAttributes() as $attr)
    {
      if($this->attributeExists($attr->name()))
      {
        $this->setData($attr->name(), $attr->data());
      }
    }
    return $this;
  }

  public function maintainsTimestamps()
  {
    return $this->_autoTimestamp;
  }

  public function updatedAttribute()
  {
    return 'updated_at';
  }

  public function createdAttribute()
  {
    return 'created_at';
  }

  protected function _updateTimestamps()
  {
    if(!$this->_autoTimestamp)
    {
      return false;
    }

    $this->setData($this->updatedAttribute(), $this->currentDateTime());
    if(!$this->exists())
    {
      $this->setData($this->createdAttribute(), $this->currentDateTime());
    }

    return true;
  }

  public function currentDateTime()
  {
    return new \DateTime;
  }

  public function touch()
  {
    $this->_updateTimestamps();
    return $this;
  }

  public function saveChanges()
  {
    $this->_updateTimestamps();
    return false;
  }

  public function getValidators()
  {
    $this->_checkAttributes();
    $validators = [];
    foreach($this->_attributes as $attr)
    {
      if($attr instanceof Attribute)
      {
        $validators[$attr->name()] = $attr->getValidators();
      }
    }
    return $validators;
  }

  public function getFilters()
  {
    $this->_checkAttributes();
    $filters = [];
    foreach($this->_attributes as $attr)
    {
      if($attr instanceof Attribute)
      {
        $filters[$attr->name()] = $attr->getFilters();
      }
    }
    return $filters;
  }

  public function importValidators(DataMapper $from)
  {
    $validators = $from->getValidators();
    foreach($validators as $attr => $validatorArray)
    {
      if($this->attributeExists($attr))
      {
        $this->_attribute($attr)->setValidators($validatorArray);
      }
    }
    return $this;
  }

  public function importFilters(DataMapper $from)
  {
    $filters = $from->getFilters();
    foreach($filters as $attr => $filterArray)
    {
      if($this->attributeExists($attr))
      {
        $this->_attribute($attr)->setFilters($filterArray);
      }
    }
    return $this;
  }

  public function importFiltersAndValidators(DataMapper $from)
  {
    $this->importFilters($from);
    $this->importValidators($from);
    return $this;
  }

  public function importRequires(DataMapper $from)
  {
    $attributes = $from->getRawAttributes();
    foreach($attributes as $attr)
    {
      if($this->attributeExists($attr->name()))
      {
        $this->_attribute($attr->name())->setRequired($attr->required());
      }
    }
    return $this;
  }
}
