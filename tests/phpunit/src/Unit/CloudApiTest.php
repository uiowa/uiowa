<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Acquia\CloudApi;

/**
 * Unit tests for the Acquia Cloud API helpers.
 *
 * Covers the notification-link parsing the cloud waiter uses to follow an
 * operation. No Acquia API access.
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

}
