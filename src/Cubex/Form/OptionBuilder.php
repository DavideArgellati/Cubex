<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Form;

use Cubex\Mapper\Database\RecordCollection;
use Cubex\Mapper\Database\RecordMapper;
use Cubex\Mapper\DataMapper;
use Cubex\Type\Enum;

class OptionBuilder
{
  protected $_source;

  public function __construct($source = null)
  {
    $this->_source = $source;
  }

  public function getOptions()
  {
    if($this->_source === null)
    {
      return null;
    }

    if($this->_source instanceof Enum)
    {
      $options = array_flip($this->_source->getConstList());
      $options = array_map('\Cubex\Helpers\Strings::titleize', $options);
      return $options;
    }

    if($this->_source instanceof RecordCollection)
    {
      return $this->fromCollection($this->_source);
    }

    if($this->_source instanceof RecordMapper)
    {
      return $this->fromRecordMapper($this->_source);
    }

    return null;
  }

  public function fromRecordMapper(RecordMapper $map)
  {
    if($map->fromRelationshipType() == RecordMapper::RELATIONSHIP_BELONGSTO
    || $map->fromRelationshipType() == RecordMapper::RELATIONSHIP_HASONE
    )
    {
      $collection = new RecordCollection($map);
      $collection->loadAll();
      return $this->fromCollection($collection);
    }
    return [];
  }

  public function fromCollection(RecordCollection $c)
  {
    $c->setLimit(0, 101);
    if($c->count() > 100)
    {
      //TODO: Has Many Results, should switch to ajax textbox
    }
    $options     = [];
    $attrName    = null;
    $attrOptions = [
      'name',
      'description',
      'display',
      'display_name',
      'display_value',
      'option_name',
      'id'
    ];
    foreach($c as $option)
    {
      if($option instanceof DataMapper)
      {
        if($attrName === null)
        {
          foreach($attrOptions as $opt)
          {
            if($option->hasAttribute($opt))
            {
              $attrName = $opt;
              break;
            }
          }
        }

        $options[$option->id()] = $option->getData($attrName);
      }
    }
    return $options;
  }
}
