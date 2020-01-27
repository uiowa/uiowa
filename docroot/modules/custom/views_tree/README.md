CONTENTS OF THIS FILE
--------------------

* Introduction
* Requirements
* Installation
* Configuration
* Maintainers


INTRODUCTION
------------

The Views Tree module provides a Views style plugin to display a tree of
elements using the adjacency model.

* For a full description of the module visit
  https://www.drupal.org/project/views_tree

* To submit bug reports and feature suggestions, or to track changes visit
  https://www.drupal.org/project/issues/views_tree


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

* Install the Views Tree module as you would normally install a contributed
  Drupal module. Visit https://www.drupal.org/node/1897420 for further
  information.


CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the module.
    2. Navigate to Administration > Structure > Views and select "Add new view"
       to create a new view.
    3. Select what information will be displayed and how it will be sorted by
       using the appropriate dropdown.
    4. Select the display format "unformatted list" of "fields". Save and
       edit.
    5. Add the appropriate fields.
    6. Using the Advanced settings create a relationship of the information to
       be displayed in the hierarchy.
    7. Edit the format of the view to "TreeHelper (Adjacency model)".
    8. Select unordered or ordered list type. Most use cases will prefer
       Unordered. Select the field with the unique identifier for each record
       from the "Main field" dropdown menu. Select the field that contains the
       unique identifier of the record's parent from the "Parent field"
       dropdown. Apply changes.


MAINTAINERS
-----------

* Daniel Wehner (dawehner) - https://www.drupal.org/u/dawehner
* Jeff Geerling (geerlingguy) - https://www.drupal.org/u/geerlingguy
* Larry Garfield (Crell) - https://www.drupal.org/u/crell
