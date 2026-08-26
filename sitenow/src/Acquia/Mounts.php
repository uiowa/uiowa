<?php

namespace SiteNow\Acquia;

use Symfony\Component\Process\Process;

/**
 * Reads and deletes multisite directories on Acquia's shared filesystem.
 *
 * Reached over drush ssh, since the Cloud API exposes no files endpoint.
 *
 * @todo Acquia reprovisions a deleted directory from the deployed sites.php
 *   within about a minute, and never prunes one whose host has left sites.php,
 *   so the content goes but the empty directory stays. See #10076.
 */
class Mounts {

  /**
   * Root of the shared filesystem mounts on Acquia.
   */
  const MOUNT_ROOT = '/mnt/gfs';

  /**
   * The shared site directory, never a valid delete target.
   */
  const SHARED_DIRECTORY = 'default';

  /**
   * Constructs the mount reader.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root, where the drush binary lives.
   * @param int $timeout
   *   Seconds allowed for a remote command; a large files directory on a
   *   shared mount can take minutes to unlink.
   */
  public function __construct(
    private string $repoRoot,
    private int $timeout = 900,
  ) {}

  /**
   * The absolute path a site's files occupy on an environment's mount.
   *
   * @param string $mount
   *   Application and Acquia environment name (e.g. "uiowa09.stage"), as
   *   resolved by the caller.
   * @param string $directory
   *   The site directory (e.g. "foo.sites.uiowa.edu").
   *
   * @return string
   *   The site's directory on the environment's mount.
   *
   * @throws \InvalidArgumentException
   *   If the mount or directory is not a value that can be safely interpolated
   *   into a remote rm command.
   */
  public function siteDirectory(string $mount, string $directory): string {
    // The remote command is an rm -rf against an interpolated path, so the two
    // interpolated parts are validated up front: a directory of '', '.' or '*'
    // would delete every site's files on the environment.
    if (!preg_match('/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/i', $directory) || str_contains($directory, '..')) {
      throw new \InvalidArgumentException("Refusing to delete files for unsafe site directory '{$directory}'.");
    }

    // 'default' passes the pattern above, and a manifest host can resolve to it
    // through sites.php.
    if (strtolower($directory) === self::SHARED_DIRECTORY) {
      throw new \InvalidArgumentException("Refusing to delete files for the shared '" . self::SHARED_DIRECTORY . "' site directory.");
    }

    if (!preg_match('/^[a-z0-9]+\.[a-z0-9]+$/i', $mount)) {
      throw new \InvalidArgumentException("Refusing to delete files on unsafe mount '{$mount}'.");
    }

    return self::MOUNT_ROOT . "/{$mount}/sites/{$directory}";
  }

  /**
   * Whether a site's directory is present on an environment's mount.
   *
   * One find call names both the mount's sites path and the site's directory,
   * and the answer is which one it echoes. Exit status and error text cannot
   * tell an absent directory from an unreachable mount, and reading the second
   * as the first would orphan the files.
   *
   * The site's directory is named outright rather than descended to, since the
   * mount's sites path is a symlink and find does not follow one without -L.
   *
   * @param string $alias
   *   The application's drush alias without the leading '@' (e.g.
   *   "uiowa09.prod").
   * @param string $mount
   *   Application and Acquia environment name.
   * @param string $directory
   *   The site directory.
   *
   * @return bool
   *   TRUE if the directory exists on the environment.
   *
   * @throws \RuntimeException
   *   If the environment cannot be reached, or its mount cannot be read, since
   *   neither is an answer about the site's files.
   */
  public function siteDirectoryExists(string $alias, string $mount, string $directory): bool {
    $path = $this->siteDirectory($mount, $directory);
    $parent = dirname($path);
    $process = $this->remote($alias, ['find', $parent, $path, '-maxdepth', '0']);
    $echoed = array_map('trim', explode("\n", $process->getOutput()));

    if (in_array($path, $echoed, TRUE)) {
      return TRUE;
    }

    // The mount echoed on its own is a site that has no files there, which is
    // a legitimate answer and leaves find exiting non-zero for the directory it
    // could not stat.
    if (in_array($parent, $echoed, TRUE)) {
      return FALSE;
    }

    throw new \RuntimeException("Cannot check files for {$directory} on {$mount}: " . ($process->getErrorOutput() ?: "{$parent} could not be read."));
  }

  /**
   * Delete a site's directory from an environment's mount.
   *
   * Idempotent.
   *
   * The rm's exit status is the only signal. Probing afterwards cannot confirm
   * anything, since reprovisioning also reads as present.
   *
   * @param string $alias
   *   The application's drush alias without the leading '@'.
   * @param string $mount
   *   Application and Acquia environment name.
   * @param string $directory
   *   The site directory to delete.
   *
   * @throws \RuntimeException
   *   If the remote command fails.
   */
  public function deleteSiteDirectory(string $alias, string $mount, string $directory): void {
    if (!$this->siteDirectoryExists($alias, $mount, $directory)) {
      return;
    }

    $path = $this->siteDirectory($mount, $directory);
    $process = $this->remote($alias, ['rm', '-rf', $path]);

    if (!$process->isSuccessful()) {
      throw new \RuntimeException("Failed to delete files for {$directory} on {$mount}: " . $process->getErrorOutput());
    }
  }

  /**
   * Run one command on an environment over drush ssh.
   *
   * @param string $alias
   *   The application's drush alias without the leading '@'.
   * @param string[] $command
   *   The remote command and its arguments. Passed as a single argument to
   *   drush ssh, so it must contain no shell operators.
   *
   * @return \Symfony\Component\Process\Process
   *   The finished process.
   */
  protected function remote(string $alias, array $command): Process {
    $process = new Process(
      [
        "{$this->repoRoot}/vendor/bin/drush",
        '--no-ansi',
        "@{$alias}",
        'ssh',
        implode(' ', $command),
      ],
      $this->repoRoot,
    );
    $process->setTimeout($this->timeout);
    $process->run();

    return $process;
  }

}
