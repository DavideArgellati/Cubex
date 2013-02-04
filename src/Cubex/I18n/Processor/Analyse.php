<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\I18n\Processor;

/**
 * Analyse codebase
 */
use Cubex\I18n\Translator\Translator;

class Analyse
{

  /**
   * @var array
   */
  protected $_translations = ['single' => [], 'plural' => []];

  /**
   * @param $base
   * @param $directory
   */
  public function processDirectory($base, $directory)
  {
    if($handle = opendir($base . $directory))
    {
      while(false !== ($entry = readdir($handle)))
      {
        if(in_array($entry, array('.', '..', 'locale', 'res')))
        {
          continue;
        }

        if(is_dir($base . $directory . DIRECTORY_SEPARATOR . $entry))
        {
          $this->processDirectory(
            $base,
            ($directory . DIRECTORY_SEPARATOR . $entry)
          );
        }
        else if(
          substr($entry, -4) == '.php'
          || substr($entry, -6) == '.phtml'
        )
        {
          $this->processFile($base, $directory . DIRECTORY_SEPARATOR . $entry);
        }
      }

      closedir($handle);
    }
  }

  /**
   * @param $base
   * @param $path
   */
  public function processFile($base, $path)
  {
    $content   = file_get_contents($base . $path);
    $path      = ltrim($path, DIRECTORY_SEPARATOR);
    $tokens    = token_get_all($content);
    $startLine = $building = 0;
    $msgId     = $type = $msgIdPlural = '';
    $started   = false;

    foreach($tokens as $token)
    {
      if($token[0] == 307 && $token[1] == 't')
      {
        $building  = 0;
        $msgId     = $msgIdPlural = '';
        $type      = 'single';
        $startLine = $token[2];
        $started   = true;
      }

      if($token[0] == 307 && $token[1] == 'tp')
      {
        $building  = 0;
        $msgId     = $msgIdPlural = '';
        $type      = 'singleplural';
        $startLine = $token[2];
        $started   = true;
      }

      if($token[0] == 307 && $token[1] == 'p')
      {
        $msgId     = $msgIdPlural = '';
        $type      = 'plural';
        $building  = 0;
        $startLine = $token[2];
        $started   = true;
      }

      if($token == ',')
      {
        if($type == 'plural')
        {
          $building = 1;
        }
        else if($type == 'single')
        {
          $token = ')';
        }
      }

      if($started && is_scalar($token) && $token == ')')
      {
        if($type == 'plural')
        {
          $this->_translations[$type][md5(
            $msgId . $msgIdPlural
          )]['data']    = array(
            $msgId,
            $msgIdPlural
          );
          $this->_translations[$type][md5(
            $msgId . $msgIdPlural
          )]['options'] = array(
            $path,
            $startLine
          );
        }
        else if($type == 'singleplural')
        {
          $msgIdPlural = str_replace('(s)', 's', $msgId);
          $msgId       = str_replace('(s)', '', $msgId);

          $this->_translations['plural'][md5(
            $msgId . $msgIdPlural
          )]['data']    = array(
            $msgId,
            $msgIdPlural
          );
          $this->_translations['plural'][md5(
            $msgId . $msgIdPlural
          )]['options'] = array(
            $path,
            $startLine
          );
        }
        else
        {
          $this->_translations[$type][$msgId][] = array(
            $path,
            $startLine
          );
        }

        $started = false;
      }

      if($started && $token[0] == 315)
      {
        if($building == 0)
        {
          $msgId .= substr($token[1], 1, -1);
        }
        else
        {
          $msgIdPlural .= substr($token[1], 1, -1);
        }
      }
    }
  }

  /**
   * @param            $language
   * @param Translator $translator
   * @param string     $sourceLanguage
   *
   * @return string
   */
  public function generatePO(
    $language, Translator $translator,
    $sourceLanguage = 'en'
  )
  {
    $wrapat = 76;
    $wrapon = 80;
    $result = '';

    foreach($this->_translations as $buildType => $translations)
    {
      foreach($translations as $message => $appearances)
      {
        if($buildType == 'plural')
        {
          $data        = $appearances;
          $appearances = array($data['options']);
          $message     = $data['data'];
        }

        foreach($appearances as $appearance)
        {
          $result .= "\n#: " . implode(":", $appearance);
        }

        $result .= "\n";
        if($buildType == 'single')
        {
          $translated = $translator->translate(
            stripslashes($message),
            $sourceLanguage,
            $language
          );
          if(strlen($message) < $wrapon)
          {
            $result .= 'msgid "' . $this->slashf($message) . '"';
          }
          else
          {
            $result .= 'msgid ""' . "\n";
            $result .= $this->wrap($message, $wrapat);
          }
          $result .= "\n";
          if(strlen($translated) < $wrapon)
          {
            $result .= 'msgstr "' . $this->slashf($translated) . '"';
          }
          else
          {
            $result .= 'msgstr ""' . "\n";
            $result .= $this->wrap($translated, $wrapat);
          }
          $result .= "\n\n";
        }
        else if($buildType == 'plural')
        {
          $singular = $translator->translate(
            $message[0],
            $sourceLanguage,
            $language
          );
          $plural   = $translator->translate(
            $message[1],
            $sourceLanguage,
            $language
          );

          if(strlen($message[0]) < $wrapon)
          {
            $result .= 'msgid "' . $this->slashf($message[0]) . '"';
          }
          else
          {
            $result .= 'msgid ""' . "\n";
            $result .= $this->wrap($message[0], $wrapat);
          }

          $result .= "\n";

          if(strlen($message[1]) < $wrapon)
          {
            $result .= 'msgid_plural "' . $this->slashf($message[1]) . '"';
          }
          else
          {
            $result .= 'msgid_plural ""' . "\n";
            $result .= $this->wrap($message[1], $wrapat);
          }

          $result .= "\n";
          $result .= 'msgstr[0] "' . $this->slashf($singular) . '"';
          $result .= "\n";
          $result .= 'msgstr[1] "' . $this->slashf($plural) . '"';
          $result .= "\n\n";
        }
      }
    }

    return $result;
  }

  public function slashf($message)
  {
    $message = $this->format($message);
    return $this->slash($message);
  }

  public function wrap($message, $at)
  {
    $message    = $this->format($message);
    $parts      = [];
    $messages   = explode("\n", $message);
    $msgs       = count($messages);
    $lineappend = $msgs > 1 ? "\\n" : '';

    foreach($messages as $i => $result)
    {
      if($i != ($msgs - 1))
      {
        $result .= $lineappend;
      }
      if(strlen($result) > $at)
      {
        $result = $this->iconvWordwrap($result, $at, " \n");
      }
      $result = explode("\n", $result);
      foreach($result as $p)
      {
        $parts[] = str_replace("\n", '', $this->slash($p));
      }
    }

    return '"' . implode("\"\n\"", $parts) . '"';
  }

  public function format($message)
  {
    $message = str_replace("\r\n", "\n", $message);
    $message = str_replace('\\\'', "'", $message);
    return $message;
  }

  /**
   * @param        $string
   * @param int    $width
   * @param string $break
   * @param bool   $cut
   * @param string $charset
   *
   * @return string
   * @throws \Exception
   */
  public function iconvWordwrap(
    $string, $width = 75, $break = "\n",
    $cut = false, $charset = 'utf-8'
  )
  {
    $stringWidth = iconv_strlen($string, $charset);
    $breakWidth  = iconv_strlen($break, $charset);

    if(strlen($string) === 0)
    {
      return '';
    }
    elseif($breakWidth === null)
    {
      throw new \Exception('Break string cannot be empty');
    }
    elseif($width === 0 && $cut)
    {
      throw new \Exception('Can\'t force cut when width is zero');
    }

    $result    = '';
    $lastStart = $lastSpace = 0;

    for($current = 0; $current < $stringWidth; $current++)
    {
      $char = iconv_substr($string, $current, 1, $charset);

      if($breakWidth === 1)
      {
        $possibleBreak = $char;
      }
      else
      {
        $possibleBreak = iconv_substr(
          $string,
          $current,
          $breakWidth,
          $charset
        );
      }

      if($possibleBreak === $break)
      {
        $result .= iconv_substr(
          $string,
          $lastStart,
          ($current - $lastStart + $breakWidth),
          $charset
        );
        $current += $breakWidth - 1;
        $lastStart = $lastSpace = $current + 1;
      }
      elseif($char === ' ')
      {
        if($current - $lastStart >= $width)
        {
          $result .=
          iconv_substr($string, $lastStart, $current - $lastStart, $charset)
          . $break;
          $lastStart = $current + 1;
        }

        $lastSpace = $current;
      }
      elseif($current - $lastStart >= $width && $cut && $lastStart >= $lastSpace)
      {
        $result .=
        iconv_substr($string, $lastStart, $current - $lastStart, $charset)
        . $break;
        $lastStart = $lastSpace = $current;
      }
      elseif($current - $lastStart >= $width && $lastStart < $lastSpace)
      {
        $result .=
        iconv_substr($string, $lastStart, $lastSpace - $lastStart, $charset)
        . $break;
        $lastStart = $lastSpace = $lastSpace + 1;
      }
    }

    if($lastStart !== $current)
    {
      $result .=
      iconv_substr($string, $lastStart, $current - $lastStart, $charset);
    }
    return $result;
  }

  /**
   * @param $text
   *
   * @return mixed
   */
  public function slash($text)
  {
    $pattern = '/<span class="notranslate">([^<]*)<\/span>/';
    $text    = preg_replace($pattern, '$1', \urldecode($text));
    return str_replace('"', '\"', $text);
  }
}

