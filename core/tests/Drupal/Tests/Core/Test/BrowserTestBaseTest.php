<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Test;

use Drupal\Tests\DrupalTestBrowser;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\BrowserTestBase;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Session;

/**
 * @coversDefaultClass \Drupal\Tests\BrowserTestBase
 * @group Test
 */
class BrowserTestBaseTest extends UnitTestCase {

  protected function mockBrowserTestBaseWithDriver($driver) {
    $session = $this->getMockBuilder(Session::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getDriver'])
      ->getMock();
    $session->expects($this->any())
      ->method('getDriver')
      ->willReturn($driver);

    $btb = $this->getMockBuilder(BrowserTestBaseMockableClassTest::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getSession'])
      ->getMock();
    $btb->expects($this->any())
      ->method('getSession')
      ->willReturn($session);

    return $btb;
  }

  /**
   * @covers ::getHttpClient
   */
  public function testGetHttpClient(): void {
    // Our stand-in for the Guzzle client object.
    $expected = new \stdClass();

    $browserkit_client = $this->getMockBuilder(DrupalTestBrowser::class)
      ->onlyMethods(['getClient'])
      ->getMock();
    $browserkit_client->expects($this->once())
      ->method('getClient')
      ->willReturn($expected);

    // Because the driver is a BrowserKitDriver, we'll get back a client.
    $driver = new BrowserKitDriver($browserkit_client);
    $btb = $this->mockBrowserTestBaseWithDriver($driver);

    $reflected_get_http_client = new \ReflectionMethod($btb, 'getHttpClient');

    $this->assertSame(get_class($expected), get_class($reflected_get_http_client->invoke($btb)));
  }

  /**
   * @covers ::getHttpClient
   */
  public function testGetHttpClientException(): void {
    // A driver type that isn't BrowserKitDriver. This should cause a
    // RuntimeException.
    $btb = $this->mockBrowserTestBaseWithDriver(new \stdClass());

    $reflected_get_http_client = new \ReflectionMethod($btb, 'getHttpClient');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The Mink client type stdClass does not support getHttpClient().');
    $reflected_get_http_client->invoke($btb);
  }

  /**
   * Tests that tearDown doesn't call cleanupEnvironment if setUp is not called.
   *
   * @covers ::tearDown
   */
  public function testTearDownWithoutSetUp(): void {
    $method = 'cleanupEnvironment';
    $this->assertTrue(method_exists(BrowserTestBase::class, $method));
    $btb = $this->getMockBuilder(BrowserTestBaseMockableClassTest::class)
      ->disableOriginalConstructor()
      ->onlyMethods([$method])
      ->getMock();
    $btb->expects($this->never())->method($method);
    $ref_tearDown = new \ReflectionMethod($btb, 'tearDown');
    $ref_tearDown->invoke($btb);
  }

}

/**
 * A class extending BrowserTestBase for testing purposes.
 */
class BrowserTestBaseMockableClassTest extends BrowserTestBase {

}
