<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Cassandra\DataType;

class IntegerType extends CassandraType
{
  public function pack($value)
  {
    $out = array();
    if($value >= 0)
    {
      while($value >= 256)
      {
        $out[] = pack('C', 0xff & $value);
        $value >>= 8;
      }
      $out[] = pack('C', 0xff & $value);
      if($value > 127)
      {
        $out[] = chr('00');
      }
    }
    else
    {
      $value = -1 - $value;
      while($value >= 256)
      {
        $out[] = pack('C', 0xff & ~$value);
        $value >>= 8;
      }
      if($value <= 127)
      {
        $out[] = pack('C', 0xff & ~$value);
      }
      else
      {
        $out[] = pack('n', 0xffff & ~$value);
      }
    }

    return strrev(implode($out));
  }

  public function unpack($data)
  {
    $val = hexdec(bin2hex($data));
    if((ord($data[0]) & 128) != 0)
    {
      $val = $val - (1 << (strlen($data) * 8));
    }
    return $val;
  }
}
