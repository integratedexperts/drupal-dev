<?php

namespace Drupal\Tests\your_site_core\Kernel;

/**
 * Class ExampleKernelTest.
 *
 * @group YourSiteCore
 */
class ExampleKernelTest extends YourSiteCoreKernelTestBase {

  /**
   * @dataProvider dataProviderAdd
   */
  public function testAdd($a, $b, $expected, $excpectExceptionMessage = NULL) {
    if ($excpectExceptionMessage) {
      $this->setExpectedException(\Exception::class, $excpectExceptionMessage);
    }

    // Replace below with a call to your class method.
    $actual = $a + $b;

    $this->assertEquals($expected, $actual);
  }

  public function dataProviderAdd() {
    return [
      [0, 0, 0],
      [1, 1, 2],
    ];
  }

}
