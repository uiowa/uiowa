<?php

namespace Drupal\Tests\sitenow\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Class ProfileTestBase.
 */
abstract class ProfileTestBase extends EntityKernelTestBase {
  /**
   * Disable strict schema checking.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Setup tasks.
   */
  public function setUp() {
    parent::setUp();
    $this->setInstallProfile('sitenow');
  }

}
