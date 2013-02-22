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
  protected $_limit;

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
    $this->connection()->getRows(
      $this->_mapperType->getTableName(),
      $ids,
      $this->_columns
    );
    return $this;
  }
}
