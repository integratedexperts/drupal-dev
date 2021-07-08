#!/usr/bin/env bash
# shellcheck disable=SC2154
#
# Helpers related to DrevOps workflow testing functionality.
#

prepare_sut() {
  step "Run SUT preparation"

  DRUPAL_VERSION=${DRUPAL_VERSION:-9}
  VOLUMES_MOUNTED=${VOLUMES_MOUNTED:-1}

  assert_not_empty "${DRUPAL_VERSION}"
  assert_not_empty "${VOLUMES_MOUNTED}"

  debug "${1}"

  assert_files_not_present_common

  substep "Initialise the project with default settings"

  # Run default install
  run_install_quiet

  assert_files_present_common
  assert_files_present_no_fresh_install
  assert_files_present_deployment
  assert_files_present_no_integration_acquia
  assert_files_present_no_integration_lagoon
  assert_files_present_no_integration_ftp
  assert_files_present_integration_dependenciesio
  assert_git_repo

  substep "Add all DrevOps files to new git repo"
  git_add_all_commit "Init DrevOps config"

  substep "Create IDE config file"
  mkdir -p .idea
  touch .idea/idea_file.txt
  assert_file_exists .idea/idea_file.txt
}

assert_ahoy_download_db() {
  step "Run DB download"

  substep "Download the database"

  # Tests are using demo database and 'ahoy download-db' command, so we need
  # to set the CURL DB to test DB.
  #
  # Override demo database with test demo database. This is required to use
  # test assertions ("star wars") with demo database.
  #
  # Ahoy will load environment variable and it will take precedence over
  # the value in .env file.
  export CURL_DB_URL="$DEMO_DB_TEST"

  # Remove any previously downloaded DB dumps.
  rm -Rf .data/db.sql

  # In this test, the database is downloaded from the public URL specified in
  # CURL_DB_URL variable.
  assert_file_not_exists .data/db.sql
  ahoy download-db
  assert_file_exists .data/db.sql

  trim_file .env
}

assert_ahoy_build() {
  step "Run project build"

  # Tests are using demo database and 'ahoy download-db' command, so we need
  # to set the CURL DB to test DB.
  #
  # Override demo database with test demo database. This is required to use
  # test assertions ("star wars") with demo database.
  #
  # Ahoy will load environment variable and it will take precedence over
  # the value in .env file.
  export CURL_DB_URL="$DEMO_DB_TEST"

  # Check that database file exists before build.
  [ -f ".data/db.sql" ] && db_file_exists=1

  run ahoy build
  # shellcheck disable=SC2154
  echo "${output}" >&3
  sync_to_host

  # Assert output messages. Note that only asserting generic messages that do
  # not depend on the type of the workflow.
  assert_output_contains "==> Building project."
  assert_output_contains "==> Removing project containers and packages available since the previous run."
  assert_output_contains "==> Building images, recreating and starting containers."
  assert_output_contains "==> Installing development dependencies."
  assert_output_contains "==> Example post site install operations."
  assert_output_contains "==> Perform example operations in non-production environment."
  assert_output_contains "==> Build complete."

  # Assert that lock files were created.
  assert_file_exists "composer.lock"
  assert_file_exists "docroot/themes/custom/star_wars/package-lock.json"

  # Assert that database file preserved after build if existed before.
  if [ "$db_file_exists" == 1 ]; then
    assert_file_exists .data/db.sql
  else
    assert_file_not_exists .data/db.sql
  fi

  # Assert the presence of files from the default configuration.
  assert_files_present_common
  assert_files_present_no_fresh_install
  assert_files_present_deployment
  assert_files_present_no_integration_acquia
  assert_files_present_no_integration_lagoon
  assert_files_present_no_integration_ftp
  assert_files_present_integration_dependenciesio

  # Assert generated settings file exists.
  assert_file_exists docroot/sites/default/settings.generated.php
  # Assert only minified compiled CSS exists.
  assert_file_exists docroot/themes/custom/star_wars/build/css/star_wars.min.css
  assert_file_not_contains docroot/themes/custom/star_wars/build/css/star_wars.min.css "background: #7e57e2"
  assert_file_not_exists docroot/themes/custom/star_wars/build/css/star_wars.css
  # Assert only minified compiled JS exists.
  assert_file_exists docroot/themes/custom/star_wars/build/js/star_wars.min.js
  assert_file_contains docroot/themes/custom/star_wars/build/js/star_wars.min.js "!function(Drupal){\"use strict\";Drupal.behaviors.star_wars"
  assert_file_not_exists docroot/themes/custom/star_wars/build/js/star_wars.js
}

assert_gitignore() {
  local skip_commit="${1:-0}"

  step "Run .gitignore test"

  create_development_settings

  if [ "${skip_commit}" -ne 1 ]; then
    substep "Commit fully configured project"
    git_add_all_commit "Commit fully built project"
  fi

  # Assert that scaffold files were added to the git repository.
  assert_git_file_is_tracked docroot/.editorconfig
  assert_git_file_is_tracked docroot/.eslintignore
  assert_git_file_is_tracked docroot/.gitattributes
  assert_git_file_is_tracked docroot/.htaccess
  assert_git_file_is_tracked docroot/autoload.php
  assert_git_file_is_tracked docroot/index.php
  assert_git_file_is_tracked docroot/robots.txt
  assert_git_file_is_tracked docroot/update.php
  # Assert that lock files were added to the git repository.
  assert_git_file_is_tracked "composer.lock"
  assert_git_file_is_tracked "docroot/themes/custom/star_wars/package-lock.json"
  # Assert that generated files were not added to the git repository.
  assert_git_file_is_not_tracked "docroot/sites/default/settings.generated.php"
  assert_git_file_is_not_tracked ".data/db.sql"
  # Assert that local settings were not added to the git repository.
  assert_git_file_is_not_tracked "docroot/sites/default/settings.local.php"
  assert_git_file_is_not_tracked "docroot/sites/default/services.local.yml"
  assert_git_file_is_not_tracked "docker-compose.override.yml"
  # Assert that built assets were not added to the git repository.
  assert_git_file_is_not_tracked "docroot/themes/custom/star_wars/build/css/star_wars.min.css"
  assert_git_file_is_not_tracked "docroot/themes/custom/star_wars/build/js/star_wars.js"

  remove_development_settings
}

assert_ahoy_cli() {
  step "Run ClI command"

  run ahoy cli "echo Test from inside of the container"
  assert_success
  assert_output_not_contains "Containers are not running."
  assert_output_contains "Test from inside of the container"
}

assert_env_changes() {
  step "Update .env file and apply changes"

  # Assert that .env does not contain test values.
  assert_file_not_contains ".env" "MY_CUSTOM_VAR"
  assert_file_not_contains ".env" "my_custom_var_value"
  # Assert that test variable is not available inside of containers.
  run ahoy cli "printenv | grep -q MY_CUSTOM_VAR"
  assert_failure
  # Assert that test value is not available inside of containers.
  run ahoy cli "echo \$MY_CUSTOM_VAR | grep -q my_custom_var_value"
  assert_failure
  assert_output_not_contains "my_custom_var_value"

  # Add variable to the .env file and apply the change to container.
  add_var_to_file .env "MY_CUSTOM_VAR" "my_custom_var_value"
  ahoy up cli
  sync_to_container

  # Assert that .env contains test values.
  assert_file_contains ".env" "MY_CUSTOM_VAR"
  assert_file_contains ".env" "my_custom_var_value"
  # Assert that test variable and values are available inside of containers.
  run ahoy cli "printenv | grep MY_CUSTOM_VAR"
  assert_success
  assert_output_contains "my_custom_var_value"
  # Assert that test variable and value are available inside of containers.
  run ahoy cli "echo \$MY_CUSTOM_VAR | grep my_custom_var_value"
  assert_output_contains "my_custom_var_value"
  assert_success

  # Restore file, apply changes and assert that original behaviour has been restored.
  restore_file ".env"
  ahoy up cli
  sync_to_container

  assert_file_not_contains ".env" "MY_CUSTOM_VAR"
  assert_file_not_contains ".env" "my_custom_var_value"
  run ahoy cli "printenv | grep -q MY_CUSTOM_VAR"
  assert_failure
  run ahoy cli "echo \$MY_CUSTOM_VAR | grep my_custom_var_value"
  assert_failure
  assert_output_not_contains "my_custom_var_value"
}

assert_ahoy_drush() {
  step "Run Drush command"

  run ahoy drush st
  assert_success
  assert_output_not_contains "Containers are not running."
}

assert_ahoy_info() {
  step "Run site info"

  run ahoy info
  assert_success
  assert_output_contains "Project                  : star_wars"
  assert_output_contains "Site local URL           : http://star-wars.docker.amazee.io"
  assert_output_contains "Path to project          : /app"
  assert_output_contains "Path to docroot          : /app/docroot"
  assert_output_contains "DB host                  : mariadb"
  assert_output_contains "DB username              : drupal"
  assert_output_contains "DB password              : drupal"
  assert_output_contains "DB port                  : 3306"
  assert_output_contains "DB port on host          :"
  assert_output_contains "Solr port on host        :"
  assert_output_contains "Mailhog URL              : http://mailhog.docker.amazee.io/"
  assert_output_contains "Xdebug                   : Disabled"
  assert_output_not_contains "Containers are not running."
}

assert_ahoy_docker_logs() {
  step "Show Docker logs"

  run ahoy logs
  assert_success
  assert_output_not_contains "Containers are not running."
}

assert_ahoy_login() {
  step "Generate one-time login link"

  run ahoy login
  assert_success
  assert_output_not_contains "Containers are not running."
}

assert_ahoy_export_db() {
  step "Export DB"
  file="${1:-mydb.sql}"
  run ahoy export-db "${file}"
  assert_success
  assert_output_not_contains "Containers are not running."
  sync_to_host
  assert_file_exists ".data/${file}"
}

assert_ahoy_lint() {
  step "Lint code"

  run ahoy lint
  assert_success
  assert_output_not_contains "Containers are not running."

  step "Assert that lint failure bypassing works"
  echo "\$a=1;" >>docroot/modules/custom/star_wars_core/star_wars_core.module
  echo ".abc{margin: 0px;}" >>docroot/themes/custom/star_wars/scss/components/_layout.scss
  sync_to_container

  # Assert failure.
  run ahoy lint
  [ "${status}" -eq 1 ]
  run ahoy lint-be
  [ "${status}" -eq 1 ]
  run ahoy lint-fe
  [ "${status}" -eq 1 ]

  # Assert failure bypass.
  add_var_to_file .env "ALLOW_BE_LINT_FAIL" "1"
  add_var_to_file .env "ALLOW_FE_LINT_FAIL" "1"
  ahoy up cli && sync_to_container

  run ahoy lint
  [ "${status}" -eq 0 ]
  run ahoy lint-be
  [ "${status}" -eq 0 ]
  run ahoy lint-fe
  [ "${status}" -eq 0 ]
  restore_file .env && ahoy up cli
}

assert_ahoy_test_unit() {
  step "Run unit tests"

  ahoy test-unit

  step "Assert that Drupal Unit test failure bypassing works"
  sed -i -e "s/assertEquals/assertNotEquals/g" docroot/modules/custom/star_wars_core/tests/src/Unit/StarWarsExampleUnitTest.php
  sync_to_container

  # Assert failure.
  run ahoy test-unit
  [ "${status}" -eq 1 ]
  sync_to_host
  assert_dir_not_empty logs
  assert_file_exists logs/unit.xml

  rm -R logs

  # Assert failure bypass.
  add_var_to_file .env "ALLOW_UNIT_TESTS_FAIL" "1" && ahoy up cli && sync_to_container
  run ahoy test-unit
  [ "${status}" -eq 0 ]
  sync_to_host
  assert_dir_not_empty logs
  assert_file_exists logs/unit.xml

  restore_file .env && ahoy up cli
}

assert_ahoy_test_kernel() {
  step "Run Kernel tests"

  ahoy test-kernel

  step "Assert that Kernel test failure bypassing works"
  sed -i -e "s/assertEquals/assertNotEquals/g" docroot/modules/custom/star_wars_core/tests/src/Kernel/StarWarsExampleKernelTest.php
  sync_to_container

  # Assert failure.
  run ahoy test-kernel
  [ "${status}" -eq 1 ]
  sync_to_host
  assert_dir_not_empty logs
  assert_file_exists logs/kernel.xml

  # Assert failure bypass.
  add_var_to_file .env "ALLOW_KERNEL_TESTS_FAIL" "1" && ahoy up cli && sync_to_container
  run ahoy test-kernel
  [ "${status}" -eq 0 ]
  sync_to_host
  assert_dir_not_empty logs
  assert_file_exists logs/kernel.xml

  restore_file .env && ahoy up cli
}

assert_ahoy_test_functional() {
  step "Run Functional tests"

  ahoy test-functional

  step "Assert that Functional test failure bypassing works"
  sed -i -e "s/assertEquals/assertNotEquals/g" docroot/modules/custom/star_wars_core/tests/src/Functional/StarWarsExampleFunctionalTest.php
  sync_to_container

  # Assert failure.
  run ahoy test-functional
  [ "${status}" -eq 1 ]
  sync_to_host
  assert_dir_not_empty logs
  assert_file_exists logs/functional.xml

  # Assert failure bypass.
  add_var_to_file .env "ALLOW_FUNCTIONAL_TESTS_FAIL" "1" && ahoy up cli && sync_to_container
  run ahoy test-functional
  [ "${status}" -eq 0 ]
  sync_to_host
  assert_dir_not_empty logs
  assert_file_exists logs/functional.xml

  restore_file .env && ahoy up cli && sync_to_container
}

assert_ahoy_test_bdd() {
  step "Run BDD tests"

  substep "Run all BDD tests"
  ahoy test-bdd
  sync_to_host
  assert_dir_not_empty screenshots
  rm -rf screenshots/*
  ahoy cli rm -rf /app/screenshots/*

  substep "Run tagged BDD tests"
  assert_dir_empty screenshots
  ahoy test-bdd -- --tags=p0
  sync_to_host
  assert_dir_not_empty screenshots
  # Test tagged with p0 are non-browser tests, so there should not be any
  # image screenshots.
  assert_file_exists "screenshots/*html"
  assert_file_not_exists "screenshots/*png"
  rm -rf screenshots/*
  ahoy cli rm -rf /app/screenshots/*

  substep "Run profile BDD tests based on BEHAT_PROFILE variable"
  assert_dir_empty screenshots
  BEHAT_PROFILE=p0 ahoy test-bdd
  sync_to_host
  assert_dir_not_empty screenshots
  # Test tagged with p0 are non-browser tests, so there should not be any
  # image screenshots.
  assert_file_exists "screenshots/*html"
  assert_file_not_exists "screenshots/*png"
  rm -rf screenshots/*
  ahoy cli rm -rf /app/screenshots/*

  substep "Assert that Behat tests failure works"
  echo "And I should be in the \"some-non-existing-page\" path" >>tests/behat/features/homepage.feature
  sync_to_container
  assert_dir_empty screenshots
  run ahoy test-bdd
  [ "${status}" -eq 1 ]
  sync_to_host
  assert_dir_not_empty screenshots
  rm -rf screenshots/*
  ahoy cli rm -rf /app/screenshots/*

  substep "Assert that Behat tests failure bypassing works"

  add_var_to_file .env "ALLOW_BDD_TESTS_FAIL" "1" && ahoy up cli && sync_to_container
  run ahoy test-bdd
  [ "${status}" -eq 0 ]
  sync_to_host
  assert_dir_not_empty screenshots
  rm -rf screenshots/*
  ahoy cli rm -rf /app/screenshots/*
  # Remove failing step from the feature.
  trim_file tests/behat/features/homepage.feature
  sync_to_container
  restore_file .env && ahoy up cli && sync_to_container

  substep "Run single Behat test"
  assert_dir_empty screenshots
  ahoy test-bdd tests/behat/features/homepage.feature
  sync_to_host
  assert_dir_not_empty screenshots
  rm -rf screenshots/*
  ahoy cli rm -rf /app/screenshots/*

  substep "Assert that single Behat test failure works"
  assert_dir_empty screenshots
  echo "And I should be in the \"some-non-existing-page\" path" >>tests/behat/features/homepage.feature
  ahoy up cli && sync_to_container
  # Assert failure.
  run ahoy test-bdd tests/behat/features/homepage.feature

  [ "${status}" -eq 1 ]
  sync_to_host
  assert_dir_not_empty screenshots
  rm -rf screenshots/*
  ahoy cli rm -rf /app/screenshots/*

  # Assert failure bypass.
  substep "Assert that single Behat test failure bypassing works"
  assert_dir_empty screenshots
  add_var_to_file .env "ALLOW_BDD_TESTS_FAIL" "1" && ahoy up cli && sync_to_container
  run ahoy test-bdd tests/behat/features/homepage.feature
  [ "${status}" -eq 0 ]
  sync_to_host
  assert_dir_not_empty screenshots
  rm -rf screenshots/*
  ahoy cli rm -rf /app/screenshots/*

  # Remove failing step from the feature.
  trim_file tests/behat/features/homepage.feature

  # Remove fail bypass.
  restore_file .env && ahoy up cli && sync_to_container
}

assert_ahoy_fe() {
  step "FE assets"

  substep "Build FE assets for production"
  assert_file_not_contains "docroot/themes/custom/star_wars/build/css/star_wars.min.css" "#7e57e2"
  echo "\$color-tester: #7e57e2;" >>docroot/themes/custom/star_wars/scss/_variables.scss
  echo "\$body-bg: \$color-tester;" >>docroot/themes/custom/star_wars/scss/_variables.scss
  sync_to_container
  ahoy fe
  sync_to_host
  assert_file_contains "docroot/themes/custom/star_wars/build/css/star_wars.min.css" "background:#7e57e2"

  substep "Build FE assets for development"
  assert_file_not_contains "docroot/themes/custom/star_wars/build/css/star_wars.min.css" "#91ea5e"
  echo "\$color-please: #91ea5e;" >>docroot/themes/custom/star_wars/scss/_variables.scss
  echo "\$body-bg: \$color-please;" >>docroot/themes/custom/star_wars/scss/_variables.scss
  sync_to_container
  ahoy fed
  sync_to_host
  # Note that assets compiled for development are not minified (contains spaces
  # between properties and their values).
  assert_file_contains "docroot/themes/custom/star_wars/build/css/star_wars.min.css" "background: #91ea5e"
}

assert_export_on_install_site() {
  step "Export DB on install"

  substep "Remove previously downloaded DB file"
  rm -Rf .data/*
  ahoy cli "rm -Rf .data/*"

  substep "Set .env variables"
  add_var_to_file .env "DB_EXPORT_BEFORE_IMPORT" "1"
  add_var_to_file .env "CURL_DB_URL" "$DEMO_DB_TEST"
  ahoy up cli && sync_to_container

  substep "Download DB file"
  ahoy download-db
  # shellcheck disable=SC2002
  cat ".data/db.sql" | head -n 1 >&3

  substep "Install site"
  ahoy install-site
  sync_to_host
  assert_file_exists .data/export_db_*

  restore_file .env && ahoy up cli && sync_to_container
}

assert_ahoy_debug() {
  step "Xdebug"

  substep "Enable debug"
  # Assert that Xdebug is disabled by default from the inside of the container.
  run ahoy cli "php -v | grep Xdebug"
  assert_failure

  # Assert info correctly shown from the outside of the container.
  run ahoy info
  assert_success
  assert_output_contains "Xdebug"
  assert_output_contains "Disabled"
  assert_output_not_contains "Enabled"
  # Enable debugging.
  run ahoy debug
  assert_success
  # Assert that the stack has restarted.
  assert_output_contains "Creating"
  assert_output_contains "Enabled debug"
  # Assert that Xdebug is enabled from the inside of the container.
  run ahoy cli "php -v | grep Xdebug"
  assert_success
  # Assert info correctly shown from the outside of the container.
  run ahoy info
  assert_success
  assert_output_not_contains "Disabled"
  assert_output_contains "Enabled"
  # Assert that command when debugging is enabled does not restart the stack.
  run ahoy debug
  assert_success
  assert_output_not_contains "Creating"
  assert_output_contains "Enabled debug"

  substep "Disable debug"
  # Assert that restarting the stack does not have Xdebug enabled.
  run ahoy up
  assert_success
  # Assert that the stack has restarted.
  assert_output_contains "Creating"
  # Assert that Xdebug is disabled from the inside of the container.
  run ahoy cli "php -v | grep Xdebug"
  assert_failure
  # Assert info correctly shown from the outside of the container.
  run ahoy info
  assert_success
  assert_output_contains "Xdebug"
  assert_output_contains "Disabled"
  assert_output_not_contains "Enabled"
}

assert_ahoy_clean() {
  step "Clean"

  # Prepare to assert that manually created file is not removed.
  touch untracked_file.txt

  create_development_settings

  ahoy clean
  # Assert that initial DrevOps files have not been removed.
  assert_files_present_common
  assert_files_present_deployment
  assert_files_present_no_integration_acquia
  assert_files_present_no_integration_lagoon
  assert_files_present_no_integration_ftp

  assert_dir_not_exists docroot/modules/contrib
  assert_dir_not_exists docroot/themes/contrib
  assert_dir_not_exists vendor
  assert_dir_not_exists docroot/themes/custom/star_wars/node_modules
  assert_dir_exists screenshots

  # Assert manually created local settings file exists.
  assert_file_exists docroot/sites/default/settings.local.php
  # Assert manually created local services file exists.
  assert_file_exists docroot/sites/default/services.local.yml
  # Assert generated settings file does not exist.
  assert_file_not_exists docroot/sites/default/settings.generated.php
  # Assert manually created file still exists.
  assert_file_exists untracked_file.txt
  # Assert IDE config file still exists.
  assert_file_exists .idea/idea_file.txt

  assert_git_repo

  remove_development_settings
}

assert_ahoy_reset() {
  step "Reset"

  create_development_settings

  ahoy reset

  assert_files_present_common
  assert_files_present_deployment
  assert_files_present_no_integration_acquia
  assert_files_present_no_integration_lagoon
  assert_files_present_no_integration_ftp

  assert_file_not_exists "docroot/sites/default/settings.local.php"
  assert_file_not_exists "docroot/sites/default/services.local.yml"

  # Assert manually created file still exists.
  assert_file_not_exists untracked_file.txt
  # Assert IDE config file still exists.
  assert_file_exists .idea/idea_file.txt

  assert_dir_not_exists screenshots

  assert_git_repo

  remove_development_settings
}

assert_page_contains() {
  path="${1}"
  content="${2}"
  t=$(mktemp)
  ahoy cli curl -L -s "http://nginx:8080${path}" >"${t}"
  assert_file_contains "${t}" "${content}"
}

assert_page_not_contains() {
  path="${1}"
  content="${2}"
  t=$(mktemp)
  ahoy cli curl -L -s "http://nginx:8080${path}" >"${t}"
  assert_file_not_contains "${t}" "${content}"
}

assert_reload_db() {
  step "Reload DB image"

  # Assert that used DB image has content.
  assert_page_contains "/" "First test node"

  # Change homepage content and assert that the change was applied.
  ahoy drush config-set system.site page.front /user -y
  assert_page_not_contains "/" "First test node"

  ahoy reload-db
  assert_page_contains "/" "First test node"
}
