# Deployment procedures for MYSITE

GitHub is a primary code repository for this project (aka "source repository").
Acquia Cloud is a hosting provider for this project and it also has a git repository (aka "destination repository"). 

The website gets deployed using artefact built on CI and pushed to Acquia Cloud. 

There are 2 types of deployments: feature branches and release tags. They are exactly the same except for the resulting branch name on Acquia Cloud (see below).

## Deployment workflow
1. Developer updates DB in the Acquia Cloud environment by copying PROD database to required environment.
2. Developer pushes code update to the GitHub branch.
3. CI system picks-up the update and does the following:
    1. Builds a website using production DB.
    2. Runs code standard checks and Behat tests on the built website.
    3. Creates a deployment artefact (project files to be pushed to Acquia Cloud repo).
    4. Pushes created artefact to the Acquia Cloud repo:
        - for feature-based branches (i.e. `feature/ABC-123`) the code is pushed to the branch with exactly the same name.
        - for release deployments, which are tag-based (i.e. `0.1.4`), the code is pushed to the branch `release/[tag]` (i.e. `release/0.1.4`).
4. Acquia Cloud picks up recent push to the repository and runs [post code update hooks](hooks/dev/post-code-update) on the environments where code is already deployed.
OR
4. If the branch has not been deployed into any Acquia Cloud environment yet and the developer starts the deployment, Acquia Cloud runs [post code deploy hooks](hooks/dev/post-code-deploy) on the environment where code is being deployed.    

## Release workflow

### Version Number
Release versions are numbered according to [Semantic Versioning](https://semver.org/).
Given a version number X.Y.Z:
  * X = Major release version. No leading zeroes.
  * Y = Minor Release version. No leading zeroes.
  * Z = Hotfix/patch version. No leading zeroes.
  
Examples:
  * Correct: `0.1.0`, `1.0.0` , `1.0.1` , `1.0.10`
  * Incorrect: `0.1` , `1` , `1.0` , `1.0.01` , `1.0.010`

### Git-flow
Use [git-flow](https://danielkummer.github.io/git-flow-cheatsheet/) to manage releases.

### Release outcome
1. Release branch exists as `release/X.Y.Z` in remote GitHub repository.
2. Release tag exists as `X.Y.Z` in remote GitHub repository.
3. The `HEAD` of the `master` branch has `X.Y.Z` tag assigned.
4. The hash of the `HEAD` of the `master` branch exists in the `develop` branch. This is to ensure that everything pushed to `master` exists in `developed` (in case if `master` had any hot-fixes that not yet have been merged to `develop`).
5. There are no PRs in GitHub related to releases.
6. Release branch is available on Acquia Cloud as `release/X.Y.Z` branch. Note: we are building release branches on Acquia Cloud out of tags in GitHub.
7. Release branch `release/X.Y.Z` is deployed into PROD environment. Note: we are NOT deploying tags to Acquia Cloud PROD.

### Important
Since Acquia Cloud becomes a destination repository, the following rules MUST be followed by all developers:
1. There should be no direct access to Acquia Cloud repository for anyone except for project Technical Lead and Deployer user.
2. There should be no pushes to Acquia Cloud repository.
3. There may be `master` or `develop` branch in Acquia Cloud repository.
4. Technical Lead is expected to regularly cleanup `feature/*` branches in Acquia Cloud repository.