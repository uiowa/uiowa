CONTENTS OF THIS FILE
---------------------
   
 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

by Benedikt Forchhammer, b.forchhammer@mind2.de

Porting this module in Drupal 8 - Purushotam

Special Thanks to Benedikt for his work in his sandbox project: entity labels.


This is a small and efficient module that allows hiding of entity label fields.
To prevent empty labels it can be configured to generate the label automatically
by a given pattern. The module can be used for any entity type that has a label,
including e.g. for node titles, comment subjects, taxonomy term names and
profile2 labels.


REQUIREMENTS
------------

No special requirements


INSTALLATION
------------

Install as you would normally install a contributed Drupal module. See:
https://drupal.org/documentation/install/modules-themes/modules-8 for further
information.

 * (optional) Download and install the token module in order to get token
   replacement help.


CONFIGURATION
-------------

 * After installing, go to: Administration » Structure » Content Types

 * In the drop down select: "Manage automatic entity labels"

 * Select which option for client and create a pattern


MAINTAINERS
-----------

Current maintainers:
 * Benedikt Forchhammer (bforchhammer) - https://www.drupal.org/user/216396
 * Pravin raj (Pravin Ajaaz) - https://www.drupal.org/user/2910049
 * Purushotam Rai (purushotam.rai) - https://www.drupal.org/user/3193859
 * Renato Gonçalves (RenatoG) - https://www.drupal.org/user/3326031
