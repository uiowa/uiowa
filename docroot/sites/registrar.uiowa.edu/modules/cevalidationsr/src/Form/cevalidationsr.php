<?php

namespace Drupal\cevalidationsr\Form;

use Drupal\cevalidationsr\cevalidationsrConnection;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
// Use Drupal\Core\Ajax\ReplaceCommand;.
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Basic CeCredential Form Validation class
 * --sample: --https://www.thirdandgrove.com/theming-form-elements-drupal-8
 * --sample: --https://www.lullabot.com/articles/drupal-8-theming-fundamentals-part-1
 * --sample: --https://www.drupal.org/docs/8/creating-custom-modules/adding-stylesheets-css-and-javascript-js-to-a-drupal-8-module
 * --sample: --https://github.com/ericski/Drupal-8-Module-Theming-Example
 * --sample: --https://github.com/acabouet/Drupal-8-Custom-Module
 * --sample: --https://github.com/cristiroma/drupal8-module-tutorial
 * --sample: --https://www.adcisolutions.com/knowledge/oop-drupal-8-and-how-use-it-create-custom-module
 * --sample: --https://opensenselabs.com/blog/tech/adding-css-fonts-and-javascript-using-library-api-drupal-8
 * --sample: --https://www.drupal.org/project/drupal/issues/2841872
 * --sample: --https://twig.symfony.com/doc/2.x/
 * --sample: --https://www.drupalexp.com/blog/creating-ajax-callback-commands-drupal-8
 * --sample: --https://www.metaltoad.com/blog/drupal-8-consumption-third-party-api
 * --sample: --https://drupalize.me/blog/201512/speak-http-drupal-httpclient
 * --sample: --https://www.sitepoint.com/using-ajax-forms-drupal-8/
 * -sample: --https://www.oreilly.com/library/view/mastering-drupal-8/9781785885976/2ebd5467-f633-4c7c-90be-930e20c14dc7.xhtml --Using Twig to attach a library
 */
class cevalidationsr extends FormBase {
  /**
   * @var \Drupal\Core\Config\Config cevalidationsr settings
   */
  protected $config = NULL;

  /**
   * @var array Store sensitive API info such as the private_key & password
   */
  protected $sensitiveConfig = [];

  /**
   * CevalidationsrConnection constructor.
   */
  public function __construct() {
    $this->config = \Drupal::config('cevalidationsr.settings');
  }

  /**
   * Get configuration or state setting for this integration module.
   *
   * @param string $name
   *   this module's config or state.
   *
   * @return string
   */
  protected function getConfig($name) {
    return $this->config->get('cevalidationsr.' . $name);
  }

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
    $apostilleemail = $this->getConfig('apostilleemail');

    // Show CHEA Logo?
    $displayCHEALogo = $this->getConfig('displayCHEALogo');

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
      '#input_mask' => '****-****-****',
      '#mask' => [
        'value' => '****-****-****',
        'reverse' => FALSE,
        'selectonfocus' => FALSE,
        'clearifnotmatch' => TRUE,
      ],
    ];

    // Add a button that handles the submission of the form.
    // See: https://api.drupal.org/api/drupal/elements/8.7.x
    // https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21Element%21RenderElement.php/class/RenderElement/8.7.x
    // https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21Element%21FormElement.php/class/FormElement/8.7.x for #attributes.
    $form['actions'] = [
      '#type' => 'button',
      '#value' => $this->t('Validate'),
      '#attributes' => ['class' => ['btn btn-primary btn-large']],
      '#ajax' => [
    // 'Drupal\modules\custom\cevalidationsr\cevalidationsrConnection::queryEndpoint',
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
    /* $state->set('cevalidationsr.nameLetter', $values['nameLetter']); */

    // Call External CeCredentialTrust API.
    $connection = new cevalidationsrConnection();
    $response   = NULL;
    $json       = $connection->queryEndpoint();
    // Get external data using Call: //'Drupal\modules\custom\cevalidationsr\cevalidationsrConnection::queryEndpoint',
    // $result = 'Drupal\modules\custom\cevalidationsr\cevalidationsrConnection::queryEndpoint';
    // list($response, $json) = 'Drupal\modules\custom\cevalidationsr\cevalidationsrConnection::queryEndpoint';
    // If response data was built and returned, display it with a sample of the
    // objects returned
    // if (isset($response)) {
    //   $build['response'] = [
    //     '#theme' => 'item_list',
    //     '#title' => t('Response: @r', [
    //       '@r' => $response->getReasonPhrase(),
    //     ]),
    //     '#items' => [
    //       'code' => t('Code: @c', ['@c' => $response->getStatusCode()]),
    //     ],
    //   ];
    // }
    // if (isset($json)) {
    //   $build['response_data'] = [
    //     '#theme' => 'item_list',
    //     '#title' => t('Response Data:'),
    //     '#items' => [
    //       'response-type' => t('Response Type: @t', [
    //         '@t' => $json->response_type,
    //       ]),
    //       'total-count' => t('Total Count: @c', [
    //         '@c' => $json->pagination->total_count,
    //       ]),
    //     ],
    //   ];
    // }.
    // Retrieve values.
    $item = json_decode($json);
    $successfail = $item->successfail_result;
    $result_table = $item->result_table;
    $scholarrecord_result = $item->scholarrecord_result;

    // Display result with replace.
    $response = new AjaxResponse();

    // https://drupal.stackexchange.com/questions/8529/is-it-possible-to-replace-more-than-one-form-element-wrappers-triggered-by-onl
    $response->addCommand(new InvokeCommand('#divValidationResult', 'removeClass', ['hidden']));

    // https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Ajax%21HtmlCommand.php/class/HtmlCommand/8.2.x
    $response->addCommand(new HtmlCommand('#successfail_result', $successfail));
    $response->addCommand(new HtmlCommand('#result_table', $result_table));
    $response->addCommand(new HtmlCommand('#scholarrecord_result', $scholarrecord_result));

    return $response;
  }

}
