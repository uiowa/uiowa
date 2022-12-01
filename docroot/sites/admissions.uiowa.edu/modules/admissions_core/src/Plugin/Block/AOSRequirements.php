<?php

namespace Drupal\admissions_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * An Area of Study Requirements Heading block.
 *
 * @Block(
 *   id = "aosrequirements_block",
 *   admin_label = @Translation("Area of Study Requirements Heading Block"),
 *   category = @Translation("Site custom")
 * )
 */
class AOSRequirements extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * Constructs a new AOSRequirements instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack object.
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   The node storage.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $request_stack, EntityStorageInterface $node_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->requestStack = $request_stack;
    $this->nodeStorage = $node_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('request_stack'), $container->get('entity_type.manager')->getStorage('node'));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $show = FALSE;
    if ($node = $this->requestStack
      ->getCurrentRequest()
      ->get('node')) {
      $fields = [
        'field_area_of_study_first_year',
        'field_area_of_study_transfer',
        'field_area_of_study_intl',
      ];
      foreach ($fields as $field) {
        if ($node->hasField($field) && !$node->get($field)->isEmpty()) {
          $show = TRUE;
        }
      }
    }
    if ($show) {
      return [
        '#markup' => $this->t('Admission Process'),
        '#prefix' => '<div class="h2 headline headline--serif element--bold-intro text-align-center">',
        '#suffix' => '</div><hr class="element--spacer-separator" />',
      ];
    }
    return [];
  }

}
