#!/usr/bin/env bats
#
# Init tests.
#

load test_helper
load test_helper_init

# To run installation tests, several fixture directories are required. They are
# defined and created in setup() test method.
#
# $BUILD_DIR - root build directory where the rest of fixture directories located.
#
# $CURRENT_PROJECT_DIR - directory where install script is executed. May have
# existing project files (e.g. from previous installations) or be empty (to
# facilitate brand-new install).
#
# $DST_PROJECT_DIR - directory where Drupal-Dev may be installed to. By default,
# install uses $CURRENT_PROJECT_DIR as a destination, but we use
# $DST_PROJECT_DIR to test a scenario where different destination is provided.
#
# $LOCAL_REPO_DIR - directory where install script will be sourcing the instance
# of Drupal-Dev.
#
# $APP_TMP_DIR - directory where the application may store it's temporary files.

setup(){
  DRUPAL_VERSION=${DRUPAL_VERSION:-8}
  CUR_DIR="$(pwd)"
  BUILD_DIR="${BUILD_DIR:-"${BATS_TMPDIR}/drupal-dev-bats"}"

  CURRENT_PROJECT_DIR="${BUILD_DIR}/star_wars"
  DST_PROJECT_DIR="${BUILD_DIR}/dst"
  LOCAL_REPO_DIR="${BUILD_DIR}/local_repo"
  APP_TMP_DIR="${BUILD_DIR}/tmp"

  prepare_fixture_dir "${BUILD_DIR}"
  prepare_fixture_dir "${CURRENT_PROJECT_DIR}"
  prepare_fixture_dir "${DST_PROJECT_DIR}"
  prepare_fixture_dir "${LOCAL_REPO_DIR}"
  prepare_fixture_dir "${APP_TMP_DIR}"
  pushd "${BUILD_DIR}" > /dev/null || exit 1

  local_repo_commit="$(prepare_local_repo "${LOCAL_REPO_DIR}")"
}

@test "Install: empty directory" {
  run_install

  assert_added_files "${CURRENT_PROJECT_DIR}"
}

@test "Install: empty directory; DST_DIR as argument" {
  run_install "${DST_PROJECT_DIR}"

  assert_added_files "${DST_PROJECT_DIR}"
}

@test "Install: empty directory; DST_DIR from env variable" {
  export DST_DIR="${DST_PROJECT_DIR}"
  run_install

  assert_added_files "${DST_PROJECT_DIR}"
}

@test "Install: empty directory; PROJECT from env variable" {
  export PROJECT="the_matrix"
  run_install

  assert_added_files "${CURRENT_PROJECT_DIR}" "the_matrix"
}

@test "Install: empty directory; PROJECT from .env file" {
  echo "PROJECT=\"the_matrix\"" > "${CURRENT_PROJECT_DIR}/.env"

  run_install

  assert_added_files "${CURRENT_PROJECT_DIR}" "the_matrix"
}

@test "Install: empty directory; PROJECT from .env.local file" {
  # Note that .env file should exist in order to read from .env.local.
  echo "PROJECT=\"star_wars\"" > "${CURRENT_PROJECT_DIR}/.env"
  echo "PROJECT=\"the_matrix\"" > "${CURRENT_PROJECT_DIR}/.env.local"

  run_install

  assert_added_files "${CURRENT_PROJECT_DIR}" "the_matrix"
}

@test "Install: directory with custom files" {
  touch "${CURRENT_PROJECT_DIR}/test1.txt"
  # File resides in directory that is included in Drupal-Dev when initialised.
  mkdir -p "${CURRENT_PROJECT_DIR}/.docker"
  touch "${CURRENT_PROJECT_DIR}/.docker/test2.txt"

  run_install

  assert_added_files "${CURRENT_PROJECT_DIR}"

  # Assert that custom file preserved.
  assert_file_exists "${CURRENT_PROJECT_DIR}/test1.txt"
  # Assert that custom file in a directory used by Drupal-Dev is preserved.
  assert_file_exists "${CURRENT_PROJECT_DIR}/.docker/test2.txt"
}

@test "Install: existing non-git project; current version" {
  # Populate current dir with a project at current version.
  run_install

  # Assert files at current version.
  assert_added_files "${CURRENT_PROJECT_DIR}"

  # Add custom files
  touch "${CURRENT_PROJECT_DIR}/test1.txt"
  # File resides in directory that is included in Drupal-Dev when initialised.
  mkdir -p "${CURRENT_PROJECT_DIR}/.docker"
  touch "${CURRENT_PROJECT_DIR}/.docker/test2.txt"

  run_install

  # Assert no changes were made.
  assert_added_files "${CURRENT_PROJECT_DIR}"

  # Assert that custom file preserved.
  assert_file_exists "${CURRENT_PROJECT_DIR}/test1.txt"
  # Assert that custom file in a directory used by Drupal-Dev is preserved.
  assert_file_exists "${CURRENT_PROJECT_DIR}/.docker/test2.txt"
}

@test "Install: existing git project; current Drupal-Dev version" {
  # Populate current dir with a project at current version.
  run_install

  # Assert files at current version.
  assert_added_files "${CURRENT_PROJECT_DIR}"

  # Add custom files
  touch "${CURRENT_PROJECT_DIR}/test1.txt"
  # File resides in directory that is included in Drupal-Dev when initialised.
  mkdir -p "${CURRENT_PROJECT_DIR}/.docker"
  touch "${CURRENT_PROJECT_DIR}/.docker/test2.txt"

  # Add all files to git repo.
  prepare_local_repo "${CURRENT_PROJECT_DIR}" 0

  run_install

  # Assert no changes were made.
  assert_added_files "${CURRENT_PROJECT_DIR}"

  # Assert that custom file preserved.
  assert_file_exists "${CURRENT_PROJECT_DIR}/test1.txt"
  # Assert that custom file in a directory used by Drupal-Dev is preserved.
  assert_file_exists "${CURRENT_PROJECT_DIR}/.docker/test2.txt"

  # Assert no changes were introduced.
  assert_contains "nothing to commit, working tree clean" "$(git --work-tree=${CURRENT_PROJECT_DIR} --git-dir=${CURRENT_PROJECT_DIR}/.git status)"
}

@test "Install: existing git project; modified Drupal-Dev version" {
  # Populate current dir with a project at current version.
  run_install

  # Assert files at current version.
  assert_added_files "${CURRENT_PROJECT_DIR}"

  # Add custom files
  touch "${CURRENT_PROJECT_DIR}/test1.txt"
  # File resides in directory that is included in Drupal-Dev when initialised.
  mkdir -p "${CURRENT_PROJECT_DIR}/.docker"
  touch "${CURRENT_PROJECT_DIR}/.docker/test2.txt"

  # Modify Drupal-Dev files.
  echo "SOMEVAR=\"someval\"" >> "${CURRENT_PROJECT_DIR}/.env"

  # Add all files to git repo.
  prepare_local_repo "${CURRENT_PROJECT_DIR}" 0

  run_install

  # Assert no changes were made.
  assert_added_files "${CURRENT_PROJECT_DIR}"

  # Assert that custom file preserved.
  assert_file_exists "${CURRENT_PROJECT_DIR}/test1.txt"
  # Assert that custom file in a directory used by Drupal-Dev is preserved.
  assert_file_exists "${CURRENT_PROJECT_DIR}/.docker/test2.txt"

  # Assert no changes were introduced, since Drupal-Dev files do not override
  # existing files by default.
  assert_contains "nothing to commit, working tree clean" "$(git --work-tree=${CURRENT_PROJECT_DIR} --git-dir=${CURRENT_PROJECT_DIR}/.git status)"
  assert_file_contains "${CURRENT_PROJECT_DIR}/.env" "SOMEVAR=\"someval\""
}

@test "Install: existing git project; modified Drupal-Dev version; use override" {
  # Populate current dir with a project at current version.
  run_install

  # Assert files at current version.
  assert_added_files "${CURRENT_PROJECT_DIR}"

  # Add custom files
  touch "${CURRENT_PROJECT_DIR}/test1.txt"
  # File resides in directory that is included in Drupal-Dev when initialised.
  mkdir -p "${CURRENT_PROJECT_DIR}/.docker"
  touch "${CURRENT_PROJECT_DIR}/.docker/test2.txt"

  # Modify Drupal-Dev files.
  echo "SOMEVAR=\"someval\"" >> "${CURRENT_PROJECT_DIR}/.env"

  # Add all files to git repo.
  prepare_local_repo "${CURRENT_PROJECT_DIR}" 0

  echo "DRUPALDEV_ALLOW_OVERRIDE=1" >> "${CURRENT_PROJECT_DIR}/.env.local"

  run_install

  # Assert no changes were made.
  assert_added_files "${CURRENT_PROJECT_DIR}"

  # Assert that custom file preserved.
  assert_file_exists "${CURRENT_PROJECT_DIR}/test1.txt"
  # Assert that custom file in a directory used by Drupal-Dev is preserved.
  assert_file_exists "${CURRENT_PROJECT_DIR}/.docker/test2.txt"

  # Assert changes were introduced, since Drupal-Dev files have overridden
  # existing files.
  assert_not_contains "nothing to commit, working tree clean" "$(git --work-tree=${CURRENT_PROJECT_DIR} --git-dir=${CURRENT_PROJECT_DIR}/.git status)"
  assert_contains "modified:   .env" "$(git --work-tree=${CURRENT_PROJECT_DIR} --git-dir=${CURRENT_PROJECT_DIR}/.git status)"
  assert_file_not_contains "${CURRENT_PROJECT_DIR}/.env" "SOMEVAR=\"someval\""
}

@test "Install: existing git project; no Drupal-Dev; adding Drupal-Dev and updating Drupal-Dev" {
  # Add custom files
  touch "${CURRENT_PROJECT_DIR}/test1.txt"
  # File resides in directory that is included in Drupal-Dev when initialised.
  mkdir -p "${CURRENT_PROJECT_DIR}/.docker"
  touch "${CURRENT_PROJECT_DIR}/.docker/test2.txt"

  # Add all files to git repo.
  prepare_local_repo "${CURRENT_PROJECT_DIR}" 0

  run_install
  assert_added_files "${CURRENT_PROJECT_DIR}"

  # Commit files required to run the project.
  git_add "${CURRENT_PROJECT_DIR}" .circleci/config.yml
  git_add "${CURRENT_PROJECT_DIR}" docroot/sites/default/settings.php
  git_add "${CURRENT_PROJECT_DIR}" docroot/sites/default/services.yml
  git_commit "${CURRENT_PROJECT_DIR}" "Init Drupal-Dev"

  # Assert that custom file preserved.
  assert_file_exists "${CURRENT_PROJECT_DIR}/test1.txt"
  # Assert that custom file in a directory used by Drupal-Dev is preserved.
  assert_file_exists "${CURRENT_PROJECT_DIR}/.docker/test2.txt"

  # Assert no changes were introduced.
  assert_contains "nothing to commit, working tree clean" "$(git --work-tree=${CURRENT_PROJECT_DIR} --git-dir=${CURRENT_PROJECT_DIR}/.git status)"

  # Releasing new version of Drupal-Dev.
  echo "# Some change to docker-compose" >> "${LOCAL_REPO_DIR}/docker-compose.yml"
  git_add "${LOCAL_REPO_DIR}" "docker-compose.yml"
  echo "# Some change to ci config" >> "${LOCAL_REPO_DIR}/.circleci/config.yml"
  git_add "${LOCAL_REPO_DIR}" ".circleci/config.yml"
  git_commit "${LOCAL_REPO_DIR}" "New version of Drupal-Dev"

  # Run install to update to the latest Drupal-Dev version.
  run_install
  assert_added_files "${CURRENT_PROJECT_DIR}"

  # Assert that non-committed file was updated.
  assert_file_contains "${CURRENT_PROJECT_DIR}/docker-compose.yml" "# Some change to docker-compose"
  # Assert that committed file was not updated.
  assert_file_not_contains "${CURRENT_PROJECT_DIR}/.circleci/config.yml" "# Some change to ci config"
  # Assert no changes to the repo.
  assert_contains "nothing to commit, working tree clean" "$(git --work-tree=${CURRENT_PROJECT_DIR} --git-dir=${CURRENT_PROJECT_DIR}/.git status)"
}

@test "Install: empty directory; no Acquia and Lagoon integrations" {
  export DRUPALDEV_OPT_PRESERVE_ACQUIA=0
  export DRUPALDEV_OPT_PRESERVE_LAGOON=1

  run_install

  assert_added_files_no_integrations "${CURRENT_PROJECT_DIR}"
  assert_added_files_no_integration_acquia "${CURRENT_PROJECT_DIR}"
  assert_added_files_no_integration_lagoon "${CURRENT_PROJECT_DIR}"
}

@test "Install: empty directory; no Acquia integration" {
  export DRUPALDEV_OPT_PRESERVE_ACQUIA=0

  run_install

  assert_added_files_no_integrations "${CURRENT_PROJECT_DIR}"
  assert_added_files_no_integration_acquia "${CURRENT_PROJECT_DIR}"
  assert_added_files_integration_lagoon "${CURRENT_PROJECT_DIR}"
}

@test "Install: empty directory; no Lagoon integration" {
  export DRUPALDEV_OPT_PRESERVE_LAGOON=0

  run_install

  assert_added_files_no_integrations "${CURRENT_PROJECT_DIR}"
  assert_added_files_integration_acquia "${CURRENT_PROJECT_DIR}"
  assert_added_files_no_integration_lagoon "${CURRENT_PROJECT_DIR}"
}

assert_added_files(){
  local dir="${1}"
  local suffix="${2:-star_wars}"

  assert_added_files_no_integrations "${dir}" "${suffix}"

  # Assert Acquia integration preserved.
  assert_added_files_integration_acquia "${dir}" "${suffix}"

  # Assert Lagoon integration preserved.
  assert_added_files_integration_lagoon "${dir}" "${suffix}"
}

assert_added_files_no_integrations(){
  local dir="${1}"
  local suffix="${2:-star_wars}"

  pushd "${dir}" > /dev/null

  assert_files_init_common "${suffix}"

  # Assert that project name is correct.
  assert_file_contains .env "PROJECT=\"${suffix}\""

  # Assert that required files were not locally excluded.
  if [ -d ".git" ] ; then
    assert_file_not_contains .git/info/exclude ".circleci/config.yml"
    assert_file_not_contains .git/info/exclude "docroot/sites/default/settings.php"
    assert_file_not_contains .git/info/exclude "docroot/sites/default/services.yml"
  fi

  popd > /dev/null
}

assert_added_files_integration_acquia(){
  local dir="${1}"
  local suffix="${2:-star_wars}"

  pushd "${dir}" > /dev/null

  # Acquia integration preserved.
  assert_dir_exists hooks
  assert_dir_exists hooks/library
  assert_file_mode hooks/library/clear-cache.sh "755"
  assert_file_mode hooks/library/enable-shield.sh "755"
  assert_file_mode hooks/library/flush-varnish.sh "755"
  assert_file_mode hooks/library/import-config.sh "755"
  assert_file_mode hooks/library/update-db.sh "755"
  assert_file_exists scripts/download-backup-acquia.sh
  assert_file_exists DEPLOYMENT.md
  assert_file_contains README.md "Please refer to [DEPLOYMENT.md](DEPLOYMENT.md)"
  assert_file_contains docroot/sites/default/settings.php "if (file_exists('/var/www/site-php')) {"
  assert_file_contains .env "AC_API_DB_SITE="
  assert_file_contains .env "AC_API_DB_ENV="
  assert_file_contains .env "AC_API_DB_NAME="
  assert_file_contains .ahoy.yml "AC_API_DB_SITE="
  assert_file_contains .ahoy.yml "AC_API_DB_ENV="
  assert_file_contains .ahoy.yml "AC_API_DB_NAME="

  popd > /dev/null
}

assert_added_files_no_integration_acquia(){
  local dir="${1}"
  local suffix="${2:-star_wars}"

  pushd "${dir}" > /dev/null

  # Acquia integration preserved.
  assert_dir_not_exists hooks
  assert_dir_not_exists hooks/library
  assert_file_not_exists scripts/download-backup-acquia.sh
  assert_file_not_contains docroot/sites/default/settings.php "if (file_exists('/var/www/site-php')) {"
  assert_file_not_contains .env "AC_API_DB_SITE="
  assert_file_not_contains .env "AC_API_DB_ENV="
  assert_file_not_contains .env "AC_API_DB_NAME="
  assert_file_not_contains .ahoy.yml "AC_API_DB_SITE="
  assert_file_not_contains .ahoy.yml "AC_API_DB_ENV="
  assert_file_not_contains .ahoy.yml "AC_API_DB_NAME="

  popd > /dev/null
}

assert_added_files_integration_lagoon(){
  local dir="${1}"
  local suffix="${2:-star_wars}"

  pushd "${dir}" > /dev/null

  assert_file_exists .lagoon.yml
  assert_file_exists drush/aliases.drushrc.php
  assert_file_contains docker-compose.yml "labels"
  assert_file_contains docker-compose.yml "lagoon.type: cli-persistent"
  assert_file_contains docker-compose.yml "lagoon.persistent.name: nginx"
  assert_file_contains docker-compose.yml "lagoon.persistent: /app/docroot/sites/default/files/"
  assert_file_contains docker-compose.yml "lagoon.type: nginx-php-persistent"
  assert_file_contains docker-compose.yml "lagoon.name: nginx"
  assert_file_contains docker-compose.yml "lagoon.type: mariadb"
  assert_file_contains docker-compose.yml "lagoon.type: solr"
  assert_file_contains docker-compose.yml "lagoon.type: none"

  popd > /dev/null
}

assert_added_files_no_integration_lagoon(){
  local dir="${1}"
  local suffix="${2:-star_wars}"

  pushd "${dir}" > /dev/null

  assert_file_not_exists .lagoon.yml
  assert_file_not_exists drush/aliases.drushrc.php
  assert_file_not_contains docker-compose.yml "labels"
  assert_file_not_contains docker-compose.yml "lagoon.type: cli-persistent"
  assert_file_not_contains docker-compose.yml "lagoon.persistent.name: nginx"
  assert_file_not_contains docker-compose.yml "lagoon.persistent: /app/docroot/sites/default/files/"
  assert_file_not_contains docker-compose.yml "lagoon.type: nginx-php-persistent"
  assert_file_not_contains docker-compose.yml "lagoon.name: nginx"
  assert_file_not_contains docker-compose.yml "lagoon.type: mariadb"
  assert_file_not_contains docker-compose.yml "lagoon.type: solr"
  assert_file_not_contains docker-compose.yml "lagoon.type: none"

  popd > /dev/null
}

run_install(){
  pushd "${CURRENT_PROJECT_DIR}" > /dev/null

  # Force install script to be downloaded from the local repo for testing.
  export DRUPALDEV_LOCAL_REPO="${LOCAL_REPO_DIR}"
  export DRUPALDEV_DEBUG=1
  # @todo:dev Remove below once tests are passing.
  export DRUPALDEV_TMP_DIR="${APP_TMP_DIR}"
  "${CUR_DIR}"/install.sh "$@" >&3

  popd > /dev/null
}

# Prepare local repository from the current codebase.
prepare_local_repo(){
  local dir="${1}"
  local do_copy_code="${2:-1}"
  local commit

  if [ ${do_copy_code} -eq 1 ]; then
    prepare_fixture_dir "${dir}"
    copy_code "${dir}"
  fi

  pushd "${dir}" > /dev/null || exit 1

  git init > /dev/null
  git config user.name "someone"
  git config user.email "someone@someplace.com"
  git add -A
  git commit -m "First commit" > /dev/null
  commit=$(git rev-parse HEAD)

  popd > /dev/null || cd "${CUR_DIR}" || exit 1

  echo ${commit}
}

git_add(){
  local dir="${1}"
  local file="${2}"
  git --work-tree="${dir}" --git-dir="${dir}/.git" add "${dir}/${file}"
}

git_commit(){
  local dir="${1}"
  local message="${2}"
  commit=$(git --work-tree="${dir}" --git-dir="${dir}/.git" commit -m "${message}")
  echo "${commit}"
}
