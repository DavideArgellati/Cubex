<?php
/**
 * @author  Richard.Gooding
 */

namespace Cubex\Cli;

use Cubex\Data\DocBlock\DocBlockParser;
use Cubex\Data\DocBlock\IDocBlockAware;
use Cubex\Data\Filter\Filter;
use Cubex\Data\Validator\Validator;
use Cubex\Foundation\Config\ConfigTrait;
use Cubex\Loader;
use Psr\Log\LogLevel;

abstract class CliCommand implements ICliTask, IDocBlockAware
{
  use ConfigTrait;

  /**
   * Automatically log to screen
   */
  protected $_autoLog = true;
  protected $_echoLevel = LogLevel::ERROR;
  protected $_defaultLogLevel = LogLevel::EMERGENCY;
  /**
   * @var CliLogger
   */
  protected $_logger;
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
  protected $_options = [];
  /**
   * @var PositionalArgument[]
   */
  protected $_positionalArgs = [];
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
  protected $_rawPositionalArgs = [];

  /**
   * @var CliArgument[]
   */
  protected $_args = [];

  protected $_publicMethods;

  protected $_comment;

  /**
   * @param Loader   $loader
   * @param string[] $rawArgs
   */
  public function __construct($loader, $rawArgs)
  {
    (new DocBlockParser($this))->parse();

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

    if($this->argumentValue('disable-logging'))
    {
      $this->_autoLog = false;
    }

    /**
     * If you wish to log to a file, or use CLI logger in a more detailed way,
     * Set _autoLog to false and initiate the CliLogger within your class
     */
    if($this->_autoLog)
    {
      $this->_logger = new CliLogger(
        $this->_echoLevel, $this->_defaultLogLevel
      );
    }

    if($this->_autoLog && $this->argumentIsSet('log-level'))
    {
      $this->_logger->setLogLevel($this->argumentValue('log-level'));
    }

    if($this->_autoLog && $this->argumentIsSet('echo-level'))
    {
      $this->_echoLevel = $this->argumentValue('echo-level');
      $this->_logger->setEchoLevel($this->_echoLevel);
    }
  }

  /**
   * Perform initialisation operations for this command
   */
  public function init()
  {
  }

  public function methodCallArgs()
  {
    return (array)$this->_rawPositionalArgs;
  }

  protected function _compiledArguments()
  {
    return $this->_args;
  }

  protected function _initArguments()
  {
    $this->_buildArguments();
    $args = $this->_argumentsList();

    $seenArrayArg = false;
    foreach($args as $arg)
    {
      if($arg instanceof PositionalArgument)
      {
        if($seenArrayArg)
        {
          throw new \Exception(
            'No more positional arguments can be specified' .
            ' after an ArrayArgument'
          );
        }

        $this->_positionalArgs[] = $arg;

        if($arg instanceof ArrayArgument)
        {
          $seenArrayArg = true;
        }
      }

      if(isset($this->_argsByName[$arg->name]))
      {
        throw new \Exception(
          'Argument name used for more than one argument: ' . $arg->name
        );
      }
      $this->_argsByName[$arg->name] = $arg;
    }

    $this->_configure();

    foreach($args as $arg)
    {
      if($arg instanceof CliArgument)
      {
        $this->_options[] = $arg;
        if($arg->hasShortName())
        {
          if(isset($this->_argsByShortName[$arg->shortName]))
          {
            throw new \Exception(
              'Short argument name used for more than one argument: -' .
              $arg->shortName
            );
          }
          $this->_argsByShortName[$arg->shortName] = $arg;
        }
      }
    }

    static::_addDisableLoggingArgIfRequired();
    static::_addEchoLevelArgIfRequired();
    static::_addLogLevelArgIfRequired();
  }

  protected function _configure()
  {
  }

  protected function _addDisableLoggingArgIfRequired()
  {
    if(!isset($this->_argsByName['disable-logging']))
    {
      $arg = new CliArgument(
        'disable-logging', 'Disable all logging', '',
        CliArgument::VALUE_NONE, '', false, false
      );

      $this->_options[]              = $arg;
      $this->_argsByName[$arg->name] = $arg;
    }
  }

  protected function _addLogLevelArgIfRequired()
  {
    if(!isset($this->_argsByName['log-level']))
    {
      $arg = new CliArgument(
        'log-level', 'Set the logging level', '',
        CliArgument::VALUE_REQUIRED, 'level'
      );

      $arg->addFilter(Filter::FILTER_LOWER);
      $arg->addFilter(Filter::FILTER_TRIM);
      $arg->addValidator(Validator::VALIDATE_CONST, [new LogLevel]);
      $this->_options[]              = $arg;
      $this->_argsByName[$arg->name] = $arg;
    }
  }

  protected function _addEchoLevelArgIfRequired()
  {
    if(!isset($this->_argsByName['echo-level']))
    {
      $arg = new CliArgument(
        'echo-level', 'Set the output logging level', '',
        CliArgument::VALUE_REQUIRED, 'level'
      );

      $arg->addFilter(Filter::FILTER_LOWER);
      $arg->addFilter(Filter::FILTER_TRIM);
      $arg->addValidator(Validator::VALIDATE_CONST, [new LogLevel]);
      $this->_options[]              = $arg;
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
    return $this->_compiledArguments();
  }

  /**
   * Display usage information.
   * Invoked automatically if --help is specified on the command-line
   */
  protected function _help()
  {
    if($this->_comment !== null)
    {
      echo $this->_comment . "\n";
    }

    $usage = "Usage: " . $_REQUEST['__path__'];
    if(count($this->_publicMethods) > 0)
    {
      $usage .= "[:method]";
    }
    if(count($this->_options) > 0)
    {
      $usage .= " [option]...";
    }

    if(count($this->_publicMethods) > 0)
    {
      $usage .= "\n\nAvailable Methods: ";
      $usage .= implode(', ', $this->_publicMethods);
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

    // are there any short arguments?
    $hasShortArgs = false;
    foreach($this->_options as $arg)
    {
      if($arg->hasShortName())
      {
        $hasShortArgs = true;
        break;
      }
    }

    foreach($this->_options as $arg)
    {
      $this->_showHelpArg($arg, $hasShortArgs);
    }
  }

  private function _showHelpArg(CliArgument $arg, $showShortArg = true)
  {
    $screenWidth      = Shell::columns();
    $labelWidth       = 30;
    $descriptionWidth = ($screenWidth - $labelWidth) - 1;

    $text = "  ";
    if($showShortArg)
    {
      if($arg->hasShortName())
      {
        $text .= '-' . $arg->shortName . ', ';
      }
      else
      {
        $text .= '    ';
      }
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
      ("\n" . str_repeat(" ", $labelWidth))
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
                throw new ArgumentException(
                  'Argument --' . $argName . ' needs a value'
                );
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
                    throw new ArgumentException(
                      'Argument -' . $thisArg . ' needs a value'
                    );
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

    // Check for conflicting arguments
    foreach($this->_options as $argObj)
    {
      if($argObj->hasData())
      {
        foreach($argObj->conflictingArgs as $otherArg)
        {
          if($this->argumentIsSet($otherArg))
          {
            throw new ArgumentException(
              'The arguments --' . $argObj->name . ' and --' . $otherArg .
              ' cannot be specified at the same time.'
            );
          }
        }
      }
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
   * Check is an argument has been configured
   *
   * @param $name
   *
   * @return bool
   */
  public function argumentIsConfigured($name)
  {
    return (isset($this->_argsByName[$name])
    || isset($this->_argsByShortName[$name]));
  }

  /**
   * Check if a command-line argument was provided
   *
   * @param string $longArgName The long name of the argument
   *
   * @return bool
   *
   * @throws \Exception
   */
  public function argumentIsSet($longArgName)
  {
    $arg = $this->_getArgObjByName($longArgName, true);
    if($arg === null)
    {
      throw new \Exception('Argument does not exist: ' . $longArgName);
    }
    return $arg->hasData();
  }

  /**
   * Get the value of a command-line argument.
   * Returns null if the argument does not exist.
   *
   * @param string $longArgName
   * @param mixed  $default The default value to return if the argument is
   *                        not set - overrides the argument's own default
   *
   * @return string|null
   *
   * @throws \Exception
   */
  public function argumentValue($longArgName, $default = null)
  {
    if(is_numeric($longArgName))
    {
      return $this->positionalArgValue($longArgName, $default);
    }

    $argObj = $this->_getArgObjByName($longArgName, true);
    if($argObj === null)
    {
      throw new \Exception('Argument does not exist: ' . $longArgName);
    }
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
    if(isset($this->_rawPositionalArgs[$argNum]))
    {
      return $this->_rawPositionalArgs[$argNum];
    }
    return $default;
  }

  /**
   * @return int
   */
  public function positionalArgCount()
  {
    return count($this->_rawPositionalArgs);
  }

  protected function _buildArguments()
  {
    $usedShorts = [];
    $class      = new \ReflectionClass(get_class($this));

    foreach($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $m)
    {
      $methodName = $m->getName();

      if(!in_array($methodName, $this->nonCallableMethods()))
      {
        $params = [];
        foreach($m->getParameters() as $param)
        {
          $params[] = '$' . $param->name . '' .
          ($param->isDefaultValueAvailable() ?
          '=' . $param->getDefaultValue() : '');
        }
        $this->_publicMethods[] = $methodName .
        (empty($params) ? '' : '(' . implode(',', $params) . ')');
      }
    }

    foreach($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $p)
    {
      $name             = $p->getName();
      $valueDescription = $defaultValue = $p->getValue($this);
      $shortCode        = strtolower(substr($name, 0, 1));

      $required    = false;
      $valueOption = CliArgument::VALUE_NONE;

      if(empty($valueDescription))
      {
        $valueDescription = 'value';
      }

      if(isset($usedShorts[$shortCode]))
      {
        $shortCode = null;
      }

      $this->_args[$name] = new CliArgument(
        $name, $name, $shortCode,
        $valueOption, $valueDescription, $required, $defaultValue
      );

      (new DocBlockParser($this->_args[$name], $p->getDocComment()))->parse();

      if($this->_args[$name]->hasShortName())
      {
        $shortCode = $this->_args[$name]->shortName;
        if(!isset($usedShorts[$shortCode]))
        {
          $usedShorts[$shortCode] = true;
        }
      }

      unset($this->$name);
    }
  }

  public function __get($name)
  {
    if($this->argumentIsConfigured($name))
    {
      return $this->argumentValue($name);
    }
    else
    {
      throw new \Exception("The arg '$name' has not been configured.");
    }
  }

  public function __isset($name)
  {
    return $this->argumentIsConfigured($name);
  }

  public function nonCallableMethods()
  {
    return [
      'setDocBlockItem',
      'setDocBlockComment',
      'nonCallableMethods',
      'methodCallArgs',
      'execute',
      '__construct',
      'init',
      'argumentIsSet',
      'argumentValue',
      'positionalArgValue',
      'positionalArgCount',
      '__get',
      'configure',
      'getConfig',
      'config',
      'argumentIsConfigured'
    ];
  }

  public function setDocBlockItem($item, $value)
  {
  }

  public function setDocBlockComment($comment)
  {
    $this->_comment = $comment;
  }
}
