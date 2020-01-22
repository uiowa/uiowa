<?php

namespace Drupal\Tests\sitenow\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Uiowa\Multisite;

/**
 * Basic file system tests for application integrity.
 *
 * @group unit
 */
class FilesystemTest extends UnitTestCase {

  /**
   * Test that the robots.txt file does not exist.
   */
  public function testRobotsTxtDoesNotExist() {
    $this->assertFileNotExists($this->root . '/robots.txt');
  }

  /**
   * Test sites.php entries exist.
   */
  public function testDirectoryAliasesExist() {
    $finder = new Finder();
    $dirs = $finder
      ->in($this->root . '/sites/')
      ->directories()
      ->depth('< 1')
      ->exclude(['default', 'g', 'settings'])
      ->sortByName();

    $haystack = file_get_contents($this->root . '/sites/sites.php');

    foreach ($dirs->getIterator() as $dir) {
      $site = $dir->getRelativePathname();
      $id = Multisite::getIdentifier("https://{$site}");
      $dev = Multisite::getInternalDomains($id)['dev'];
      $test = Multisite::getInternalDomains($id)['test'];
      $prod = Multisite::getInternalDomains($id)['prod'];

      $needle = <<<EOD
\$sites['$dev'] = '$site';
\$sites['$test'] = '$site';
\$sites['$prod'] = '$site';
EOD;
      $this->assertContains($needle, $haystack);
    }
  }

  /**
   * Test global settings are as expected.
   */
  public function testGlobalSettingsFile() {
    $file = $this->root . '/sites/settings/global.settings.php';
    $this->assertFileExists($file);
    $haystack = file_get_contents($file);

    $needle = <<<EOD
\$blt_override_config_directories = FALSE;
EOD;

    $this->assertContains($needle, $haystack);

    $needle = <<<EOD
if (isset(\$config_directories['vcs'])) {
  unset(\$config_directories['vcs']);
}
EOD;

    $this->assertContains($needle, $haystack);

    $needle = <<<EOD
if (InstallerKernel::installationAttempted() && php_sapi_name() != 'cli') {
  exit;
}
EOD;

    $this->assertContains($needle, $haystack);
  }

  /**
   * Test sitenow specific settings exist.
   */
  public function testSitenowGlobalSettings() {
    $file = $this->root . '/sites/settings/sitenow.settings.php';
    $this->assertFileExists($file);
    $haystack = file_get_contents($file);

    $needle = <<<EOD
\$settings['config_sync_directory'] = DRUPAL_ROOT . '/profiles/custom/sitenow/config/sync';
EOD;

    $this->assertContains($needle, $haystack);
  }

  /**
   * Test that multisite files exist and that BLT config is set correctly.
   */
  public function testMultisiteFiles() {
    $finder = new Finder();
    $dirs = $finder
      ->in($this->root . '/sites/')
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

        $yaml = Yaml::parse(file_get_contents("{$path}/blt.yml"));
        $db = $yaml['drupal']['db']['database'];

        $this->assertEquals($site, $yaml['project']['local']['hostname']);
        $this->assertEquals($site, $yaml['project']['human_name']);
        $this->assertEquals($id, $yaml['project']['machine_name']);
        $this->assertEquals('https', $yaml['project']['local']['protocol']);
        $this->assertEquals('self', $yaml['drush']['aliases']['local']);

        $needle = <<<EOD
\$ah_group = getenv('AH_SITE_GROUP');

if (file_exists('/var/www/site-php')) {
  require "/var/www/site-php/{\$ah_group}/{$db}-settings.inc";
}

require DRUPAL_ROOT . "/../vendor/acquia/blt/settings/blt.settings.php";
EOD;

        $file = "{$path}/settings.php";
        $this->assertFileExists($file);
        $haystack = file_get_contents($file);
        $this->assertContains($needle, $haystack);

        // Profile specific tests.
        switch ($yaml['project']['profile']['name']) {
          case 'sitenow':
            $file = "{$path}/settings/includes.settings.php";
            $this->assertFileExists($file);

            $needle = <<<EOD
\$additionalSettingsFiles = [
  DRUPAL_ROOT . "/sites/settings/sitenow.settings.php"
];

foreach (\$additionalSettingsFiles as \$settingsFile) {
  if (file_exists(\$settingsFile)) {
    require \$settingsFile;
  }
}
EOD;

            $haystack = file_get_contents($file);
            $this->assertContains($needle, $haystack);

            break;
        }
      }
    }
  }

}
