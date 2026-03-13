<?php

namespace Drupal\registrar_core\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Final Exam Schedule block.
 *
 * @Block(
 *   id = "final_exam_schedule_block",
 *   admin_label = @Translation("Final Exam Schedule"),
 *   category = @Translation("Site custom")
 * )
 */
class FinalExamScheduleBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * The MAUI API service.
   *
   * @var \Drupal\uiowa_maui\MauiApi
   */
  protected $maui;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new FinalExamScheduleBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\uiowa_maui\MauiApi $maui
   *   The MAUI API service.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MauiApi $maui, FormBuilderInterface $formBuilder, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->maui = $maui;
    $this->formBuilder = $formBuilder;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('uiowa_maui.api'),
      $container->get('form_builder'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $sessions = [];
    foreach ($this->maui->getSessionsBounded() as $session) {
      $sessions[$session->legacyCode] = Html::escape($session->shortDescription);
    }

    $form['session_id'] = [
      '#title' => $this->t('Session'),
      '#type' => 'select',
      '#options' => $sessions,
      '#default_value' => $this->configFactory->get('registrar_core.final_exam_schedule')?->get('session_id') ?? NULL,
      '#description' => $this->t('The session schedule to use. <em>Note: This will affect all final exam schedule blocks</em>.'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $session = $form_state->getValue('session_id');
    $old_session = $this->configFactory->get('registrar_core.final_exam_schedule')
      ->get('session_id');
    if ($session !== $old_session) {
      $config = $this->configFactory->getEditable('registrar_core.final_exam_schedule');
      $config->set('session_id', $session);
      $config->set('session_name', $form['settings']['session_id']['#options'][$form_state->getValue('session_id')]);
      $config->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->configFactory->get('registrar_core.final_exam_schedule');
    $build['form'] = $this->formBuilder->getForm('Drupal\registrar_core\Form\FinalExamScheduleForm', [
      'session_id' => $config->get('session_id'),
      'session_name' => $config->get('session_name'),
    ]);
    return $build;
  }

}
