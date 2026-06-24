<?php

namespace SiteNow\Robo\Plugin\Commands;

use Robo\Tasks;
use Robo\Symfony\ConsoleIO;

/**
 * Accessibility audit commands using Siteimprove Alfa CLI.
 *
 * Requires Node 20+. @see https://github.com/Siteimprove/alfa.
 */
class SiteimproveCommands extends Tasks {

  const ALFA_CLI = '@siteimprove/alfa-cli@0.81.4';

  /**
   * WCAG 2.2 Level A and AA success criterion fragment IDs.
   *
   * Used to filter audit results to our default conformance target.
   * Mirrors the Conformance.isAA() filter in alfa's JS API.
   * Note: 4.1.1 (parsing) was removed from WCAG 2.2.
   *
   * @see https://www.w3.org/TR/WCAG22/
   */
  const WCAG22_AA = [
    // Level A.
    'non-text-content', 'audio-only-and-video-only-prerecorded',
    'captions-prerecorded', 'audio-description-or-media-alternative-prerecorded',
    'info-and-relationships', 'meaningful-sequence', 'sensory-characteristics',
    'use-of-color', 'audio-control', 'keyboard', 'no-keyboard-trap',
    'timing-adjustable', 'pause-stop-hide', 'three-flashes-or-below-threshold',
    'bypass-blocks', 'page-titled', 'focus-order', 'link-purpose-in-context',
    'pointer-gestures', 'pointer-cancellation', 'label-in-name', 'motion-actuation',
    'language-of-page', 'on-focus', 'on-input', 'error-identification',
    'labels-or-instructions', 'name-role-value', 'status-messages',
    'consistent-help', 'redundant-entry',
    // Level AA.
    'captions-live', 'audio-description-prerecorded', 'orientation',
    'identify-input-purpose', 'contrast-minimum', 'resize-text', 'images-of-text',
    'reflow', 'non-text-contrast', 'text-spacing', 'content-on-hover-or-focus',
    'multiple-ways', 'headings-and-labels', 'focus-visible', 'language-of-parts',
    'consistent-navigation', 'consistent-identification', 'error-suggestion',
    'error-prevention-legal-financial-data', 'focus-appearance',
    'dragging-movements', 'target-size-minimum',
    'accessible-authentication-minimum',
  ];

  /**
   * Run an accessibility audit against a URL using Siteimprove Alfa CLI.
   *
   * Defaults to WCAG 2.2 A and AA conformance. Use --all to audit all rules.
   * Reports both failures and cant-tell outcomes.
   *
   * Examples:
   *   vendor/bin/robo si:audit https://home.ddev.site/
   *   vendor/bin/robo si:audit https://home.ddev.site/ --rules=sia-r65
   *   vendor/bin/robo si:audit https://home.ddev.site/ --all
   *
   * Note: authenticated pages are not supported.
   *
   * @param string $url
   *   The URL of the page to audit.
   * @param array $opts
   *   Options.
   *
   * @option $rules Comma-separated rule IDs to filter (e.g. sia-r65, sia-r111).
   * @option $all Audit all rules, not just WCAG 2.2 A and AA.
   *
   * @command si:audit
   */
  public function siAudit(
    ConsoleIO $io,
    string $url,
    array $opts = [
      'rules' => '',
      'all' => FALSE,
    ],
  ): int {
    $io->title('Siteimprove Alfa Audit');
    $io->say("URL: $url");
    if (!$opts['all']) {
      $io->say('Conformance: WCAG 2.2 A and AA (use --all to audit all rules)');
    }

    $nvmBin = getenv('NVM_BIN');
    $npx = $nvmBin ? "$nvmBin/npx" : 'npx';
    $raw = shell_exec(escapeshellcmd($npx) . ' ' . self::ALFA_CLI . ' audit ' . escapeshellarg($url) . ' 2>/dev/null');

    if (empty($raw)) {
      $io->error('Audit produced no output. Ensure Node 20+ is active (nvm use 20).');
      return 1;
    }

    $data = json_decode($raw, TRUE);

    if (!isset($data['@graph'])) {
      $io->error('Audit output could not be parsed:');
      $io->writeln($raw);
      return 1;
    }

    // Build array of rule ID -> WCAG criterion fragments from TestCase objects.
    $ruleCriteria = [];
    foreach ($data['@graph'] as $item) {
      if (!in_array('TestCase', (array) ($item['@type'] ?? []))) {
        continue;
      }
      $ruleId = basename($item['@id'] ?? '');
      $isPartOf = $item['isPartOf'] ?? [];
      // JSON-LD omits the wrapping array when there is only one value.
      if (isset($isPartOf['@id'])) {
        $isPartOf = [$isPartOf];
      }
      foreach ($isPartOf as $part) {
        if (preg_match('/^WCAG2\d*:(.+)$/', $part['@id'] ?? '', $m)) {
          $ruleCriteria[$ruleId][] = $m[1];
        }
      }
    }

    $filterRules = array_filter(array_map('trim', explode(',', $opts['rules'])));
    $outcomeFilter = ['earl:failed', 'earl:cantTell'];

    $assertions = array_filter($data['@graph'], function ($item) use ($filterRules, $outcomeFilter, $ruleCriteria, $opts) {
      if (($item['@type'] ?? '') !== 'Assertion') {
        return FALSE;
      }
      if (!in_array($item['result']['outcome'] ?? '', $outcomeFilter)) {
        return FALSE;
      }
      $ruleId = basename($item['test']['@id'] ?? '');
      if ($filterRules) {
        return in_array($ruleId, $filterRules);
      }
      if (!$opts['all']) {
        return !empty(array_intersect($ruleCriteria[$ruleId] ?? [], self::WCAG22_AA));
      }
      return TRUE;
    });

    if (empty($assertions)) {
      $io->success('No issues found.');
      return 0;
    }

    $failures = array_filter($assertions, fn($a) => ($a['result']['outcome'] ?? '') === 'earl:failed');
    $uncertain = array_filter($assertions, fn($a) => ($a['result']['outcome'] ?? '') === 'earl:cantTell');

    if (!empty($failures)) {
      $this->printGroup($io, $failures, 'FAILED', 'error');
    }
    if (!empty($uncertain)) {
      $this->printGroup($io, $uncertain, 'UNCERTAIN', 'comment');
    }

    $io->writeln(sprintf('<error> %d failure(s) </error> <comment> %d cant-tell(s) </comment>', count($failures), count($uncertain)));
    return empty($failures) ? 0 : 1;
  }

  /**
   * Prints assertions grouped by rule ID.
   */
  protected function printGroup(ConsoleIO $io, array $assertions, string $label, string $style): void {
    $byRule = [];
    foreach ($assertions as $a) {
      $ruleId = basename($a['test']['@id'] ?? 'unknown');
      $byRule[$ruleId][] = $a['result']['info'] ?? '(no info)';
    }

    $io->section("$label (" . count($assertions) . ')');

    foreach ($byRule as $ruleId => $messages) {
      $io->writeln("<$style>[$ruleId]</$style> https://alfa.siteimprove.com/rules/$ruleId");
      foreach (array_unique($messages) as $message) {
        $io->writeln("  - $message");
      }
      $io->newLine();
    }
  }

}
