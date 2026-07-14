<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\ReportUsersCommand;

/**
 * Unit tests for the users report's drush-output parsing.
 *
 * The buildRows() method turns a site's `users:list --format=json` output into
 * report rows; the helpers reduce roles to the editorial set, format the login,
 * and classify the no-users-found result. Pure logic: no drush or SSH.
 *
 * @group unit
 */
class ReportUsersTest extends UnitTestCase {

  /**
   * A ReportUsersCommand subclass that exposes its protected methods.
   */
  private function command(): object {
    return new class extends ReportUsersCommand {

      /**
       * {@inheritdoc}
       */
      public function rows(string $domain, string $output, array $exclude = []): array {
        return $this->buildRows($domain, $output, $exclude);
      }

      /**
       * {@inheritdoc}
       */
      public function roles(mixed $roles): string {
        return $this->extractRoles($roles);
      }

      /**
       * {@inheritdoc}
       */
      public function login(mixed $timestamp): string {
        return $this->formatLogin($timestamp);
      }

      /**
       * {@inheritdoc}
       */
      public function noUsers(string $error): bool {
        return $this->isNoUsersError($error);
      }

    };
  }

  /**
   * A user with the given fields, as `users:list --format=json` records them.
   */
  private function user(int $uid, string $mail, array $roles, int $login): array {
    return [
      'uid' => $uid,
      'mail' => $mail,
      'roles' => $roles,
      'user_login' => $login,
    ];
  }

  /**
   * Each qualifying user becomes one row for the site.
   */
  public function testBuildsOneRowPerUser(): void {
    $login = strtotime('2025-06-15 12:00:00 UTC');
    $json = json_encode([
      '5' => $this->user(5, 'jdoe@uiowa.edu', ['authenticated', 'editor', 'publisher'], $login),
      '6' => $this->user(6, 'asmith@uiowa.edu', ['authenticated', 'webmaster'], $login),
    ]);

    $rows = $this->command()->rows('cs.uiowa.edu', $json);

    $this->assertSame([
      ['jdoe@uiowa.edu', 'cs.uiowa.edu', 'editor, publisher', '2025-06-15'],
      ['asmith@uiowa.edu', 'cs.uiowa.edu', 'webmaster', '2025-06-15'],
    ], $rows);
  }

  /**
   * User 1 (the platform superuser) is never reported.
   */
  public function testSkipsUidOne(): void {
    $json = json_encode([
      '1' => $this->user(1, 'admin@uiowa.edu', ['webmaster'], strtotime('2025-01-01 12:00:00 UTC')),
      '5' => $this->user(5, 'jdoe@uiowa.edu', ['editor'], strtotime('2025-01-01 12:00:00 UTC')),
    ]);

    $rows = $this->command()->rows('cs.uiowa.edu', $json);

    $this->assertCount(1, $rows);
    $this->assertSame('jdoe@uiowa.edu', $rows[0][0]);
  }

  /**
   * Excluded emails are omitted, case-insensitively.
   */
  public function testExcludesUsers(): void {
    $login = strtotime('2025-06-15 12:00:00 UTC');
    $json = json_encode([
      '5' => $this->user(5, 'jdoe@uiowa.edu', ['editor'], $login),
      '6' => $this->user(6, 'asmith@uiowa.edu', ['webmaster'], $login),
    ]);

    $rows = $this->command()->rows('cs.uiowa.edu', $json, ['jdoe@uiowa.edu']);

    $this->assertCount(1, $rows);
    $this->assertSame('asmith@uiowa.edu', $rows[0][0]);
  }

  /**
   * A record with no email is skipped.
   */
  public function testSkipsUsersWithoutEmail(): void {
    $json = json_encode([
      '5' => $this->user(5, '', ['editor'], strtotime('2025-06-15 12:00:00 UTC')),
    ]);

    $this->assertSame([], $this->command()->rows('cs.uiowa.edu', $json));
  }

  /**
   * Leading connection chatter before the JSON is tolerated.
   */
  public function testStripsLeadingChatter(): void {
    $login = strtotime('2025-06-15 12:00:00 UTC');
    $json = json_encode(['5' => $this->user(5, 'jdoe@uiowa.edu', ['editor'], $login)]);
    $output = " [notice] Connecting to prod...\n" . $json;

    $rows = $this->command()->rows('cs.uiowa.edu', $output);

    $this->assertCount(1, $rows);
    $this->assertSame('jdoe@uiowa.edu', $rows[0][0]);
  }

  /**
   * Unparseable output yields no rows rather than an error.
   */
  public function testUnparseableOutputYieldsNoRows(): void {
    $this->assertSame([], $this->command()->rows('cs.uiowa.edu', 'not json at all'));
  }

  /**
   * Roles are reduced to the editorial set, in ascending-privilege order.
   */
  public function testExtractRolesFiltersAndOrders(): void {
    $command = $this->command();
    // Input order is deliberately scrambled; output must be low-to-high.
    $this->assertSame('viewer, editor', $command->roles(['authenticated', 'editor', 'viewer']));
    $this->assertSame('editor, publisher, webmaster', $command->roles(['webmaster', 'publisher', 'editor', 'administrator']));
    // Non-editorial roles alone produce nothing.
    $this->assertSame('', $command->roles(['authenticated', 'sign_manager']));
  }

  /**
   * A delimited roles string is accepted as well as an array.
   */
  public function testExtractRolesAcceptsString(): void {
    $command = $this->command();
    $this->assertSame('editor, publisher', $command->roles("authenticated\neditor\npublisher"));
    $this->assertSame('webmaster', $command->roles('administrator, webmaster'));
    $this->assertSame('', $command->roles(NULL));
  }

  /**
   * Login timestamps format as Y-m-d; a missing login is blank.
   */
  public function testFormatLogin(): void {
    $command = $this->command();
    $this->assertSame('2025-06-15', $command->login(strtotime('2025-06-15 12:00:00 UTC')));
    $this->assertSame('', $command->login(0));
    $this->assertSame('', $command->login(NULL));
  }

  /**
   * The no-users-found result is recognized; real errors are not.
   */
  public function testIsNoUsersError(): void {
    $command = $this->command();
    $this->assertTrue($command->noUsers("In UsersCommands.php line 124:\n\n  No users found.\n"));
    $this->assertFalse($command->noUsers('Host key verification failed.'));
    $this->assertFalse($command->noUsers(''));
  }

}
