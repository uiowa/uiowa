<?php

namespace Drupal\policy_core\Controller;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Url;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Dompdf\Dompdf;
use iio\libmergepdf\Merger;
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
   * File storage paths.
   */
  const EXPORT_DIR = 'public://exports';
  const EXPORT_FILE = 'policy-manual.pdf';
  const TEMP_DIR = 'private://policy_pdf_batches';
  const ROOT_MPID = 'menu_link_content:74d425e3-eac8-434d-ac03-cc0f63c32563';

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
    $file_path = self::EXPORT_DIR . '/' . self::EXPORT_FILE;
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
    // Starting menu link plugin ID.
    // Format as `menu_link_content:{uuid}`.
    $mpid = self::ROOT_MPID;
    $nodes = self::getNodesByMenuOrder('main', 'page', $mpid);

    $batch_size = 20;
    $order = 0;
    foreach (array_chunk($nodes, $batch_size) as $chunk) {
      $batch_builder->addOperation([static::class, 'batchProcess'], [
        $chunk,
        $order,
      ]);
      $order++;
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
  public static function batchProcess(array $nodes_chunk, int $order, array &$context): void {
    $renderer = \Drupal::service('renderer');
    $fs = \Drupal::service('file_system');

    ini_set('memory_limit', '512M');

    $fonts = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;0,900;1,400;1,700&family=Zilla+Slab:wght@400;600;700&display=swap" rel="stylesheet">';

    // Not including policy-specific print.css to keep breadcrumb hidden.
    $path_resolver = \Drupal::service('extension.path.resolver');
    $path = $path_resolver->getPath('module', 'policy_core');
    $policy_css = DRUPAL_ROOT . '/' . $path . '/css/policy.css';
    $print_styles = '<style>' . file_get_contents($policy_css) . '</style>';
    // Inline overrides.
    $print_styles .= '<style>body {font-family: "Roboto", sans-serif;} h1 {font-family: "Zilla Slab", serif}.block.block-menu, .block-system-breadcrumb-block, .block-field-blocknodepagetitle {display: none !important;} .pdf-page { page-break-after: always !important; } .pdf-page:last-child { page-break-after: auto !important; }</style>';

    $base_url = 'https://policy.uiowa.edu/';
    $html = '<html><head><base href="' . $base_url . '">' . $fonts . $print_styles . '</head><body>';
    foreach ($nodes_chunk as $node) {
      $render_array = \Drupal::entityTypeManager()
        ->getViewBuilder('node')
        ->view($node['node'], 'pdf');

      $parent_title = $node['parent_title'];
      if ($parent_title) {
        $render_array['parent_title'] = ['#markup' => $parent_title];
      }

      $html .= '<div class="pdf-page">' . $renderer->render($render_array) . '</div>';
    }
    $html .= '</body></html>';

    $tmp = $fs->getTempDirectory();

    $dompdf = new Dompdf([
      'isRemoteEnabled' => TRUE,
      'fontDir' => $tmp,
      'fontCache' => $tmp,
      'tempDir' => $tmp,
      'chroot' => DRUPAL_ROOT,
    ]);

    $pdf_stream_path = $tmp . '/batch_' . $order . '.pdf';

    try {
      $dompdf->loadHtml($html);
      $dompdf->render();
      file_put_contents($pdf_stream_path, $dompdf->output());
    }
    catch (\Exception $e) {
      \Drupal::logger('policy_core')
        ->error('PDF generation failed for chunk @order: @message', [
          '@order' => $order,
          '@message' => $e->getMessage(),
        ]);
      $context['results']['pdf_files'][] = [
        'order' => $order,
        'path' => NULL,
      ];
      $context['message'] = t('PDF generation failed for chunk @order.', ['@order' => $order]);
      return;
    }

    // Store chunk info in results so they can merge in order.
    $context['results']['pdf_files'][] = [
      'order' => $order,
      'path' => $pdf_stream_path,
    ];

    $context['message'] = t('Processed @count nodes in this batch.', ['@count' => count($nodes_chunk)]);
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations): void {
    $messenger = \Drupal::messenger();
    $fs = \Drupal::service('file_system');

    if (!$success || empty($results['pdf_files'])) {
      $messenger->addError(t('An error occurred during PDF generation.'));
      return;
    }

    // Sort files by their original order to preserve menu order.
    usort($results['pdf_files'], function ($a, $b) {
      return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
    });

    try {
      $merger = new Merger();

      foreach ($results['pdf_files'] as $pdf_info) {
        if (empty($pdf_info['path'])) {
          // Skip failed chunk entries.
          continue;
        }
        $real = $fs->realpath($pdf_info['path']);
        if ($real && file_exists($real)) {
          $merger->addFile($real);
        }
      }

      $merged_pdf = $merger->merge();

      // Cleanup temp files after merge.
      foreach ($results['pdf_files'] as $pdf_info) {
        if (empty($pdf_info['path'])) {
          continue;
        }
        $real = $fs->realpath($pdf_info['path']);
        if ($real && file_exists($real)) {
          @unlink($real);
        }
      }

      $directory = self::EXPORT_DIR;
      $destination = self::EXPORT_DIR . '/' . self::EXPORT_FILE;
      $fs->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      file_put_contents($fs->realpath($destination), $merged_pdf);

      $messenger->addStatus(t('PDF generation completed successfully.'));
    }
    catch (\Exception $e) {
      \Drupal::logger('policy_core')
        ->error('Failed to complete PDF generation: @message', ['@message' => $e->getMessage()]);
      $messenger->addError(t('Failed to complete PDF generation.'));
    }
  }

  /**
   * Download the stored copy.
   */
  public function pdfContentDownload(): BinaryFileResponse {
    $file_path = self::EXPORT_DIR . '/' . self::EXPORT_FILE;
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
  public static function getNodesByMenuOrder(string $menu_name, string $node_type, string $root): array {
    $menu_tree = \Drupal::menuTree();

    // Load the tree starting at the given root.
    $parameters = (new MenuTreeParameters())
      ->setRoot($root);
    $tree = $menu_tree->load($menu_name, $parameters);

    // Apply manipulators to sort by weight and check access.
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $menu_tree->transform($tree, $manipulators);

    $results = [];

    // Traverse recursively.
    $traverse = function (array $tree_items, array &$results) use (&$traverse, $node_type) {
      foreach ($tree_items as $item) {
        $link = $item->link;

        // Skip over disabled and subtree links.
        if (!$link->isEnabled()) {
          continue;
        }

        $parent_title = NULL;

        if ($link->getRouteName() === 'entity.node.canonical') {
          $nid = $link->getRouteParameters()['node'] ?? NULL;
          if ($nid) {
            $menu_link_manager = \Drupal::service('plugin.manager.menu.link');
            $links = $menu_link_manager->loadLinksByRoute('entity.node.canonical', ['node' => $nid]);
            if (!empty($links)) {
              $link = reset($links);
              $parent = $link->getParent();
              if ($parent) {
                $parent_title = $menu_link_manager->createInstance($parent)
                  ->getTitle();
                if ($parent_title === 'Table of Contents') {
                  $parent_title = 'Policy Manual';
                }
              }
            }
          }
          $node = Node::load($nid);
          if ($node instanceof NodeInterface &&
            $node->isPublished() &&
            $node->bundle() === $node_type) {
            $results[] = [
              'node' => $node,
              'parent_title' => $parent_title,
            ];
          }
        }

        // Dive into subtree if present.
        if (!empty($item->subtree)) {
          $traverse($item->subtree, $results);
        }
      }
    };

    $traverse($tree, $results);

    // Remove the first node (root "Table of Contents" link).
    array_shift($results);

    // De-dupe nodes in case of anchor variants.
    return array_values(array_reduce($results, function ($keep, $item) {
      $keep[$item['node']->id()] = $item;
      return $keep;
    }, []));
  }

}
