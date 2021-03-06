#!/usr/bin/env bats
#
# Test runner for deployment tests.
#
# shellcheck disable=SC2030,SC2031,SC2129

load _helper
load _helper_drevops
load _helper_drevops_deployment

@test "Deployment; Acquia integration" {
  pushd "${BUILD_DIR}" > /dev/null || exit 1

  # Source directory for initialised codebase.
  # If not provided - directory will be created and a site will be initialised.
  # This is to facilitate local testing.
  SRC_DIR="${SRC_DIR:-}"

  # "Remote" repository to deploy the artifact to. It is located in the host
  # filesystem and just treated as a remote for currently installed codebase.
  REMOTE_REPO_DIR=${REMOTE_REPO_DIR:-${BUILD_DIR}/deployment_remote}

  step "Starting DEPLOYMENT tests."

  if [ ! "${SRC_DIR}" ]; then
    SRC_DIR="${BUILD_DIR}/deployment_src"
    substep "Deployment source directory is not provided - using directory ${SRC_DIR}"
    prepare_fixture_dir "${SRC_DIR}"

    # Enable Acquia integration for this test to run independent deployment
    # by using install auto-discovery.
    export DATABASE_DOWNLOAD_SOURCE="acquia"

    # Override download from Acquia with a special flag. This still allows to
    # validate that download script expects credentials, but does not actually
    # run the download (it would fail since there is no Acquia environment
    # attached to this test).
    # A demo test database will be used as actual database to provision site
    # during this test.
    echo "DB_DOWNLOAD_PROCEED=0" >> "${CURRENT_PROJECT_DIR}"/.env

    # We need to use "current" directory as a place where the deployment script
    # is going to run from, while "SRC_DIR" is a place where files are taken
    # from for deployment. They may be the same place, but we are testing them
    # if they are separate, because most likely SRC_DIR will contain code
    # built on previous build stages.
    provision_site "${CURRENT_PROJECT_DIR}"

    assert_files_present_common "star_wars" "StarWars" "${CURRENT_PROJECT_DIR}"
    assert_files_present_deployment "star_wars" "${CURRENT_PROJECT_DIR}"
    assert_files_present_integration_acquia "star_wars" 1 "${CURRENT_PROJECT_DIR}"
    assert_files_present_no_integration_lagoon "star_wars" "${CURRENT_PROJECT_DIR}"
    assert_files_present_no_integration_ftp "star_wars" "${CURRENT_PROJECT_DIR}"

    substep "Copying built codebase into code source directory ${SRC_DIR}"
    cp -R "${CURRENT_PROJECT_DIR}/." "${SRC_DIR}/"
  else
    substep "Using provided SRC_DIR ${SRC_DIR}"
    assert_dir_not_empty "${SRC_DIR}"
  fi

  # Make sure that all files were copied out from the container or passed from
  # the previous stage of the build.
  assert_files_present_common "star_wars" "StarWars" "${SRC_DIR}"
  assert_files_present_deployment "star_wars" "${SRC_DIR}"
  assert_files_present_integration_acquia "star_wars" 1 "${SRC_DIR}"
  assert_files_present_no_integration_lagoon "star_wars" "${SRC_DIR}"
  assert_files_present_no_integration_ftp "star_wars" "${SRC_DIR}"
  assert_git_repo "${SRC_DIR}"

  # Make sure that one of the excluded directories will be ignored in the
  # deployment artifact.
  mkdir -p "${SRC_DIR}"/docroot/themes/custom/star_wars/node_modules
  touch "${SRC_DIR}"/docroot/themes/custom/star_wars/node_modules/test.txt

  step "Preparing remote repo directory ${REMOTE_REPO_DIR}"
  prepare_fixture_dir "${REMOTE_REPO_DIR}"
  git_init 1 "${REMOTE_REPO_DIR}"

  popd > /dev/null

  pushd "${CURRENT_PROJECT_DIR}" > /dev/null

  step "Running deployment"
  export DEPLOY_GIT_REMOTE="${REMOTE_REPO_DIR}"/.git
  export DEPLOY_CODE_ROOT="${CURRENT_PROJECT_DIR}"
  export DEPLOY_CODE_SRC="${SRC_DIR}"
  export DEPLOY_GIT_USER_EMAIL="${DEPLOY_GIT_USER_EMAIL:-testuser@example.com}"
  export DEPLOY_TYPE="code"

  # Proceed with deployment.
  # @todo: Add tests for deployment kill-switch.
  export DEPLOY_PROCEED=1

  run ahoy deploy
  assert_success
  assert_output_contains "==> Started CODE deployment."

  # Assert that no other deployments ran.
  assert_output_not_contains "==> Started WEBHOOK deployment."
  assert_output_not_contains "==> Successfully called webhook."
  assert_output_not_contains "ERROR: Webhook deployment failed."
  assert_output_not_contains "==> Finished WEBHOOK deployment."

  assert_output_not_contains "==> Started DOCKER deployment."
  assert_output_not_contains "Services map is not specified in DOCKER_MAP variable."
  assert_output_not_contains "==> Finished DOCKER deployment."

  assert_output_not_contains "==> Started LAGOON deployment."
  assert_output_not_contains "==> Finished LAGOON deployment."

  step "Assert remote deployment files"
  assert_deployment_files_present "${REMOTE_REPO_DIR}"

  # Assert Acquia hooks are present.
  assert_files_present_integration_acquia "star_wars" 0 "${REMOTE_REPO_DIR}"

  popd > /dev/null
}

@test "Deployment; Lagoon integration" {
  pushd "${BUILD_DIR}" > /dev/null || exit 1

  # Source directory for initialised codebase.
  # If not provided - directory will be created and a site will be initialised.
  # This is to facilitate local testing.
  SRC_DIR="${SRC_DIR:-}"

  step "Starting DEPLOYMENT tests."

  SRC_DIR="${BUILD_DIR}/deployment_src"
  substep "Deployment source directory is not provided - using directory ${SRC_DIR}"
  prepare_fixture_dir "${SRC_DIR}"

  # Provision the codebase with Lagoon deployment type and Lagoon integration.
  answers=(
    "Star wars" # name
    "nothing" # machine_name
    "nothing" # org
    "nothing" # org_machine_name
    "nothing" # module_prefix
    "nothing" # profile
    "nothing" # theme
    "nothing" # URL
    "nothing" # fresh_install
    "nothing" # database_download_source
    "nothing" # database_store_type
    "lagoon" # deploy_type
    "n" # preserve_ftp
    "n" # preserve_acquia
    "y" # preserve_lagoon
    "n" # preserve_dependenciesio
    "nothing" # preserve_doc_comments
    "nothing" # preserve_drevops_info
  )
  provision_site "${CURRENT_PROJECT_DIR}" 0 "${answers[@]}"

  assert_files_present_common "star_wars" "StarWars" "${CURRENT_PROJECT_DIR}"
  assert_files_present_deployment "star_wars" "${CURRENT_PROJECT_DIR}"
  assert_files_present_integration_lagoon "star_wars" "${CURRENT_PROJECT_DIR}"
  assert_files_present_no_integration_acquia "star_wars" "${CURRENT_PROJECT_DIR}"
  assert_files_present_no_integration_ftp "star_wars" "${CURRENT_PROJECT_DIR}"

  substep "Copying built codebase into code source directory ${SRC_DIR}"
  cp -R "${CURRENT_PROJECT_DIR}/." "${SRC_DIR}/"

  # Make sure that all files were copied out from the container or passed from
  # the previous stage of the build.
  assert_files_present_common "star_wars" "StarWars" "${SRC_DIR}"
  assert_files_present_deployment "star_wars" "${SRC_DIR}"
  assert_files_present_integration_lagoon "star_wars" "${SRC_DIR}"
  assert_files_present_no_integration_acquia "star_wars" "${SRC_DIR}"
  assert_files_present_no_integration_ftp "star_wars" "${SRC_DIR}"
  assert_git_repo "${SRC_DIR}"

  popd > /dev/null

  pushd "${CURRENT_PROJECT_DIR}" > /dev/null

  step "Running deployment"
  # Always force installing of the Lagoon CLI binary in tests rather then using
  # a local version.
  export FORCE_INSTALL_LAGOON_CLI=1
  export LAGOON_BIN_PATH="${APP_TMP_DIR}"
  export LAGOON_INSTANCE="testlagoon"
  export LAGOON_PROJECT="testproject"
  export DEPLOY_BRANCH="testbranch"

  mock_lagoon=$(mock_command "lagoon")

  # Proceed with deployment.
  export DEPLOY_PROCEED=1

  run ahoy deploy
  assert_success

  assert_output_contains "==> Started Lagoon deployment."
  assert_output_contains "==> Finished Lagoon deployment."

  # Assert lagoon binary exists and was called.
  assert_file_exists "${LAGOON_BIN_PATH}/lagoon"
  assert_equal 1 "$(mock_get_call_num "${mock_lagoon}")"
  assert_contains "-l testlagoon deploy branch -p testproject -b testbranch" "$(mock_get_call_args "${mock_lagoon}")"

  # Assert that no other deployments ran.
  assert_output_not_contains "==> Started CODE deployment."
  assert_output_not_contains "==> Finished CODE deployment."

  assert_output_not_contains "==> Started WEBHOOK deployment."
  assert_output_not_contains "==> Successfully called webhook."
  assert_output_not_contains "ERROR: Webhook deployment failed."
  assert_output_not_contains "==> Finished WEBHOOK deployment."

  assert_output_not_contains "==> Started DOCKER deployment."
  assert_output_not_contains "Services map is not specified in DOCKER_MAP variable."
  assert_output_not_contains "==> Finished DOCKER deployment."

  popd > /dev/null
}
