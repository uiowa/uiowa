<?php

namespace Drupal\Tests\uiowa_core\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the card render element.
 *
 * @group uiowa_core
 */
class CardElementTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'uids_base';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'uiowa_core',
    'uiowa_core_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set uids_base header type to avoid Twig error.
    $this->config('uids_base.settings')->set('header.type', 'inline')->save();
  }

  /**
   * Tests a card being rendered.
   */
  public function testCard(): void {
    $assert_session = $this->assertSession();

    // Check card element defaults.
    $this->drupalGet('/card_test_element');

    // The card should not have the click-container class since there is no URL.
    $this->assertTrue($this->cardExists()->hasAttribute('data-uids-no-link'), 'Card should have the data-uids-no-link attribute');
    $this->assertFalse($this->cardExists()->hasClass('click-container'), 'Card should not have the .click-container class.');

    // Check that no button type is showing.
    $this->assertFalse($this->cardHasLinkedButton());
    $this->assertFalse($this->cardHasPseudoButton());
    $this->assertFalse($this->cardHasIndicatorButton());

    // Assert card has no title.
    $assert_session->pageTextNotContains('Continue Your Story at Iowa');

    // Check pseudo-button: card has title, URL, and link text.
    $this->drupalGet('/card_test_element', [
      'query' => [
        'title' => TRUE,
        'url' => TRUE,
        'link_text' => TRUE,
      ],
    ]);

    // The card should not have the click-container class since there is no URL.
    $this->assertFalse($this->cardExists()->hasAttribute('data-uids-no-link'), 'Card should not have the data-uids-no-link attribute.');
    $this->assertTrue($this->cardExists()->hasClass('click-container'), 'Card should have the .click-container class.');

    // Check only pseudo-button is showing.
    $this->assertFalse($this->cardHasLinkedButton());
    $this->assertTrue($this->cardHasPseudoButton());
    $this->assertFalse($this->cardHasIndicatorButton());

    $assert_session->pageTextContains('Continue Your Story at Iowa');

    // Check indicator button: card has URL and link indicator.
    $this->drupalGet('/card_test_element', [
      'query' => [
        'url' => TRUE,
        'link_indicator' => TRUE,
      ],
    ]);

    // The card should not have the click-container class since there is no URL.
    $this->assertFalse($this->cardExists()->hasAttribute('data-uids-no-link'), 'Card should have the data-uids-no-link attribute');
    $this->assertTrue($this->cardExists()->hasClass('click-container'), 'Card should have the .click-container class.');

    // Check only pseudo-button is showing.
    $this->assertFalse($this->cardHasLinkedButton());
    $this->assertFalse($this->cardHasPseudoButton());
    $this->assertTrue($this->cardHasIndicatorButton());

    // Assert card has no title.
    $assert_session->pageTextNotContains('Continue Your Story at Iowa');
  }

  /**
   * Return the card element if it exists.
   */
  protected function cardExists(): NodeElement {
    return $this->assertSession()
      ->elementExists('css', '.card');
  }

  /**
   * Checks if the card has a linked button.
   */
  protected function cardHasLinkedButton(): bool {
    $button = $this->getCardButton();
    return !is_null($button) &&
      $button->getText() === 'Get started' &&
      $button->getTagName() === 'a' &&
      $button->getAttribute('href') === 'https://uiowa.edu';
  }

  /**
   * Check if the card has a pseudo-button.
   */
  protected function cardHasPseudoButton(): bool {
    $button = $this->getCardButton();
    return !is_null($button) &&
      $button->getText() === 'Get started' &&
      $button->getTagName() === 'div' &&
      !$button->hasAttribute('href');
  }

  /**
   * Check if the card has an indicator button.
   */
  protected function cardHasIndicatorButton(): bool {
    $button = $this->getCardButton();
    return !is_null($button) &&
      $button->getText() === '' &&
      $button->getTagName() === 'a' &&
      $button->find('css', '.fa-arrow-right') &&
      $button->getAttribute('href') === 'https://uiowa.edu';
  }

  /**
   * Get the card button, if it exists.
   */
  protected function getCardButton(): ?NodeElement {
    return $this->cardExists()
      ->find('css', '.bttn');
  }

}
