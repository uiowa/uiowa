<?php

namespace Drupal\sitenow_dispatch\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\uiowa_core\HeadlineHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block which renders the thank you form.
 *
 * @Block(
 *   id = "sitenow_dispatch_thankyou_form",
 *   admin_label = @Translation("Thank you form"),
 *   category = @Translation("SiteNow Dispatch")
 * )
 */
class ThankYouBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * The form_builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The config.factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $formBuilder, ConfigFactory $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $formBuilder;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['heading'] = [
      '#theme' => 'uiowa_core_headline',
      '#headline' => $this->configuration['headline'],
      '#hide_headline' => $this->configuration['hide_headline'],
      '#heading_size' => $this->configuration['heading_size'],
      '#headline_style' => $this->configuration['headline_style'],
    ];

    $build['form'] = $this->formBuilder->getForm('\Drupal\sitenow_dispatch\Form\ThankYouForm');

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    // Check that we have a campaign set before allowing
    // the block to be placed.
    $enabled = $this->configFactory->get('sitenow_dispatch.settings')->get('thanks.enabled');

    if (!($enabled)) {
      $form['no_campaign'] = [
        '#prefix' => '<div>',
        '#suffix' => '</div>',
        '#markup' => $this->t('This block must be enabled by an administrator. Please contact the <a href=":email">ITS Help Desk</a> to request access.', [
          ':email' => 'mailto:its-helpdesk@uiowa.edu',
        ]),
      ];

    }
    else {
      $form['headline'] = HeadlineHelper::getElement([
        'headline' => $this->configuration['headline'] ?? NULL,
        'hide_headline' => $this->configuration['hide_headline'] ?? 0,
        'heading_size' => $this->configuration['heading_size'] ?? 'h2',
        'headline_style' => $this->configuration['headline_style'] ?? 'default',
      ], FALSE);

    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    foreach ($form_state->getValues()['headline']['container'] as $name => $value) {
      $this->configuration[$name] = $value;
    }
  }

}
