<?php
/**
 * @author  brooke.bryan
 */
namespace Cubex\Foundation\Config;

interface IConfigurable
{
  /**
   * @param \Cubex\Foundation\Config\ConfigGroup $configuration
   *
   * @return static
   */
  public function configure(ConfigGroup $configuration);

  /**
   * @return \Cubex\Foundation\Config\ConfigGroup
   */
  public function getConfig();
}
