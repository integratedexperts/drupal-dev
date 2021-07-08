#!/usr/bin/env bash
##
# Update DrevOps.
#

set -e
[ -n "${DREVOPS_DEBUG}" ] && set -x

# The URL of the install script.
export DREVOPS_INSTALL_URL="${DREVOPS_INSTALL_URL:-https://raw.githubusercontent.com/richardgaunt/drevops/${DRUPAL_VERSION:-9}.x/install.php}"

# Allow to provide custom DrevOps commit hash to download the sources from.
export DREVOPS_COMMIT="${DREVOPS_COMMIT:-}"

# ------------------------------------------------------------------------------

curl -L "${DREVOPS_INSTALL_URL}"?"$(date +%s)" > /tmp/install.php && php /tmp/install.php --quiet; rm /tmp/install.php;

