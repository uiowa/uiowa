<?php

namespace Drupal\uiowa_apr;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * The APR service sets some dynamic properties based on the environment.
 *
 * @property \Drupal\Core\Config\ImmutableConfig $config The APR config.
 * @property string $environment The APR environment.
 * @property string $endpoint The APR API endpoint.
 */
class Apr {

  /**
   * The APR settings config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The APR environment.
   *
   * @var string
   */
  protected $environment;

  /**
   * The APR API endpoint.
   *
   * @var string
   */
  protected $endpoint;

  /**
   * Constructs an Apr object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('uiowa_apr.settings');
    $this->setEnvironment();
    $this->setEndpoint();
  }

  /**
   * Get a protected property.
   *
   * @param string $name
   *   The property name.
   *
   * @return mixed
   *   The property value.
   */
  public function __get($name) {
    return $this->$name;
  }

  /**
   * Set the environment property based on config first then AH environment.
   */
  protected function setEnvironment() {
    if ($env = $this->config->get('environment')) {
      $this->environment = $env;
    }
    else {
      $env = getenv('AH_SITE_ENVIRONMENT');

      switch ($env) {
        case 'dev':
        case 'test':
          $this->environment = 'test';
          break;

        case 'prod':
          $this->environment = 'prod';
          break;

        default:
          $this->environment = 'prod';
      }
    }
  }

  /**
   * Set the API endpoint based on the environment property.
   */
  protected function setEndpoint() {
    if ($this->environment == 'test') {
      $this->endpoint = 'https://test.its.uiowa.edu/apr';
    }
    elseif ($this->environment == 'prod') {
      $this->endpoint = 'https://apps.its.uiowa.edu/apr';
    }
  }

}
