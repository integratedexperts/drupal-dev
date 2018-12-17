#!/usr/bin/env bash
##
# Update composer dependencies and create a PR.
#

set -x
set -e
set -o pipefail

# GitHub OAuth2 token.
DEPS_GITHUB_TOKEN=${DEPS_GITHUB_TOKEN:-}
# The branch to raise PR against.
DEPS_BRANCH_BASE=${DEPS_BRANCH_BASE:-}
# The current branch.
DEPS_BRANCH_HEAD=${DEPS_BRANCH_HEAD:-}
# The title of the PR. the body of the PR will be the boy of the last commit.
DEPS_PR_TITLE=${DEPS_PR_TITLE:-Dependencies update}
# The prefix of the branch for dependency PRs.
DEPS_BRANCH_PREFIX=${DEPS_BRANCH_PREFIX:=deps}

################################################################################

[ "$DEPS_GITHUB_TOKEN" == "" ] && echo "==> ERROR: Missing value for \$DEPS_GITHUB_TOKEN" && exit 1
[ "$DEPS_BRANCH_BASE" == "" ] && echo "==> ERROR: Missing value for \$DEPS_BRANCH_BASE" && exit 1
[ "$DEPS_BRANCH_HEAD" == "" ] && echo "==> ERROR: Missing value for \$DEPS_BRANCH_HEAD" && exit 1
[ "$DEPS_PR_TITLE" == "" ] && echo "==> ERROR: Missing value for \$DEPS_PR_TITLE" && exit 1

# Add changelog generation plugin.
composer global require pyrech/composer-changelogs
config extra.composer-changelogs.commit-auto always

# Run updates.
composer update
commit_msg=$(git log -1 --pretty=%B)

echo $commit_msg

# Create unique hash of the current composer.lock.
hash=$(md5sum composer.lock)
hash=${hash%% *}

git branch -b deps/$hash
git push origin deps/$hash

curl -d "{\"title\":\"$DEPS_PR_TITLE\", \"body\": \"$commit_msg\", \"head\": \"$DEPS_BRANCH_HEAD\", \"base\": \"$DEPS_BRANCH_BASE\" }" \
  -H "Content-Type: application/json" \
  -H "Authorization: token $DEPS_GITHUB_TOKEN" \
  -X POST https://api.github.com/repos/$DEPS_REPO_OWNER/$DEPS_REPO_NAME/pulls
