<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Annotations\Update;
use Acquia\Blt\Robo\Common\YamlWriter;
use Acquia\Blt\Robo\Common\YamlMunge;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Yaml\Yaml;
use Uiowa\Multisite;

/**
 * Define update commands.
 *
 * @Annotation
 */
class UpdateCommands extends BltTasks {

  /**
   * Execute uiowa application updates.
   *
   * @command uiowa:update
   */
  public function uiowaUpdate() {
    $schema = $this->getSchemaVersion();
    $reflection = new \ReflectionClass(UpdateCommands::class);
    $methods = array_filter($reflection->getMethods(), function ($v) {
      return $v->name != 'uiowaUpdate' && $v->class == UpdateCommands::class;
    });

    $reader = new AnnotationReader();
    $updates = [];

    foreach ($methods as $method) {
      $annotation = $reader->getMethodAnnotation($method, 'Acquia\Blt\Annotations\Update');

      if ($annotation && $annotation->version > $schema) {
        $updates[$method->name] = $annotation->description;
      }
    }

    if ($updates) {
      $this->printArrayAsTable($updates, ['Name', 'Description']);
      if ($this->confirm('You will execute the above updates. Are you sure?') === FALSE) {
        throw new \Exception('Aborted.');
      }
      else {
        foreach ($updates as $name => $description) {
          $this->say("Executing {$name}: {$description}");
          call_user_func([$this, $name]);
        }
      }
    }
    else {
      $this->say('There are no outstanding updates.');
    }
  }

  /**
   * Get the current schema version from the filesystem.
   *
   * @return string
   *   The current schema version or 1000 if none is set.
   */
  protected function getSchemaVersion() {
    $file = $this->getConfigValue('repo.root') . '/blt/.uiowa_schema_version';

    if (file_exists($file)) {
      return file_get_contents($file);
    }
    else {
      return '1000';
    }
  }

  /**
   * Write version to the schema file.
   *
   * @param int $version
   *   The version number to write to the schema file.
   */
  protected function setSchemaVersion($version) {
    $file = $this->getConfigValue('repo.root') . '/blt/.uiowa_schema_version';
    file_put_contents($file, $version);
  }

  /**
   * Update 1001.
   *
   * @Update(
   *   version = "1001",
   *   description = "Write database include to settings.php for every multisite."
   * )
   */
  protected function update1001() {
    $root = $this->getConfigValue('repo.root');
    $search = 'require DRUPAL_ROOT . "/../vendor/acquia/blt/settings/blt.settings.php";' . "\n";
    $sites = Multisite::getAllSites($root);

    foreach ($sites as $site) {
      $yaml = YamlMunge::parseFile("{$root}/docroot/sites/{$site}/blt.yml");
      $db = $yaml['drupal']['db']['database'];

      $replace = <<<EOD
if (file_exists('/var/www/site-php')) {
  require '/var/www/site-php/uiowa/{$db}-settings.inc';
}

require DRUPAL_ROOT . "/../vendor/acquia/blt/settings/blt.settings.php";

EOD;

      $result = $this->taskReplaceInFile("{$root}/docroot/sites/{$site}/settings.php")
        ->from($search)
        ->to($replace)
        ->run();

      if (!$result->wasSuccessful()) {
        $this->logger->error("Unable to update settings.php file for {$site}.");
      }
    }

    $this->setSchemaVersion(1001);
  }

  /**
   * Update 1002.
   *
   * @Update(
   *   version = "1002",
   *   description = "Update multisite blt.yml files to localize sitenow config."
   * )
   */
  protected function update1002() {
    $root = $this->getConfigValue('repo.root');
    $sites = Multisite::getAllSites($root);

    foreach ($sites as $site) {
      $file = "{$root}/docroot/sites/{$site}/blt.yml";
      $yaml = YamlMunge::parseFile($file);
      $yaml['project']['profile']['name'] = 'sitenow';
      $yaml['cm']['core']['dirs']['sync']['path'] = 'profiles/custom/sitenow/config/sync';
      $yaml['cm']['core']['install_from_config'] = TRUE;

      $yaml['sync']['commands'] = [
        'blt:init:settings',
        'drupal:sync:db',
        'drupal:update',
        'sitenow:multisite:noop',
        'sitenow:multisite:noop',
      ];

      if (isset($yaml['project']['requester'])) {
        $requester = $yaml['project']['requester'];
        unset($yaml['project']['requester']);
        $yaml['uiowa']['sitenow']['requester'] = $requester;
      }

      file_put_contents("{$root}/docroot/sites/{$site}/blt.yml", Yaml::dump($yaml, 10, 2));
    }

    $this->setSchemaVersion(1002);
  }

  /**
   * Update 1003.
   *
   * @Update(
   *   version = "1003",
   *   description = "Write an includes.settings.php file for each multisite."
   * )
   */
  protected function update1003() {
    $data = <<<EOD
<?php

/**
 * @file
 * Generated by BLT. A central aggregation point for adding settings files.
 */

/**
 * Add settings using full file location and name.
 *
 * It is recommended that you use the DRUPAL_ROOT and \$site_dir components to
 * provide full pathing to the file in a dynamic manner.
 */
\$additionalSettingsFiles = [
  DRUPAL_ROOT . "/sites/settings/sitenow.settings.php"
];

foreach (\$additionalSettingsFiles as \$settingsFile) {
  if (file_exists(\$settingsFile)) {
    require \$settingsFile;
  }
}

EOD;
    $root = $this->getConfigValue('repo.root');
    $sites = Multisite::getAllSites($root);

    foreach ($sites as $site) {
      $file = "{$root}/docroot/sites/{$site}/settings/includes.settings.php";
      file_put_contents($file, $data);
    }

    $this->setSchemaVersion(1003);
  }

  /**
   * Update 1004.
   *
   * @Update(
   *   version = "1004",
   *   description = "Revert multisite database configuration to BLT defaults for VM."
   * )
   */
  protected function update1004() {
    $root = $this->getConfigValue('repo.root');
    $sites = Multisite::getAllSites($root);

    foreach ($sites as $site) {
      $file = "{$root}/docroot/sites/{$site}/blt.yml";
      $yaml = YamlMunge::parseFile($file);

      unset($yaml['drupal']['db']['host']);
      unset($yaml['drupal']['db']['user']);
      unset($yaml['drupal']['db']['password']);

      file_put_contents("{$root}/docroot/sites/{$site}/blt.yml", Yaml::dump($yaml, 10, 2));
    }

    $this->setSchemaVersion(1004);
  }

  /**
   * Update 1005.
   *
   * @Update(
   *   version = "1005",
   *   description = "Set VM vhosts for each multisite."
   * )
   */
  protected function update1005() {
    $root = $this->getConfigValue('repo.root');
    $sites = Multisite::getAllSites($root);
    $file = $this->getConfigValue('vm.config');

    $writer = new YamlWriter($file);
    $vm_config = $writer->getContents();

    foreach ($sites as $site) {
      $id = Multisite::getIdentifier("https://{$site}");

      $vm_config['apache_vhosts'][] = [
        'servername' => "{$id}.uiowa.local.site",
        'documentroot' => $vm_config['apache_vhosts'][0]['documentroot'],
        'extra_parameters' => $vm_config['apache_vhosts'][0]['extra_parameters'],
      ];
    }

    $writer->write($vm_config);

    $this->setSchemaVersion(1005);
  }

  /**
   * Update 1006.
   *
   * @Update(
   *   version = "1006",
   *   description = "Set local hostname for multisites and regenerate settings."
   * )
   */
  protected function update1006() {
    $root = $this->getConfigValue('repo.root');
    $sites = Multisite::getAllSites($root);

    foreach ($sites as $site) {
      $file = "{$root}/docroot/sites/{$site}/blt.yml";
      $id = Multisite::getIdentifier("https://{$site}");
      $yaml = YamlMunge::parseFile($file);
      $yaml['project']['local']['hostname'] = "{$id}.local.drupal.uiowa.edu";
      file_put_contents("{$root}/docroot/sites/{$site}/blt.yml", Yaml::dump($yaml, 10, 2));

      $file = "{$root}/docroot/sites/{$site}/local.drush.yml";

      $this->taskFilesystemStack()
        ->remove($file)
        ->run();
    }

    $this->invokeCommand('bis');
    $this->setSchemaVersion(1006);
  }

  /**
   * Update 1007.
   *
   * @Update(
   *   version = "1007",
   *   description = "Add local Drush aliases for existing sites."
   * )
   */
  protected function update1007() {
    $root = $this->getConfigValue('repo.root');
    $sites = Multisite::getAllSites($root);

    $uiowa_01 = [
      'uipda.grad.uiowa.edu',
      'policy.clas.uiowa.edu',
      'iowasuperfund.uiowa.edu',
      'icsa.uiowa.edu',
      'cogscilang.grad.uiowa.edu',
      'theming.uiowa.edu',
    ];

    foreach ($sites as $site) {
      $id = Multisite::getIdentifier("https://{$site}");
      $file = "{$root}/drush/sites/{$id}.site.yml";

      if (in_array($site, $uiowa_01)) {
        $app = 'uiowa01';

      }
      else {
        $app = 'uiowa';
      }

      $yaml = YamlMunge::parseFile("{$root}/drush/sites/{$app}.site.yml");
      $yaml['local']['uri'] = Multisite::getInternalDomains($id)['local'];
      $yaml['dev']['uri'] = Multisite::getInternalDomains($id)['dev'];
      $yaml['test']['uri'] = Multisite::getInternalDomains($id)['test'];
      $yaml['prod']['uri'] = $site;

      file_put_contents($file, Yaml::dump($yaml, 10, 2));
    }

    $this->setSchemaVersion(1007);
  }

  /**
   * Update 1008.
   *
   * @Update(
   *   version = "1008",
   *   description = "Update drush aliases to use https:// in *.uri key."
   * )
   */
  protected function update1008() {
    $root = $this->getConfigValue('repo.root');
    $sites = Multisite::getAllSites($root);


    $uiowa_01 = [
      'uipda.grad.uiowa.edu',
      'policy.clas.uiowa.edu',
      'iowasuperfund.uiowa.edu',
      'icsa.uiowa.edu',
      'cogscilang.grad.uiowa.edu',
      'theming.uiowa.edu',
    ];

    foreach ($sites as $site) {
      $id = Multisite::getIdentifier("https://{$site}");
      $file = "{$root}/drush/sites/{$id}.site.yml";

      if (in_array($site, $uiowa_01)) {
        $app = 'uiowa01';

      }
      else {
        $app = 'uiowa';
      }

      $yaml = YamlMunge::parseFile("{$root}/drush/sites/{$app}.site.yml");
      $yaml['local']['uri'] = 'https://' . Multisite::getInternalDomains($id)['local'];
      $yaml['dev']['uri'] = 'https://' . Multisite::getInternalDomains($id)['dev'];
      $yaml['test']['uri'] = 'https://' . Multisite::getInternalDomains($id)['test'];
      $yaml['prod']['uri'] = 'https://' . $site;

      file_put_contents($file, Yaml::dump($yaml, 10, 2));
    }

    $this->setSchemaVersion(1008);
  }

}
