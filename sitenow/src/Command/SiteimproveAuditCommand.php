<?php

namespace SiteNow\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Accessibility audit using the Siteimprove Alfa CLI.
 *
 * This runs Alfa, the open-source rule engine behind Siteimprove's automated
 * accessibility checks, against a single rendered URL. It catches the
 * deterministic DOM/ARIA rules (e.g. sia-r17) but is NOT a replacement for a
 * full Siteimprove crawl: the hosted product layers heuristic/NLP analysis,
 * PDF/readability checks, authenticated crawling and some rules the CLI does
 * not emit. Treat a clean run here as "the deterministic subset passed," not
 * "Siteimprove is clean."
 *
 * Requires Node 20+. SiteNow is moving its Node floor to 22+ (#10246); the
 * DDEV web container already runs a new enough Node, so the reliable way to
 * run this is inside the container:
 *   ddev exec ./sn si:audit https://education.uiowa.ddev.site/
 *
 * @see https://github.com/Siteimprove/alfa
 * @see https://alfa.siteimprove.com/rules
 */
#[AsCommand(
  name: 'si:audit',
  description: 'Run a Siteimprove Alfa accessibility audit against a URL.',
  aliases: ['audit'],
)]
class SiteimproveAuditCommand extends Command {

  /**
   * Pinned Alfa CLI version.
   *
   * The audit command and the EARL formatter are separate packages; the
   * formatter must be installed alongside the CLI or the default earl output
   * fails to load.
   */
  const ALFA_CLI = '@siteimprove/alfa-cli@0.81.4';
  const ALFA_FORMATTER_EARL = '@siteimprove/alfa-formatter-earl@0.81.4';

  /**
   * Minimum Node major version Alfa CLI supports.
   */
  const NODE_MIN_MAJOR = 20;

  /**
   * WCAG 2.2 Level A and AA success criterion fragment IDs.
   *
   * Used to filter audit results to our default conformance target. Mirrors
   * the Conformance.isAA() filter in Alfa's JS API. 4.1.1 (parsing) was
   * removed from WCAG 2.2.
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
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->addArgument('url', InputArgument::REQUIRED, 'The URL of the page to audit (publicly reachable; authenticated pages are not supported).')
      ->addOption('rules', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated rule IDs to filter (e.g. sia-r17,sia-r111).', '')
      ->addOption('all', NULL, InputOption::VALUE_NONE, 'Audit all rules, not just WCAG 2.2 A and AA.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $url = $input->getArgument('url');
    $all = (bool) $input->getOption('all');

    $io->title('Siteimprove Alfa Audit');
    $io->text("URL: $url");
    $io->text($all
      ? 'Conformance: all rules'
      : 'Conformance: WCAG 2.2 A and AA (use --all to audit all rules)');
    $io->newLine();

    if (($node = $this->checkNode($io)) === FALSE) {
      return Command::FAILURE;
    }

    $io->text("Running audit (Node $node). First run downloads the Alfa packages; this can take a minute...");

    $cmd = sprintf(
      'npx -y -p %s -p %s alfa audit %s 2>/dev/null',
      escapeshellarg(self::ALFA_CLI),
      escapeshellarg(self::ALFA_FORMATTER_EARL),
      escapeshellarg($url),
    );
    $raw = shell_exec($cmd);

    if (empty($raw)) {
      $io->error('Audit produced no output. Ensure the URL is reachable and Node ' . self::NODE_MIN_MAJOR . '+ is active.');
      return Command::FAILURE;
    }

    $data = json_decode($raw, TRUE);
    if (!isset($data['@graph'])) {
      $io->error('Audit output could not be parsed:');
      $io->writeln(substr($raw, 0, 2000));
      return Command::FAILURE;
    }

    // Map rule ID -> WCAG criterion fragments from the TestCase objects.
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

    $filterRules = array_filter(array_map('trim', explode(',', (string) $input->getOption('rules'))));
    $outcomeFilter = ['earl:failed', 'earl:cantTell'];

    $assertions = array_filter($data['@graph'], function ($item) use ($filterRules, $outcomeFilter, $ruleCriteria, $all) {
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
      if (!$all) {
        return !empty(array_intersect($ruleCriteria[$ruleId] ?? [], self::WCAG22_AA));
      }
      return TRUE;
    });

    if (empty($assertions)) {
      $io->success('No issues found.');
      return Command::SUCCESS;
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
    return empty($failures) ? Command::SUCCESS : Command::FAILURE;
  }

  /**
   * Verifies the active Node version meets the Alfa CLI floor.
   *
   * @return string|false
   *   The detected version string on success, or FALSE if too old/missing.
   */
  protected function checkNode(SymfonyStyle $io): string|false {
    $version = trim((string) shell_exec('node -v 2>/dev/null'));
    if ($version === '') {
      $io->error('Node is not available on PATH. Run inside DDEV: ddev exec ./sn si:audit <url>');
      return FALSE;
    }
    if (preg_match('/^v(\d+)/', $version, $m) && (int) $m[1] < self::NODE_MIN_MAJOR) {
      $io->error(sprintf(
        'Node %s is too old; Alfa CLI needs %d+. Run inside DDEV (ddev exec ./sn si:audit <url>) or `nvm use 22`.',
        $version,
        self::NODE_MIN_MAJOR,
      ));
      return FALSE;
    }
    return $version;
  }

  /**
   * Prints assertions grouped by rule ID.
   */
  protected function printGroup(SymfonyStyle $io, array $assertions, string $label, string $style): void {
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