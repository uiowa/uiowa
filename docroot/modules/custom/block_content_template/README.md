# Block content template for Drupal 8

Block content entities are not rendered within a template outside the block rendering system. Placing a content block in
a node for instance with the new layout builder works fine, or rendering with views too, but there's no way to edit them
from the frontend directly because there are no contextual links, even though the $build contains those!

This module alters the rendering of a content block by adding a theming function with suggestions per block type and id
Important, this only happens within following contexts:

- views
- entity reference
- layout builder

see block_content_template_block_content_view_alter().

Theming suggestions:

- block-content-template--BUNDLE
- block-content-template--ID

See following issues on drupal.org for more background

- https://www.drupal.org/node/2704331
- https://www.drupal.org/project/drupal/issues/2666578
- https://www.drupal.org/project/drupal/issues/2859197
