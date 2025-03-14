<?php

namespace Drupal\sitenow\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\system\Controller\Http4xxController;

/**
 * Custom 403 handler to return a message for unpublished nodes.
 */
class Custom403Controller extends ControllerBase {
  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected AliasManagerInterface $aliasManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new Custom403Controller.
   */
  public function __construct(AliasManagerInterface $alias_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->aliasManager = $alias_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('path_alias.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Checks if the request is for an unpublished node.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return bool|string
   *   Returns FALSE if the node is published or not found,
   *   otherwise returns the unpublished message.
   */
  private function checkUnpublishedNode(Request $request) {
    $path = $request->getPathInfo();
    $system_path = $this->aliasManager->getPathByAlias($path);

    if (preg_match('/^\/node\/(\d+)$/', $system_path, $matches)) {
      $node = $this->entityTypeManager->getStorage('node')->load($matches[1]);

      if ($node && !$node->isPublished()) {
        return $this->t('This page is unavailable and may be under review.');
      }
    }
    return FALSE;
  }

  /**
   * Custom logic for handling 403 errors.
   */
  public function build(Request $request) {
    if ($message = $this->checkUnpublishedNode($request)) {
      return ['#markup' => $message];
    }

    $http4xxController = new Http4xxController();
    return $http4xxController->on403();
  }

  /**
   * Sets the custom page title.
   */
  public function title(Request $request) {
    return $this->checkUnpublishedNode($request) ? $this->t('Unavailable') : $this->t('Access Denied');
  }

}
