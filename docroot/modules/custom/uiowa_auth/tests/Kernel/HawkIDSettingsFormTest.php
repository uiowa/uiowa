<?php

namespace Drupal\Tests\uiowa_auth\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\uiowa_auth\Form\HawkIDSettingsForm;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Test the HawkID settings form.
 *
 * @group kernel
 */
class HawkIDSettingsFormTest extends EntityKernelTestBase {
  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['uiowa_auth', 'externalauth', 'samlauth'];

  /**
   * Test form submits with valid values.
   */
  public function testValidHawkIdForm() {
    $factory = $this->container->get('config.factory');
    $hawkid_settings_form = new HawkIDSettingsForm($factory);
    $form_state = new FormState();
    $form = [];

    $form_state->setValues([
      'role_mappings' => 'admin|urn:oid:1.2.34.5|CN=foo,OU=bar,OU=baz' . PHP_EOL . 'webmaster|urn:oid:1.2.34.5|CN=foo,OU=bar' . PHP_EOL . 'webmaster|urn:oid:1.2.34.56789.10|CN=baz,OU=qux',
    ]);

    $hawkid_settings_form->submitForm($form, $form_state);
    $this->assertEquals(FALSE, $form_state->hasAnyErrors());

    $this->assertEquals([
      'admin|urn:oid:1.2.34.5|CN=foo,OU=bar,OU=baz',
      'webmaster|urn:oid:1.2.34.5|CN=foo,OU=bar',
      'webmaster|urn:oid:1.2.34.56789.10|CN=baz,OU=qux',
    ], $factory->get('uiowa_auth.settings')->get('role_mappings'));
  }

  /**
   * Test form fails with invalid values.
   *
   * @dataProvider invalidValues
   */
  public function testInvalidHawkIdForm($mapping, $message) {
    $factory = $this->container->get('config.factory');
    $hawkid_settings_form = new HawkIDSettingsForm($factory);
    $form_state = new FormState();
    $form = [];

    $form_state->setValues([
      'role_mappings' => $mapping,
    ]);

    $hawkid_settings_form->validateForm($form, $form_state);
    $this->assertArrayHasKey('role_mappings', $form_state->getErrors());
    $this->assertEquals($message, $form_state->getErrors()['role_mappings']->render());
  }

  /**
   * Data provider for invalid values.
   *
   * @see testInvalidHawkIdForm()
   */
  public function invalidValues() {
    return [
      [
        'foo|bar',
        'Invalid role mapping foo|bar. Ensure the mapping follows the rid|attr|value format.',
      ],
      [
        'foo|OU=bar,OU=baz',
        'Invalid role mapping foo|OU=bar,OU=baz. Ensure the mapping follows the rid|attr|value format.',
      ],
      [
        'CN=foo|OU=bar,OU=baz|qux|quz',
        'Invalid role mapping CN=foo|OU=bar,OU=baz|qux|quz. Ensure the mapping follows the rid|attr|value format.',
      ],
    ];
  }

}
