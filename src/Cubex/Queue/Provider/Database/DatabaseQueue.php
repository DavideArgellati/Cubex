<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Queue\Provider\Database;

use Cubex\Helpers\Strings;
use Cubex\Mapper\Database\RecordCollection;
use Cubex\Queue\Queue;
use Cubex\Queue\QueueConsumer;
use Cubex\Queue\QueueProvider;
use Cubex\ServiceManager\ServiceConfigTrait;
use Cubex\Sprintf\ParseQuery;

class DatabaseQueue implements QueueProvider
{
  use ServiceConfigTrait;

  protected $_map;

  public function push(Queue $queue, $data = null)
  {
    $mapper            = $this->_queueMapper(true);
    $mapper->queueName = $queue->name();
    $mapper->data      = $data;
    $mapper->saveChanges();
  }

  public function consume(Queue $queue, QueueConsumer $consumer)
  {
    $maxAttempts = $this->config()->getInt("max_attempts", 3);
    $ownkey      = \Cubex\FileSystem\FileSystem::readRandomCharacters(30);
    $waits       = 0;

    while(true)
    {
      $mapper     = $this->_queueMapper(true);
      $collection = new RecordCollection($mapper);

      $collection->runQuery(
        "UPDATE %T SET %C = %d, %C = %s WHERE %C = %d LIMIT 1",
        $mapper->getTableName(),
        'locked',
        1,
        'locked_by',
        $ownkey,
        'locked',
        0
      );

      $mapper = $collection->loadOneWhere(
        ['locked' => 1, 'locked_by' => $ownkey]
      );

      if($mapper === null)
      {
        $waitTime = $consumer->waitTime($waits);
        if($waitTime === false)
        {
          break;
        }
        else if($waitTime > 0)
        {
          $waits++;
          sleep($waitTime);
        }
      }
      else
      {
        $waits  = 0;
        $result = $consumer->process($queue, $mapper->data);
        if($result || $mapper->attempts > $maxAttempts)
        {
          $mapper->delete();
        }
        else
        {
          $mapper->locked   = 0;
          $mapper->lockedBy = '';
          $mapper->attempts++;
          $mapper->saveChanges();
        }
      }
    }
    $consumer->shutdown();
  }

  protected function _queueMapper()
  {
    $this->_map = new QueueMapper();
    $this->_map->setTableName(
      $this->config()->getStr("table_name", "cubex_queue")
    );
    $this->_map->setServiceName(
      $this->config()->getStr("db_service", "db")
    );
    return $this->_map;
  }
}
