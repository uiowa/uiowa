<?php

namespace Drupal\uipress_core\Plugin\Block;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Block\BlockBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Cart buttons block.
 *
 * @Block(
 *   id = "cartbuttons_block",
 *   admin_label = @Translation("Cart Buttons Block"),
 *   category = @Translation("Site custom")
 * )
 */
class CartButtons extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $routeMatch) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
    );
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
    $node = $this->routeMatch->getParameter('node');
    $href = '';
    if ($node) {
      $pid = $node->get('field_book_type')->getValue()[0]['target_id'];
      $paragraph = Paragraph::load($pid);
      $isbn = $paragraph->get('field_book_isbn')->getValue()[0]['value'];
      $href = 'https://cdcshoppingcart.uchicago.edu/Cart/ChicagoBook.aspx?ISBN=' . $isbn . '&PRESS=iowa';
    }

    return [
      'href' => [
        '#markup' => $href,
      ],
    ];
  }

}
