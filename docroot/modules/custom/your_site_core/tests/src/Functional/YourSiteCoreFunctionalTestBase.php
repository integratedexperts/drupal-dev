<?php

namespace Drupal\Tests\your_site_core\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\your_site_core\Traits\YourSiteCoreTestHelperTrait;

/**
 * Class YourSiteCoreKernelTestBase.
 */
abstract class YourSiteCoreFunctionalTestBase extends BrowserTestBase {

  use YourSiteCoreTestHelperTrait;
  
  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';

}
