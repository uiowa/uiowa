<?php

namespace Uiowa\Blt\Plugin\Filesets;

// Do not remove this, even though it appears to be unused.
// @codingStandardsIgnoreLine
use Acquia\Blt\Annotations\Fileset;
use Acquia\Blt\Robo\Config\ConfigAwareTrait;
use Robo\Contract\ConfigAwareInterface;
use Symfony\Component\Finder\Finder;

/**
 * Class Filesets.
 *
 * Each fileset in this class should be tagged with a @fileset annotation and
 * should return \Symfony\Component\Finder\Finder object.
 *
 * @package Acquia\Blt\Custom
 * @see \Acquia\Blt\Robo\Filesets\Filesets
 */
class MultisiteFilesets implements ConfigAwareInterface {
  use ConfigAwareTrait;

  /**
   * Defines the multisites fileset.
   *
   * @fileset(id="files.php.multisites")
   */
  public function multisites() {
    $finder = new Finder();
    $root = $this->config->get('repo.root');

    return $finder
      ->in("{$root}/docroot/sites/")
      ->directories()
      ->depth('< 1')
      ->exclude(['default', 'g', 'settings', 'simpletest'])
      ->sortByName();
  }

}
