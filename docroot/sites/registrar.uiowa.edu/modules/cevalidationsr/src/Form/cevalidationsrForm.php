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
      '#markup' => '<div class="form-group padtop0"><div id="SULogo" class="margintop15"><a href="https://secure.cecredentialtrust.com" target="_blank"><img id="SULogoimg1" height="50" src=https://cedimages.blob.core.windows.net/publicimages/Content/Images/CeDiplomaImages/poweredbyCeCredentialTrustLogo_180x34.png alt="Powered by CeCredential TRUST" /></a></div></div>',
    ];

    $form['result_table'] = [
      '#type' => 'markup',
      '#markup' => '<div id="divValidationResult" class="row hidden"><div class="col-md-8"><p id="successfail_result" style="font-size:1.2em;"></p><table id="result_table" class="table table-bordered table-striped"><tbody id="result_tbody"></tbody></table></div></div>',
    ];

    $aposttext = '<div class="row"><div class="col-md-8"><hr class="hr-gray" /><h4>Apostille:</h4><p class="text-justify">An Apostille may neither be required nor necessary. The CeDiploma has legal standing, is non-repudiating, and can be validated through the Institution&rsquo;s website to provide absolute confidence in the credential&rsquo;s authenticity. Questions should be redirected to <a href="mailto:' . $apostilleemail . '?subject=Apostille Information Request" data-rel="external" target="_blank">' . $apostilleemail . '</a>.</p></div></div>';
    $form['apostille_info'] = [
      '#type' => 'markup',
      '#markup' => t($aposttext),
    ];

    $form['scholarRecord_result'] = [
      '#type' => 'markup',
      '#markup' => '<div class="row padtop0"><div class="col-md-8"><div id="scholarrecord_result" style="font-size:1.2em;"></div></div></div>',
    ];

    if ($displayCHEALogo) {
      $form['chea_info'] = [
        '#type' => 'markup',
        '#markup' => '<div class="row padtop24"><div class="col-md-8"><hr class="hr-gray"><p id="logoCHEA"><a class="no-line" href="https://www.chea.org/search-institutions" data-rel="external" target="_blank" rel="noopener noreferrer" aria-label="CHEA.org Search Institution (in tab)"><img id="CheaImageId" src="https://cedimages.blob.core.windows.net/publicimages/Content/Images/CeDiplomaImages/Logo_CHEA_100x36.png" alt="CHEA Logo"></a></p><p id="pCHEA">You may check institutional accreditation through the Council for Higher Education Accreditation (CHEA):<br><span><i>(CHEA is an independent, non-profit organization, and neither endorses, authorizes, sponsors, nor is affiliated with CeCredential Trust.)</i> <a href="https://www.chea.org/search-institutions" data-rel="external" target="_blank" rel="noopener noreferrer" aria-label="CHEA.org Search Institutions (in tab)">https://www.chea.org/search-institutions</a>.</span></p></div></div>',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate credentialId.
    $credentialId = $form_state->getValue('credentialId');
    if (strlen($credentialId) < 1) {
      // Set an error for the form element with a key of "credentialId".
      $form_state->setErrorByName('credentialId', $this->t('The CeDiD must be set.'));
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
    // Pass values in system state.
    $state  = \Drupal::state();
    $values = $form_state->getValues();
    $state->set('cevalidationsr.credentialId', $values['credentialId']);

    // Call External CeCredentialTrust API.
    $connection = new cevalidationsrConnection();
    $response   = NULL;
    $json       = $connection->queryEndpoint();

    // Retrieve values.
    $item = json_decode($json);
    $successfail = $item->successfail_result;
    $result_table = $item->result_table;
    $scholarrecord_result = $item->scholarrecord_result;

    // Display result with replace.
    $response = new AjaxResponse();

    $response->addCommand(new InvokeCommand('#divValidationResult', 'removeClass', ['hidden']));
    $response->addCommand(new HtmlCommand('#successfail_result', $successfail));
    $response->addCommand(new HtmlCommand('#result_table', $result_table));
    $response->addCommand(new HtmlCommand('#scholarrecord_result', $scholarrecord_result));

    return $response;
  }

}
