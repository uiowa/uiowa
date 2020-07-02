<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\YamlMunge;
use Composer\Semver\Semver;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Symfony\Component\Finder\Finder;

/**
 * Git commands.
 */
class GitCommands extends BltTasks {

  /**
   * Validate clean command.
   *
   * @hook validate uiowa:git:clean
   */
  public function validateClean(CommandData $commandData) {
    $remotes = $this->getConfigValue('git.remotes');

    foreach ($remotes as $remote) {
      $result = $this->taskExecStack()
        ->exec("git ls-remote {$remote}")
        ->stopOnFail()
        ->silent(TRUE)
        ->run();

      if (!$result->wasSuccessful()) {
        return new CommandError("Error connecting to Acquia remote {$remote}. Double check permissions and SSH key.");
      }
    }
  }

  /**
   * Delete all artifact branches except develop/main from Acquia remotes.
   *
   * @command uiowa:git:clean
   *
   * @aliases ugc
   */
  public function clean() {
    // Keep the last five releases. In reality, reverting to anything beyond
    // even the previous release is probably unfeasible.
    $tags = $this->getOriginTagsAsArtifacts();
    $keep = array_slice($tags, 0, 5);

    // We never want to delete the two main artifact branches. Additionally,
    // we cannot delete the default Acquia remote branch.
    array_push($keep, 'refs/heads/master', 'refs/heads/main-build', 'refs/heads/develop-build');

    $remotes = $this->getConfigValue('git.remotes');

    $delete = [];

    foreach ($remotes as $remote) {
      $result = $this->taskExecStack()
        ->exec("git ls-remote --heads --tags --refs {$remote}")
        ->stopOnFail()
        ->silent(TRUE)
        ->run();

      $output = $result->getMessage();
      $heads = explode(PHP_EOL, $output);

      foreach ($heads as $head) {
        list($sha, $ref) = explode("\t", $head);
        $sha = substr($sha, 0, 8);

        if (!in_array($ref, $keep)) {
          $delete[$remote][$sha] = $ref;
        }
      }

      if (!empty($delete[$remote])) {
        $this->printArrayAsTable($delete[$remote], ['SHA', 'Ref']);
      }
    }

    if (!empty($delete)) {
      if (!$this->confirm('You will delete the references in the tables above from the Acquia remotes. Are you sure?')) {
        throw new \Exception('Aborted.');
      }
      else {
        foreach ($delete as $remote => $data) {
          foreach ($data as $sha => $ref) {
            $this->taskExecStack()
              ->exec("git push --delete {$remote} {$ref}")
              ->run();
          }
        }
      }
    }
    else {
      $this->yell('There are no branches to clean up!');
    }
  }

  /**
   * Run post build tasks.
   *
   * This command should not be executed directly. It is called after the
   * build artifact is created in blt.yml.
   *
   * @see: blt/blt.yml
   *
   * @command uiowa:post:build
   *
   * @hidden
   */
  public function postArtifactBuild() {
    $this->writeGitVersion();
    $this->copyDrushCommands();
  }

  /**
   * Write an unannotated Git tag version string to custom assets.
   */
  protected function writeGitVersion() {
    $result = $this->taskGit()
      ->dir($this->getConfigValue('repo.root'))
      ->exec('describe --tags')
      ->stopOnFail(FALSE)
      ->silent(TRUE)
      ->run();

    if (!$result->wasSuccessful()) {
      $this->logger->warning("Unable to determine Git version for info files.");
    }
    else {
      $deploy = $this->getConfigValue('deploy.dir');
      $version = $result->getMessage();

      $finder = new Finder();
      $files = $finder
        ->files()
        ->in([
          "{$deploy}/docroot/profiles/custom/",
          "{$deploy}/docroot/themes/custom/",
          "{$deploy}/docroot/modules/custom/",
        ])
        ->depth('< 2')
        ->name('*.info.yml')
        ->sortByName();

      foreach ($files->getIterator() as $file) {
        if (file_exists($file)) {
          $yaml = YamlMunge::parseFile($file);

          if (!isset($yaml['version'])) {
            $yaml['version'] = $version;
            YamlMunge::writeFile($file, $yaml);
            $this->logger->notice("Wrote Git version {$version} to {$file}.");
          }
          else {
            $this->logger->warning("File {$file} already contains version.");
          }
        }
        else {
          $this->logger->warning("Unable to write Git version to non-existent file {$file}.");
        }
      }
    }
  }

  /**
   * Copy global Drush commands into the build artifact before it is committed.
   *
   * Since drush/Commands/ is listed in the upstream deploy-exclude.txt file,
   * any hard-coded commands (ex. PolicyCommands.php) will not be committed to
   * the build artifact. Rather than override that file and lose upstream
   * changes, we can copy our Drush commands via a post-command hook.
   */
  protected function copyDrushCommands() {
    $root = $this->getConfigValue('repo.root');
    $deploy_dir = $this->getConfigValue('deploy.dir');

    $finder = new Finder();

    $files = $finder
      ->in("{$root}/drush/Commands/")
      ->files()
      ->depth('< 1')
      ->exclude(['contrib'])
      ->name('*Commands.php')
      ->sortByName();

    foreach ($files->getIterator() as $file) {
      $name = $file->getRelativePathname();

      $this->taskFilesystemStack()
        ->stopOnFail()
        ->copy("{$root}/drush/Commands/{$name}", "{$deploy_dir}/drush/Commands/{$name}")
        ->run();

      $this->say("Copied {$name} Drush commands to deploy directory.");
    }
  }

  /**
   * Get the origin tags and return them as their corresponding artifacts.
   *
   * @return array
   *   Array of artifact tag references in the form of refs/tags/semver-build.
   *
   * @throws \Robo\Exception\TaskException
   */
  protected function getOriginTagsAsArtifacts() {
    $result = $this->taskExecStack()
      ->exec('git ls-remote --tags --refs origin')
      ->stopOnFail()
      ->silent(TRUE)
      ->run();

    $output = $result->getMessage();
    $heads = explode(PHP_EOL, $output);
    $tags = [];

    // Get the semantic version of the tag as a string.
    foreach ($heads as $head) {
      $tag = explode("refs/tags/", $head)[1];
      $tags[] = $tag;
    }

    // Sort the tags in reverse order, i.e. newest to oldest.
    $tags = Semver::rsort($tags);

    // Iterate each tag and append it as an artifact variant.
    $artifacts = [];

    foreach ($tags as $tag) {
      $artifacts[] = "refs/tags/{$tag}-build";
    }

    return $artifacts;
  }

}
