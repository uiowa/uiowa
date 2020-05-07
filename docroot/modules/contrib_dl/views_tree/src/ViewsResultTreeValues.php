<?php

namespace Drupal\views_tree;

use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Methods to get result tree based on views results.
 */
class ViewsResultTreeValues {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * Sets tree values given a view and result set.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view object.
   * @param \Drupal\views\ResultRow[] $result
   *   The result set.
   *
   * @return array
   *   The result.
   */
  public function setTreeValues(ViewExecutable $view, array $result) {
    $fields = $view->field;
    $options = $view->getStyle()->options;

    $parents = [];

    if (!$fields[$options['main_field']] instanceof FieldPluginBase) {
      $this->messenger()->addError($this->t('Main field is invalid: %field', ['%field' => $options['main_field']]), 'error');
      return [];
    }

    if (!$fields[$options['parent_field']] instanceof FieldPluginBase) {
      $this->messenger()->addError($this->t('Parent field is invalid: %field', ['%field' => $options['parent_field']]), 'error');
      return [];
    }

    // The field structure of Field API fields in a views result object is...
    // ridiculous. To avoid having to deal with it, we'll first iterate over all
    // records and normalize out the main and parent IDs to new properties. That
    // vastly simplifies the code that follows. This particular magic
    // incantation extracts the value from each record for the appropriate field
    // specified by the user. It then normalizes that value down to just an int,
    // even though in some cases it is an array. See views_tree_normalize_key().
    // Finally, we build up a list of all main keys in the result set so that we
    // can normalize top-level records below.
    foreach ($result as $i => $record) {
      $result[$i]->views_tree_main = $this->normalizeKey($fields[$options['main_field']]->getValue($record), $fields[$options['main_field']]);
      $result[$i]->views_tree_parent = $this->normalizeKey($fields[$options['parent_field']]->getValue($record), $fields[$options['parent_field']]);

      $parents[] = $record->views_tree_main;
    }

    // Normalize the top level of records to all point to 0 as their parent
    // We only have to do this once, so we do it here in the wrapping function.
    foreach ($result as $i => $record) {
      if (!in_array($record->views_tree_parent, $parents)) {
        $result[$i]->views_tree_parent = 0;
      }
    }

    // Add the depth onto the result.
    foreach ($result as $row) {
      $current_row = $row;
      $depth = 0;
      while ($current_row->views_tree_parent != '0') {
        $depth++;
        if ($parent_row = $this->findRowByParent($result, $current_row->views_tree_parent)) {
          $current_row = $parent_row;
        }
        else {
          break;
        }
      }
      $row->views_tree_depth = $depth;
    }

    return $result;
  }

  /**
   * Finds a row given a parent.
   *
   * @param \Drupal\views\ResultRow[] $result
   *   The view result array.
   * @param mixed $parent_id
   *   The parent ID.
   *
   * @return \Drupal\views\ResultRow|null
   *   The corresponding row if foundm, NULL otherwise.
   */
  protected function findRowByParent(array $result, $parent_id) {
    foreach ($result as $row) {
      if ($parent_id == $row->views_tree_main) {
        return $row;
      }
    }
  }

  /**
   * Normalize a value out of the record to an int.
   *
   * If the field in question comes from Field API, then it will be an array,
   * not an int. We need to detect that and extract the int value we want from
   * it. Note that because Field API structures are so free-form, we have to
   * specifically support each field type.  For now we support entityreference
   * (target_id), nodereference (nid), userreference (uid), organic groups
   * (gid), and taxonomyreference (tid).
   *
   * @param mixed $value
   *   The value to normalize. It should be either an int or an array. If an
   *   int, it is returned unaltered. If it's an array, we extract the int we
   *   want and return that.
   * @param \Drupal\views\Plugin\views\field\FieldPluginBase $field
   *   Metadata about the field we are extracting information from.
   *
   * @return int
   *   The value of this key, normalized to an int.
   */
  protected function normalizeKey($value, FieldPluginBase $field) {
    if (is_array($value) && count($value)) {
      return reset($value);
    }
    else {
      return $value ? $value : 0;
    }
  }

}
