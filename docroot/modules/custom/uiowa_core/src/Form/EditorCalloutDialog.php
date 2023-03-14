<?php

namespace Drupal\uiowa_core\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\editor\Entity\Editor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Font Awesome icon dialog for text editors.
 */
class EditorCalloutDialog extends FormBase {
  /**
   * Drupal configuration service container.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactory $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'callout_dialog';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param \Drupal\editor\Entity\Editor $editor
   *   The text editor to which this dialog corresponds.
   */
  public function buildForm(array $form, FormStateInterface $form_state, Editor $editor = NULL) {
    $form['#tree'] = TRUE;

    $form['heading'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Heading'),
      '#size' => 50,
      '#default_value' => '',
    ];

    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#rows' => 3,
      '#default_value' => '',
    ];

    $form['alignment'] = [
      '#type' => 'radios',
      '#title' => $this->t('Alignment'),
      '#options' => [
        'inline--align-left' => $this->t('Left'),
        'inline--align-center' => $this->t('Center'),
        'inline--align-right' => $this->t('Right'),
      ],
      '#default_value' => 'inline--align-right',
    ];
    $form['size'] = [
      '#type' => 'select',
      '#title' => $this->t('Size'),
      '#options' => [
        'inline--size-small' => $this->t('Small'),
        'inline--size-medium' => $this->t('Medium'),
        'inline--size-large' => $this->t('Large'),
        'inline--size-full' => $this->t('Full'),
      ],
      '#default_value' => 'inline--size-small',
    ];
    $form['bg_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Background Color'),
      '#options' => [
        'none' => $this->t('None'),
        'bg--black' => $this->t('Black'),
        'bg--gold' => $this->t('Gold'),
        'bg--gray' => $this->t('Gray'),
        'bg--white' => $this->t('White'),
      ],
      '#default_value' => 'none',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['save_modal'] = [
      '#type' => 'submit',
      '#value' => $this->t('Insert Callout'),
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitForm',
        'event' => 'click',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $item = $form_state->getValues();

    $values = [];

    $wrapper_class = implode(' ', [
      'callout',
      $item['alignment'],
      $item['size'],
    ]);
    $wrapper_class .= ($item['bg_color'] === 'none') ? '' : ' ' . $item['bg_color'];

    $values['wrapper']['tag'] = 'div';
    $values['wrapper']['attributes'] = [
      'class' => [
        $wrapper_class,
      ],
    ];

    $values['heading']['tag'] = 'h2';
    $values['heading']['value'] = $item['heading'];
    $values['heading']['attributes'] = [
      'class' => [
        implode(' ', [
          'headline',
          'block__headline',
          'headline--serif',
          'headline--underline',
          'headline--center',
        ]),
      ],
    ];

    $values['body']['tag'] = 'p';
    $values['body']['value'] = $item['body'];

    $response->addCommand(new EditorDialogSave($values));
    $response->addCommand(new CloseModalDialogCommand());

    return $response;

  }

}
