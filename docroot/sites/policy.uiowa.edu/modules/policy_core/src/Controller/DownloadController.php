<?php

namespace Drupal\policy_core\Controller;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Url;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Dompdf\Dompdf;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Policy Manual download utility.
 */
class DownloadController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  public function __construct(FileSystemInterface $fileSystem, MessengerInterface $messenger) {
    $this->fileSystem = $fileSystem;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('messenger')
    );
  }

  /**
   * Policy manual status page.
   */
  public function policyManualPage(Request $request) {
    $file_path = 'public://exports/policy-manual.pdf';
    $real_path = $this->fileSystem->realpath($file_path);

    if (file_exists($real_path) && !$request->query->get('regenerate')) {
      $created = date('Y-m-d H:i:s', filectime($real_path));
      $markup = [
        '#type' => 'container',
      ];
      $markup['summary'] = [
        '#markup' => "<p>Policy manual PDF is ready (Last generated: {$created}).</p>",
      ];
      $markup['download'] = [
        '#type' => 'link',
        '#title' => $this->t('Download PDF'),
        '#url' => Url::fromRoute('policy_core.download_policy_manual'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
      $markup['regenerate'] = [
        '#type' => 'link',
        '#title' => $this->t('Regenerate PDF'),
        '#url' => Url::fromRoute('policy_core.policy_manual_page', [], ['query' => ['regenerate' => 1]]),
        '#attributes' => ['class' => ['button']],
      ];
      return $markup;
    }

    // If regenerating, delete the file.
    if ($request->query->get('regenerate') && file_exists($real_path)) {
      unlink($real_path);
    }

    // Start the batch process.
    $batch_builder = new BatchBuilder();
    // Start at the "Table of Contents" menu section.
    $mlid = 'menu_link_content:74d425e3-eac8-434d-ac03-cc0f63c32563';
    $nodes = self::getNodesByMenuOrder('main', 'page', $mlid);

    $batch_size = 20;
    foreach (array_chunk($nodes, $batch_size) as $chunk) {
      $batch_builder->addOperation([static::class, 'batchProcess'], [$chunk]);
    }

    $batch_builder
      ->setTitle($this->t('Generating Policy Manual PDF...'))
      ->setInitMessage($this->t('Starting PDF generation...'))
      ->setProgressMessage($this->t('Processed @current out of @total. Please wait while content is fetched and combined into a single PDF.'))
      ->setErrorMessage($this->t('An error occurred during PDF generation.'))
      ->setFinishCallback([static::class, 'batchFinished']);

    batch_set($batch_builder->toArray());
    return batch_process(Url::fromRoute('policy_core.policy_manual_page_finish')->toString());
  }

  /**
   * Batch operation callback.
   */
  public static function batchProcess(array $nodes_chunk, array &$context): void {
    $renderer = \Drupal::service('renderer');
    $fs = \Drupal::service('file_system');

    $html = '';
    foreach ($nodes_chunk as $node) {
      $render_array = \Drupal::entityTypeManager()
        ->getViewBuilder('node')
        ->view($node, 'pdf');
      $html .= '<div class="pdf-page">' . $renderer->render($render_array) . '</div>';
    }

    $temp_dir = 'private://policy_pdf_batches';
    $fs->prepareDirectory($temp_dir, FileSystemInterface::CREATE_DIRECTORY);
    $temp_file = tempnam($fs->realpath($temp_dir), 'batch_') . '.html';
    file_put_contents($temp_file, $html);

    $context['results']['temp_files'][] = $temp_file;
    $context['message'] = t('Processed @count nodes in this batch.', ['@count' => count($nodes_chunk)]);
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations): void {
    $messenger = \Drupal::messenger();
    $fs = \Drupal::service('file_system');

    if (!$success) {
      $messenger->addError(t('An error occurred during PDF generation.'));
      return;
    }

    $html = '<html><head><style> body { font-family: sans-serif; } .pdf-page { page-break-after: always; } .pdf-page:last-child { page-break-after: auto; } </style></head><body>';
    foreach ($results['temp_files'] as $temp_file) {
      $html .= file_get_contents($temp_file);
      unlink($temp_file);
    }
    $html .= '</body></html>';

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->render();

    $directory = 'public://exports';
    $destination = $directory . '/policy-manual.pdf';
    $fs->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    file_put_contents($fs->realpath($destination), $dompdf->output());

    $messenger->addStatus(t('PDF generation completed successfully.'));
  }

  /**
   * Download the stored copy.
   */
  public function downloadPolicyManual(): BinaryFileResponse {
    $file_path = 'public://exports/policy-manual.pdf';
    $real_path = $this->fileSystem->realpath($file_path);

    if (file_exists($real_path)) {
      $response = new BinaryFileResponse($real_path);
      $response->setContentDisposition(
        ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        basename($real_path)
      );
      $response->headers->set('Content-Type', 'application/pdf');
      return $response;
    }

    throw new NotFoundHttpException();
  }

  /**
   * Finish page after batch.
   */
  public function policyManualPageFinish(): array {
    $markup = [
      '#type' => 'container',
    ];
    $markup['summary'] = [
      '#markup' => '<p>You can now download the Policy Manual as a PDF.</p>',
    ];
    $markup['download'] = [
      '#type' => 'link',
      '#title' => $this->t('Download PDF'),
      '#url' => Url::fromRoute('policy_core.download_policy_manual'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];
    return $markup;

  }

  /**
   * Fetch nodes by menu order.
   */
  protected static function getNodesByMenuOrder(string $menu_name, string $node_type, string $root): array {
    $menu_tree = \Drupal::menuTree();

    $parameters = (new MenuTreeParameters())->setRoot($root);
    $tree = $menu_tree->load($menu_name, $parameters);

    $nodes = [];

    $traverse = function ($tree_items, &$nodes) use (&$traverse, $node_type) {
      foreach ($tree_items as $item) {
        $link = $item->link;
        if ($link->getRouteName() === 'entity.node.canonical') {
          $nid = $link->getRouteParameters()['node'] ?? NULL;
          if ($nid) {
            $node = Node::load($nid);
            if ($node && $node->isPublished() && $node->bundle() === $node_type) {
              $nodes[] = $node;
            }
          }
        }
        if (!empty($item->subtree)) {
          $traverse($item->subtree, $nodes);
        }
      }
    };

    $traverse($tree, $nodes);

    // Dedupe nodes because of anchor links.
    $seen = [];
    $unique_nodes = [];
    foreach ($nodes as $node) {
      if (!isset($seen[$node->id()])) {
        $unique_nodes[] = $node;
        $seen[$node->id()] = TRUE;
      }
    }

    return $unique_nodes;
  }

}
