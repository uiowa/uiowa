<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\media\Source;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaTypeInterface;
use Drupal\media\MediaSourceFieldConstraintsInterface;
use GuzzleHttp\Client;
use GuzzleHttp\RedirectMiddleware;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;

/**
 * Provides media type plugin for Panopto.
 *
 * @MediaSource(
 *   id = "panopto",
 *   label = @Translation("Panopto"),
 *   description = @Translation("Use Panopto for reusable media."),
 *   allowed_field_types = {"string", "string_long", "link"},
 *   default_thumbnail_filename = "no-thumbnail.png",
 *   forms = {
 *     "media_library_add" = "Drupal\sitenow_media_wysiwyg\Form\PanoptoForm",
 *   },
 *
 * )
 */
class Panopto extends MediaSourceBase implements MediaSourceFieldConstraintsInterface {

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
    $fields = NULL;
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceFieldConstraints() {
    return ['PanoptoURL' => []];
  }

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type) {
    return parent::createSourceField($type)->set('label', 'Panopto Url');
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    $source = $media->get($this->configuration['source_field']);

    // The source is a required, single value field so we can make assumptions.
    // @see: PanoptoURLConstraintValidator.
    $parsed = UrlHelper::parse($source->getValue()[0]['uri']);
    $id = $parsed['query']['id'];

    switch ($attribute_name) {
      // @todo: Leverage the API or another mechanism to get a better name.
      case 'default_name':
        return 'media:' . $media->bundle() . ':' . $media->uuid();

      // @todo: Leverage the API or another mechanism to get the thumbnail.
      case 'thumbnail_uri':
        $client = new Client([
          'allow_redirects' => [
            'track_redirects' => TRUE,
          ],
          'base_uri' => 'https://uicapture.hosted.panopto.com',
        ]);

        $response = $client->get("/Panopto/Services/FrameGrabber.svc/FrameRedirect?objectId={$id}&mode=Delivery&random=0.304899272650899&usePng=False");
        $redirects = $response->getHeader(RedirectMiddleware::HISTORY_HEADER);
        $source = end($redirects);

        // @todo: Put this in a subfolder that is created by this module.
        /** @var \Drupal\File\FileInterface $file */
        $file = system_retrieve_file($source, NULL, TRUE, FileSystemInterface::EXISTS_REPLACE);
        return $file->getFileUri();
    }

    return NULL;
  }

}
