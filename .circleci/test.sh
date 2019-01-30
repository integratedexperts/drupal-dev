#!/usr/bin/env bash
##
# Run tests in CI.
#
set -e

echo "==> Lint code"
ahoy lint

echo "==> Run PHPUnit tests"
ahoy test-phpunit

# Running Behat tests can be done in parallel, provided that you set
# build concurrency CircleCI UI to a number larger then 1 container and
# your tests are tagged with `p0`, `p1` etc. to assign tests to a specific
# build node.
#
# Using `progress_fail` format allows to get an instant feedback about
# broken tests without stopping all other tests or waiting for the build
# to finish. This is particularly useful for projects with large number
# of tests.
#
# We are also using --rerun option to overcome some false positives that
# could appear in browser-based tests. With this option, Behat remembers
# which tests failed during previous run and re-runs only them.
#
# Lastly, we copy test results (artifacts) out of containers and
# store them so that CircleCI could show them in 'Artifacts' tab.

echo "==> Run Behat tests"
#ahoy cli "mkdir -p /app/screenshots"
[ "${CIRCLE_NODE_TOTAL}" -gt "1" ] && BEHAT_PROFILE=--profile=p${CIRCLE_NODE_INDEX} && echo "BEHAT_PROFILE=${BEHAT_PROFILE}">>.env.local
ahoy test-behat -- "--format=progress_fail" || ahoy test-behat -- "--rerun --format=progress_fail"
