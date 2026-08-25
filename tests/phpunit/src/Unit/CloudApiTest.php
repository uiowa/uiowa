<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Response\OperationResponse;
use Drupal\Tests\UnitTestCase;
use SiteNow\Acquia\CloudApi;

/**
 * Unit tests for the Acquia Cloud API helpers.
 *
 * Covers the notification-link parsing and the two polling loops that decide
 * whether a write finished. The API is stood in for. No Acquia API access.
 *
 * @group unit
 */
class CloudApiTest extends UnitTestCase {

  /**
   * Build a links object shaped like an OperationResponse's.
   */
  private function links(string $href): object {
    return (object) ['notification' => (object) ['href' => $href]];
  }

  /**
   * The notification UUID is read from the operation's link.
   */
  public function testNotificationUuidParsesTheLink() {
    $uuid = '3d87eca7-89d1-47e2-84db-bc7ad52a9363';
    $links = $this->links("https://cloud.acquia.com/api/notifications/{$uuid}");

    $this->assertSame($uuid, CloudApi::notificationUuid($links));
  }

  /**
   * An operation with no links cannot be confirmed.
   */
  public function testNotificationUuidRejectsMissingLinks() {
    $this->assertNull(CloudApi::notificationUuid(NULL));
    $this->assertNull(CloudApi::notificationUuid((object) []));
  }

  /**
   * A link that does not end in a UUID is refused rather than requested.
   */
  public function testNotificationUuidRejectsNonUuidPath() {
    $this->assertNull(CloudApi::notificationUuid(
      $this->links('https://cloud.acquia.com/api/notifications/')
    ));
    $this->assertNull(CloudApi::notificationUuid(
      $this->links('https://cloud.acquia.com/api/notifications/not-a-uuid')
    ));
  }

  /**
   * An unauthenticated client; nothing under test reaches the API.
   *
   * Constructing a Connector emits a deprecation from the Acquia SDK on PHP
   * 8.4, which PHPUnit reports as unexpected output; mask it around the one
   * call rather than letting it mark every test risky.
   */
  private function client(): Client {
    $reporting = error_reporting(error_reporting() & ~E_DEPRECATED);

    try {
      return Client::factory(new Connector(['key' => 'test', 'secret' => 'test']));
    }
    finally {
      error_reporting($reporting);
    }
  }

  /**
   * A CloudApi whose polling loops are reachable and answered from a script.
   *
   * @param string[] $statuses
   *   Notification statuses to return in order, or exception messages when
   *   prefixed with 'throw:'.
   * @param int $timeout
   *   Seconds the loops may wait. Zero makes a still-present resource time out
   *   on the first pass.
   */
  private function api(array $statuses = [], int $timeout = 0): CloudApi {
    return new class($this->client(), $statuses, $timeout) extends CloudApi {

      /**
       * Constructs the double.
       */
      public function __construct(Client $client, private array $statuses, int $timeout) {
        parent::__construct($client, $timeout, 0);
      }

      /**
       * Exposes awaitOperation().
       */
      public function pubAwaitOperation(OperationResponse $operation, string $label): void {
        $this->awaitOperation($operation, $label);
      }

      /**
       * Exposes confirmAbsent().
       */
      public function pubConfirmAbsent(callable $present, string $label): void {
        $this->confirmAbsent($present, $label);
      }

      /**
       * Answers notification reads from the script.
       */
      protected function notificationStatus(string $uuid): string {
        $next = array_shift($this->statuses) ?? 'completed';

        if (str_starts_with($next, 'throw:')) {
          throw new \RuntimeException(substr($next, 6));
        }

        return $next;
      }

    };
  }

  /**
   * An operation carrying a usable notification link.
   */
  private function operation(): OperationResponse {
    return new OperationResponse((object) [
      'message' => 'test',
      '_links' => $this->links('https://cloud.acquia.com/api/notifications/3d87eca7-89d1-47e2-84db-bc7ad52a9363'),
    ]);
  }

  // --- awaitOperation ---------------------------------------------------

  /**
   * A completed operation returns without incident.
   */
  public function testAwaitAcceptsCompletedOperations() {
    $this->api(['completed'])->pubAwaitOperation($this->operation(), 'Delete of thing');

    $this->assertTrue(TRUE);
  }

  /**
   * An operation still running is polled until it leaves in-progress.
   */
  public function testAwaitPollsWhileInProgress() {
    $api = $this->api(['in-progress', 'in-progress', 'completed'], 60);

    $api->pubAwaitOperation($this->operation(), 'Delete of thing');

    $this->assertTrue(TRUE);
  }

  /**
   * Any terminal status other than completed is a failure.
   */
  public function testAwaitRefusesFailedOperations() {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("did not succeed: Acquia reported status 'failed'");

    $this->api(['failed'])->pubAwaitOperation($this->operation(), 'Delete of thing');
  }

  /**
   * An operation still in progress at the deadline times out.
   */
  public function testAwaitTimesOut() {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Timed out after 0s waiting for Delete of thing');

    $this->api(['in-progress'])->pubAwaitOperation($this->operation(), 'Delete of thing');
  }

  /**
   * An unreadable notification is reported rather than retried forever.
   */
  public function testAwaitReportsAnUnreadableNotification() {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot confirm Delete of thing: gateway timeout');

    $this->api(['throw:gateway timeout'])->pubAwaitOperation($this->operation(), 'Delete of thing');
  }

  /**
   * An operation with no notification link is skipped, not failed.
   */
  public function testAwaitSkipsAnOperationWithNoLink() {
    $operation = new OperationResponse((object) ['message' => 'test', '_links' => (object) []]);

    $this->api()->pubAwaitOperation($operation, 'Delete of thing');

    $this->assertTrue(TRUE);
  }

  // --- confirmAbsent ----------------------------------------------------

  /**
   * A resource already gone confirms immediately.
   */
  public function testConfirmAbsentAcceptsAnAbsentResource() {
    $this->api()->pubConfirmAbsent(fn() => FALSE, 'database foo on uiowa09');

    $this->assertTrue(TRUE);
  }

  /**
   * The probe repeats until the resource stops being reported.
   */
  public function testConfirmAbsentPollsUntilTheResourceGoes() {
    $answers = [TRUE, TRUE, FALSE];

    $this->api([], 60)->pubConfirmAbsent(
      function () use (&$answers) {
        return array_shift($answers);
      },
      'database foo on uiowa09'
    );

    $this->assertSame([], $answers);
  }

  /**
   * A resource still reported at the deadline times out.
   */
  public function testConfirmAbsentTimesOut() {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Timed out after 0s confirming database foo on uiowa09 was deleted');

    $this->api()->pubConfirmAbsent(fn() => TRUE, 'database foo on uiowa09');
  }

  /**
   * A listing that cannot be read is reported, not read as absence.
   */
  public function testConfirmAbsentReportsAnUnreadableListing() {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot confirm database foo on uiowa09 was deleted: 500 error');

    $this->api()->pubConfirmAbsent(
      fn() => throw new \RuntimeException('500 error'),
      'database foo on uiowa09'
    );
  }

}
