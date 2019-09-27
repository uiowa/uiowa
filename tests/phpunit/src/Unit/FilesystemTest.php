<?php

namespace Drupal\Tests\sitenow\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Sitenow\Multisite;

/**
 * Basic file system tests.
 *
 * @group unit
 */
class FilesystemTest extends UnitTestCase {

  /**
   * Test that the robots.txt file does not exist.
   */
  public function testRobotsTxtDoesNotExist() {
    $this->assertFileNotExists(__DIR__ . '/../../../../docroot/robots.txt');
  }

  /**
   * Test global settings are as expected.
   */
  public function testGlobalSettingsFile() {
    $file = __DIR__ . '/../../../../docroot/sites/settings/global.settings.php';
    $this->assertFileExists($file);
    $haystack = file_get_contents($file);

    $needle = <<<EOD
if (\$site_dir != 'default') {
  \$db = str_replace('.', '_', \$site_dir);
  \$db = str_replace('-', '_', \$db);

  if (file_exists('/var/www/site-php')) {
    require "/var/www/site-php/uiowa/{\$db}-settings.inc";
  }
}
EOD;

    $this->assertContains($needle, $haystack);

    // Test that the DB include comes before the BLT config override.
    $before_blt = stristr($haystack, '$blt_override_config_directories = FALSE;', TRUE);
    $this->assertContains($needle, $before_blt);

    $needle = <<<EOD
\$blt_override_config_directories = FALSE;
\$config_directories[CONFIG_SYNC_DIRECTORY] = DRUPAL_ROOT . '/profiles/custom/sitenow/config/sync';
EOD;

    $this->assertContains($needle, $haystack);

    $needle = <<<EOD
if (isset(\$config_directories['vcs'])) {
  unset(\$config_directories['vcs']);
}
EOD;

    $this->assertContains($needle, $haystack);

    $needle = <<<EOD
if (drupal_installation_attempted() && php_sapi_name() != 'cli') {
  exit;
}
EOD;

    $this->assertContains($needle, $haystack);
  }

  /**
   * Test that multisitefiles exist and that BLT config is set correctly.
   */
  public function testMultisiteFiles() {
    $finder = new Finder();
    $dirs = $finder
      ->in(__DIR__ . '/../../../../docroot/sites/')
      ->directories()
      ->depth('< 1')
      ->exclude(['g', 'settings'])
      ->sortByName();

    foreach ($dirs->getIterator() as $dir) {
      $site = $dir->getRelativePathname();
      $path = $dir->getRealPath();

      $this->assertFileExists("{$path}/blt.yml");
      $this->assertFileExists("{$path}/default.local.drush.yml");
      $this->assertFileExists("{$path}/default.settings.php");
      $this->assertFileExists("{$path}/settings.php");
      $this->assertFileExists("{$path}/settings/default.includes.settings.php");
      $this->assertFileExists("{$path}/settings/default.local.settings.php");

      // The default site does not follow the same naming conventions.
      if ($site != 'default') {
        $id = Multisite::getIdentifier("https://{$site}");

        $blt = Yaml::parse(file_get_contents("{$path}/blt.yml"));
        $this->assertEquals($site, $blt['project']['local']['hostname']);
        $this->assertEquals($site, $blt['project']['human_name']);
        $this->assertEquals($id, $blt['project']['machine_name']);
        $this->assertEquals('https', $blt['project']['local']['protocol']);
        $this->assertEquals('self', $blt['drush']['aliases']['local']);
        $this->assertEquals("{$id}.prod", $blt['drush']['aliases']['remote']);

        $db = str_replace('.', '_', $site);
        $db = str_replace('-', '_', $db);
        $this->assertEquals($db, $blt['drupal']['db']['database']);
      }
    }
  }

}
