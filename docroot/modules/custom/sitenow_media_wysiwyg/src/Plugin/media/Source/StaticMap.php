<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\media\Source;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\Exception\InvalidStreamWrapperException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Render\RendererInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaTypeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\TransferException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides media type plugin for Static Map.
 *
 * @MediaSource(
 *   id = "static_map",
 *   label = @Translation("Static Map"),
 *   description = @Translation("Use Static Map for reusable media."),
 *   allowed_field_types = {"string", "string_long", "link", "static_map_url"},
 *   forms = {
 *     "media_library_add" = "Drupal\sitenow_media_wysiwyg\Form\StaticMapForm",
 *   },
 * )
 */
class StaticMap extends MediaSourceBase {
  use LoggerChannelTrait;

  const BASE_URL = 'https://maps.uiowa.edu';
  const STATIC_URL = 'https://staticmap.concept3d.com';

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
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
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
    $fields = NULL;
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceFieldConstraints() {
    return ['StaticMapUrl' => []];
  }

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type) {
    return parent::createSourceField($type)->set('label', 'Static map URL');
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    $uuid = $media->uuid();
    $source = $media->get($this->configuration['source_field']);

    // The source is a required, single value field.
    $parsed = UrlHelper::parse($source->getValue()[0]['uri']);
    $regex = \Drupal::config('sitenow_media_wysiwyg.settings')
      ->get('sitenow_media_wysiwyg.static_map_regex');
    if ($regex) {
      preg_match($regex, $parsed['fragment'], $regex_matches);
      $marker = $regex_matches[1];

      if (str_contains($marker, '?')) {
        $marker = strstr($marker, '?', TRUE);
      }

      switch ($attribute_name) {
        case 'default_name':
          return 'media:' . $media->bundle() . ':marker-' . $marker;

        case 'thumbnail_uri':
          try {
            $thumbnail_url = self::STATIC_URL . "/map/static-map/?map=1890&loc=" . $marker . "&scale=1&zoom=17";
            $this->client->request('GET', $thumbnail_url);

            $scheme = $this->configFactory->get('system.file')
              ->get('default_scheme');
            $destination = "$scheme://static_map_thumbnails/";
            $realpath = $this->fs->realpath($destination);
            $destination_file = "$destination$uuid.jpg";

            if ($this->fs->prepareDirectory($realpath, FileSystemInterface::CREATE_DIRECTORY)) {
              try {
                $data = (string) \Drupal::httpClient()
                  ->get($thumbnail_url)
                  ->getBody();
                return $this->fs->saveData($data, $destination_file, FileSystemInterface::EXISTS_REPLACE);
              }
              catch (TransferException $exception) {
                \Drupal::messenger()
                  ->addError($this->t('Failed to fetch file due to error "%error"', ['%error' => $exception->getMessage()]));
              }
              catch (FileException | InvalidStreamWrapperException $e) {
                \Drupal::messenger()
                  ->addError($this->t('Failed to save file due to error "%error"', ['%error' => $e->getMessage()]));
              }
            }
          }
          catch (ClientException $e) {
            $this->logger()
              ->warning($this->t('Unable to get thumbnail image for @media.', [
                '@media' => $media->uuid(),
              ]));

            // Use the default thumbnail if we can't get one.
            return NULL;
          }
      }
    }
    else {
      \Drupal::messenger()->addError($this->t('Failed to retrieve necessary settings.'));
    }
    return NULL;
  }

}
