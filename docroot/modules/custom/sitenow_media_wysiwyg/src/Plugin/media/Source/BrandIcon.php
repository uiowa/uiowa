<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\media\Source;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Render\RendererInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides media type plugin for Brand Icon.
 *
 * @MediaSource(
 *   id = "brand_icon",
 *   label = @Translation("Brand Icon"),
 *   description = @Translation("Use Brand Icon for reusable svg media."),
 * allowed_field_types = {"string", "string_long", "link", "brand_icon_url"},
 *   forms = {
 *     "media_library_add" = "Drupal\sitenow_media_wysiwyg\Form\BrandIconForm",
 *   },
 * )
 */
class BrandIcon extends MediaSourceBase {
  use LoggerChannelTrait;

  /**
   * The name of the source field.
   */
  const SOURCE_FIELD = 'brand_icon_url';

  /**
   * Constructs a new class instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager service.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The field type plugin manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, ConfigFactoryInterface $config_factory, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('config.factory'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    return [
      'default_name' => $this->t('Default name'),
      'thumbnail_uri' => $this->t('Thumbnail URI'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    $source = $media->get($this->configuration['source_field']);
    $brand_icon_path = $source->getValue()[0]['uri'];

    switch ($attribute_name) {
      case 'default_name':
        return 'media:' . $media->bundle() . ':brand-icon-' . pathinfo($brand_icon_path, PATHINFO_FILENAME);

      case 'thumbnail_uri':
        return 'media:' . $media->bundle() . ':brand-icon-' . pathinfo($brand_icon_path, PATHINFO_FILENAME);
    }

    return NULL;
  }

}
