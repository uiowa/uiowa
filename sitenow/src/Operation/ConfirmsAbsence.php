<?php

namespace SiteNow\Operation;

/**
 * Confirms that a deleted cloud resource is really gone.
 */
trait ConfirmsAbsence {

  /**
   * Poll until a resource stops being reported, or fail.
   *
   * A Cloud API write returns once the request is accepted, and the
   * notification link that would let a caller poll for completion is not
   * always present. The authority on whether a delete finished is therefore
   * the resource no longer being listed.
   *
   * @param callable $present
   *   Returns TRUE while the resource is still listed.
   * @param string $label
   *   What is being confirmed, used in error messages (e.g. "database foo on
   *   uiowa09").
   * @param int $timeout
   *   Seconds to wait before giving up.
   * @param int $interval
   *   Seconds between polls.
   *
   * @throws \RuntimeException
   *   If the resource is still listed when the timeout expires, or if the
   *   listing cannot be read.
   */
  protected function confirmAbsent(callable $present, string $label, int $timeout, int $interval): void {
    $deadline = time() + $timeout;

    while (TRUE) {
      try {
        $still_there = (bool) $present();
      }
      catch (\Exception $e) {
        throw new \RuntimeException("Cannot confirm {$label} was deleted: {$e->getMessage()}", 0, $e);
      }

      if (!$still_there) {
        return;
      }

      if (time() >= $deadline) {
        throw new \RuntimeException("Timed out after {$timeout}s confirming {$label} was deleted; Acquia still reports it. Nothing further was deleted.");
      }

      sleep($interval);
    }
  }

}
