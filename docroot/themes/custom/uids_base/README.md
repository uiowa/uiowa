# UIDS (UIowa Design System) Base Theme
A Drupal base theme for implementing the UIowa Design System.

# Twig Coding Standards
Drupal does not define many coding standard with regard to Twig, but this theme follows what standards there are: 
https://www.drupal.org/docs/develop/coding-standards/twig-coding-standards

We use these additional standards for this theme:
1. Function arguments are wrapped with single quotes.
    ```twig
    {{ attach_library('uids_base/card-block') }}
    ```
2. The subject of `include`, `embed`, and `extend` statements use single quotes.
3. When using the `with` keyword, keys are wrapped with single quotes.
4. Arguments passed to the `with` are each written out on their own line.
5. A comma is present at the end of a list of arguments.
    ```twig
    {% include '@uids_base/uids/site-parent.html.twig' with {
      'parent_name': site_parent_name,
      'parent_url': site_parent_url,
    } %}
    ```
