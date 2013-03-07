<?php
/**
 * @author gareth.evans
 */
namespace Cubex\Core\Tests\Http;

use Cubex\Core\Http\Request;
use Cubex\Tests\TestCase;

class RequestTest extends TestCase
{
  /**
   * @dataProvider requestUrlSprintfProvider
   */
  public function testUrlSprintf(Request $request, $patterns, $results)
  {
    foreach($patterns as $patternI => $pattern)
    {
      $this->assertEquals($results[$patternI], $request->urlSprintf($pattern));
    }
  }

  public function requestUrlSprintfProvider()
  {
    return [
      [
        new Request("/some/path", "www.example.com"),
        ["%d.%t", "%h", "%h%pa"],
        ["example.com", "www.example.com", "www.example.com/some/path"]
      ],
      [
        new Request("/some/path", "sub.example.com:8000"),
        ["%p%d.%t", "%h", "%d.%t:%po"],
        ["http://example.com", "sub.example.com:8000", "example.com:8000"]
      ],
      [
        new Request("/some/path", "example.co.uk"),
        ["%d.%t", "%h", "%h%pa"],
        ["example.co.uk", "example.co.uk", "example.co.uk/some/path"]
      ]
    ];
  }
}
