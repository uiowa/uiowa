<?php

namespace Drupal\admissions_core\Entity;

use Drupal\admissions_core\AdmissionsCoreInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;
use Drupal\uiowa_core\Entity\RendersAsCardTrait;

/**
 * An interface for admissions requirements paragraphs for area of study pages.
 */
class AdmissionsRequirement extends Paragraph implements RendersAsCardInterface {

  use RendersAsCardTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    $this->buildCardStyles($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#content' => ['field_ar_intro'],
    ]);

    // Old preprocess function turned into method.
    $card_details = $this->getDetails();

    if (isset($card_details['label'])) {
      // Label based on parent field.
      $build['#title'] = $card_details['label'];

      // Render icon as image.
      $build['#media']['icon'] = [
        '#type' => 'markup',
        '#markup' => '<img src="/themes/custom/uids_base/assets/images/' . strtolower($card_details['label']) . '.png" alt="' . $card_details['label'] . '" />',
      ];
    }

    // Custom list of links.
    if (isset($card_details['card_list'])) {
      $build['#content']['card_list'] = $card_details['card_list'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      'card_headline_style' => 'headline--serif',
      'card_media_position' => 'card--layout-left',
      'media_format' => 'media--circle',
      'media_size' => 'media--small',
      'borderless' => 'borderless',
    ];
  }

  /**
   * Provide card details.
   */
  protected function getDetails(): array {
    $details = [];
    $card_list = [];
    $admin_context = \Drupal::service('router.admin_context');
    if (!$admin_context->isAdminRoute()) {
      $parent = $this->getParentEntity();
      if ($parent instanceof ContentEntityInterface) {
        $admissions_requirements = [
          'field_area_of_study_first_year' => $this->t('First-Year'),
          'field_area_of_study_transfer' => $this->t('Transfer'),
          'field_area_of_study_intl' => $this->t('International'),
        ];
        $id = $this->id();
        foreach ($admissions_requirements as $requirement => $label) {
          if ($parent->hasField($requirement)) {
            foreach ($parent->get($requirement)->getValue() as $item) {
              if ($item['target_id'] === $id) {
                $details['label'] = $label;
                // Look up any published transfer tips with the AoS id.
                if ($requirement === 'field_area_of_study_transfer') {
                  $query = \Drupal::entityQuery('node')
                    ->condition('status', 1)
                    ->condition('type', 'transfer_tips')
                    ->condition('field_transfer_tips_aos', $parent->id())
                    ->accessCheck();
                  $nids = $query->execute();

                  if (!empty($nids)) {
                    // Get the first array item. We aren't going for perfection.
                    $transfer_tip = reset($nids);
                    // Get and pass the transfer tip path to the template
                    // if it exists.
                    $transfer_tip_url = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $transfer_tip);
                    $card_list['transfer_tip'] = [
                      '#type' => 'markup',
                      '#markup' => '<a href="' . $transfer_tip_url . '" class=""><span class="text--black"><i role="presentation" class="fas fa-lightbulb"></i></span> Transfer tips</a>',
                    ];
                  }
                  $query = \Drupal::entityQuery('node')
                    ->condition('type', 'major')
                    ->condition('status', 1)
                    ->condition('field_major_area_of_study', $parent->id(), '=')
                    ->accessCheck();
                  // We only really need to know if there are areas of study,
                  // and not which or how many, because the link will just be
                  // based on the aos id.
                  $count = $query->count()->execute();
                  if ($count > 0) {
                    // Get the link to the 2 Plus 2 community college
                    // aggregator page using area of study title.
                    $area_of_study_title = $parent->getTitle();
                    $slug = \Drupal::service('pathauto.alias_cleaner')->cleanString($area_of_study_title);
                    $two_plus_two_url = \Drupal::service('path_alias.manager')->getAliasByPath(AdmissionsCoreInterface::TWO_PLUS_TWO_PATH . $slug);
                    $card_list['two_plus_two'] = [
                      '#type' => 'markup',
                      '#markup' => '<a href="' . $two_plus_two_url . '" class=""><span class="text--black"><i role="presentation" class="fas fa-calendar-check"></i></span> 2 plus 2 plan</a>',
                    ];
                  }
                }
              }
            }
          }
        }
      }

      if ($this->hasField('field_ar_requirement')) {
        foreach ($this->get('field_ar_requirement')->getIterator() as $key => $link) {
          $card_list['ar_requirement'][$key] = [
            '#type' => 'markup',
            '#markup' => '<a href="' . $link->getUrl()->toString() . '" class=""><span class="text--black"><i role="presentation" class="fas fa-arrow-right"></i></span> ' . $link->get('title')->getString() . ' </a>',
          ];
        }
      }

      if ($this->hasField('field_ar_process') && !$this->get('field_ar_process')->isEmpty()) {
        foreach ($this->get('field_ar_process')->getIterator() as $key => $link) {
          $card_list['ar_process'][$key] = [
            '#type' => 'markup',
            '#markup' => '<a href="' . $link->getUrl()->toString() . '" class=""><span class="text--black"><i role="presentation" class="fas fa-arrow-right"></i></span> ' . $link->get('title')->getString() . ' </a>',
          ];
        }
      }

      if (!empty($card_list)) {
        $details['card_list'] = [
          '#theme' => 'item_list',
          '#type' => 'ul',
          '#items' => $card_list,
          '#attributes' => ['class' => 'element--list-none fa-field-item'],
        ];
      }
    }
    return $details;
  }

}
