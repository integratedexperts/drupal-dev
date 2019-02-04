#!/usr/bin/env bash
##
# Test Drupal-Dev scripts.
#
set -e

CUR_DIR="$(cd "$(dirname "$(dirname "${BASH_SOURCE[0]}")")/.." && pwd)"

targets=()
while IFS=  read -r -d $'\0'; do
    targets+=("$REPLY")
done < <(
  find \
    "${CUR_DIR}"/scripts \
    "${CUR_DIR}"/.circleci \
    "${CUR_DIR}"/.drupal-dev \
    "${CUR_DIR}"/.drupal-dev/tests/bats \
    -type f \
    \( -name "*.sh" -or -name "*.bash" \) \
    -print0
  )

echo "==> Start linting scripts in ${CUR_DIR}"
for file in "${targets[@]}"; do
  [ -f "${file}" ] && LC_ALL=C.UTF-8 shellcheck "${file}";
done;