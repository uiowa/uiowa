<?php

namespace Drupal\registrar_core\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\uiowa_maui\MauiApi;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class RegistrarCoreCommands extends DrushCommands {
  use LoggerChannelTrait;
  use StringTranslationTrait;

  /**
   * The uiowa_maui.api service.
   *
   * @var \Drupal\uiowa_maui\MauiApi
   */
  protected $mauiApi;

  /**
   * The cache.uiowa_maui service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $mauiCache;

  /**
   * The datetime.time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drush command constructor.
   *
   * @param \Drupal\uiowa_maui\MauiApi $mauiApi
   *   The uiowa_maui.api service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $mauiCache
   *   The cache.uiowa_maui service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime.time service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(MauiApi $mauiApi, CacheBackendInterface $mauiCache, TimeInterface $time, ConfigFactoryInterface $config_factory) {
    $this->mauiApi = $mauiApi;
    $this->mauiCache = $mauiCache;
    $this->time = $time;
    $this->configFactory = $config_factory;
  }

  /**
   * Triggers the final exam import.
   *
   * @command registrar_core:final-exam-schedule
   * @aliases registrar-exams
   * @usage registrar_core:final-exam-schedule
   *  Ideally this is done as a crontab that is only run once a day.
   */
  public function getFinalExam() {
    $session_id = $this->configFactory->get('registrar_core.final_exam_schedule')->get('session_id');
    $data = $this->mauiApi->getFinalExamSchedule($session_id);

    switch ($data['_status']) {
      case 'ok':
        $data = $data['_data'];
        break;

      default:
        // Handle errored or empty results.
        $arguments = [
          '@status' => $data['_status'],
          '@session_id' => $session_id,
          '@message' => $data['_message'],
        ];
        $this->getLogger('registrar_core')->notice($this->t('Final exam schedule import for session @session_id: @status. @message', $arguments));
        return;
    }

    // Create a cache item set to 24 hours.
    $cid = "uiowa_maui:request:final_exam_schedule:{$session_id}";
    $request_time = $this->time->getRequestTime();
    $this->mauiCache->set($cid, $data, $request_time + 86400);
  }

}
