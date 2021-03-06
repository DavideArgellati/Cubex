<?php
/**
 * @author  brooke.bryan
 */
namespace Cubex\I18n\Translator;

use Cubex\Foundation\Config\IConfigurable;

interface ITranslator extends IConfigurable
{

  /**
   * @param string $text            Text to translate
   * @param string $sourceLanguage  original text language
   * @param string $targetLanguage  expected return language
   *
   * @return string Translation
   */
  public function translate($text, $sourceLanguage, $targetLanguage);
}
