<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Helpers;

class Strings
{
  public static function camelWords($string)
  {
    return preg_replace(
      "/(([a-z])([A-Z])|([A-Z])([A-Z][a-z]))/",
      "\\2\\4 \\3\\5",
      $string
    );
  }

  public static function underWords($string)
  {
    return str_replace('_', ' ', $string);
  }

  public static function variableToUnderScore($variable)
  {
    $variable = self::camelWords($variable);
    $variable = str_replace(' ', '_', $variable);
    $variable = strtolower($variable);
    return $variable;
  }

  public static function variableToCamelCase($variable)
  {
    $variable = self::variableToPascalCase($variable);
    $variable = lcfirst($variable);
    return $variable;
  }

  public static function variableToPascalCase($variable)
  {
    $variable = self::camelWords($variable);
    $variable = self::underWords($variable);
    $variable = strtolower($variable);
    $variable = ucwords($variable);
    $variable = str_replace(' ', '', $variable);
    return $variable;
  }

  public static function titleize($title)
  {
    return ucwords(static::humanize($title));
  }

  public static function humanize($string, $splitOnCamel = true)
  {
    if($splitOnCamel)
    {
      $string = static::variableToUnderScore($string);
    }
    $string       = preg_replace('/_id$/', "", $string);
    $replacements = [
      "-" => ' ',
      "_" => ' ',
    ];
    return ucfirst(strtr($string, $replacements));
  }

  public static function hyphenate($string)
  {
    $replacements = [
      " " => '-',
      "_" => '-',
    ];
    return strtr($string, $replacements);
  }

  public static function urlize($url)
  {
    return strtolower(static::hyphenate($url));
  }

  public static function docCommentLines($comment)
  {
    $comments = [];
    $comment  = substr($comment, 3, -2);
    foreach(explode("\n", $comment) as $comment)
    {
      $comment = trim(ltrim(trim($comment), '*'));
      if(!empty($comment))
      {
        $comments[] = $comment;
      }
    }
    return $comments;
  }

  public static function stringToRange($string)
  {
    preg_match_all("/([0-9]{1,2})-?([0-9]{0,2}) ?,?;?/", $string, $match);
    $n = array();
    foreach($match[1] as $k => $v)
    {
      $n = array_merge(
        $n,
        range($v, (empty($match[2][$k]) ? $v : $match[2][$k]))
      );
    }
    return ($n);
  }
}
