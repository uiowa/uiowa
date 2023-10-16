<?php

declare(strict_types=1);

namespace Drupal\theme_permission\Controller;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\system\Controller\SystemController;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Theme\ThemeAccessCheck;
use Drupal\system\SystemManager;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormBuilderInterface;

/**
 * Access Controller.
 *
 * @package Drupal\theme_permission\Controller.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class AccessController extends SystemController {

  /**
   * Current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Constructs a  AccessController.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   A request stack symfony instance.
   * @param \Drupal\system\SystemManager $systemManager
   *   System manager service.
   * @param \Drupal\Core\Theme\ThemeAccessCheck $theme_access
   *   The theme access checker service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_link_tree
   *   The menu link tree service.
   */
  public function __construct(AccountInterface $current_user,
    RequestStack $request_stack,
    SystemManager $systemManager,
    ThemeAccessCheck $theme_access,
    FormBuilderInterface $form_builder,
    ThemeHandlerInterface $theme_handler,
    MenuLinkTreeInterface $menu_link_tree,
    ModuleExtensionList $module_extension_list) {
    parent::__construct($systemManager, $theme_access, $form_builder, $theme_handler, $menu_link_tree, $module_extension_list);
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('system.manager'),
      $container->get('access_check.theme'),
      $container->get('form_builder'),
      $container->get('theme_handler'),
      $container->get('menu.link_tree'),
      $container->get('extension.list.module')
    );
  }

  /**
   * Check permission.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Get Account.
   * @param string|null $theme
   *   Theme Name.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, string $theme = NULL): AccessResultInterface {
    if (empty($theme)) {

      $current_request = $this->requestStack->getCurrentRequest();
      if ($current_request) {
        $theme = $current_request->query->get('theme');
      }
    }

    $auth = $account->hasPermission("administer themes $theme");
    if ($auth) {
      return AccessResult::allowed();
    }
    else {
      return AccessResult::forbidden();
    }
  }

  /**
   * {@inheritdoc}
   *
   *  @SuppressWarnings(PHPMD)
   */
  public function themesPage() {
    $config = $this->config('system.theme');
    // Get all available themes.
    /** @var \Drupal\Core\Extension\ThemeExtensionList $themes */
    $themes = $this->themeHandler->rebuildThemeData();
    /* @phpstan-ignore-next-line */
    uasort($themes, [ModuleExtensionList::class, 'sortByName']);

    $theme_default = $config->get('default');
    $theme_groups = ['installed' => [], 'uninstalled' => []];
    $admin_theme = $config->get('admin');
    $admin_theme_options = [];
    $incompatible_installed = FALSE;
    /* @phpstan-ignore-next-line */
    foreach ($themes as &$theme) {
      $theme_name = $theme->getName();
      $auth = $this->currentUser
        ->hasPermission("administer themes $theme_name");
      $uninstall_theme = $this->currentUser
        ->hasPermission("uninstall themes $theme_name");
      if ($auth) {
        if (!empty($theme->info['hidden'])) {
          continue;
        }
        if (!$incompatible_installed && $theme->info['core_incompatible'] && $theme->status) {
          $incompatible_installed = TRUE;
          $this->messenger()->addWarning($this->t(
            'There are errors with some installed themes. Visit the <a href=":link">status report page</a> for more information.',
            [':link' => Url::fromRoute('system.status')->toString()]
          ));
        }
        $theme->is_default = ($theme->getName() === $theme_default);
        $theme->is_admin = ($theme->getName() === $admin_theme || ($theme->is_default && empty($admin_theme)));
        $theme->is_experimental = isset($theme->info['experimental']) && $theme->info['experimental'];

        // Identify theme screenshot.
        $theme->screenshot = NULL;
        // Create a list which includes
        // the current theme and all its base themes.
        /* @phpstan-ignore-next-line */
        if (isset($themes[$theme->getName()]->base_themes)) {
          $theme_keys = array_keys($themes[$theme->getName()]->base_themes);
          $theme_keys[] = $theme->getName();
        }
        else {
          $theme_keys = [$theme->getName()];
        }
        // Look for a screenshot in the current
        // theme or in its closest ancestor.
        foreach (array_reverse($theme_keys) as $theme_key) {
          /* @phpstan-ignore-next-line */
          if (isset($themes[$theme_key]) && file_exists($themes[$theme_key]->info['screenshot'])) {
            $theme->screenshot = [
              'uri' => $themes[$theme_key]->info['screenshot'],
              'alt' => $this->t('Screenshot for @theme theme', ['@theme' => $theme->info['name']]),
              'title' => $this->t('Screenshot for @theme theme', ['@theme' => $theme->info['name']]),
              'attributes' => ['class' => ['screenshot']],
            ];
            break;
          }
        }

        if (empty($theme->status)) {
          // Require the 'content' region to make sure the main page
          // content has a common place in all themes.
          $theme->incompatible_region = !isset($theme->info['regions']['content']);
          /* @phpstan-ignore-next-line */
          $theme->incompatible_php = version_compare(phpversion(), $theme->info['php']) < 0;
          // Confirm that all base themes are available.
          /* @phpstan-ignore-next-line */
          $theme->incompatible_base = (isset($theme->info['base theme']) && !($theme->base_themes === array_filter($theme->base_themes)));
          // Confirm that the theme engine is available.
          $theme->incompatible_engine = isset($theme->info['engine']) && !isset($theme->owner);
          // Confirm that module dependencies are available.
          $theme->incompatible_module = FALSE;
          // Confirm that the user has permission to enable modules.
          $theme->insufficient_module_permissions = FALSE;
        }

        // Check module dependencies.
        if ($theme->module_dependencies) {
          $modules = $this->moduleExtensionList->getList();
          foreach ($theme->module_dependencies as $dependency => $dependency_object) {
            if ($incompatible = $this->checkDependencyMessage($modules, $dependency, $dependency_object)) {
              $theme->module_dependencies_list[$dependency] = $incompatible;
              $theme->incompatible_module = TRUE;
              continue;
            }

            // @todo Add logic for not displaying hidden modules in
            //   https://drupal.org/node/3117829.
            /* @phpstan-ignore-next-line */
            $module_name = $modules[$dependency]->info['name'];
            /* @phpstan-ignore-next-line */
            $theme->module_dependencies_list[$dependency] = $modules[$dependency]->status ? $this->t('@module_name', ['@module_name' => $module_name]) : $this->t('@module_name (<span class="admin-disabled">disabled</span>)', ['@module_name' => $module_name]);

            // Create an additional property that contains only disabled module
            // dependencies. This will determine
            // if it is possible to install the
            // theme, or if modules must first be enabled.
            if (!$modules[$dependency]->status) {
              $theme->module_dependencies_disabled[$dependency] = $module_name;
              if (!$this->currentUser()->hasPermission('administer modules')) {
                $theme->insufficient_module_permissions = TRUE;
              }
            }
          }
        }

        $theme->operations = [];
        if (!empty($theme->status) || !$theme->info['core_incompatible'] && !$theme->incompatible_php && !$theme->incompatible_base && !$theme->incompatible_engine && !$theme->incompatible_module && empty($theme->module_dependencies_disabled)) {
          // Create the operations links.
          $query['theme'] = $theme->getName();
          if ($this->themeAccess->checkAccess($theme->getName())) {
            $theme->operations[] = [
              'title' => $this->t('Settings'),
              'url' => Url::fromRoute('system.theme_settings_theme', ['theme' => $theme->getName()]),
              'attributes' => ['title' => $this->t('Settings for @theme theme', ['@theme' => $theme->info['name']])],
            ];
          }
          if (!empty($theme->status)) {
            if (!$theme->is_default) {
              $theme_uninstallable = TRUE;
              /* @phpstan-ignore-next-line */
              if ($theme->getName() === $admin_theme) {
                $theme_uninstallable = FALSE;
              }
              // Check it isn't the base of theme of an installed theme.
              /* @phpstan-ignore-next-line */
              foreach ($theme->required_by as $themename => $dependency) {
                /* @phpstan-ignore-next-line */
                if (!empty($themes[$themename]->status)) {
                  $theme_uninstallable = FALSE;
                }
              }
              if ($theme_uninstallable && $uninstall_theme) {
                $theme->operations[] = [
                  'title' => $this->t('Uninstall'),
                  'url' => Url::fromRoute('system.theme_uninstall'),
                  'query' => $query,
                  /* @phpstan-ignore-next-line */
                  'attributes' => ['title' => $this->t('Uninstall @theme theme', ['@theme' => $theme->info['name']])],
                ];
              }
              $theme->operations[] = [
                'title' => $this->t('Set as default'),
                'url' => Url::fromRoute('system.theme_set_default'),
                'query' => $query,
                /* @phpstan-ignore-next-line */
                'attributes' => ['title' => $this->t('Set @theme as default theme', ['@theme' => $theme->info['name']])],
              ];
            }
            /* @phpstan-ignore-next-line */
            $admin_theme_options[$theme->getName()] = $theme->info['name'] . ($theme->is_experimental ? ' (' . $this->t('Experimental') . ')' : '');
          }
          else {
            $theme->operations[] = [
              'title' => $this->t('Install'),
              'url' => Url::fromRoute('system.theme_install'),
              'query' => $query,
              'attributes' => ['title' => $this->t('Install @theme theme', ['@theme' => $theme->info['name']])],
            ];
            $theme->operations[] = [
              'title' => $this->t('Install and set as default'),
              'url' => Url::fromRoute('system.theme_set_default'),
              'query' => $query,
              'attributes' => ['title' => $this->t('Install @theme as default theme', ['@theme' => $theme->info['name']])],
            ];
          }
        }

        // Add notes to default theme, administration theme and experimental
        // themes.
        $theme->notes = [];
        if ($theme->is_default) {
          $theme->notes[] = $this->t('default theme');
        }
        if ($theme->is_admin) {
          $theme->notes[] = $this->t('administration theme');
        }
        if ($theme->is_experimental) {
          $theme->notes[] = $this->t('experimental theme');
        }

        // Sort installed and uninstalled themes into their own groups.
        $theme_groups[$theme->status ? 'installed' : 'uninstalled'][] = $theme;
      }
    }

    // There are two possible theme groups.
    $theme_group_titles = [
      'installed' => $this->formatPlural(count($theme_groups['installed']), 'Installed theme', 'Installed themes'),
    ];
    if (!empty($theme_groups['uninstalled'])) {
      $theme_group_titles['uninstalled'] = $this->formatPlural(count($theme_groups['uninstalled']), 'Uninstalled theme', 'Uninstalled themes');
    }

    uasort($theme_groups['installed'], 'system_sort_themes');
    $this->moduleHandler()->alter('system_themes_page', $theme_groups);

    $build = [];
    $build[] = [
      '#theme' => 'system_themes_page',
      '#theme_groups' => $theme_groups,
      '#theme_group_titles' => $theme_group_titles,
    ];
    $admin_theme_auth = $this->currentUser->hasPermission("Edit Administration theme");
    if ($admin_theme_auth) {
      /* @phpstan-ignore-next-line */
      $build[] = $this->formBuilder->getForm('Drupal\system\Form\ThemeAdminForm', $admin_theme_options);
    }
    /* @phpstan-ignore-next-line */
    return $build;
  }

}
