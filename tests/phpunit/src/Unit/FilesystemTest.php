<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Acquia\Blt\Robo\Common\YamlMunge;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;
use Uiowa\Multisite;

/**
 * Basic file system tests for application integrity.
 *
 * @group unit
 */
class FilesystemTest extends UnitTestCase {

  /**
   * Each app should have a Drush alias.
   */
  public function testAppDrushAliasesExist() {
    $config = YamlMunge::parseFile($this->root . '/../blt/blt.yml');

    foreach ($config['uiowa']['applications'] as $application => $attrs) {
      $this->assertFileExists($this->root . "/../drush/sites/{$application}.site.yml");
    }
  }

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
    $sites = Multisite::getAllSites($this->root . '/..');
    $haystack = file_get_contents($this->root . '/sites/sites.php');

    foreach ($sites as $site) {
      $id = Multisite::getIdentifier("https://{$site}");
      $local = Multisite::getInternalDomains($id)['local'];
      $dev = Multisite::getInternalDomains($id)['dev'];
      $test = Multisite::getInternalDomains($id)['test'];
      $prod = Multisite::getInternalDomains($id)['prod'];

      $needle = <<<EOD
\$sites['$local'] = '$site';
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
   * Test that multisite files exist and that BLT config is set correctly.
   */
  public function testMultisiteFiles() {
    $sites = Multisite::getAllSites($this->root . '/..');

    foreach ($sites as $site) {
      $path = "docroot/sites/{$site}";

      // Output the site to the console to identify test failures.
      fwrite(STDERR, $site . PHP_EOL);

      $this->assertFileExists("{$path}/blt.yml");
      $this->assertFileExists("{$path}/default.local.drush.yml");
      $this->assertFileExists("{$path}/default.settings.php");
      $this->assertFileExists("{$path}/settings.php");
      $this->assertFileExists("{$path}/settings/default.includes.settings.php");
      $this->assertFileExists("{$path}/settings/default.local.settings.php");

      $id = Multisite::getIdentifier("//{$site}");
      $local = Multisite::getInternalDomains($id)['local'];

      // Test BLT config.
      $yaml = Yaml::parse(file_get_contents("{$path}/blt.yml"));
      $db = $yaml['drupal']['db']['database'];

      $this->assertEquals(Multisite::getDatabaseName($site), $db);
      $this->assertEquals($local, $yaml['project']['local']['hostname']);
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

      // Test Drush aliases.
      $yaml = Yaml::parseFile($this->root . "/../drush/sites/{$id}.site.yml");
      $expected_files_path = "sites/{$site}/files";

      foreach (['local', 'dev', 'test', 'prod'] as $env) {
        $this->assertEquals($expected_files_path, $yaml[$env]['paths']['files']);
      }
    }
  }

}
