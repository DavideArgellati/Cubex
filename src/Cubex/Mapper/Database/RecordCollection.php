<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Mapper\Database;

use Cubex\Database\ConnectionMode;
use Cubex\Mapper\Collection;
use Cubex\Sprintf\ParseQuery;

class RecordCollection extends Collection
{
  protected $_mapperType;
  protected $_columns = ['*'];

  public function __construct(RecordMapper $map, array $columns = ['*'])
  {
    $this->_mapperType = $map;
  }

  public function setColumns(array $columns = ['*'])
  {
    $this->_columns = $columns;
    return $this;
  }

  public function loadAll()
  {
    static::loadWhere("1=1");
    return $this;
  }

  public function loadOneWhere($pattern /* , $arg, $arg, $arg ... */)
  {
    call_user_func_array(array($this, 'loadWhere'), func_get_args());

    if(count($this->_mappers) > 1)
    {
      $this->clear();
      throw new \Exception("More than one result in loadOneWhere() $pattern");
    }
  }

  public function loadMatches(SearchObject $search)
  {
    static::loadWhere("%QO", $search);
    return $this;
  }

  public function loadWhere($pattern /* , $arg, $arg, $arg ... */)
  {
    $this->clear();
    $args = func_get_args();
    array_shift($args);
    array_unshift($args, $this->_mapperType->getTableName());
    array_unshift($args, $this->_columns);

    $pattern = 'SELECT %LC FROM %T WHERE ' . $pattern;
    array_unshift($args, $pattern);

    $connection = $this->_mapperType->connection(
      new ConnectionMode(ConnectionMode::READ)
    );

    $query = ParseQuery::parse($connection, $args);

    if($query !== false)
    {
      $rows = $connection->getRows($query);
      if($rows)
      {
        foreach($rows as $row)
        {
          $map = clone $this->_mapperType;
          $map->hydrate((array)$row);
          $map->setExists(true);
          $this->addMapper($map);
        }
      }
    }
  }
}