<?php

namespace Drupal\uiowa_entities\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Academic unit form.
 *
 * @property \Drupal\uiowa_entities\AcademicUnitInterface $entity
 */
class AcademicUnitForm extends EntityForm {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityFieldManagerInterface $entityFieldManager, ConfigFactoryInterface $configFactory) {
    $this->entityFieldManager = $entityFieldManager;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the academic unit.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\uiowa_entities\Entity\AcademicUnit::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];

    $form['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Type of academic unit'),
      '#options' => [
        'college' => $this->t('Collegiate'),
        'non-collegiate' => $this->t('Non-Collegiate'),
      ],
      '#default_value' => $this->entity->get('type'),
    ];

    $form['homepage'] = [
      '#type' => 'url',
      '#title' => $this->t('Academic unit homepage link.'),
      '#default_value' => $this->entity->get('homepage'),
      '#description' => $this->t('URL to get more information on this academic unit.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $entity = parent::buildEntity($form, $form_state);

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $message_args = [
      '%type' => $this->entity->get('type'),
      '%label' => $this->entity->label(),
    ];
    $message = $result === SAVED_NEW
      ? $this->t('Created new %type Academic Unit %label.', $message_args)
      : $this->t('Updated %type Academic Unit %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
