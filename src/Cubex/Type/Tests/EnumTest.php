<?php
/**
 * @author: gareth.evans
 */
namespace Cubex\Type\Tests;

use Cubex\Foundation\Tests\CubexTestCase;
use Cubex\Type\Tests\Type\Bool;
use Cubex\Type\Tests\Type\EnumNoConstants;
use Cubex\Type\Tests\Type\EnumNoDefault;

class EnumTest extends CubexTestCase
{
  public function testSomething()
  {
    $this->setExpectedException(
      "UnexpectedValueException", "Enum 'non_value' does not exist"
    );

    new Bool("non_value");
  }

  public function testSetAndToString()
  {
    $enum = new Bool(Bool::BOOL_TRUE);
    $this->assertEquals($enum, Bool::BOOL_TRUE);
  }

  public function testExcptionThrownWhenNoDefaultSet()
  {
    $this->setExpectedException(
      "UnexpectedValueException", "No default enum set"
    );

    new EnumNoDefault();
  }

  public function testExcptionThrownWhenNoConstantsSet()
  {
    $this->setExpectedException(
      "UnexpectedValueException", "No constants set"
    );

    new EnumNoConstants();
  }

  public function testDefaultSetWhenNoValuePassed()
  {
    $enum = new Bool();
    $this->assertEquals($enum, Bool::__default);
  }

  public function testGetConstList()
  {
    $enum = new Bool();

    $constants = [
      "BOOL_TRUE"  => "1",
      "BOOL_FALSE" => "0"
    ];
    $this->assertEquals($constants, $enum->getConstList());

    $constantsWithDefault = array_merge($constants, ["__default" => "1"]);
    $this->assertEquals($constantsWithDefault, $enum->getConstList(true));
  }

  public function testCallStatic()
  {
    $enum = Bool::BOOL_FALSE();
    $this->assertEquals($enum, Bool::BOOL_FALSE);
  }

  public function testConstantExists()
  {
    $enum = new Bool();

    $this->assertTrue($enum->constantExists("bool_true"));
    $this->assertFalse($enum->constantExists("random"));
  }

  public function testIs()
  {
    $enum = new Bool(BOOL::BOOL_TRUE);

    $this->assertTrue($enum->is(BOOL::BOOL_TRUE));
    $this->assertFalse($enum->is(BOOL::BOOL_FALSE));
  }

  /**
   * @dataProvider compareCouples
   */
  public function testMatch($shouldMatch, $val1, $val2, $strict)
  {
    if($shouldMatch)
    {
      $this->assertTrue(Bool::match($val1, $val2, $strict));
    }
    else
    {
      $this->assertFalse(Bool::match($val1, $val2, $strict));
    }
  }

  public function compareCouples()
  {
    return [
      [true, 1, 1, true],
      [true, new Bool(), new Bool(), true],
      [true, new Bool(), 1, true],
      [true, new Bool(), Bool::BOOL_TRUE, true],
      [false, 1, 2, true],
      [false, new Bool(), new Bool(BOOL::BOOL_FALSE), true],
      [false, new Bool(), 0, true],
      [false, new Bool(), Bool::BOOL_FALSE, true],
      [true, "foo", "foo", false],
      [false, "foo", "foo", true],
    ];
  }
}
