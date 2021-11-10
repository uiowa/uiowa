<?php

namespace Drupal\layout_builder_custom\Plugin\Diff\Field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\diff\DiffEntityParser;
use Drupal\diff\FieldDiffBuilderBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin to diff layout section fields.
 *
 * @FieldDiffBuilder(
 *   id = "layout_section_field_diff_builder",
 *   label = @Translation("Layout Section Field Diff"),
 *   field_types = {
 *     "layout_section"
 *   },
 * )
 */
class LayoutSectionFieldBuilder extends FieldDiffBuilderBase {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a CoreFieldBuilder object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The configuration factory object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\diff\DiffEntityParser $entity_parser
   *   The entity parser.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, DiffEntityParser $entity_parser, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_parser);
    $this->renderer = $renderer;
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
      $container->get('diff.entity_parser'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items) {
    $result = [];
    // @todo Do the comparisons and such here.
    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items->getSections() as $id => $section) {
      $result[$id] = implode(',', $section->toArray()['layout_settings']['layout_builder_styles_style']);
      foreach ($section->getComponents() as $comp_id => $component) {
        $config = $component->get('configuration');
        if (!isset($config['block_revision_id'])) {
          continue;
        }
        $rev_id = $config['block_revision_id'];
        $block = \Drupal::entityTypeManager()->getStorage('block_content')->loadRevision($rev_id);
        if ($block) {
          foreach ($block->toArray() as $arr_key => $arr_value) {
            if (str_starts_with($arr_key, 'field_')) {
              foreach ($arr_value as $field_num => $field) {
                foreach ($field as $value_key => $value_value) {
                  $indexer = implode('.', [$arr_key, $field_num, $value_key]);
                  if (is_array($value_value)) {
                    $value_value = implode('.', $value_value);
                  }
                  $result[$id] .= implode("\t | \t", [$comp_id, $indexer, $value_value]);
                }
              }
            }
          }
        }
      }
//      $result[$id] = implode(",", $result[$id]);
    }
    return $result;
  }

}
