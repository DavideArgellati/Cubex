<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Database;

use Cubex\ServiceManager\Service;

interface DatabaseService extends Service
{

  /**
   * @param string $mode Either 'r' (reading) or 'w' (reading and writing)
   */
  public function connect($mode = 'w');

  /**
   * Disconnect from the connection
   *
   * @return mixed
   */
  public function disconnect();

  /**
   * Run a standard query
   *
   * @param $query
   *
   * @return mixed
   */
  public function query($query);

  /**
   * Get a single field
   *
   * @param $query
   *
   * @return mixed
   */
  public function getField($query);

  /**
   * Get a single row
   *
   * @param $query
   *
   * @return mixed
   */
  public function getRow($query);

  /**
   * Get multiple rows
   *
   * @param $query
   *
   * @return mixed
   */
  public function getRows($query);

  /**
   * Get a keyed array based on the first field of the result
   *
   * @param $query
   *
   * @return mixed
   */
  public function getKeyedRows($query);

  /**
   * Number of rows for a query
   *
   * @param $query
   *
   * @return mixed
   */
  public function numRows($query);

  /**
   * Get column names
   *
   * @param $query
   *
   * @return mixed
   */
  public function getColumns($query);


  /**
   * Escape column name
   *
   * @param $column
   *
   * @return mixed
   */
  public function escapeColumnName($column);

  /**
   * Escape string value for insert
   *
   * @param $string
   *
   * @return mixed
   */
  public function escapeString($string);
}
