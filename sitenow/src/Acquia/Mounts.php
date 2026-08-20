<?php

namespace SiteNow\Acquia;

use Symfony\Component\Process\Process;

/**
 * Reads and deletes multisite directories on Acquia's shared filesystem.
 *
 * Reached over drush ssh, since the Cloud API exposes no files endpoint.
 *
 * @todo Acquia reprovisions a site's directory from the deployed sites.php
 *   within about a minute of its removal, and never prunes one whose host has
 *   left sites.php. Deleting here clears the site's content but the empty
 *   directory returns until the deprovision merges, and then persists. See
 *   #10076.
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
   *   Application and Acquia environment name (e.g. "uiowa09.stage"), which is
   *   not always the drush alias environment name. Resolved by the caller.
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

    // 'default' passes the pattern above but is the shared directory every
    // application serves its own site from, and a manifest host can resolve to
    // it through sites.php. The command checks for this too; refusing here as
    // well means no caller can reach the remote rm by another route.
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
   * Probing with find rather than test keeps this a single remote command with
   * no shell operators, and reports absence as empty output instead of relying
   * on an exit status that also covers connection failures.
   *
   * @param string $alias
   *   The site's drush alias without the leading '@' (e.g. "sitesfoo.prod").
   * @param string $mount
   *   Application and Acquia environment name.
   * @param string $directory
   *   The site directory.
   *
   * @return bool
   *   TRUE if the directory exists on the environment.
   *
   * @throws \RuntimeException
   *   If the environment cannot be reached.
   */
  public function siteDirectoryExists(string $alias, string $mount, string $directory): bool {
    $path = $this->siteDirectory($mount, $directory);
    $process = $this->remote($alias, ['find', $path, '-maxdepth', '0']);

    // A non-zero exit means the path does not exist, which is a legitimate
    // answer here; only a failure to reach the environment is an error.
    $error = $process->getErrorOutput();

    if (!$process->isSuccessful() && !str_contains($error, 'No such file or directory')) {
      throw new \RuntimeException("Cannot check files for {$directory} on {$mount}: {$error}");
    }

    return str_contains($process->getOutput(), $path);
  }

  /**
   * Delete a site's directory from an environment's mount.
   *
   * Idempotent: a directory that is already absent is a success, so a retry
   * after a partial run does not fail here.
   *
   * @param string $alias
   *   The site's drush alias without the leading '@'.
   * @param string $mount
   *   Application and Acquia environment name.
   * @param string $directory
   *   The site directory to delete.
   *
   * @throws \RuntimeException
   *   If the remote command fails, or if the directory is still present after
   *   it reported success.
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

    // The remote rm reports success for a path it never touched, so the delete
    // is confirmed by looking again rather than by trusting the exit status.
    if ($this->siteDirectoryExists($alias, $mount, $directory)) {
      throw new \RuntimeException("Deleted files for {$directory} on {$mount}, but {$path} is still present.");
    }
  }

  /**
   * Run one command on an environment over drush ssh.
   *
   * @param string $alias
   *   The site's drush alias without the leading '@'.
   * @param string[] $command
   *   The remote command and its arguments. Passed as a single argument to
   *   drush ssh, so it must contain no shell operators.
   *
   * @return \Symfony\Component\Process\Process
   *   The finished process.
   */
  private function remote(string $alias, array $command): Process {
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
