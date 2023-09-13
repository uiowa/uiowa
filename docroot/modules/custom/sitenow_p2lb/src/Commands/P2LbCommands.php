<?php

namespace Drupal\sitenow_p2lb\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Psr\Log\LoggerInterface;

/**
 * A Drush command file for sitenow_p2lb.
 */
class P2LbCommands extends DrushCommands {

  /**
   * The account_switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * The sitenow_p2lb logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Command constructor.
   */
  public function __construct(AccountSwitcherInterface $accountSwitcher, LoggerInterface $logger, EntityTypeManagerInterface $entityTypeManager) {
    $this->accountSwitcher = $accountSwitcher;
    $this->logger = $logger;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Clean up and remove v2/P2LB.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command sitenow_p2lb:cleanup
   *
   * @option batch The batch size
   * @aliases p2lb-cleanup
   * @usage sitenow_p2lb:cleanup
   *  Ideally run during the finishing process.
   * @usage sitenow_p2lb:cleanup --batch=5
   *  Process nodes with a specified batch size.
   */
  public function cleanup(array $options = ['batch' => 5]) {
    // Switch to the admin user to pass access check.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));

    $storage = $this->entityTypeManager->getStorage('node');

    // Get existing page nodes.
    $query = $storage
      ->getQuery()
      ->condition('type', 'page')
      ->accessCheck(TRUE);
    $entities = $query->execute();

    // If we don't have any entities, send a message and exit.
    if (empty($entities)) {
      $this->logger('sitenow_p2lb')->notice('No pages available to update.');

      // Switch user back.
      $this->accountSwitcher->switchBack();
      return;
    }

    // Batch them up.
    // Create the operations array for the batch.
    $operations = [];
    $num_operations = 0;
    $batch_id = 1;
    // Quick manipulate to ensure we have a positive
    // integer to use for the batch size.
    $batch_size = max(1, abs((int) $options['batch']));
    for ($i = 0; $i < count($entities);) {
      $nids = $storage
        ->getQuery()
        ->condition('type', 'page')
        ->range($i, $batch_size)
        ->execute();

      $operations[] = [
        '\Drupal\sitenow_p2lb\P2LbDeleteRevisions::deleteRevisions',
        [
          $batch_id,
          $nids,
        ],
      ];
      $batch_id++;
      $num_operations++;
      $i += $batch_size;
    }
    $batch = [
      'title' => t('Processing @num node(s).', [
        '@num' => $num_operations,
      ]),
      'operations' => $operations,
    ];

    batch_set($batch);
    drush_backend_batch_process();
    $this->logger('sitenow_p2lb')->notice('Process batch operations ended.');

    // Delete orphaned paragraphs, three-levels deep (section > block > item).
    $purger = \Drupal::service('entity_reference_revisions.orphan_purger');
    for ($i = 0; $i < 3; $i++) {
      $purger->setBatch(['paragraph']);
      drush_backend_batch_process();
    }

    // Turn off V2.
    $config_factory = \Drupal::configFactory();
    $sitenow_v2 = $config_factory->getEditable('config_split.config_split.sitenow_v2');
    $sitenow_v2->set('status', FALSE);
    $sitenow_v2->save(TRUE);

    // Turn off P2LB.
    $sitenow_p2lb = $config_factory->getEditable('config_split.config_split.p2lb');
    $sitenow_p2lb->set('status', FALSE);
    $sitenow_p2lb->save(TRUE);

    // Programmatically run `cim`.
    $alias = Drush::aliasManager()->getSelf();
    $config_import = Drush::processManager()->drush($alias, 'cim');
    $config_import->run($config_import->showRealtime());
    $config_import->getOutput();
    drupal_flush_all_caches();

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

}