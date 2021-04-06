<?php

namespace Drupal\layout_builder_custom\Plugin\Display;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\uiowa_core\HeadlineHelper;
use Drupal\views\Plugin\Block\ViewsBlock;
use Drupal\views\Plugin\views\display\Block as CoreBlock;

/**
 * Provides a List Block display plugin override.
 *
 * Adapted from Drupal\ctools_views\Plugin\Display\Block and
 * https://www.drupal.org/project/views_block_placement_exposed_form_defaults.
 */
class ListBlock extends CoreBlock {

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);
    $filtered_allow = array_filter($this->getOption('allow'));
    $filter_options = [
      // We are just changing the label here to be consistent current use.
      'items_per_page' => $this->t('Items to display'),
    ];
    $filter_intersect = array_intersect_key($filter_options, $filtered_allow);

    $options['allow'] = [
      'category' => 'block',
      'title' => $this->t('Allow settings'),
      'value' => empty($filtered_allow) ? $this->t('None') : implode(', ', $filter_intersect),
    ];

    $customizable_filters = $this->getOption('filter_in_block');
    $filter_count = !empty($customizable_filters) ? count($customizable_filters) : 0;
    $options['allow']['value'] .= ', ' . $this->formatPlural($filter_count, '1 filter in block', '@count filters in block');
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    if ($form_state->get('section') !== 'allow') {
      return;
    }

    // Making the label more user-friendly.
    $form['allow']['#options']['items_per_page'] = $this->t('Items to display');

    $defaults = [];
    if (!empty($form['allow']['#default_value'])) {
      $defaults = array_filter($form['allow']['#default_value']);
      if (!empty($defaults['items_per_page'])) {
        $defaults['items_per_page'] = 'items_per_page';
      }
    }

    $form['allow']['#default_value'] = $defaults;

    // Show exposed filters that can be set in the block form.
    $customized_filters = $this->getOption('filter_in_block');
    $form['filter_in_block'] = [
      '#type' => 'checkboxes',
      '#options' => $this->getListOfExposedFilters(),
      '#title' => $this->t('Filter in block'),
      '#description' => $this->t('Select the filters which users should be able to customize default values for when placing the views block into a layout.'),
      '#default_value' => !empty($customized_filters) ? $customized_filters : [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);
    if ($form_state->get('section') === 'allow') {
      $this->setOption('filter_in_block', Checkboxes::getCheckedCheckboxes($form_state->getValue('filter_in_block')));
    }
  }

  /**
   * Get a list of exposed filters.
   *
   * @return array
   *   An array of filters keyed by machine name with label values.
   */
  protected function getListOfExposedFilters() {
    $filter_options = [];
    foreach ($this->getHandlers('filter') as $filer_name => $filter_plugin) {
      if ($filter_plugin->isExposed() && $exposed_info = $filter_plugin->exposedInfo()) {
        $filter_options[$filer_name] = $exposed_info['label'];
      }
    }
    return $filter_options;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm(ViewsBlock $block, array &$form, FormStateInterface $form_state) {
    $form = parent::blockForm($block, $form, $form_state);

    $allow_settings = array_filter($this->getOption('allow'));
    $block_configuration = $block->getConfiguration();

    // @todo Possibly wire this up to the views title?
    $form['headline'] = HeadlineHelper::getElement([
      'headline' => $block_configuration['headline']['headline'] ?? NULL,
      'hide_headline' => $block_configuration['headline']['hide_headline'] ?? 0,
      'heading_size' => $block_configuration['headline']['heading_size'] ?? 'h2',
      'headline_style' => $block_configuration['headline']['headline_style'] ?? 'default',
      'child_heading_size' => $block_configuration['headline']['child_heading_size'] ?? 'h3',
    ]);
    $form['headline']['#weight'] = 1;

    // Modify "Items per page" block settings form.
    if (!empty($allow_settings['items_per_page'])) {
      $form['override']['items_per_page']['#type'] = 'number';
      $form['override']['items_per_page']['#min'] = 0;
      $form['override']['items_per_page']['#title'] = $this->t('Items to display');
      $form['override']['items_per_page']['#description'] = $this->t('Select the number of entries to display');
      unset($form['override']['items_per_page']['#options']);
    }

    // Display exposed filters to allow them to be set for the block.
    $customizable_filters = $this->getOption('filter_in_block');
    if (!empty($customizable_filters)) {
      $form['override']['exposed_filters'] = [
        '#type' => 'details',
        '#title' => $this->t('Exposed filters'),
        '#description' => $this->t('Set default filters.'),
        '#tree' => TRUE,
      ];

      // Provide "Exposed filters" block settings form.
      $exposed_filter_values = !empty($block_configuration['exposed_filter_values']) ? $block_configuration['exposed_filter_values'] : [];

      $subform_state = SubformState::createForSubform($form['override']['exposed_filters'], $form, $form_state);
      $subform_state->set('exposed', TRUE);

      $filter_plugins = $this->getHandlers('filter');

      foreach ($customizable_filters as $filter_id) {
        /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
        $filter = $filter_plugins[$filter_id];
        $filter->buildExposedForm($form['override']['exposed_filters'], $subform_state);

        // Set the label and default values of the form element, based on the
        // block configuration.
        $exposed_info = $filter->exposedInfo();
        $form['override']['exposed_filters'][$filter_id]['#title'] = $exposed_info['label'];
        // The following is essentially using this patch:
        // https://www.drupal.org/project/views_block_placement_exposed_form_defaults/issues/3158789
        if ($form['override']['exposed_filters'][$filter_id]['#type'] == 'entity_autocomplete') {
          $form['override']['exposed_filters'][$filter_id]['#default_value'] = EntityAutocomplete::valueCallback(
            $form['override']['exposed_filters'][$filter_id],
            $exposed_filter_values[$filter_id],
            $form_state
          );
        }
        else {
          $form['override']['exposed_filters'][$filter_id]['#default_value'] = !empty($exposed_filter_values[$filter_id]) ? $exposed_filter_values[$filter_id] : [];
        }
      }
    }

    // Set overrides to show up in the middle of the form.
    $form['override']['#weight'] = 5;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit(ViewsBlock $block, $form, FormStateInterface $form_state) {

    // Set default value for items_per_page if left blank.
    if (empty($form_state->getValue(['override', 'items_per_page']))) {
      $form_state->setValue(['override', 'items_per_page'], 'none');
    }

    parent::blockSubmit($block, $form, $form_state);

    // Alter the headline field settings for configuration.
    $block->setConfigurationValue('headline', $form_state->getValue([
      'headline',
      'container',
    ]));

    // Save "Filter in block" settings to block configuration.
    $block->setConfigurationValue('exposed_filter_values', $form_state->getValue([
      'override',
      'exposed_filters',
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function preBlockBuild(ViewsBlock $block) {
    parent::preBlockBuild($block);

    $config = $block->getConfiguration();

    // Attach the headline, if configured.
    if (!empty($config['headline'])) {
      $headline = $config['headline'];
      $this->view->element['headline'] = [
        '#theme' => 'uiowa_core_headline',
        '#headline' => $headline['headline'],
        '#hide_headline' => $headline['hide_headline'],
        '#heading_size' => $headline['heading_size'],
        '#headline_style' => $headline['headline_style'],
      ];
      if (empty($headline['headline'])) {
        $child_heading_size = $headline['child_heading_size'];
      }
      else {
        $child_heading_size = HeadlineHelper::getHeadingSizeUp($headline['heading_size']);
      }

      $this->view->display_handler->setOption('heading_size', $child_heading_size);
    }

    // Set view filter based on "Filter" setting.
    $exposed_filter_values = !empty($config['exposed_filter_values']) ? $config['exposed_filter_values'] : [];
    $this->view->setExposedInput($exposed_filter_values);
  }

  /**
   * {@inheritdoc}
   *
   * @todo Determine whether this is necessary to have.
   */
  public function usesExposed() {
    $filters = $this->getHandlers('filter');
    foreach ($filters as $filter) {
      if ($filter->isExposed() && !empty($filter->exposedInfo())) {
        return TRUE;
      }
    }
    // Hotfix shim to keep these pagers working for now.
    // @todo Solve ListBlock paging.
    $display = $this->view->getDisplay();
    $exceptions = [
      'block_people_slf',
      'block_people_sfl',
      'block_articles',
    ];
    if (in_array($display->display['id'], $exceptions)) {
      return TRUE;
    }
    return FALSE;
  }

}
