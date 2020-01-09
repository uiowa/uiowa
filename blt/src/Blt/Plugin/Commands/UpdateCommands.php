<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Annotations\Update;
use Acquia\Blt\Robo\Common\YamlMunge;
use Doctrine\Common\Annotations\AnnotationReader;
use Uiowa\Multisite;

/**
 * Define update commands.
 *
 * @Annotation
 */
class UpdateCommands extends BltTasks {

  /**
   * Execute SiteNow updates.
   *
   * @command sitenow:update
   */
  public function sitenowUpdate() {
    $schema = $this->getSitenowSchemaVersion();
    $reflection = new \ReflectionClass(UpdateCommands::class);
    $methods = array_filter($reflection->getMethods(), function ($v) {
      return $v->name != 'sitenowUpdate' && $v->class == UpdateCommands::class;
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
  protected function getSitenowSchemaVersion() {
    $file = $this->getConfigValue('repo.root') . '/blt/.sitenow_schema_version';

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
  protected function setSitenowSchemaVersion($version) {
    $file = $this->getConfigValue('repo.root') . '/blt/.sitenow_schema_version';
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

    $this->setSitenowSchemaVersion(1001);
  }

}
