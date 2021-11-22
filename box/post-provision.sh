#!/bin/bash
#
# DrupalVM post-provision tasks.
#

# Ensure write access on xdebug log.
sudo chmod 766 /tmp/xdebug.log || true
