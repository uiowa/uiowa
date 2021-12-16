<?php

namespace Drupal\sitenow_dispatch\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure sitenow_dispatch settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The dispatch service.
   *
   * @var \Drupal\sitenow_dispatch\Dispatch
   */
  protected $dispatch;

  /**
   * The dispatch service.
   *
   * @var \Drupal\uiowa_core\Access\UiowaCoreAccess
   */
  protected $check;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitenow_dispatch_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sitenow_dispatch.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, $dispatch, $check, $entityTypeManager) {
    parent::__construct($config_factory);
    $this->dispatch = $dispatch;
    $this->check = $check;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('sitenow_dispatch.dispatch'),
      $container->get('uiowa_core.access_checker'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory()->get('sitenow_dispatch.settings');
    $api_key = $config->get('api_key');

    // Set the form tree to make accessing all nested values easier elsewhere.
    $form['#tree'] = TRUE;

    $form['description_text'] = [
      '#markup' => '<p><a href="https://its.uiowa.edu/dispatch">Dispatch</a> is a web service that allows users to create and manage campaigns to generate PDF content, email messages, SMS messages, or voice calls. You must have a Dispatch <a href="https://apps.its.uiowa.edu/dispatch/help/faq#q1">client and account</a> to use certain Dispatch functionality within your site.</p>',
    ];

    $form['api_key'] = [
      '#type' => 'password',
      '#title' => $this->t('API key'),
      '#attributes' => [
        'value' => $api_key,
      ],
      '#description' => $this->t('A valid Dispatch client API key. See the Dispatch <a href="https://apps.its.uiowa.edu/dispatch/help/api">API key documentation</a> for more information.'),
      '#states' => [
        'required' => [
          ':input[name="thanks[enabled]"]' => ['unchecked' => TRUE],
        ],
      ],
    ];

    if ($api_key && $client = $config->get('client')) {
      $form['api_key']['#description'] .= $this->t('&nbsp;<em>Currently set to @client client</em>.', [
        '@client' => $client,
      ]);
    }

    // Grab the current user to set access to the Thanks
    // form settings only for administrators.
    /** @var Drupal\Core\Access\AccessResultInterface $access */
    $access = $this->check->access($this->currentUser()->getAccount());
    $enabled = $config->get('thanks.enabled') ?? FALSE;

    $form['thanks'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Thank You Form'),
      '#description' => $this->t('The Thank You form creates a block you can place on a page to allow people to send an email to a University employee and their supervisor. The email uses the <a href="@link">1row x 1col curated Dispatch template</a> by default. The fields above correlate to the placeholders available in that template. The row1_content placeholder will be set to the message filled out by the person submitting the form.', [
        '@link' => 'https://apps.its.uiowa.edu/dispatch/help/curatedtemplate/UI%201%20row%20x%201%20col%20-%20Version%202',
      ]),
      '#collapsible' => TRUE,
      '#access' => $access->isAllowed() ? TRUE : $enabled,
    ];

    $form['thanks']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => 'Enabled',
      '#description' => $this->t('Only administrators can access this checkbox. Checking this will allow webmasters access to the below configuration and block.'),
      '#access' => $access->isAllowed(),
      '#default_value' => $enabled,
    ];

    // The thank you form requires a separate key set for csi.drupal to not
    // expose our communications with clients. It also requires an HR token.
    // Both of these should be set in configuration overrides.
    if ($config->get('thanks.api_key') && $config->get('thanks.hr_token')) {
      $form['thanks']['campaign'] = [
        '#type' => 'hidden',
        '#value' => 985361362,
      ];

      $form['thanks']['communication'] = [
        '#type' => 'hidden',
        '#value' => 985337764,
      ];

      $form['thanks']['placeholder']['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#required' => $enabled,
        '#default_value' => $config->get('thanks.placeholder.title') ?? $this->t('Thank You'),
        '#description' => $this->t('Used as the email subject and title.'),
      ];

      $form['thanks']['placeholder']['unit'] = [
        '#type' => 'textfield',
        '#title' => $this->t('College/Unit'),
        '#required' => $enabled,
        '#default_value' => $config->get('thanks.placeholder.unit') ?? $this->config('system.site')->get('name'),
        '#description' => $this->t('The unit or college name to display in the email header.'),
      ];

      $form['thanks']['placeholder']['row1_heading'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Row 1 Heading'),
        '#required' => $enabled,
        '#default_value' => $config->get('thanks.placeholder.row1_heading') ?? $this->t('Thank You'),
        '#description' => $this->t('The heading to display in the email body.'),
      ];

      $form['thanks']['placeholder']['unitAddress'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Unit Address'),
        '#description' => $this->t('Street address of college, unit, or department.'),
        '#default_value' => $config->get('thanks.placeholder.unitAddress'),
      ];

      // This is as a disclaimer in the various emails. It is dynamically
      // overridden for the recipient depending on the supervisor config.
      $form['thanks']['placeholder']['footer_statement'] = [
        '#type' => 'hidden',
        '#value' => $this->t('This is a copy of a Thank You form submission from the @website website.', [
          '@website' => $this->config('system.site')->get('name'),
        ]),
      ];

      $form['thanks']['supervisor'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Supervisor Email'),
        // Default to include if it hasn't been set yet.
        '#default_value' => $config->get('thanks.supervisor') ?? TRUE,
        '#required' => FALSE,
        '#description' => $this->t("If checked, copies will be sent to the employee's supervisor(s). Supervisor information will be retrieved automatically."),
      ];

      // Support multiple: https://www.drupal.org/project/drupal/issues/3214029.
      $form['thanks']['email'] = [
        '#type' => 'email',
        '#title' => $this->t('Additional email'),
        '#description' => $this->t('An additional email address to send a copy to.'),
        '#default_value' => $config->get('thanks.email'),
      ];
    }
    else {
      $form['thanks']['#description'] = $this->t('The Thank You email is not configured properly. Please contact the <a href=":link">ITS Help Desk</a>.', [
        ':link' => 'https://its.uiowa.edu/contact',
      ]);
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // It is possible to use the thank you form without an API key.
    if ($api_key = trim($form_state->getValue('api_key'))) {
      // Validate the api_key being submitted in the form rather than config.
      $client = $this->dispatch->request('GET', 'client', [], [
        'headers' => [
          'x-dispatch-api-key' => $api_key,
        ],
      ]);

      if ($client === FALSE) {
        $form_state->setErrorByName('api_key', 'Invalid API key, please verify that your API key is correct and try again.');
      }
      else {
        $form_state->setValue('api_key', $api_key);
        $form_state->setValue('client', $client->name);
      }
    }

    // Convert boolean fields for easier use in the form submission/template.
    $bools = [
      ['thanks', 'enabled'],
      ['thanks', 'supervisor'],
    ];

    foreach ($bools as $bool) {
      $form_state->setValue($bool, (bool) $form_state->getValue($bool));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('sitenow_dispatch.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('client', $form_state->getValue('client'))
      ->set('thanks', [
        'enabled' => $form_state->getValue(['thanks', 'enabled']),
        'campaign' => $form_state->getValue(['thanks', 'campaign']),
        'communication' => $form_state->getValue(['thanks', 'communication']),
        'placeholder' => $form_state->getValue(['thanks', 'placeholder']),
        'supervisor' => $form_state->getValue(['thanks', 'supervisor']),
        'email' => $form_state->getValue(['thanks', 'email']),
      ])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
