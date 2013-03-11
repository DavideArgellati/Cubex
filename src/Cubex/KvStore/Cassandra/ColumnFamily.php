<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\KvStore\Cassandra;

use Thrift\Exception\TApplicationException;
use cassandra\AuthenticationException;
use cassandra\AuthorizationException;
use cassandra\Column;
use cassandra\ColumnOrSuperColumn;
use cassandra\ColumnParent;
use cassandra\ColumnPath;
use cassandra\Compression;
use cassandra\ConsistencyLevel;
use cassandra\CounterColumn;
use cassandra\Deletion;
use cassandra\InvalidRequestException;
use cassandra\KeyRange;
use cassandra\KeySlice;
use cassandra\Mutation;
use cassandra\NotFoundException;
use cassandra\SchemaDisagreementException;
use cassandra\SlicePredicate;
use cassandra\SliceRange;
use cassandra\SuperColumn;
use cassandra\TimedOutException;
use cassandra\UnavailableException;

class ColumnFamily
{
  protected $_connection;
  protected $_name;
  protected $_keyspace;
  protected $_consistency;
  protected $_cqlVersion = 3;
  protected $_returnAttribute = true;

  public function __construct(Connection $connection, $name, $keyspace)
  {
    $this->_connection = $connection;
    $this->_name       = $name;
    $this->_keyspace   = $keyspace;
  }

  public function setReturnAttribute($bool = true)
  {
    $this->_returnAttribute = (bool)$bool;
    return $this;
  }

  public function returnAttribute()
  {
    return $this->_returnAttribute;
  }

  public function setCqlVersion($version = 3)
  {
    $this->_cqlVersion = $version;
    return $this;
  }

  public function cqlVersion()
  {
    return $this->_cqlVersion;
  }

  public function name()
  {
    return $this->_name;
  }

  public function setName($name)
  {
    $this->_name = $name;
    return $this;
  }

  public function setConnection(Connection $connection)
  {
    $this->_connection = $connection;
    return $this;
  }

  public function connection()
  {
    $this->_connection->setKeyspace($this->_keyspace);
    return $this->_connection;
  }

  public function keyspace()
  {
    return $this->_keyspace;
  }

  protected function _client()
  {
    return $this->connection()->client();
  }

  protected function _columnPath()
  {
    return new ColumnPath(['column_family' => $this->_name]);
  }

  protected function _columnParent()
  {
    return new ColumnParent(['column_family' => $this->_name]);
  }

  public function setConsistencyLevel($level = ConsistencyLevel::QUORUM)
  {
    $this->_consistency = $level;
    return $this;
  }

  public function consistencyLevel()
  {
    if($this->_consistency === null)
    {
      $this->_consistency = ConsistencyLevel::QUORUM;
    }
    return $this->_consistency;
  }

  public function columnCount($key, array $columnNames = null)
  {
    $parent = $this->_columnParent();
    $level  = $this->consistencyLevel();
    $slice  = new SlicePredicate(['column_names' => $columnNames]);
    try
    {
      if(is_array($key))
      {
        return $this->_client()->multiget_count($key, $parent, $slice, $level);
      }
      else
      {
        return $this->_client()->get_count($key, $parent, $slice, $level);
      }
    }
    catch(NotFoundException $e)
    {
      return 0;
    }
    catch(\Exception $e)
    {
      throw $this->formException($e);
    }
  }

  public function multiColumnCount(array $keys, array $columnNames = null)
  {
    return $this->columnCount($keys, $columnNames);
  }

  public function get($key, array $columns)
  {
    $result = null;
    $level  = $this->consistencyLevel();

    if(count($columns) === 1)
    {
      $path         = $this->_columnPath();
      $path->column = head($columns);
      try
      {
        $result = $this->_client()->get($key, $path, $level);
        $result = [$result];
      }
      catch(NotFoundException $e)
      {
        $result = [];
      }
      catch(\Exception $e)
      {
        throw $this->formException($e);
      }
    }
    else
    {
      $parent = $this->_columnParent();
      $slice  = new SlicePredicate(['column_names' => $columns]);
      try
      {
        $result = $this->_client()->get_slice($key, $parent, $slice, $level);
      }
      catch(NotFoundException $e)
      {
        $result = [];
      }
      catch(\Exception $e)
      {
        throw $this->formException($e);
      }
    }
    return $this->_formColumnResult($result);
  }

  public function getSlice(
    $key, $start = '', $finish = '', $reverse = false, $limit = 100
  )
  {
    $result = null;

    $level = $this->consistencyLevel();
    $range = $this->makeSlice($start, $finish, $reverse, $limit);
    $slice = new SlicePredicate(['slice_range' => $range]);

    $parent = $this->_columnParent();
    try
    {
      $result = $this->_client()->get_slice($key, $parent, $slice, $level);
    }
    catch(NotFoundException $e)
    {
      $result = [];
    }
    catch(\Exception $e)
    {
      throw $this->formException($e);
    }
    return $this->_formColumnResult($result);
  }

  public function multiGetSlice(
    array $keys, $start = '', $finish = '', $reverse = false, $limit = 100
  )
  {
    $result = null;

    $level = $this->consistencyLevel();
    $range = $this->makeSlice($start, $finish, $reverse, $limit);
    $slice = new SlicePredicate(['slice_range' => $range]);

    $parent = $this->_columnParent();
    try
    {
      $result = $this->_client()->multiget_slice(
        $keys,
        $parent,
        $slice,
        $level
      );
    }
    catch(NotFoundException $e)
    {
      $result = [];
    }
    catch(\Exception $e)
    {
      throw $this->formException($e);
    }

    $final = [];
    foreach($keys as $key)
    {
      $final[$key] = !isset($result[$key]) ?
      null : $this->_formColumnResult($result[$key]);
    }

    return $final;
  }

  public function makeSlice(
    $start = '', $finish = '', $reverse = false, $limit = 100
  )
  {
    $range           = new SliceRange();
    $range->start    = $start;
    $range->finish   = $finish;
    $range->reversed = $reverse;
    $range->count    = $limit;
    return $range;
  }

  public function makePredicate($of = null)
  {
    if($of instanceof SliceRange)
    {
      return new SlicePredicate(['slice_range' => $of]);
    }
    else if(is_array($of))
    {
      return new SlicePredicate(['column_names' => $of]);
    }
    return $of;
  }

  public function multiGet(array $keys, array $columns = null)
  {
    $result = null;
    $level  = $this->consistencyLevel();
    $parent = $this->_columnParent();
    $slice  = new SlicePredicate(['column_names' => $columns]);

    try
    {
      $result = $this->_client()->multiget_slice(
        $keys,
        $parent,
        $slice,
        $level
      );
    }
    catch(NotFoundException $e)
    {
      $result = [];
    }
    catch(\Exception $e)
    {
      throw $this->formException($e);
    }

    $final = [];
    foreach($keys as $key)
    {
      $final[$key] = !isset($result[$key]) ?
      null : $this->_formColumnResult($result[$key]);
    }

    return $final;
  }

  public function getKeys(
    $start = '', $finish = '', $count = 100, $predicate = null
  )
  {
    if($predicate === null)
    {
      $predicate = new SliceRange(['start' => '', 'finish' => '']);
    }
    $range        = new KeyRange(
      [
      'start_key' => $start,
      'end_key'   => $finish
      ]
    );
    $range->count = $count;

    return $this->_getRangeSlice($range, $predicate);
  }

  public function getTokens(
    $startToken = 0, $finishToken = 0, $count = 100,
    $predicate = null
  )
  {
    if($predicate === null)
    {
      $predicate = new SliceRange(['start' => '', 'finish' => '']);
    }
    $range        = new KeyRange(
      [
      'start_token' => "$startToken",
      'end_token'   => "$finishToken"
      ]
    );
    $range->count = $count;

    return $this->_getRangeSlice($range, $predicate);
  }

  protected function _getRangeSlice(KeyRange $range, $predicate)
  {
    $final  = null;
    $level  = $this->consistencyLevel();
    $parent = $this->_columnParent();

    try
    {
      $result = $this->_client()->get_range_slices(
        $parent,
        $this->makePredicate($predicate),
        $range,
        $level
      );

      if(is_array($result))
      {
        foreach($result as $keySlice)
        {
          if($keySlice instanceof KeySlice)
          {
            $key         = $keySlice->key;
            $final[$key] = $this->_formColumnResult($keySlice->columns);
          }
        }
      }
    }
    catch(NotFoundException $e)
    {
      $final = [];
    }
    catch(\Exception $e)
    {
      throw $this->formException($e);
    }

    return $final;
  }

  public function insert($key, array $columns, $expiry = null)
  {
    $mutationMap = $mutations = [];
    $column      = null;
    $level       = $this->consistencyLevel();
    $parent      = $this->_columnParent();

    foreach($columns as $columnName => $columnValue)
    {
      $column            = new Column();
      $column->name      = $columnName;
      $column->value     = $columnValue;
      $column->ttl       = $expiry;
      $column->timestamp = $this->timestamp();

      $mutations[] = new Mutation(
        [
        'column_or_supercolumn' => new ColumnOrSuperColumn(
          ['column' => $column]
        )
        ]
      );
    }

    if(count($columns) === 1 && $column instanceof Column)
    {
      $this->_client()->insert($key, $parent, $column, $level);
    }
    else
    {
      $mutationMap[$key][$this->name()] = $mutations;
      $this->_client()->batch_mutate($mutationMap, $level);
    }
  }

  public function remove($key, array $columns = null, $timestamp = null)
  {
    $this->_remove($key, null, $columns, $timestamp);
  }

  protected function _remove(
    $keys, $superColumn = null, array $columns = null, $timestamp = null
  )
  {
    if($keys === null)
    {
      return null;
    }

    if(!is_array($keys))
    {
      $keys    = [$keys];
      $numKeys = 1;
    }
    else
    {
      $numKeys = count($keys);
    }

    $level = $this->consistencyLevel();
    $path  = $this->_columnPath();

    if($timestamp === null)
    {
      $timestamp = $this->timestamp();
    }

    if($numKeys == 1 && $columns === null)
    {
      foreach($keys as $key)
      {
        $this->_client()->remove($key, $path, $timestamp, $level);
      }
    }
    else if($numKeys == 1 && count($columns) == 1)
    {
      $path->column = head($columns);
      foreach($keys as $key)
      {
        $this->_client()->remove($key, $path, $timestamp, $level);
      }
    }
    else
    {
      $deletion = new Deletion(['timestamp' => $timestamp]);
      if($superColumn !== null)
      {
        $sc            = 'super_column';
        $deletion->$sc = $superColumn;
      }
      if($columns !== null)
      {
        $deletion->predicate = new SlicePredicate(['column_names' => $columns]);
      }
      $mutations   = [new Mutation(['deletion' => $deletion])];
      $mutationMap = [];

      foreach($keys as $key)
      {
        $mutationMap[$key][$this->name()] = $mutations;
      }
      $this->_client()->batch_mutate($mutationMap, $level);
    }
  }

  public function removeSuper(
    $key, $superColumn, array $columns = null, $timestamp = null
  )
  {
    $this->_remove($key, $superColumn, $columns, $timestamp);
  }

  public function incement($key, $column, $incement = 1)
  {
    $level          = $this->consistencyLevel();
    $parent         = $this->_columnParent();
    $counter        = new CounterColumn();
    $counter->value = abs($incement);
    $counter->name  = $column;
    $this->_client()->add($key, $parent, $counter, $level);
  }

  public function decrement($key, $column, $decrement = 1)
  {
    $level          = $this->consistencyLevel();
    $parent         = $this->_columnParent();
    $counter        = new CounterColumn();
    $counter->value = abs($decrement) * -1;
    $counter->name  = $column;
    $this->_client()->add($key, $parent, $counter, $level);
  }

  public function removeCounter($key, $column)
  {
    $level        = $this->consistencyLevel();
    $path         = $this->_columnPath();
    $path->column = $column;
    $this->_client()->remove_counter($key, $path, $level);
  }

  public function runQuery($query, $compression = Compression::NONE)
  {
    if($this->cqlVersion() === 3)
    {
      $result = $this->_client()->execute_cql3_query(
        $query,
        $compression,
        $this->consistencyLevel()
      );
    }
    else
    {
      $result = $this->_client()->execute_cql_query($query, $compression);
    }
    return $result;
  }

  protected function _formColumnResult($result)
  {
    if($result === null)
    {
      return $result;
    }

    if(is_array($result))
    {
      $final = [];
      foreach($result as $col)
      {
        $col = $this->_formColumn($col);
        if($this->returnAttribute())
        {
          $final[$col->name()] = $col;
        }
        else
        {
          $final[$col[0]] = $col[1];
        }
      }
      return $final;
    }
    else if($result instanceof ColumnOrSuperColumn)
    {
      return $this->_formColumn($result);
    }
    else
    {
      return $result;
    }
  }

  protected function _formColumn(ColumnOrSuperColumn $input)
  {
    $column     = null;
    $counterCol = 'counter_column';
    $superCol   = 'super_column';

    if($input->column instanceof Column)
    {
      if($this->returnAttribute())
      {
        $column = new ColumnAttribute($input->column->name);
        $column->setData($input->column->value);
        $column->setUpdatedTime($input->column->timestamp);
        $column->setExpiry($input->column->ttl);
      }
      else
      {
        return [$input->column->name, $input->column->value];
      }
    }
    else if($input->$counterCol instanceof CounterColumn)
    {
      if($this->returnAttribute())
      {
        $column = new ColumnAttribute($input->$counterCol->name);
        $column->setData($input->$counterCol->value);
        $column->setIsCounter();
      }
      else
      {
        return [$input->$counterCol->name, $input->$counterCol->value];
      }
    }
    else if($input->$superCol instanceof SuperColumn)
    {
      $column = new ColumnAttribute($input->$superCol->name);
      $cols   = [];
      foreach($input->$superCol->columns as $col)
      {
        if($this->returnAttribute())
        {
          $subCol = new ColumnAttribute($col->name);
          $subCol->setData($col->value);
          $subCol->setUpdatedTime($col->timestamp);
          $subCol->setExpiry($col->ttl);

          $cols[$col->name] = $subCol;
        }
        else
        {
          $cols[$col->name] = $col->value;
        }
      }

      if($this->returnAttribute())
      {
        $column->setData($cols);
        $column->setIsSuper();
      }
      else
      {
        return [$input->$superCol->name, $cols];
      }
    }

    return $column;
  }

  public function timestamp()
  {
    $parts   = explode(" ", (string)microtime());
    $subSecs = preg_replace('/0./', '', $parts[0], 1);
    return ($parts[1] . $subSecs) / 100;
  }

  public function formException(\Exception $e)
  {
    try
    {
      throw $e;
    }
    catch(NotFoundException $e)
    {
      return new CassandraException(
        "A specific column was requested that does not exist.", 404, $e
      );
    }
    catch(InvalidRequestException $e)
    {
      return new CassandraException(
        "Invalid request could mean keyspace or column family does not exist," .
        " required parameters are missing, or a parameter is malformed. " .
        "why contains an associated error message.", 400, $e
      );
    }
    catch(UnavailableException $e)
    {
      return new CassandraException(
        "Not all the replicas required could be created and/or read", 503, $e
      );
    }
    catch(TimedOutException $e)
    {
      return new CassandraException(
        "The node responsible for the write or read did not respond during" .
        " the rpc interval specified in your configuration (default 10s)." .
        " This can happen if the request is too large, the node is" .
        " oversaturated with requests, or the node is down but the failure" .
        " detector has not yet realized it (usually this takes < 30s).",
        408, $e
      );
    }
    catch(TApplicationException $e)
    {
      return new CassandraException(
        "Internal server error or invalid Thrift method (possible if " .
        "you are using an older version of a Thrift client with a " .
        "newer build of the Cassandra server).", 500, $e
      );
    }
    catch(AuthenticationException $e)
    {
      return new CassandraException(
        "Invalid authentication request " .
        "(user does not exist or credentials invalid)", 401, $e
      );
    }
    catch(AuthorizationException $e)
    {
      return new CassandraException(
        "Invalid authorization request (user does not have access to keyspace)",
        403, $e
      );
    }
    catch(SchemaDisagreementException $e)
    {
      return new CassandraException(
        "Schemas are not in agreement across all nodes", 500, $e
      );
    }
    catch(\Exception $e)
    {
      return new CassandraException($e->getMessage(), $e->getCode(), $e);
    }
  }
}
