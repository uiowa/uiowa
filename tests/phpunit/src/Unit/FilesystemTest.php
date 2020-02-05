<?php

namespace Drupal\Tests\sitenow\Unit;

use Acquia\Blt\Robo\Common\YamlMunge;
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
\$config_initializer = new ConfigInitializer(\$repo_root, new ArgvInput());
\$config_initializer->setSite(\$site_dir);
\$blt_config = \$config_initializer->initialize();

\$blt_override_config_directories = FALSE;

if (\$blt_sync_path = \$blt_config->get('cm.core.dirs.sync.path')) {
  \$settings['config_sync_directory'] = DRUPAL_ROOT . '/' . \$blt_sync_path;
}
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

      // Output the site to the console to identify test failures.
      fwrite(STDERR, $site . PHP_EOL);

      $this->assertFileExists("{$path}/blt.yml");
      $this->assertFileExists("{$path}/default.local.drush.yml");
      $this->assertFileExists("{$path}/default.settings.php");
      $this->assertFileExists("{$path}/settings.php");
      $this->assertFileExists("{$path}/settings/default.includes.settings.php");
      $this->assertFileExists("{$path}/settings/default.local.settings.php");

      // The default site does not follow the same naming conventions.
      if ($site != 'default') {
        $id = Multisite::getIdentifier("https://{$site}");
        $local = Multisite::getInternalDomains($id)['local'];
        $yaml = Yaml::parse(file_get_contents("{$path}/blt.yml"));
        $db = $yaml['drupal']['db']['database'];

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

            $yaml = YamlMunge::parseFile("{$path}/blt.yml");
            $this->assertEquals('profiles/custom/sitenow/config/sync', $yaml['cm']['core']['dirs']['sync']['path']);
            $this->assertEquals(TRUE, $yaml['cm']['core']['install_from_config']);
            break;
        }
      }
    }
  }

}
