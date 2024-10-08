<?php

namespace Drupal\registrar_core\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MauiApi $maui, FormBuilderInterface $formBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->maui = $maui;
    $this->formBuilder = $formBuilder;
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
      '#default_value' => $this->configuration['session'] ?? NULL,
      '#required' => TRUE,
    ];

    $form['last_updated'] = [
      '#type' => 'date',
      '#title' => $this->t('Last Updated'),
      '#default_value' => $this->configuration['last_updated'] ?? NULL,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['session'] = $form_state->getValue('session');
    $this->configuration['session_name'] = $form['settings']['session']['#options'][$form_state->getValue('session')];
    $this->configuration['last_updated'] = $form_state->getValue('last_updated');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $session = $this->configuration['session'];
//    $session_name = $this->configuration['session_name'];
//    $last_updated = $this->configuration['last_updated'];
//    $data = $this->maui->getFinalExamSchedule($session);
//    if (empty($data) || !isset($data['NewDataSet']['Table'])) {
//      // @todo Add some handling if data fetching failed
//      //   or there's something weird with the structure.
//      return [];
//    }
//    $data = $data['NewDataSet']['Table'];
//    $headers = [
//      'Sections',
//      'Course Title',
//      'Start Times',
//      'End Times',
//      'Rooms',
//    ];
//
//    $allowed_tags = [
//      'a',
//      'strong',
//      'em',
//      'br',
//    ];
//
//    $build['form']['search'] = [
//      '#type' => 'textfield',
//      '#title' => $this->t('Search'),
//      '#default_value' => '',
//      '#description' => $this->t('The string to search against.'),
//    ];
//
//    // At this point we still have more data from MAUI than we needed,
//    // and also need to process the data we do want to keep.
//    $search = '';
//    foreach ($data as $index => $row) {
//      $pass = FALSE;
//      $new_row = [];
//      foreach (['sections',
//        'course_title',
//        'start_time',
//        'end_time',
//        'rooms',
//      ] as $key) {
//        $value = Markup::create(Xss::filter($row[$key], $allowed_tags));
//        if (!$pass && str_contains($value, $search)) {
//          $pass = TRUE;
//        }
//        $new_row[] = $value;
//      }
//      if ($pass) {
//        $data[$index] = $new_row;
//      }
//      else {
//        unset($data[$index]);
//      }
//    }
//
//    usort($data, function ($a, $b) {
//      return $a[0] <=> $b[0];
//    });
//
//    $table = [
//      '#theme' => 'table',
//      '#header' => $headers,
//      '#rows' => $data,
//    ];
//
//    $build['wrapper'] = [
//      '#type' => 'html_tag',
//      '#tag' => 'div',
//      '#attributes' => [
//        'class' => ['table-responsive'],
//      ],
//      'table' => $table,
//    ];

    $build['form'] = $this->formBuilder->getForm('Drupal\registrar_core\Form\FinalExamScheduleForm');
    $build['form']['final_exam']['session_id']['#value'] = $session;
    return $build;
  }

}
