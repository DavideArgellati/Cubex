<?php
/**
 * @author  Richard.Gooding
 */

namespace Cubex\Cli;

use Cubex\Foundation\Config\ConfigTrait;
use Cubex\Loader;

abstract class CliCommand implements CliTask
{
  use ConfigTrait;

  /**
   * @var Loader
   */
  protected $_loader;
  /**
   * @var \string[]
   */
  protected $_rawArgs;
  /**
   * @var CliArgument[]
   */
  protected $_options;
  /**
   * @var PositionalArgument[]
   */
  protected $_positionalArgs;
  /**
   * @var CliArgument[]
   */
  protected $_argsByName;
  /**
   * @var CliArgument[]
   */
  protected $_argsByShortName;
  /**
   * @var string[]
   */
  protected $_rawPositionalArgs;

  /**
   * @param Loader   $loader
   * @param string[] $rawArgs
   */
  public function __construct($loader, $rawArgs)
  {
    $this->_loader  = $loader;
    $this->_rawArgs = $rawArgs;

    $this->_argsByName      = [];
    $this->_argsByShortName = [];

    $this->_initArguments();

    if(in_array('--help', $this->_rawArgs))
    {
      $this->_help();
      die();
    }

    try
    {
      $this->_parseArguments($this->_rawArgs);
    }
    catch(ArgumentException $e)
    {
      echo "\nERROR: " . $e->getMessage() . "\n";
      $this->_help();
      die();
    }
  }

  /**
   * Perform initialisation operations for this command
   */
  public function init()
  {
  }

  protected function _initArguments()
  {
    $args = $this->_argumentsList();

    $seenArrayArg = false;
    foreach($args as $arg)
    {
      if($arg instanceof PositionalArgument)
      {
        if($seenArrayArg)
        {
          throw new \Exception(
            'No more positional arguments can be specified after an ArrayArgument'
          );
        }

        $this->_positionalArgs[] = $arg;

        if($arg instanceof ArrayArgument)
        {
          $seenArrayArg = true;
        }
      }
      else
      {
        $this->_options[] = $arg;
        if($arg->hasShortName())
        {
          $this->_argsByShortName[$arg->shortName] = $arg;
        }
      }

      $this->_argsByName[$arg->name] = $arg;
    }
  }

  /**
   * Return the list of command-line options known by this command
   *
   * @return CliArgument[]
   */
  protected function _argumentsList()
  {
    return [];
  }

  /**
   * Display usage information. Invoked automatically if --help is specified on the command-line
   */
  protected function _help()
  {
    $usage = "Usage: " . $_REQUEST['__path__'];
    if(count($this->_options) > 0)
    {
      $usage .= " [option]...";
    }

    if(count($this->_positionalArgs) > 0)
    {
      foreach($this->_positionalArgs as $arg)
      {
        if($arg->required)
        {
          $usage .= " " . $arg->name;
        }
        else
        {
          $usage .= " [" . $arg->name . "]";
        }
        if($arg instanceof ArrayArgument)
        {
          $usage .= "...";
        }
      }
    }

    echo "\n" . $usage . "\n\n";

    foreach($this->_options as $arg)
    {
      $this->_showHelpArg($arg);
    }
  }

  private function _showHelpArg(CliArgument $arg)
  {
    $labelWidth       = 30;
    $descriptionWidth = 50;

    $text = "  ";
    if($arg->hasShortName())
    {
      $text .= '-' . $arg->shortName . ', ';
    }
    else
    {
      $text .= '    ';
    }

    $text .= '--' . $arg->longName;
    if($arg->valueOption == CliArgument::VALUE_REQUIRED)
    {
      $text .= '=' . $arg->valueDescription;
    }
    else if($arg->valueOption == CliArgument::VALUE_OPTIONAL)
    {
      $text .= '[=' . $arg->valueDescription . ']';
    }

    if(strlen($text) < $labelWidth)
    {
      $text = str_pad($text, $labelWidth, " ", STR_PAD_RIGHT);
    }
    else
    {
      $text .= "\n" . str_repeat(" ", $labelWidth);
    }

    $text .= wordwrap(
      $arg->description,
      $descriptionWidth,
      "\n" . str_repeat(" ", $labelWidth)
    );

    echo $text . "\n";
  }

  /**
   * Parse the raw command-line arguments.
   * Override this to customise how arguments are parsed.
   *
   * @param string[] $args The raw arguments passed to the command
   *
   * @throws ArgumentException
   */
  protected function _parseArguments($args)
  {
    // skip the script name argument
    array_shift($args);

    // Build arrays of option_name => value_option
    $shortOpts = [];
    $longOpts  = [];
    foreach($this->_options as $argObj)
    {
      $longOpts[$argObj->longName] = $argObj->valueOption;
      if($argObj->hasShortName())
      {
        $shortOpts[$argObj->shortName] = $argObj->valueOption;
      }
    }

    // First split up the arguments to allow for different syntax etc.
    $maxPositionalArg = count($this->_positionalArgs) - 1;
    $positionalArgNum = 0;
    while(count($args) > 0)
    {
      $argStr = array_shift($args);
      $len    = strlen($argStr);

      if(($len > 1) && ($argStr[0] == '-'))
      {
        $eqPos = strpos($argStr, '=');
        $value = false;
        if($eqPos)
        {
          $argName = substr($argStr, 0, $eqPos);
          $value   = substr($argStr, $eqPos + 1);
        }
        else
        {
          $argName = $argStr;
        }
        $argName = ltrim($argName, '-');

        if(($len > 2) && ($argStr[1] == '-'))
        {
          // It's a long option
          if(isset($longOpts[$argName]))
          {
            if($longOpts[$argName] != CliArgument::VALUE_NONE)
            {
              if($value !== false)
              {
                $thisValue = $value;
              }
              else if(count($args) > 0)
              {
                $thisValue = array_shift($args);
              }
              else if($longOpts[$argName] == CliArgument::VALUE_OPTIONAL)
              {
                $thisValue = true;
              }
              else
              {
                throw new ArgumentException('Argument --' . $argName . ' needs a value');
              }
            }
            else
            {
              $thisValue = true;
            }

            $argObj = $this->_getArgObjByName($argName, true);
            $argObj->setData($thisValue);
          }
          else
          {
            throw new ArgumentException('Unknown argument: --' . $argName);
          }
        }
        else
        {
          // It's a short option or set of short options
          while(strlen($argName) > 0)
          {
            $thisArg = $argName[0];
            if(isset($shortOpts[$thisArg]))
            {
              $thisValue = false;
              if($shortOpts[$thisArg] != CliArgument::VALUE_NONE)
              {
                // this argument can take a value
                if(strlen($argName) > 1)
                {
                  $thisValue = substr($argName, 1);
                  $argName   = "";
                }
                else if($value !== false)
                {
                  $thisValue = $value;
                }
                else if(count($args) > 0)
                {
                  $thisValue = array_shift($args);
                }

                if($thisValue === false)
                {
                  if($shortOpts[$thisArg] == CliArgument::VALUE_OPTIONAL)
                  {
                    $thisValue = true;
                  }
                  else
                  {
                    throw new ArgumentException('Argument -' . $thisArg . ' needs a value');
                  }
                }
              }
              else
              {
                $thisValue = true;
              }

              // trim this option from the start of the string
              if($argName != "")
              {
                $argName = substr($argName, 1);
              }

              $argObj = $this->_getArgObjByName(
                $thisArg,
                false
              );
              $argObj->setData($thisValue);
            }
            else
            {
              throw new ArgumentException('Unknown argument: -' . $thisArg);
            }
          }
        }
      }
      else
      {
        $this->_rawPositionalArgs[] = $argStr;

        if(($maxPositionalArg > -1) && ($positionalArgNum <= $maxPositionalArg))
        {
          $argObj = $this->_positionalArgs[$positionalArgNum];

          if($argObj instanceof ArrayArgument)
          {
            $argObj->addData($argStr);
          }
          else
          {
            $argObj->setData($argStr);
            $positionalArgNum++;
          }
        }
      }
    }

    // Check for missing required arguments
    $missingArgs = [];
    foreach($this->_options as $argObj)
    {
      if($argObj->required && (!$argObj->hasData()))
      {
        $missingArgs[] = '--' . $argObj->name;
      }
    }

    foreach($this->_positionalArgs as $argObj)
    {
      if($argObj->required && (!$argObj->hasData()))
      {
        $missingArgs[] = $argObj->name;
      }
    }

    if(count($missingArgs) > 0)
    {
      throw new ArgumentException(
        'The following arguments are required: ' . implode(", ", $missingArgs)
      );
    }
  }

  /**
   * @param $name
   * @param $isLongName
   *
   * @return CliArgument|null
   */
  protected function _getArgObjByName($name, $isLongName = true)
  {
    if($isLongName && isset($this->_argsByName[$name]))
    {
      return $this->_argsByName[$name];
    }
    else if((!$isLongName) && isset($this->_argsByShortName[$name]))
    {
      return $this->_argsByShortName[$name];
    }

    return null;
  }

  /**
   * Check if a command-line argument was provided
   *
   * @param string $longArgName The long name of the argument
   *
   * @return bool
   */
  public function argumentIsSet($longArgName)
  {
    return $this->_getArgObjByName($longArgName, true)->hasData();
  }

  /**
   * Get the value of a command-line argument. Returns null if the argument does not exist.
   *
   * @param string $longArgName
   * @param mixed  $default The default value to return if the argument is
   *                        not set - overrides the argument's own default
   *
   * @return string|null
   */
  public function argumentValue($longArgName, $default = null)
  {
    if(is_numeric($longArgName))
    {
      return $this->positionalArgValue($longArgName, $default);
    }

    $argObj = $this->_getArgObjByName($longArgName, true);
    if($argObj->hasData())
    {
      return $argObj->getData();
    }
    else
    {
      return $default === null ? $argObj->defaultValue : $default;
    }
  }

  /**
   * @param int   $argNum
   * @param mixed $default
   *
   * @return mixed
   */
  public function positionalArgValue($argNum, $default = null)
  {
    return isset($this->_rawPositionalArgs[$argNum]) ? $this->_rawPositionalArgs[$argNum] : $default;
  }

  /**
   * @return int
   */
  public function positionalArgCount()
  {
    return count($this->_rawPositionalArgs);
  }
}