<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Data;

class CompositeAttribute extends Attribute
{
  /**
   * @var Attribute[]
   */
  protected $_subAttributes = [];
  protected $_attributeOrder = [];
  protected $_setData;

  public function __construct($name)
  {
    $this->setName($name);
    $this->_modified = false;
  }

  public function addSubAttribute(Attribute $a)
  {
    $this->_subAttributes[$a->name()] = $a;
    $this->_attributeOrder[]          = $a->name();
    return $this;
  }

  public function attributeOrder()
  {
    return $this->_attributeOrder;
  }

  public function attributePosition($name)
  {
    return array_search($name, $this->_attributeOrder);
  }

  public function containsAttribute($name)
  {
    return isset($this->_subAttributes[$name]);
  }

  public function getAttribute($name)
  {
    return $this->_subAttributes[$name];
  }

  public function getNamedArray()
  {
    $return = [];
    foreach($this->_subAttributes as $attribute)
    {
      $return[$attribute->name()] = $attribute->data();
    }
    return $return;
  }

  public function getValueArray()
  {
    return array_values($this->getNamedArray());
  }

  public function availableAttributes()
  {
    return array_keys($this->_subAttributes);
  }

  public function attributeValue($name)
  {
    if($this->containsAttribute($name))
    {
      return $this->getAttribute($name)->data();
    }
    return null;
  }

  public function data()
  {
    return $this->getNamedArray();
  }

  public function rawData()
  {
    return $this->_setData;
  }

  public function setData($data)
  {
    if(func_num_args() > 1)
    {
      $data = func_get_args();
    }

    $this->_setData = $data;

    if(is_array($data))
    {
      if(is_assoc($data))
      {
        foreach($data as $k => $v)
        {
          if($this->containsAttribute($k))
          {
            $this->getAttribute($k)->setData($v);
          }
        }
      }
      else
      {
        foreach($data as $i => $v)
        {
          if(isset($this->_attributeOrder[$i]))
          {
            $attr = $this->getAttribute($this->_attributeOrder[$i]);
            $attr->setData($v);
          }
        }
      }
    }
    else if(is_object($data))
    {
      if($data instanceof Attribute)
      {
        if($this->containsAttribute($data->name()))
        {
          $this->getAttribute($data->name())->setData($data->data());
        }
      }
      else
      {
        foreach($this->availableAttributes() as $attr)
        {
          if(property_exists($data, $attr))
          {
            $this->getAttribute($attr)->setData($data->$attr);
          }
          else if(method_exists($data, $attr))
          {
            $this->getAttribute($attr)->setData($data->$attr());
          }
        }
      }
    }
    else
    {
      throw new \Exception(
        "Only objects and arrays can be set onto composite attributes"
      );
    }
  }
}
