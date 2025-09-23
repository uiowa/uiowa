<?php

namespace Drupal\policy_core\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Url;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
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
class PDFContentController extends ControllerBase implements ContainerInjectionInterface {

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
   * PDF Content Status Page.
   */
  public function pdfContent(Request $request) {
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
        '#url' => Url::fromRoute('policy_core.pdf_content_download'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
      $markup['regenerate'] = [
        '#type' => 'link',
        '#title' => $this->t('Regenerate PDF'),
        '#url' => Url::fromRoute('policy_core.pdf_content', [], ['query' => ['regenerate' => 1]]),
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
    return batch_process(Url::fromRoute('policy_core.pdf_content_finish')->toString());
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

      // Add menu parent title if available.
      $menu_link_manager = \Drupal::service('plugin.manager.menu.link');
      $links = $menu_link_manager->loadLinksByRoute('entity.node.canonical', ['node' => $node->id()]);
      if (!empty($links)) {
        $link = reset($links);
        $parent = $link->getParent();
        if ($parent && $parent !== '') {
          $title = $menu_link_manager->createInstance($parent)->getTitle();
          // Override "Table of Contents" title for PDF output.
          if ($title === 'Table of Contents') {
            $title = 'Policy Manual';
          }
          $parent_title = [
            '#markup' => '<h1>' . $title . '</h1>',
            '#allowed_tags' => ['h1'],
          ];
          $render_array['parent_title'] = $parent_title;
        }
      }

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
    $theme_handler = \Drupal::service('theme_handler');

    if (!$success) {
      $messenger->addError(t('An error occurred during PDF generation.'));
      return;
    }

    // Include print.css from the uids_base.
    // Not including policy specific print styles to keep breadcrumb hidden.
    $theme_path = $theme_handler->getTheme('uids_base')->getPath();
    $print_styles = file_get_contents($theme_path . '/assets/css/theme/print.css');
    $print_styles .= '\n' . '.pdf-page { page-break-after: always !important; } .pdf-page:last-child { page-break-after: auto !important; }';

    $style = '<style>' . Html::escape($print_styles) . '</style>';

    $html = '<html><head>' . $style . '</head><body>';

    foreach ($results['temp_files'] as $temp_file) {
      $html .= file_get_contents($temp_file);
      unlink($temp_file);
    }
    $html .= '</body></html>';

    $dompdf = new Dompdf();
    try {
      $dompdf->loadHtml($html);
      $dompdf->render();
    }
    catch (\Exception $e) {
      \Drupal::logger('policy_core')->error('PDF generation failed: @message', ['@message' => $e->getMessage()]);
      \Drupal::messenger()->addError(t('Unable to generate the PDF.'));
      return;
    }

    $directory = 'public://exports';
    $destination = $directory . '/policy-manual.pdf';
    $fs->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    file_put_contents($fs->realpath($destination), $dompdf->output());

    $messenger->addStatus(t('PDF generation completed successfully.'));
  }

  /**
   * Download the stored copy.
   */
  public function pdfContentDownload(): BinaryFileResponse {
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
  public function pdfContentFinish(): array {
    $markup = [
      '#type' => 'container',
    ];
    $markup['summary'] = [
      '#markup' => '<p>You can now download the Policy Manual as a PDF.</p>',
    ];
    $markup['download'] = [
      '#type' => 'link',
      '#title' => $this->t('Download PDF'),
      '#url' => Url::fromRoute('policy_core.pdf_content_download'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];
    return $markup;

  }

  /**
   * Fetch nodes by menu order.
   */
  protected static function getNodesByMenuOrder(string $menu_name, string $node_type, string $root): array {
    $menu_tree = \Drupal::menuTree();

    // Load the tree starting at the given root.
    $parameters = (new MenuTreeParameters())->setRoot($root);
    $tree = $menu_tree->load($menu_name, $parameters);

    // Apply manipulators to sort by weight and check access.
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $menu_tree->transform($tree, $manipulators);

    $nodes = [];

    // Traverse recursively.
    $traverse = function (array $tree_items, array &$nodes) use (&$traverse, $node_type) {
      foreach ($tree_items as $item) {
        $link = $item->link;
        if ($link->getRouteName() === 'entity.node.canonical') {
          $nid = $link->getRouteParameters()['node'] ?? NULL;
          if ($nid) {
            $node = Node::load($nid);
            if ($node instanceof NodeInterface &&
              $node->isPublished() &&
              $node->bundle() === $node_type) {
              $nodes[] = $node;
            }
          }
        }

        // Dive into subtree if present.
        if (!empty($item->subtree)) {
          $traverse($item->subtree, $nodes);
        }
      }
    };

    $traverse($tree, $nodes);

    // Remove the first node, "Table of Contents".
    array_shift($nodes);

    // De-dupe nodes in case of anchor variants.
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
