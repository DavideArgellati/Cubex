<?php
/**
 * @author: brooke.bryan
 * @author: gareth.evans
 */
namespace Cubex\Cli;

/**
 * Basic Shell improvements
 */
use Cubex\Data\Ephemeral\EphemeralCache;
use Cubex\Data\Ephemeral\ExpiringEphemeralCache;
use Cubex\Helpers\System;

class Shell
{
  const COLOUR_FOREGROUND_BLACK        = '0;30';
  const COLOUR_FOREGROUND_DARK_GREY    = '1;30';
  const COLOUR_FOREGROUND_BLUE         = '0;34';
  const COLOUR_FOREGROUND_LIGHT_BLUE   = '1;34';
  const COLOUR_FOREGROUND_GREEN        = '0;32';
  const COLOUR_FOREGROUND_LIGHT_GREEN  = '1;32';
  const COLOUR_FOREGROUND_CYAN         = '0;36';
  const COLOUR_FOREGROUND_LIGHT_CYAN   = '1;36';
  const COLOUR_FOREGROUND_RED          = '0;31';
  const COLOUR_FOREGROUND_LIGHT_RED    = '1;31';
  const COLOUR_FOREGROUND_PURPLE       = '0;35';
  const COLOUR_FOREGROUND_LIGHT_PURPLE = '1;35';
  const COLOUR_FOREGROUND_BROWN        = '0;33';
  const COLOUR_FOREGROUND_YELLOW       = '1;33';
  const COLOUR_FOREGROUND_LIGHT_GREY   = '0;37';
  const COLOUR_FOREGROUND_WHITE        = '1;37';

  const COLOUR_BACKGROUND_BLACK      = '40';
  const COLOUR_BACKGROUND_RED        = '41';
  const COLOUR_BACKGROUND_GREEN      = '42';
  const COLOUR_BACKGROUND_YELLOW     = '43';
  const COLOUR_BACKGROUND_BLUE       = '44';
  const COLOUR_BACKGROUND_MAGENTA    = '45';
  const COLOUR_BACKGROUND_CYAN       = '46';
  const COLOUR_BACKGROUND_LIGHT_GREY = '47';

  protected static $_foregroundColour;
  protected static $_backgroundColour;

  /**
   * Set forground colour for all shell output
   *
   * @param string $colour
   */
  public static function setForeground($colour = self::COLOUR_FOREGROUND_WHITE)
  {
    self::$_foregroundColour = $colour;
  }

  /**
   * Set background colour for all shell output
   *
   * @param string $colour
   */
  public static function setBackground($colour = self::COLOUR_BACKGROUND_BLACK)
  {
    self::$_backgroundColour = $colour;
  }

  public static function clearForeground()
  {
    self::$_foregroundColour = null;
  }

  public static function clearBackground()
  {
    self::$_backgroundColour = null;
  }

  /**
   * output coloured text based on the defined colours
   *
   * @param $string
   *
   * @return string
   */
  public static function colouredText($string)
  {
    if(!self::supportsColor())
    {
      return $string;
    }

    $colourString = '';

    if(self::$_foregroundColour !== null)
    {
      $colourString .= "\033[" . self::$_foregroundColour . "m";
    }

    if(self::$_backgroundColour !== null)
    {
      $colourString .= "\033[" . self::$_backgroundColour . "m";
    }

    return $colourString . $string . "\033[0m";
  }

  /**
   * Output specific coloured text
   *
   * @param             $string
   * @param null|string $foreground
   * @param null|string $background
   *
   * @return string
   */
  public static function colourText(
    $string, $foreground = null,
    $background = null
  )
  {
    $existingForeground = self::$_foregroundColour;
    $existingBackground = self::$_backgroundColour;

    self::clearBackground();
    self::clearForeground();

    self::setForeground($foreground);
    self::setBackground($background);

    $coloured = self::colouredText($string);

    self::setForeground($existingForeground);
    self::setBackground($existingBackground);

    return $coloured;
  }

  /**
   * Returns the number of columns the current shell has for display.
   *
   * @return int  The number of columns.
   *
   * @param int $default
   *
   */
  public static function columns($default = 80)
  {
    if(self::isPiped())
    {
      return $default;
    }

    $cacheKey = 'columns';
    $cols = ExpiringEphemeralCache::getCache($cacheKey, __CLASS__, null);

    if($cols === null)
    {
      $tputPath = self::findCommand("tput");
      if($tputPath)
      {
        $cols = (int)trim(exec('"' . $tputPath . '" cols', $x, $ret));
        if($ret != 0)
        {
          self::_setCommandBroken("tput");
        }
      }

      if($cols < 1 && System::isWindows())
      {
        $cols = static::_windowsColumns();
      }

      if($cols === null)
      {
        $cols = $default;
      }

      ExpiringEphemeralCache::storeCache($cacheKey, $cols, __CLASS__, 2);
    }

    return $cols;
  }

  /**
   * Find the number of colums on a windows shell.
   *
   * @return int
   */
  protected static function _windowsColumns()
  {
    $columns = null;

    $path = self::findCommand("mode");
    if($path)
    {
      exec($path, $output);

      if(is_array($output))
      {
        foreach($output as $line)
        {
          if(substr(trim($line), 0, 8) === "Columns:")
          {
            $matched = preg_match(
              "/^\s*Columns\:\s*(\d+)\s*$/",
              $line,
              $matches
            );

            if($matched)
            {
              $columns = (int)$matches[1];
              break;
            }
          }
        }
      }
    }

    return $columns;
  }

  /**
   * Checks whether the output of the current script
   * is a TTY or a pipe / redirect
   *
   * @return bool Output being piped
   */
  public static function isPiped()
  {
    return (function_exists('posix_isatty') && !posix_isatty(STDOUT));
  }

  /**
   * Clear screen
   */
  public static function clear()
  {
    echo "\033[2J\033[;H";
  }

  /**
   * Clear the current line
   *
   * @param bool $cursorToStart If true then return the cursor to
   *                            the start of the line,
   *                            otherwise leave it where it is
   */
  public static function clearLine($cursorToStart = true)
  {
    echo "\033[2K";
    if($cursorToStart)
    {
      echo "\r";
    }
  }

  /**
   * Clear from the cursor the end of the current line
   */
  public static function clearToEol()
  {
    echo "\033[K";
  }

  /**
   * Clear from the cursor to the end of the screen
   */
  public static function clearToEndOfScreen()
  {
    echo "\033[J";
  }

  /**
   * Determine if the current CLI Shell can support color. This may need
   * updating and is likely not accurate. More importantly there may be ways
   * around to support color for these but they've not been implemented here
   * yet.
   *
   * @return bool
   */
  public static function supportsColor()
  {
    if(isset($_SERVER["TERM"]) && $_SERVER["TERM"] === "cygwin")
    {
      return false;
    }
    else if(isset($_SERVER["PROMPT"]) && $_SERVER["PROMPT"] === "\$P\$G")
    {
      return false;
    }

    return true;
  }

  /**
   * Set the cursor position - 1,1 is top left
   *
   * @param int $row
   * @param int $col
   */
  public static function setCursorPos($row, $col)
  {
    echo "\033[" . $row . ';' . $col . 'H';
  }

  /**
   * Move the cursor to the top left of the window
   */
  public static function home()
  {
    self::setCursorPos(1, 1);
  }

  /**
   * Hide the cursor
   */
  public static function hideCursor()
  {
    echo "\033[?25l";
  }

  /**
   * Show the cursor
   */
  public static function showCursor()
  {
    echo "\033[?12l\033[?25h";
  }

  /**
   * Redraw the screen without clearing it first to avoid flicker
   *
   * @param string $newContent
   */
  public static function redrawScreen($newContent)
  {
    self::home();
    echo str_replace("\n", "\033[K\n", $newContent);
    self::clearToEndOfScreen();
  }

  public static function commandExists($cmd)
  {
    return self::findCommand($cmd) ? true : false;
  }

  public static function findCommand($cmd)
  {
    $cacheKey = 'findCommand:' . $cmd;
    $path = EphemeralCache::getCache($cacheKey, __CLASS__, null);
    if($path === null)
    {
      $retval = -1;
      $searchCmd = System::isWindows() ? 'where' : 'which';
      exec(sprintf('%s "%s" 2>/dev/null', $searchCmd, $cmd), $output, $retval);
      if($retval === 0)
      {
        $path = $output[0];
      }
      EphemeralCache::storeCache($cacheKey, $path, __CLASS__);
    }
    return $path;
  }

  /**
   * Flag a command as not existing so we don't try to use it if it's broken
   *
   * @param $cmd
   */
  private static function _setCommandBroken($cmd)
  {
    EphemeralCache::storeCache('findCommand:' . $cmd, false, __CLASS__);
  }
}

