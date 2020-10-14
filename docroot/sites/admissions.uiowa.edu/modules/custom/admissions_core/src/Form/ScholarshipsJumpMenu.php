<?php

namespace Drupal\admissions_core\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a Admissions Core form.
 */
class ScholarshipsJumpMenu extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'admissions_core_scholarships_jump_menu';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['scholarship_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type of Student'),
      '#options' => [
        'first-year' => 'First Year',
        'transfer' => 'Transfer',
        'international' => 'International'
      ],
      "#empty_option" => t('- Select -'),
      '#ajax' => [
        'callback' => [$this, 'scholarshipTypeCallback'],
        'event' => 'change',
      ]
    ];
    $form['resident'] = [
      '#type' => 'select',
      '#title' => $this->t('Resident'),
      '#options' => [
        'resident' => 'From Iowa',
        'nonresident' => 'From Outside Iowa'
      ],
      "#empty_option" => t('- Select Residency -'),
      '#states' => [
        'visible' => [
          ':input[name="scholarship_type"]' => ['value' => 'first-year'],
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'scholarshipResidentCallback'],
        'event' => 'change',
      ]
    ];

    return $form;
  }

  /**
   * Scholarship Type Jump Select.
   */
  public function scholarshipTypeCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $build_info = $form_state->getBuildInfo();

    if ($selectedValue = $form_state->getValue('scholarship_type')) {
      if ($selectedValue == 'international') {
        $url = Url::fromRoute('entity.node.canonical', ['node' => $build_info["scholarship_paths"]["international"]]);
        $response->addCommand(new InvokeCommand('#edit-scholarship-type', 'val', ['']));
        $response->addCommand(new RedirectCommand($url->toString()));
        return $response;
      }
      if ($selectedValue == 'transfer') {
        $url = Url::fromRoute('entity.node.canonical', ['node' => $build_info["scholarship_paths"]["transfer"]]);
        $response->addCommand(new InvokeCommand('#edit-scholarship-type', 'val', ['']));
        $response->addCommand(new RedirectCommand($url->toString()));
        return $response;
      }
    }

    return $response;
  }

  /**
   * Scholarship Resident Jump Select.
   */
  public function scholarshipResidentCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $build_info = $form_state->getBuildInfo();
    if ($selectedValue = $form_state->getValue('resident')) {
      if ($selectedValue == 'resident') {
        $url = Url::fromRoute('entity.node.canonical', ['node' => $build_info["scholarship_paths"]["resident"]]);
        $response->addCommand(new InvokeCommand('#edit-scholarship-type', 'val', ['']));
        $response->addCommand(new InvokeCommand('#edit-resident', 'val', ['']));
        $response->addCommand(new RedirectCommand($url->toString()));
        return $response;
      }
      else {
        $url = Url::fromRoute('entity.node.canonical', ['node' => $build_info["scholarship_paths"]["nonresident"]]);
        $response->addCommand(new InvokeCommand('#edit-scholarship-type', 'val', ['']));
        $response->addCommand(new InvokeCommand('#edit-resident', 'val', ['']));
        $response->addCommand(new RedirectCommand($url->toString()));
        return $response;
      }
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Do nothing.
  }

}
