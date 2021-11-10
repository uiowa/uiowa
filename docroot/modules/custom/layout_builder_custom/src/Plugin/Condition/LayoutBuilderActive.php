<?php

namespace Drupal\layout_builder_custom\Plugin\Condition;

use Drupal\fragments\Entity\FragmentInterface;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Layout Builder Enabled' condition.
 *
 * @Condition(
 *   id = "layout_builder_active",
 *   label = @Translation("Layout Builder active"),
 * )
 */
class LayoutBuilderActive extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  use LayoutEntityHelperTrait;

  /**
   * The route matcher.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Creates a new LayoutBuilderEnabled instance.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The entity storage.
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(RouteMatchInterface $current_route_match, array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $current_route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('current_route_match'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['enabled'] = [
      '#title' => $this->t('Hidden when Layout Builder is active'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['enabled'],
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['enabled'] = $form_state->getValue('enabled');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    if ($this->configuration['enabled']) {
      return $this->t('Hidden when layout builder is active.');
    }
    return $this->t('Not enabled when layout builder is active.');
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    if (empty($this->configuration['enabled']) && !$this->isNegated()) {
      return TRUE;
    }
    // Default to showing.
    $show = TRUE;

    // Currently, Layout Builder only exists in the context of
    // entities and this covers that case.
    $node = $this->routeMatch->getParameter('node');

    if ($node instanceof NodeInterface) {
      $show = !$this->isLayoutCompatibleEntity($node);
    }

    // This covers layout builder for fragments.
    $fragment = $this->routeMatch->getParameter('fragment');

    if ($fragment instanceof FragmentInterface) {
      $show = !$this->isLayoutCompatibleEntity($fragment);
    }

    // Handle negation.
    if ($this->isNegated()) {
      $show = !$show;
    }

    return $show;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['enabled' => 0] + parent::defaultConfiguration();
  }

}
