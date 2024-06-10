<?php

namespace Drupal\cevalidationsr\Form;

use Drupal\cevalidationsr\CevalidationsrConnection;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Cevalidationsr Form.
 */
class CevalidationsrForm extends FormBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cevalidationsr_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get Apostille Email.
    $config = \Drupal::config('cevalidationsr.settings');
    $apostilleemail = $config->get('cevalidationsr.apostilleemail');

    // Show CHEA Logo?
    $displayCHEALogo = $config->get('cevalidationsr.displayCHEALogo');

    $form['#prefix'] = '<div class="container">';
    $form['#suffix'] = '</div>';

    $form['#attached']['library'][] = 'cevalidationsr/bootstrap-cdn';
    $form['#attached']['library'][] = 'cevalidationsr/google-fonts';
    $form['#attached']['library'][] = 'cevalidationsr/cevalidationsr.library';

    $form['credentialId'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Please enter CeDiD:'),
      '#label_attributes' => ['class' => ['labelgray']],
      '#required' => TRUE,
      '#description' => $this->t('Not case sensitive.'),
      '#attributes' => [
        'class' => ['CeDiDNumber', 'text-box', 'single-line'],
        'style' => 'width: auto',
        'data-masked-input' => ["wwww-wwww-wwww"],
        'data-mask' => ["AAAA-AAAA-AAAA"],
        'data-val' => ["true"],
        'data-val-regex' => ["____-____-____ format required."],
        'data-val-regex-pattern' => ["(([a-zA-Z0-9]{4})[-]([a-zA-Z0-9]{4})[-]([a-zA-Z0-9]{4}))"],
        'data-val-required' => ["The CeDiD field is required."],
        'placeholder' => ["____-____-____"],
      ],
      '#maxlength' => 14,
      '#size' => 14,
    ];

    // Add a button that handles the submission of the form.
    $form['actions'] = [
      '#type' => 'button',
      '#value' => $this->t('Validate'),
      '#attributes' => ['class' => ['bttn bttn--primary']],
      '#ajax' => [
        'callback' => '::setMessage',
        'wrapper' => 'result_table',
        'progress' => [
          'type' => 'throbber',
          'message' => 'Processing ...',
        ],
      ],
    ];

    $form['cecredentialtrustlogo'] = [
      '#type' => 'markup',
      '#markup' => '<div class="block-margin__bottom--extra"><div id="SULogo"><a href="https://secure.cecredentialtrust.com" target="_blank"><img id="SULogoimg1" height="50" src=https://cedimages.blob.core.windows.net/publicimages/Content/Images/CeDiplomaImages/poweredbyCeCredentialTrustLogo_180x34.png alt="Powered by CeCredential TRUST" /></a></div></div>',
    ];

    $form['error_message'] = [
      '#type' => 'markup',
      '#markup' => '<div class="block-margin__bottom" id="error-message"></div>',
    ];

    $form['result_table'] = [
      '#type' => 'markup',
      '#markup' => '<div id="divValidationResult" class="block-margin__top block-margin__bottom hidden"><div><p id="successfail_result" ></p><table id="result_table" class="table--gray-borders"><tbody id="result_tbody"></tbody></table></div></div>',
    ];

    $aposttext = '<div class="block-padding__top--minimal block-padding__bottom--minimal"><div><h2 class="h4">Apostille:</h2><p>An Apostille may neither be required nor necessary. The CeDiploma has legal standing, is non-repudiating, and can be validated through the Institution&rsquo;s website to provide absolute confidence in the credential&rsquo;s authenticity. Questions should be redirected to <a href="mailto:' . $apostilleemail . '?subject=Apostille Information Request" data-rel="external" target="_blank">' . $apostilleemail . '</a>.</p></div></div>';
    $form['apostille_info'] = [
      '#type' => 'markup',
      '#markup' => t($aposttext),
    ];

    $form['scholarRecord_result'] = [
      '#type' => 'markup',
      '#markup' => '<div><div><div id="scholarrecord_result"></div></div></div>',
    ];

    if ($displayCHEALogo) {
      $form['chea_info'] = [
        '#type' => 'markup',
        '#markup' => '<div><div><hr class="element--spacer-thin"><p class="block-padding__top--minimal" id="logoCHEA"><a href="https://www.chea.org/search-institutions" data-rel="external" target="_blank" rel="noopener noreferrer" aria-label="CHEA.org Search Institution (in tab)"><img id="CheaImageId" src="https://cedimages.blob.core.windows.net/publicimages/Content/Images/CeDiplomaImages/Logo_CHEA_100x36.png" alt="CHEA Logo"></a></p><p id="pCHEA">You may check institutional accreditation through the Council for Higher Education Accreditation (CHEA):<br><span><i>(CHEA is an independent, non-profit organization, and neither endorses, authorizes, sponsors, nor is affiliated with CeCredential Trust.)</i> <a href="https://www.chea.org/search-institutions" data-rel="external" target="_blank" rel="noopener noreferrer" aria-label="CHEA.org Search Institutions (in tab)">https://www.chea.org/search-institutions</a>.</span></p></div></div>',
      ];
    }

    return $form;
  }

  /**
   * Validate the credential ID.
   *
   * @param string $credentialId
   *   The credential ID to validate.
   */
  protected function validateCredentialId($credentialId) {
    if (strlen($credentialId) < 1) {
      return $this->t('The CeDiD must be set.');
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $credentialId = $form_state->getValue('credentialId');
    $error = $this->validateCredentialId($credentialId);
    if ($error) {
      $form_state->setErrorByName('credentialId', $error);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Display result.
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // Clear previous error messages.
    $response->addCommand(new HtmlCommand('#error-message', ''));
    // Clear previous messages.
    \Drupal::messenger()->deleteAll();

    $credentialId = $form_state->getValue('credentialId');
    $error = $this->validateCredentialId($credentialId);

    if ($error) {
      // Add the error message to the messenger.
      \Drupal::messenger()->addError($error->render());

      // Add the messages to the response.
      $messages = ['#type' => 'status_messages'];
      $response->addCommand(new HtmlCommand('#error-message', \Drupal::service('renderer')->renderRoot($messages)));

      return $response;
    }
    else {
      // Pass values in system state.
      $state  = \Drupal::state();
      $values = $form_state->getValues();
      $state->set('cevalidationsr.credentialId', $values['credentialId']);

      // Call External CeCredentialTrust API.
      $connection = new cevalidationsrConnection();
      $json       = $connection->queryEndpoint();

      // Retrieve values.
      $item = json_decode($json);
      $successfail = $item->successfail_result;
      $result_table = $item->result_table;
      $scholarrecord_result = $item->scholarrecord_result;

      // Display result with replace.
      $response->addCommand(new InvokeCommand('#divValidationResult', 'removeClass', ['hidden']));
      $response->addCommand(new HtmlCommand('#successfail_result', $successfail));
      $response->addCommand(new HtmlCommand('#result_table', $result_table));
      $response->addCommand(new HtmlCommand('#scholarrecord_result', $scholarrecord_result));
    }

    return $response;
  }

}
