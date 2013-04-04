<?php
/**
 * @author  brooke.bryan
 */
namespace Cubex\I18n\Format;

use Cubex\I18n\Locale;

class AbstractFormat
{
  protected static function _getLocale()
  {
    $l = new Locale();
    return $l->getLocale();
  }

  protected static function _getTimezone()
  {
    $l = new Locale();
    return $l->getTimezone();
  }
}
