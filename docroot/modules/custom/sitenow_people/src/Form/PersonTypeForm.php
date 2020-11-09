<?php

namespace Drupal\sitenow_people\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\FieldConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Person Type form.
 *
 * @property \Drupal\sitenow_people\PersonTypeInterface $entity
 */
class PersonTypeForm extends EntityForm {

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
      '#description' => $this->t('Label for the person type.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\sitenow_people\Entity\PersonType::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
      '#description' => $this->t('Description of the person type.'),
    ];

    $form['allowed_fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Allowed fields'),
      '#description' => $this->t('Show these person fields when this person type is selected.'),
    ];

    $fields = array_filter(
      $this->entityFieldManager->getFieldDefinitions('node', 'person'),
      function ($field_definition) {
        return $field_definition instanceof FieldConfigInterface;
      }
    );
    $field_settings = $this->configFactory->get('sitenow_people.field_settings');
    if ($field_settings->get('default_fields')) {
      $default_fields = array_keys($field_settings->get('default_fields'));
      foreach ($fields as $field) {
        $field_name = $field->getName();
        if (in_array($field_name, $default_fields)) {
          unset($fields[$field_name]);
        }
      }
    }
    foreach ($fields as $fieldID => $field) {
      $field_name = $field->getName();
      $field_label = $field->getLabel();
      $form['allowed_fields'][$field_name] = [
        '#type' => 'checkbox',
        '#title' => $field_label,
        '#default_value' => in_array($field_name, $this->entity->getAllowedFields()),
        '#parents' => [
          'allowed_fields',
          $fieldID,
        ],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $entity = parent::buildEntity($form, $form_state);

    // We need to convert the individual checkbox values that were submitted
    // in the form to a single array containing all the fields that
    // were checked.
    $allowedFields = $form_state->getValue('allowed_fields');
    if ($allowedFields) {
      $allowedFields = array_keys(array_filter($allowedFields));
    }
    else {
      $allowedFields = [];
    }
    $entity->set('allowed_fields', $allowedFields);

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new person type %label.', $message_args)
      : $this->t('Updated person type %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
