#!/bin/bash
#
# DrupalVM post-provision tasks.
#

# Update Composer to version 2 since setting composer_version didn't work.
composer self-update --2
