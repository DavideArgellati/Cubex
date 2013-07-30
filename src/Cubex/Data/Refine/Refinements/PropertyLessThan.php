<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Data\Refine\Refinements;

class PropertyLessThan extends AbstractProperty
{
  protected function _validate($data, $match)
  {
    return $data < $match;
  }
}
