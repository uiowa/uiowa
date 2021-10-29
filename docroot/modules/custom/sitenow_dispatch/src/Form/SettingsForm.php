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
   * @var Dispatch
   */
  protected $dispatch;

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
  public function __construct(ConfigFactoryInterface $config_factory, $dispatch) {
    parent::__construct($config_factory);
    $this->dispatch = $dispatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('sitenow_dispatch.dispatch')
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
      '#markup' => '<p><a href="https://its.uiowa.edu/dispatch">Dispatch</a> is a web service that allows users to create and manage campaigns to generate PDF content, email messages, SMS messages, or voice calls. You must have a Dispatch <a href="https://apps.its.uiowa.edu/dispatch/help/faq#q1">client and account</a> to use Dispatch functionality within your site.</p>',
    ];

    $form['api_key'] = [
      '#type' => 'password',
      '#required' => TRUE,
      '#title' => $this->t('API key'),
      '#attributes' => [
        'value' => $api_key,
      ],
      '#description' => $this->t('A valid Dispatch client API key. See the Dispatch <a href="https://apps.its.uiowa.edu/dispatch/help/api">API key documentation</a> for more information.'),
    ];

    if ($api_key && $client = $config->get('client')) {
      $form['api_key']['#description'] .= $this->t(' <em>Currently set to @client client</em>.', [
        '@client' => $client,
      ]);
    }

    // Grab the current user to set access to the Thanks
    // form settings only for administrators.
    $current_user = $this->currentUser();
    $form['thanks'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Thank You Email'),
      '#description' => $this->t('Thank You email configuration. The <a href="@link">1row x 1col curated Dispatch template</a> is used.', [
        '@link' => 'https://apps.its.uiowa.edu/dispatch/help/curatedtemplate/UI%201%20row%20x%201%20col%20-%20Version%202',
      ]),
      '#access' => in_array('administrator', $current_user->getRoles()),
      '#collapsible' => TRUE,
    ];

    // The thank you form requires a separate key set for csi.drupal to not
    // expose our communications with clients. It also requires an HR token.
    // Both of these should be set in configuration overrides.
    if ($api_key && $config->get('thanks.hr_token')) {
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
        '#default_value' => $config->get('thanks.placeholder.title') ?? $this->t('Thank You'),
        '#required' => TRUE,
        '#description' => $this->t('Used as the email subject and title.'),
      ];

      $form['thanks']['placeholder']['unit'] = [
        '#type' => 'textfield',
        '#title' => $this->t('College/Unit'),
        '#default_value' => $config->get('thanks.placeholder.unit'),
        '#required' => TRUE,
        '#description' => $this->t('The unit or college name to display in the email header.'),
      ];

      // @todo Change this to dependency injection.
      if ($config->get('thanks.placeholder.banner_image')) {
        $media = \Drupal::service('entity_type.manager')
          ->getStorage('media')
          ->load($config->get('thanks.placeholder.banner_image'));
      }
      // @todo Update the form type.
      $form['thanks']['placeholder']['banner_image'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'media',
        '#title' => $this->t('Banner Image'),
        '#default_value' => (isset($media)) ? $media : NULL,
        '#selection_settings' => [
          'target_bundles' => [
            'image',
          ],
        ],
        // @todo Update to required if we have a default image.
        //   But maybe we don't need to replace the default image
        //   in Dispatch if we don't have a better one.
        '#required' => FALSE,
        '#description' => $this->t('The banner image to display at the top of the email body.'),
      ];

      $form['thanks']['placeholder']['row1_heading'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Row 1 Heading'),
        '#default_value' => $config->get('thanks.placeholder.row1_heading'),
        '#required' => TRUE,
        '#description' => $this->t('The heading to display in the email body.'),
      ];
    }
    else {
      $form['thanks']['#description'] = $this->t('The Thank You email is not configured properly. Please contact support.');
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $api_key = trim($form_state->getValue('api_key'));
    $response = $this->dispatch->getFromDispatch('https://apps.its.uiowa.edu/dispatch/api/v1/client', $api_key);

    // If the response is empty, we have an invalid API key.
    if ($response == []) {
      $form_state->setErrorByName('api_key', 'Invalid API key, please verify that your API key is correct and try again.');
    }
    else {
      $form_state->setValue('client', $response->name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo Clean this up and change to DI.
    $media = \Drupal::service('entity_type.manager')
      ->getStorage('media')
      ->load($form_state->getValue(['thanks', 'placeholder', 'banner_image']));
    $uri = $media->get('field_media_image')->entity->uri->value;
    // @todo Change this to get a specific responsive image style of
    //   the image, rather than a direct file URL. Maybe?
    $image_url = file_create_url($uri);

    // @todo Separate Dispatch config and other settings config.
    //   Currently we have both the media id and the URL.
    $this->config('sitenow_dispatch.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('client', $form_state->getValue('client'))
      ->set('thanks', [
        'campaign' => $form_state->getValue(['thanks', 'campaign']),
        'communication' => $form_state->getValue(['thanks', 'communication']),
        'placeholder' => $form_state->getValue(['thanks', 'placeholder']),
      ]);
    $this->config('sitenow_dispatch.settings')
      ->set('thanks.placeholder.banner_image_url', $image_url)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
