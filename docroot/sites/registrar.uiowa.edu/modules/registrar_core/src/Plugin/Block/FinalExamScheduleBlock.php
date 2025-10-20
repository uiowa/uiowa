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

    $form['session'] = [
      '#title' => $this->t('Session'),
      '#type' => 'select',
      '#options' => $sessions,
      '#default_value' => $this->configFactory->get('registrar.final_exam_schedule')?->get('session') ?? NULL,
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
    $session = $form_state->getValue('session');
    $old_session = $this->configFactory->get('registrar.final_exam_schedule.session');
    if ($session !== $old_session) {
      $config = $this->configFactory->getEditable('registrar.final_exam_schedule');
      $config->set('session', $session)
        ->save();
    }
    // @todo Update this to use a site-wide config value,
    //   as well as in the build process.
    $this->configuration['session_name'] = $form['settings']['session']['#options'][$form_state->getValue('session')];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['form'] = $this->formBuilder->getForm('Drupal\registrar_core\Form\FinalExamScheduleForm', [
      'session_id' => $this->configuration['session'],
      'session_name' => $this->configuration['session_name'],
    ]);
    return $build;
  }

}
