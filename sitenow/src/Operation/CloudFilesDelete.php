<?php

namespace SiteNow\Operation;

use Symfony\Component\Process\Process;

/**
 * Deletes a multisite's files directory on one Acquia environment.
 *
 * Removes the site's whole directory from the environment's shared mount, not
 * only its contents. BLT's umd emptied `files/*` and `files-private/*` and left
 * the parent directory in place, which is why hosts deleted long ago still have
 * directories (holding their .htaccess files) on the mounts.
 *
 * The mount is addressed by the environment's real name, which is not always
 * the drush alias name: uiowa07-09 call their middle environment 'stage' while
 * uiowa01-06 call it 'test'. The caller resolves that from the site's drush
 * alias and passes it as $mount.
 */
class CloudFilesDelete {

  /**
   * Root of the shared filesystem mounts on Acquia.
   */
  const MOUNT_ROOT = '/mnt/gfs';

  /**
   * Constructs the operation.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root, where the drush binary lives.
   * @param string $alias
   *   The site's drush alias without the leading '@' (e.g. "sitesfoo.prod").
   * @param string $mount
   *   Application and real environment name (e.g. "uiowa09.stage").
   * @param string $directory
   *   The site directory to delete (e.g. "foo.sites.uiowa.edu").
   * @param int $timeout
   *   Seconds allowed for the remote command; a large files directory on a
   *   shared mount can take minutes to unlink.
   *
   * @throws \InvalidArgumentException
   *   If the mount or directory is not a value that can be safely interpolated
   *   into a remote rm command.
   */
  public function __construct(
    private string $repoRoot,
    private string $alias,
    private string $mount,
    private string $directory,
    private int $timeout = 900,
  ) {
    // The remote command is an rm -rf against an interpolated path, so the two
    // interpolated parts are validated up front: a directory of '', '.' or '*'
    // would delete every site's files on the environment.
    if (!preg_match('/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/i', $this->directory) || str_contains($this->directory, '..')) {
      throw new \InvalidArgumentException("Refusing to delete files for unsafe site directory '{$this->directory}'.");
    }

    if (!preg_match('/^[a-z0-9]+\.[a-z0-9]+$/i', $this->mount)) {
      throw new \InvalidArgumentException("Refusing to delete files on unsafe mount '{$this->mount}'.");
    }
  }

  /**
   * The absolute path this operation deletes.
   *
   * @return string
   *   The site's directory on the environment's mount.
   */
  public function path(): string {
    return self::MOUNT_ROOT . "/{$this->mount}/sites/{$this->directory}";
  }

  /**
   * Whether the site directory is present on the mount.
   *
   * Probing with find rather than test keeps this a single remote command with
   * no shell operators, and reports absence as empty output instead of relying
   * on an exit status that also covers connection failures.
   *
   * @return bool
   *   TRUE if the directory exists on the environment.
   *
   * @throws \RuntimeException
   *   If the environment cannot be reached.
   */
  public function exists(): bool {
    $process = $this->remote(['find', $this->path(), '-maxdepth', '0']);

    // A non-zero exit means the path does not exist, which is a legitimate
    // answer here; only a failure to reach the environment is an error.
    $error = $process->getErrorOutput();

    if (!$process->isSuccessful() && !str_contains($error, 'No such file or directory')) {
      throw new \RuntimeException("Cannot check files for {$this->directory} on {$this->mount}: {$error}");
    }

    return str_contains($process->getOutput(), $this->path());
  }

  /**
   * Delete the site directory, then confirm it is gone.
   *
   * Idempotent: a directory that is already absent is a success, so a retry
   * after a partial run does not fail here.
   *
   * @throws \RuntimeException
   *   If the remote command fails, or if the directory is still present after
   *   it reported success.
   */
  public function run(): void {
    if (!$this->exists()) {
      return;
    }

    $process = $this->remote(['rm', '-rf', $this->path()]);

    if (!$process->isSuccessful()) {
      throw new \RuntimeException("Failed to delete files for {$this->directory} on {$this->mount}: " . $process->getErrorOutput());
    }

    // The remote rm reports success for a path it never touched, so the delete
    // is confirmed by looking again rather than by trusting the exit status.
    if ($this->exists()) {
      throw new \RuntimeException("Deleted files for {$this->directory} on {$this->mount}, but {$this->path()} is still present.");
    }
  }

  /**
   * Run one command on the environment over drush ssh.
   *
   * @param string[] $command
   *   The remote command and its arguments. Passed as a single argument to
   *   drush ssh, so it must contain no shell operators.
   *
   * @return \Symfony\Component\Process\Process
   *   The finished process.
   */
  private function remote(array $command): Process {
    $process = new Process(
      [
        "{$this->repoRoot}/vendor/bin/drush",
        '--no-ansi',
        "@{$this->alias}",
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
