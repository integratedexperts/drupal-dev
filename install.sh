#!/usr/bin/env bash
##
# DrevOps legacy installer.
#
# Use install.php.
#
# Left here for compatibility with earlier DrevOps versions and will be removed
# in DrevOps 1.11.

{

set -e

DRUPAL_VERSION="${DRUPAL_VERSION:-9}"
DREVOPS_INSTALL_URL="${DREVOPS_INSTALL_URL:-https://raw.githubusercontent.com/richardgaunt/drevops/${DRUPAL_VERSION:-9}.x/install.php}"

echo "This install script has been deprecated and replaced with a new install script."
echo
echo "For interactive installation or update:"
echo "curl -L ${DREVOPS_INSTALL_URL}?$(date +%s) > /tmp/install.php && php /tmp/install.php; rm /tmp/install.php >/dev/null"
echo
echo "For quiet installation or update:"
echo "curl -L ${DREVOPS_INSTALL_URL}?$(date +%s) > /tmp/install.php && php /tmp/install.php --quiet; rm /tmp/install.php >/dev/null"
echo

}
