<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\media\Source;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Render\RendererInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaTypeInterface;
use Drupal\media\MediaSourceFieldConstraintsInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RedirectMiddleware;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;

/**
 * Provides media type plugin for Static Maps..
 *
 * @MediaSource(
 *   id = "static_map",
 *   label = @Translation("Static Map"),
 *   description = @Translation("Use static maps for reusable media."),
 *   allowed_field_types = {"string", "string_long", "link"},
 *   forms = {
 *     "media_library_add" = "Drupal\sitenow_media_wysiwyg\Form\StaticMapForm",
 *   },
 *
 * )
 */
class StaticMap extends MediaSourceBase implements MediaSourceFieldConstraintsInterface {
  use LoggerChannelTrait;

  const BASE_URL = 'https://staticmap.concept3d.com';

  /**
   * The http_client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * The file_system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fs;

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
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface|\Drupal\media_entity_panopto\Plugin\media\Source\FieldTypePluginManagerInterface $field_type_manager
   *   The field type plugin manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \GuzzleHttp\Client $client
   *   The http_client service.
   * @param \Drupal\Core\File\FileSystemInterface $fs
   *   The file_system service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, ConfigFactoryInterface $config_factory, RendererInterface $renderer, Client $client, FileSystemInterface $fs) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory);
    $this->client = $client;
    $this->fs = $fs;
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
      $container->get('renderer'),
      $container->get('http_client'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    return [
      'link' => $this->t('Link'),
      'zoom' => $this->t('Zoom'),
      'label' => $this->t('Label'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceFieldConstraints() {
    return ['StaticMapURL' => []];
  }

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type) {
    return parent::createSourceField($type)->set('label', 'Static Map Url');
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    $uuid = $media->uuid();
    $source = $media->get($this->configuration['source_field']);

    // The source is a required, single value field.
    // @see: StaticMapURLConstraintValidator::validate().
    $parsed = UrlHelper::parse($source->getValue()[0]['uri']);
    $id = $parsed['query']['id'];

    switch ($attribute_name) {
      case 'link':
        return $media['link'];

      case 'zoom':
        return $media['zoom'];

      case 'label':
        return $media['label'];

      default:
        return parent::getMetadata($media, $attribute_name);
        }

    return NULL;
  }

}
