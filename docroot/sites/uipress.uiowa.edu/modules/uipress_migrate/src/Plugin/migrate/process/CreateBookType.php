<?php

namespace Drupal\uipress_migrate\Plugin\migrate\process;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom process plugin to create book type items on book nodes.
 *
 * @MigrateProcessPlugin(
 *   id = "create_book_type"
 * )
 */
class CreateBookType extends ProcessPluginBase implements ContainerFactoryPluginInterface {
  use LoggerChannelTrait;

  /**
   * The entity_type.manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The date.formatter service.
   */
  protected DateFormatterInterface $dateFormat;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, DateFormatterInterface $dateFormat) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->dateFormat = $dateFormat;
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
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    $retail_price = (!empty($value['retail_price'])) ? $value['retail_price'][0]['value'] : '';
    $sale_price = (!empty($value['sale_price'])) ? $value['sale_price'][0]['value'] : '';
    $promo = (!empty($value['promo'])) ? $value['promo'][0]['value'] : '';
    if (!empty($value['expire_date'][0]['value'])) {
      $expire_date = $this->dateFormat->format(strtotime($value['expire_date'][0]['value']), 'custom', 'Y-m-d');
    }
    else {
      $expire_date = '';
    }

    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $this->entityTypeManager->getStorage('paragraph')->create([
      'type' => 'book_type',
      'field_book_type' => $value['type'],
      'field_book_isbn' => $value['isbn'],
      'field_book_retail_price' => $retail_price,
      'field_book_sale_price' => $sale_price,
      'field_book_sale_code' => $promo,
      'field_book_ebook_ownership' => $value['ownership'],
      'field_book_sale_expiry_date' => $expire_date,
    ]);

    $paragraph->save();

    return [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
  }

}
