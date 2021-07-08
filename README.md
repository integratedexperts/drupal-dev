[//]: # (#;< DREVOPS)
<p align="center">
	<img width="400" src="https://raw.githubusercontent.com/wiki/integratedexperts/drupal-dev/images/drevops_logo_text_white.png" alt="DrevOps Logo" />
</div>
<h3 align="center">Build, Test, Deploy scripts for Drupal using Docker and CI/CD</h3>
<div align="center">

[![CircleCI](https://circleci.com/gh/drevops/drevops/tree/8.x.svg?style=shield)](https://circleci.com/gh/drevops/drevops/tree/8.x)
![Drupal 8](https://img.shields.io/badge/Drupal-8-blue.svg)
[![Licence: GPL 3](https://img.shields.io/badge/licence-GPL3-blue.svg)](https://github.com/drevops/drevops/blob/8.x/LICENSE)
[![Dependencies.io](https://img.shields.io/badge/dependencies.io-enabled-green.svg)](https://dependencies.io)
[![Tweet](https://img.shields.io/twitter/url/http/shields.io.svg?style=social)](https://twitter.com/intent/tweet?text=%F0%9F%92%A7%2B%20%F0%9F%90%B3%20%2B%20%E2%9C%93%E2%9C%93%E2%9C%93%20%2B%20%F0%9F%A4%96%20%3D%20DrevOps%20-%20%20Build%2C%20Test%2C%20Deploy%20scripts%20for%20Drupal%20using%20Docker%20and%20CI%2FCD&amp;url=https://www.drevops.com&amp;via=drev_ops&amp;hashtags=drupal,devops,workflow,composer,template,kickstart,ci,test,build)

</div>

**Please help to improve this [README.md](README.md) file to make it more clear what this project does for the newcomers.**

<hr>

### Installation
1. Run installer:
   ```
   curl -L https://raw.githubusercontent.com/richardgaunt/drevops/9.x/install.php > /tmp/install.php && php /tmp/install.php --interactive; rm /tmp/install.php
   ```
2. Commit added files.

3. Follow instructions in the generated `README.md` files of your project.

## Understanding DrevOps
DrevOps is a Drupal project scaffolding template with included development
environment, CI configuration and tools. It can be used on new or existing
projects as is or can be customized as required. It may also be used as a
reference for custom configurations.

DrevOps is designed to support updating of the code templates it provides.
This makes it easy to keep projects using DrevOps up-to-date with the latest
scaffolding code.

DrevOps supports both database-driven development and fresh install for every
build. Database-driven development is when the existing production database is
used for every build (useful for product development), while fresh install is
when the site is installed from the scratch during every build (useful for
module or profile development).

DrevOps has own automated tests that guarantees stability and ease of maintenance.

<details>

**<summary>Expand for more non-technical information</summary>**

### What is the problem that DrevOps is trying to solve?
Increase the quality of the produced Drupal websites with minimum setup effort and knowledge.

### Why is this problem important?
High quality Drupal websites are stable, secure, faster and safer to change.

But developers do not always have the time or skills to setup the tools required
to produce and maintain high-quality Drupal websites.

### How does DrevOps solve it?
Quick install of best practices Drupal configuration on Docker stack using a
single command lowers entry barrier, while unification of the developer's
experience improves development speed across projects.

### Who is it for?
- Digital agencies that want to standardise their development stack (standard
  operating environment) across projects
- Developers that are looking for best practices
- Developers that do not possess required time or knowledge to setup these tools
  themselves

### How does it work?
- You run installer script once
- DrevOps brings the latest release into your codebase
- You commit all new files
- If required, you may override files with changes relevant only to a specific
  project.

### What if I don't like the defaults this project provides?
Since DrevOps is a template, it can be forked and adjusted to your needs. The
installer script will continue to work with your fork, provided that you adjust
several environment variables.

**In other words - you will have your own development templates for your own
projects  the way you want it!**

</details>

![Workflow](https://raw.githubusercontent.com/wiki/drevops/drevops/images/workflow.png)

### Updating DrevOps
Run `ahoy update` to download the latest version of DrevOps for your project.

<details>
<summary>Show update process screenshot</summary>

![Installer](https://raw.githubusercontent.com/wiki/drevops/drevops/images/project-update.png)
</details>

## Why is DrevOps awesome?
- **Highly configurable**  <br/>
  All configuration is made through environment variables without the need to change any of the scripts.
- **Flexible**<br/>
  Anything can be overridden for your specific project and will not be lost during the next update.
- **Pure Docker stack**  <br/>
  No special binaries to work with Docker. No generated Docker Compose configurations from custom configurations. Modify configuration to fit your project needs.
- **Tested**  <br/>
  There are tests for workflows, configuration, deployments, CI. Everything. And they run automatically on every commit.
- **Versioned**  <br/>
  It is always clear which version of the stack your site uses.
- **Upgradable**  <br/>
  Your website can be updated to a newer version of DrevOps with minimal effort. Your project customisations are easily preserved.
- **Documented**  <br/>
  The major areas of DrevOps are explicitly documented, while most of the code is self-documented.

## What areas does DrevOps cover?
- Drupal
- Local development environment
- Code linting
- Testing
- Automated builds (CI)
- Deployment
- Documentation
- Integrations
- Maintenance

<details>

**<summary>More details</summary>**

| **Area**                                      | **Feature**                                                                                                         | **Why it is important** |
| --- | --- | --- |
| **Drupal**                                    |
| Versions                                      | Drupal 7 support                                                                                                    | Drupal 7 is still widely used |
|                                               | Drupal 8 support                                                                                                    | Drupal 8 is current version   |
|                                               | Separate branches for each Drupal version                                                                           | Handling both Drupal versions in the same repository allows to easily re-use some commits across branches. |
| [Composer-based configuration](composer.json) | Pure composer configuration                                                                                         | Website is assembled using industry-standard tools such as Composer |
|                                               | Uses [drupal-scaffold](https://github.com/drupal-composer/drupal-scaffold)                                          | Industry-standard composer package to scaffold some of Drupal files |
|                                               | [Scripts](scripts/composer) to create required settings and files with environment variables support                | Required files and directories created automatically.Environment variables override support allows to provide override values without the need to change scripts. Useful for per-environment overrides. |
|                                               | [Settings file](docroot/sites/default/settings.php) with multi-environment support        | Per-environment variables allow to easily target specific settings to specific environments without too much mess |
|                                               | [Best-practices development modules](composer.json)                                                                 | Having the same development modules on each website helps to reduce development time. |
| Custom [module scaffolding](docroot/modules/custom/your_site_core/your_site_core.module) | Mechanism to organise contributed module-related hook implementations into standalone files | Helps avoid large files with all hook implementation, which leads to a simple maintenance. |
| Custom [theme scaffolding](docroot/themes/custom/your_site_theme)                      | |                                                                          |
|                                               | Grunt + SASS/SCSS + globbing + Livereload                                                                           | Grunt configuration defines multiple build steps to work with frontend in Drupal.<br/>Livereload allows to automatically refresh the page once there are changes to styles or scripts. |
| Patches management                            | Based on [composer-patches](https://github.com/cweagans/composer-patches)                                           | Support for custom (per-project) and contributed patches is simply essential for any project. |
| **Local development environment**             |
| Docker                                        | Using stable [Amazee images](https://hub.docker.com/r/amazeeio)                                                     | Amazee images are stable - they are covered by tests and are used in many production environments. |
|                                               | Pure Docker [configuration](docker-compose.yml)                                                                     | Pure Docker configuration allows anyone with Docker knowledge to alter configuration as required. |
|                                               | Custom application support                                                                                          | As a result of using Docker, it is possible to install any application, provided that it can be ran in container |
|                                               | Multi-application support                                                                                           | As a result of using Docker, it is possible to have multiple applications (not only Drupal) in one stack. For example, decoupled Drupal and Frontend VueJS application. |
|                                               | Using [Pygmy](https://github.com/amazeeio/pygmy)                                                                    | Adds support for additional Docker tools as well as Mailhog (to test emails) |
| Unified Development Experience (DX)           | [Ahoy](https://github.com/ahoy-cli/ahoy) commands to abstract complex tasks into set of workflow commands           | To improve development speed and unify development tasks across projects |
|                                               | [Single command](.ahoy.yml) project build                                                                           | Project must be built in the same way on any environment and a single command always guarantees that it will be done in the same predictable way.Improve development speed and easy maintenance. |
|                                               | Database sanitization [support](scripts/sanitize.sql)                                                               | Remove all personal data from the database before working on it. |
| Configuration                                 | Configuration provided through a (file)[.env] with reasonable defaults                                              | To cover majority of the project without the need to change any scripts |
| **Code linting**                              |
| PHP                                           | [Drupal PHP coding standards](https://www.drupal.org/docs/develop/standards/coding-standards)                       | To increase code quality and lower technical debt |
|                                               | [PHP Version compatibility](https://github.com/PHPCompatibility/PHPCompatibility)                                   | To avoid language constructs that may not be supported by a certain version of the language |
| JavaScript                                    | [Drupal JS coding standards](https://www.drupal.org/docs/develop/standards/javascript/javascript-coding-standards)  | To increase code quality and lower technical debt |
| SASS/SCSS                                     |                                                                                                                     | To increase code quality and lower technical debt |
| **Testing**                                   |
| [PHPUnit](https://phpunit.de/)\*              | Configuration + examples                                                                                            | Enables unit-testing capability to improve code quality and stability |
| [Behat](http://behat.org)                     | [Configuration](behat.yml) + [examples](tests/behat/features)                                                       | Enables integration-testing capability using fast tests to improve code quality and stability |
|                                               | Browser testing                                                                                                     | Enables integration-testing capability with JavaScript support to improve code quality and stability |
| **Automated builds**                          |
| [CI template](.circleci/config.yml)           | Build + Test + Deploy                                                                                               | Standard workflow for automated builds |
|                                               | Conditional Deploy                                                                                                  | Supports conditional deployments based on tags and branches to allow selective deployments |
|                                               | Parallel builds to speedup pipeline                                                                                 | Running jobs in parallel may significantly lower the build time for large projects |
|                                               | Cached database support                                                                                             | Reduce build times by using daily cached database |
|                                               | Identical environment as local and production                                                                       | While running on a 3rd party provider, CI uses hosting stack identical to the local and production in order to identify any problems at early stages.CI also acts as a &#39;shadow&#39; developer by running exactly the same commands as what a developer would run locally. |
| **Documentation**                             |
| Readme files                                  | Generic project information                                                                                         | Helps to find relevant project information in one place |
|                                               | Local environment setup                                                                                             | Helps to lower project onboarding time |
|                                               | List of available commands                                                                                          | Helps to lower project onboarding time |
|                                               | Build badges                                                                                                        | Shows current build status          |
|                                               | [FAQs](FAQs.md)                                                                                                     | Helps to lower project onboarding time |
|                                               | [Deployment information](DEPLOYMENT.md)                                                                             | Helps to find relevant deployment information in one place |
| Onboarding checklist                          | [Onboarding checklist](.github/ONBOARDING.md)                                                                       | Helps to track the progress of onboarding to DrevOps |
| GitHub management                             | [Pull request template](.github/PULL_REQUEST_TEMPLATE.md)                                                           | Helps to improve team collaboration and reduce the time for pull request management |
|                                               | Pre-defined issue and pull request labels\*                                                                         | Helps to improve team collaboration and reduce the time for pull request management |
| **Integrations and deployments** |
| Acquia                                        | Production database from Acquia                                                                                     | Using production database for development and automated builds (CI) requires database dump from Acquia |
|                                               | Deploy code to Acquia                                                                                               | Deploying to Acquia requires packaging Composer-based project into artifact before pushing |
|                                               | [Deployment hooks](hooks)                                                                                           | Standardised deployment hooks guarantee that every deployment is reproducible |
| Lagoon                                        | Deployment configuration                                                                                            | Lagoon configuration is required to perform deployments |
|                                               | Production database from Lagoon                                                                                     | Using production database for development and automated builds (CI) requires database dump from Lagoon |
| [dependencies.io](https://www.dependencies.io/) | Automated pull request submissions for automated updates.                                                         | Automated dependencies updates allow to keep the project up to date by automatically creating pull requests with updated dependencies on a daily basis |
| **Maintenance (of DrevOps)**               |
| Install and upgrade                           | Follows [SemVer](https://semver.org/) model for releases                                                            | Projects may refer to a specific version of DrevOps, which sets expectations about what tools and configuration is available |
|                                               | Managed as an agile project                                                                                         | New features and defects can be addressed in a shorter development cycle.GitHub issues organised on the Kanban board provide clear visibility for future releases |
|                                               | One-liner [install script](install.sh) with optional wizard                                                         | Minimises the time to try DrevOps.Provides centralised point for installation into new and existing projects, as well as updates. |
| Stability                                     | [Test suite](scripts/drevops/tests/bats) for all provided commands                                                                  | Guarantees that commands will work |
|                                               | Own CI to run test suite                                                                                            | Pull requests and releases are stable |
|                                               | Daily Drupal and NPM updates                                                                                        | Composer (including Drupal) and NPM packages are always using the latest versions. |
| Documentation                                 | Contribution guide\*                                                                                                | Engages community to contribute back |
|                                               | [Pull request template](.github/PULL_REQUEST_TEMPLATE.md)                                                           | Helps to improve community collaboration and reduce the time for pull request management. |

\* Denotes features planned for 1.7 release

</details>

## Contributing
- Progress is tracked as [GitHub project](https://github.com/drevops/drevops/projects/1).
- Development takes place in 2 independent branches named after Drupal core version: `7.x` or `8.x`.
- Create an issue and prefix title with Drupal core version: `[8.x] Updated readme file.`.
- Create PRs with branches prefixed with Drupal core version: `7.x` or `8.x`. For example, `feature/8.x-updated-readme`.

### Main concepts behind DrevOps
- **Fetch as much of development configuration as possible from DrevOps repository**<br/>
  Allows to keep your project up-to-date with DrevOps
- **Avoid adding things to the wrong places**<br/>
  Example: Using Composer scripts for workflow commands. Instead, use tools specifically designed for this, like Ahoy
- **Abstract similar functionality into steps**<br/>
  Allows to apply changes at the larger scale without the need to modify each project
- **Run the most of the code in the containers**<br/>
  Reduces the number of required tools on the host machine

--------------------------------------------------------------------------------

## Paid support
[Integrated Experts](https://github.com/integratedexperts) provides paid support for DrevOps:
- New and existing project onboarding.
- Support plans with SLAs.
- Priority feature implementation.
- Updates to the latest version of the platform.
- DevOps consulting and custom implementations.

Contact us at [support@integratedexperts.com](mailto:support@integratedexperts.com)

## Useful projects

- [Drupal module testing in CircleCI](https://github.com/integratedexperts/drupal_circleci)
- [MariaDB Docker image with enclosed data](https://github.com/drevops/mariadb-drupal-data) - Docker image to capture database data as a Docker layer.
- [CI Builder Docker image](https://github.com/drevops/ci-builder) - Docker image for CI builder container with many pre-installed tools.
- [Behat Steps](https://github.com/integratedexperts/behat-steps) - Collection of Behat step definitions.
- [Behat Screenshot](https://github.com/integratedexperts/behat-screenshot) - Behat extension and a step definition to create HTML and image screenshots on demand or test fail.
- [Behat Progress Fail](https://github.com/integratedexperts/behat-format-progress-fail) - Behat output formatter to show progress as TAP and fails inline.
- [Behat Relativity](https://github.com/integratedexperts/behat-relativity) - Behat context for relative elements testing.
- [Code Artifact Builder](https://github.com/integratedexperts/robo-git-artefact) - Robo task to push git artifact to remote repository.
- [GitHub Labels](https://github.com/integratedexperts/github-labels) - create labels on GitHub in bulk.
- [Formatted git messages](https://github.com/alexdesignworks/git-hooks) - pre-commit git hook to check that commit messages formatted correctly.

--------------------------------------------------------------------------------
**Below is a content of the `README.md` file that will be added to your project.**

**All content above this line will be automatically removed during installation.**

[//]: # (#;> DREVOPS)
# YOURSITE
Drupal 9 implementation of YOURSITE for YOURORG

[![CircleCI](https://circleci.com/gh/your_org/your_site.svg?style=shield)](https://circleci.com/gh/your_org/your_site)
![Drupal 8](https://img.shields.io/badge/Drupal-8-blue.svg)

[//]: # (#;< DEPENDENCIESIO)

[![Dependencies.io](https://img.shields.io/badge/dependencies.io-enabled-green.svg)](https://dependencies.io)

[//]: # (#;> DEPENDENCIESIO)

[//]: # (DO NOT REMOVE THE BADGE BELOW. IT IS USED BY DREVOPS TO TRACK INTEGRATION)

[![DrevOps](https://img.shields.io/badge/DrevOps-DREVOPS_VERSION_URLENCODED-blue.svg)](https://github.com/drevops/drevops/tree/DREVOPS_VERSION)

[//]: # (Remove the section below once onboarding is finished)
## Onboarding
Use [Onboarding checklist](ONBOARDING.md) to track the project onboarding progress.

## Local environment setup
- Make sure that you have latest versions of all required software installed:
  - [Docker](https://www.docker.com/)
  - [Pygmy](https://pygmy.readthedocs.io/)
  - [Ahoy](https://github.com/ahoy-cli/ahoy)
- Make sure that all local web development services are shut down (Apache/Nginx, Mysql, MAMP etc).
- Checkout project repository (in one of the [supported Docker directories](https://docs.docker.com/docker-for-mac/osxfs/#access-control)).

[//]: # (#;< ACQUIA)

- Authenticate with Acquia Cloud API
  1. Create your Acquia Cloud API token:<br/>
     Acquia Cloud UI -> Account -> API tokens -> Create Token
  2. Copy `default.env.local` to `.env.local`.
  3. Populate `$AC_API_KEY` and `$AC_API_SECRET` environment variables in
     `.env.local` file with values generated in the step above.

[//]: # (#;> ACQUIA)

[//]: # (#;< LAGOON)

- Authenticate with Lagoon
  1. Create an SSH key and add it to your account in the [Lagoon Dashboard](https://ui-lagoon-master.ch.amazee.io/).
  2. Copy `default.env.local` to `.env.local`.
  3. Update `$LAGOON_SSH_KEY_FILE` environment variable in `.env.local` file
  with the path to the SSH key.

[//]: # (#;> LAGOON)


[//]: # (#;< !FRESH_INSTALL)

- `ahoy download-db`

[//]: # (#;> !FRESH_INSTALL)
- `pygmy up`
- `ahoy build`

## Available `ahoy` commands
Run each command as `ahoy <command>`.
  ```
  build        Build or rebuild the project.
  clean        Remove containers and all build files.
  cli          Start a shell or run a command inside the CLI service container.
  debug        Enable debug configuration.
  deploy       Run remote deployment procedures
  doctor       Find problems with current project setup.
  down         Stop Docker containers and remove container, images, volumes and networks.
  download-db  Download database.
  drush        Run drush commands in the CLI service container.
  export-db    Export database dump or database image (DATABASE_IMAGE variable must be set).
  fe           Build front-end assets.
  fed          Build front-end assets for development.
  few          Watch front-end assets during development.
  flush-redis  Flush Redis cache.
  info         Show information about this project.
  install-site Install a site.
  lint         Lint back-end and front-end code.
  lint-be      Lint back-end code.
  lint-fe      Lint front-end code.
  login        Login to a website.
  logs         Show Docker logs for all or specified services.
  pull         Pull latest docker images.
  pull-db      Download database image with the latest nightly dump. Run "ahoy reload-db" to reload DB in the running stack.
  reload-db    Reload the database container using local database image.
  reset        Reset environment: remove containers, all build, uncommitted files.
  restart      Restart all or specified stopped and running Docker containers.
  start        Start all or specified existing Docker containers.
  stop         Stop all or specified running Docker containers.
  test         Run all tests.
  test-bdd     Run BDD tests.
  test-unit    Run PhpUnit unit tests.
  up           Build and start all or specified Docker containers.
  update       Update development stack.
  ```

### Updating development stack

Development stack needs to be downloaded for each environment, but some files may be committed to the project repository.
Update process brings new versions of development stack files and may overwrite some of them. The changes in these files
need to be reviewed and selectively committed.

1. Start a new branch to make sure that your changes do not affect the main branch
2. Run `ahoy update` to download the latest version of the development stack
3. Review and commit changes
4. Make sure that your CI build passes with updated development stack configuration
5. Merge your changes to the main branch

## Adding Drupal modules

`composer require drupal/module_name`

## Adding patches for Drupal modules

1. Add `title` and `url` to patch on https://drupal.org to the `patches` array in `extra` section in `composer.json`.

```
    "extra": {
        "patches": {
            "drupal/core": {
                "Contextual links should not be added inside another link - https://www.drupal.org/node/2898875": "https://www.drupal.org/files/issues/contextual_links_should-2898875-3.patch"
            }
        }
    }
```

2. `composer update --lock`

## Front-end and Livereload
- `ahoy fe` - build SCSS and JS assets.
- `ahoy fed` - build SCSS and JS assets for development.
- `ahoy few` - watch asset changes and reload the browser (using Livereload). To enable Livereload integration with Drupal, add to `settings.php` file (already added to `settings.local.php`):
  ```
  $settings['livereload'] = TRUE;
  ```

## Coding standards
PHP and JS code linting uses [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) with Drupal rules from [Coder](https://www.drupal.org/project/coder) module and additional local overrides in `phpcs.xml` and `.eslintrc.json`.

SASS and SCSS code linting use [Sass Lint](https://github.com/sasstools/sass-lint) with additional local overrides in `.sass-lint.yml`.

Set `ALLOW_LINT_FAIL_BE=1` in `.env` to allow back-end code lint failures.

Set `ALLOW_LINT_FAIL_FE=1` in `.env` to allow front-end code lint failures.

## Behat tests
Behat configuration uses multiple extensions:
- [Drupal Behat Extension](https://github.com/jhedstrom/drupalextension) - Drupal integration layer. Allows to work with Drupal API from within step definitions.
- [Behat Screenshot Extension](https://github.com/integratedexperts/behat-screenshot) - Behat extension and a step definition to create HTML and image screenshots on demand or test fail.
- [Behat Progress Fail Output Extension](https://github.com/integratedexperts/behat-format-progress-fail) - Behat output formatter to show progress as TAP and fail messages inline. Useful to get feedback about failed tests while continuing test run.
- `FeatureContext` - Site-specific context with custom step definitions.

Add `@skipped` tag to failing tests if you would like to skip them.

## Automated builds (Continuous Integration)
In software engineering, continuous integration (CI) is the practice of merging all developer working copies to a shared mainline several times a day.
Before feature changes can be merged into a shared mainline, a complete build must run and pass all tests on CI server.

This project uses [Circle CI](https://circleci.com/) as a CI server: it imports production backups into fully built codebase and runs code linting and tests. When tests pass, a deployment process is triggered for nominated branches (usually, `master` and `develop`).

Add `[skip ci]` to the commit subject to skip CI build. Useful for documentation changes.

### SSH
Circle CI supports shell access to the build for 120 minutes after the build is finished when the build is started with SSH support. Use "Rerun job with SSH" button in Circle CI UI to start build with SSH support.

### Cache
Circle CI supports caching between builds. The cache takes care of saving the state of your dependencies between builds, therefore making the builds run faster.
Each branch of your project will have a separate cache. If it is the very first build for a branch, the cache from the default branch on GitHub (normally `master`) will be used. If there is no cache for master, the cache from other branches will be used.
If the build has inconsistent results (build fails in CI but passes locally), try to re-running the build without cache by clicking 'Rebuild without cache' button.

### Test artifacts
Test artifacts (screenshots etc.) are available under "Artifacts" tab in Circle CI UI.

[//]: # (#;< DEPLOYMENT)

## Deployment
Please refer to [DEPLOYMENT.md](DEPLOYMENT.md)

[//]: # (#;> DEPLOYMENT)

[//]: # (#;< DEPENDENCIESIO)

## Automated patching
[dependencies.io](https://dependencies.io) integration allows to keep the
project up to date by automatically creating pull requests with updated
dependencies on a daily basis.

[//]: # (#;> DEPENDENCIESIO)

## FAQs
Please refer to [FAQs](FAQs.md)
