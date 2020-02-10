<?php

namespace Drupal\auto_entitylabel;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\Component\Utility\Html;

/**
 * Class for Auto Entity Label Manager.
 */
class AutoEntityLabelManager implements AutoEntityLabelManagerInterface {
  use StringTranslationTrait;

  /**
   * Automatic label is disabled.
   */
  const DISABLED = 0;

  /**
   * Automatic label is enabled. Will always be generated.
   */
  const ENABLED = 1;

  /**
   * Automatic label is optional. Will only be generated if no label was given.
   */
  const OPTIONAL = 2;

  /**
   * Automatic label is prefilled.
   */
  const PREFILLED = 3;

  /**
   * The content entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The type of the entity.
   *
   * @var string
   */
  // @codingStandardsIgnoreLine
  protected $entity_type;

  /**
   * The bundle of the entity.
   *
   * @var string
   */
  // @codingStandardsIgnoreLine
  protected $entity_bundle;

  /**
   * Indicates if the automatic label has been applied.
   *
   * @var bool
   */
  // @codingStandardsIgnoreLine
  protected $auto_label_applied = FALSE;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Automatic label configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs an AutoEntityLabelManager object.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to add the automatic label to.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Utility\Token $token
   *   Token manager.
   */
  public function __construct(ContentEntityInterface $entity, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, Token $token) {
    $this->entity = $entity;
    $this->entity_type = $entity->getEntityType()->id();
    $this->entity_bundle = $entity->bundle();
    $this->bundle_entity_type = $entity_type_manager->getDefinition($this->entity_type)->getBundleEntityType();

    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->token = $token;
  }

  /**
   * Checks if the entity has a label.
   *
   * @return bool
   *   True if the entity has a label property.
   */
  public function hasLabel() {
    /** @var \Drupal\Core\Entity\EntityTypeInterface $definition */
    $definition = $this->entityTypeManager->getDefinition($this->entity->getEntityTypeId());
    return $definition->hasKey('label');
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel() {

    if (!$this->hasLabel()) {
      throw new \Exception('This entity has no label.');
    }

    $pattern = $this->getPattern();

    if ($pattern) {
      $label = $this->generateLabel($pattern, $this->entity);
    }
    else {
      $label = $this->getAlternativeLabel();
    }

    $label = mb_substr($label, 0, 255);
    $label_name = $this->getLabelName();
    $this->entity->$label_name->setValue($label);

    $this->auto_label_applied = TRUE;
    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAutoLabel() {
    return $this->getConfig('status') == self::ENABLED;
  }

  /**
   * {@inheritdoc}
   */
  public function hasOptionalAutoLabel() {
    return $this->getConfig('status') == self::OPTIONAL;
  }

  /**
   * {@inheritdoc}
   */
  public function autoLabelNeeded() {
    $not_applied = empty($this->auto_label_applied);
    $required = $this->hasAutoLabel();
    $optional = $this->hasOptionalAutoLabel() && empty($this->entity->label());
    return $not_applied && ($required || $optional);
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->getConfig('status');
  }

  /**
   * {@inheritdoc}
   */
  public function getPattern() {
    $pattern = $this->getConfig('pattern') ?: '';
    $pattern = trim($pattern);
    return $pattern;
  }

  /**
   * Gets the field name of the entity label.
   *
   * @return string
   *   The entity label field name. Empty if the entity has no label.
   */
  public function getLabelName() {
    $label_field = '';

    if ($this->hasLabel()) {
      $definition = $this->entityTypeManager->getDefinition($this->entity->getEntityTypeId());
      $label_field = $definition->getKey('label');
    }

    return $label_field;
  }

  /**
   * Gets the entity bundle label or the entity label.
   *
   * @return string
   *   The bundle label.
   */
  protected function getBundleLabel() {
    $entity_type = $this->entity->getEntityTypeId();
    $bundle = $this->entity->bundle();

    // Use the the human readable name of the bundle type. If this entity has no
    // bundle, we use the name of the content entity type.
    if ($bundle != $entity_type) {
      $bundle_entity_type = $this->entityTypeManager
        ->getDefinition($entity_type)
        ->getBundleEntityType();
      $label = $this->entityTypeManager
        ->getStorage($bundle_entity_type)
        ->load($bundle)
        ->label();
    }
    else {
      $label = $this->entityTypeManager
        ->getDefinition($entity_type)
        ->getLabel();
    }

    return $label;
  }

  /**
   * Generates the label according to the settings.
   *
   * @param string $pattern
   *   Label pattern. May contain tokens.
   * @param object $entity
   *   Content entity.
   *
   * @return string
   *   A label string
   */
  protected function generateLabel($pattern, $entity) {
    $entity_type = $entity->getEntityType()->id();
    // To avoid that the token replacement leaking render metadata (which might
    // be a problem when generating labels using JSON:api or similar) we pass in
    // metadata to the token replacement.
    // @see https://www.drupal.org/project/auto_entitylabel/issues/3051165
    $metadata = new BubbleableMetadata();
    $output = $this->token->replace($pattern,
      [$entity_type => $entity],
      ['clear' => TRUE],
      $metadata
    );

    // Decode HTML entities, returning them to their original UTF-8 characters.
    $output = Html::decodeEntities($output);

    // Strip tags and Remove special characters.
    $pattern = !empty($this->getConfig('escape')) ? '/[^a-zA-Z0-9\s]|[\t\n\r\0\x0B]/' : '/[\t\n\r\0\x0B]/';
    $output = preg_replace($pattern, ' ', strip_tags($output));

    // Invoke hook_auto_entitylabel_label_alter().
    $entity_clone = clone $entity;
    \Drupal::moduleHandler()->alter('auto_entitylabel_label', $output, $entity_clone);

    // Trim stray whitespace from beginning and end. Also converts 2 or more
    // whitespace characters within label to a single space.
    $output = preg_replace('/\s{2,}/', ' ', trim($output));

    return $output;
  }

  /**
   * Returns automatic label configuration of the content entity bundle.
   *
   * @param string $value
   *   The configuration value to get.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   Return Config.
   */
  protected function getConfig($value) {
    if (!isset($this->config)) {
      $this->config = $this->configFactory->get('auto_entitylabel.settings.' . $this->entity_type . '.' . $this->entity_bundle);
    }
    return $this->config->get($value);
  }

  /**
   * Gets an alternative entity label.
   *
   * @return string
   *   Translated label string.
   */
  protected function getAlternativeLabel() {
    $content_type = $this->getBundleLabel();

    if ($this->entity->id()) {
      $label = $this->t('@type @id', [
        '@type' => $content_type,
        '@id' => $this->entity->id(),
      ]);
    }
    else {
      $label = $content_type;
    }

    return $label;
  }

  /**
   * Constructs the list of options for the given bundle.
   *
   * @codingStandardsIgnoreStart
   */
  public static function auto_entitylabel_options($entity_type, $bundle_name) {
    // @codingStandardsIgnoreEnd
    $options = [
      'auto_entitylabel_disabled' => t('Disabled'),
    ];
    if (self::auto_entitylabel_entity_label_visible($entity_type)) {
      $options += [
        'auto_entitylabel_enabled' => t('Automatically generate the label and hide the label field'),
        'auto_entitylabel_optional' => t('Automatically generate the label if the label field is left empty'),
      ];
    }
    else {
      $options += [
        'auto_entitylabel_enabled' => t('Automatically generate the label'),
      ];
    }
    return $options;
  }

  /**
   * Check if given entity bundle has a visible label on the entity form.
   *
   * @param string $entity_type
   *   The entity type.
   *
   * @return bool
   *   TRUE if the label is rendered in the entity form, FALSE otherwise.
   *
   * @todo
   *   Find a generic way of determining the result of this function. This
   *   will probably require access to more information about entity forms
   *   (entity api module?).
   *
   * @codingStandardsIgnoreStart
   */
  public static function auto_entitylabel_entity_label_visible($entity_type) {
    // @codingStandardsIgnoreEnd
    $hidden = [
      'profile2' => TRUE,
    ];

    return empty($hidden[$entity_type]);
  }

}
