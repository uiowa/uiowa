<?php

namespace SiteNow\Traits;

/**
 * Posts a message to Slack when a webhook is configured.
 *
 * The webhook URL comes from the SLACK_WEBHOOK_URL environment variable. A
 * notification is never load-bearing: this reports why one was skipped or
 * failed and leaves it to the caller to record, so a silent notification stays
 * diagnosable without ever failing the command that sent it.
 *
 * @todo deploy:update has its own copy of this; fold it in once this has run a
 *   few releases (it is on the deploy path, so not in the same change).
 */
trait NotifiesSlack {

  /**
   * Post a message to the configured Slack webhook.
   *
   * @param string $message
   *   The message text.
   * @param string $emoji
   *   The icon emoji, conventionally reflecting the worst outcome reported.
   * @param string $username
   *   The name the message posts under.
   *
   * @return string|null
   *   NULL when the message was delivered; otherwise why it was not.
   */
  protected function notifySlack(string $message, string $emoji = ':information_source:', string $username = 'SiteNow'): ?string {
    $webhook = getenv('SLACK_WEBHOOK_URL');
    if (!$webhook) {
      return 'SLACK_WEBHOOK_URL not set';
    }

    $payload = json_encode([
      'username' => $username,
      'text' => $message,
      'icon_emoji' => $emoji,
    ]);

    // Cap the time spent so a slow webhook cannot stall the command.
    $ch = curl_init($webhook);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === FALSE || $status < 200 || $status >= 300) {
      return $error !== '' ? $error : "HTTP {$status}";
    }

    return NULL;
  }

}
