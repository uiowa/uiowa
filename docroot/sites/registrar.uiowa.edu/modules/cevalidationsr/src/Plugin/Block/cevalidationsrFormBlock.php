<?php
/**
 * @file
 * Contains \Drupal\cevalidationsr\Plugin\Block\cevalidationsrForm.
 */

namespace Drupal\cevalidationsr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormInterface;

/**
 * Provides a 'cevalidationsrFormBlock' block.
 * @Block(
 * id = "cevalidationsr_Form_block",
 * admin_label = @Translation("CeCredential Validation Scholar Record Form block"),
 * category = @Translation("Site custom")
 * )
 */

class cevalidationsrFormBlock extends BlockBase
{
    /**
     * {@inheritdoc}
     */
    public function build()
    {
         $form = \Drupal::formBuilder()->getForm('Drupal\cevalidationsr\Form\cevalidationsrForm');
         return $form;
    }
}
