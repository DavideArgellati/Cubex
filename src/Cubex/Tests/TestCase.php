<?php
/**
 * @author: gareth.evans
 */
namespace Cubex\Tests;

class TestCase extends \PHPUnit_Framework_TestCase
{
  public function setUp()
  {
    LogicComponent::bindServiceManager();
  }
}
