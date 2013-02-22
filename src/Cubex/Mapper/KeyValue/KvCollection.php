<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Mapper\KeyValue;

use Cubex\Mapper\Collection;

class KvCollection extends Collection
{
  /**
   * @var KvMapper
   */
  protected $_mapperType;
  protected $_columns;
  protected $_limit = 100;

  public function __construct(KvMapper $map, array $mappers = null)
  {
    parent::__construct($map, $mappers);
  }

  public function setColumns(array $columns = null)
  {
    $this->_columns = $columns;
    return $this;
  }

  public function setLimit($limit = 100)
  {
    $this->_limit = (int)$limit;
    return $this;
  }

  public function getLimit()
  {
    return $this->_limit;
  }

  public function connection()
  {
    return $this->_mapperType->connection();
  }

  public function loadIds($ids)
  {
    if(func_num_args() > 1)
    {
      $ids = func_get_args();
    }
    else if(!is_array($ids))
    {
      $ids = [$ids];
    }

    $results = $this->connection()->getRows(
      $this->_mapperType->getTableName(),
      $ids,
      $this->_columns
    );

    $this->clear();
    if($results !== null && is_array($results))
    {
      foreach($results as $key => $result)
      {
        if(empty($result))
        {
          continue;
        }
        $map = clone $this->_mapperType;
        $map->hydrate($result, true, true);
        $map->setId($key);
        $map->setExists(true);
        $this->addMapper($map);
      }
    }

    return $this;
  }
}
