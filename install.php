<?php

/**
 * @file
 * DrevOps installer.
 *
 * Usage:
 * @code
 * curl -L https://raw.githubusercontent.com/drevops/drevops/8.x/install.php | php
 * curl -L https://raw.githubusercontent.com/drevops/drevops/8.x/install.php | php -- /path/to/destination/directory
 * curl -L https://raw.githubusercontent.com/drevops/drevops/8.x/install.php | php -- --quiet /path/to/destination/directory
 * curl -L https://raw.githubusercontent.com/drevops/drevops/8.x/install.php | php -- help
 * curl -L https://raw.githubusercontent.com/drevops/drevops/8.x/install.php | php -- --help
 * @endcode
 *
 * Variables precedence (top values win):
 * - Values from CLI arguments and options
 * - Values from the environment
 * - Values from the .env file
 * - Defaults
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.InlineComment.SpacingBefore
 * phpcs:disable Drupal.Commenting.InlineComment.SpacingAfter
 */

/**
 * Defines Drupal version supported by this installer.
 */
define('INSTALLER_DRUPAL_VERSION', 9);

/**
 * Defines current working directory.
 */
define('CUR_DIR', getcwd());

/**
 * Defines installer exist codes.
 */
define('INSTALLER_EXIT_SUCCESS', 0);
define('INSTALLER_EXIT_ERROR', 1);

/**
 * Defines installer status message flags.
 */
define('INSTALLER_STATUS_SUCCESS', 0);
define('INSTALLER_STATUS_ERROR', 1);
define('INSTALLER_STATUS_MESSAGE', 2);
define('INSTALLER_STATUS_DEBUG', 3);

/**
 * Defines "yes" and "no" answer strings.
 */
define('ANSWER_YES', 'y');
define('ANSWER_NO', 'n');

/**
 * Main install functionality.
 */
function install(array $argv) {
  init_cli_args_and_options($argv);
  load_dotenv(get_dst_dir() . '/.env');
  init_config();

  if (get_config('help')) {
    print_help();
    return INSTALLER_EXIT_SUCCESS;
  }

  check_requirements();

  print_header();

  gather_answers();

  $proceed = ask_should_proceed();
  if (!$proceed) {
    print_abort();
    return INSTALLER_EXIT_SUCCESS;
  }
  finalise_interactions();

  download();

  prepare_destination();

  process();

  copy_files();

  load_dotenv(get_dst_dir() . '/.env');

  process_demo();

  print_footer();

  return INSTALLER_EXIT_SUCCESS;
}

function check_requirements() {
  command_exists('git');
  command_exists('tar');
  command_exists('composer');
}

function prepare_destination() {
  $dst = get_dst_dir();

  if (!is_dir($dst)) {
    status(sprintf('Creating destination directory "%s".', $dst));
    mkdir($dst);
    if (!is_writable($dst)) {
      throw new \RuntimeException(sprintf('Destination directory "%s" is not writable.', $dst));
    }
  }

  if (is_readable("$dst/.git")) {
    status(sprintf('Git repository exists in "%s" - skipping initialisation.', $dst));
  }
  else {
    status(sprintf('Initialising Git repository in directory "%s".', $dst), INSTALLER_STATUS_MESSAGE, FALSE);
    do_exec("git --work-tree=\"$dst\" --git-dir=\"$dst/.git\" init > /dev/null");
    if (!is_readable("$dst/.git")) {
      throw new \RuntimeException(sprintf('Unable to init git project in directory "%s".', $dst));
    }
    print ' ';
    status('Done', INSTALLER_STATUS_SUCCESS);
  }
}

function finalise_interactions() {
  close_stdin_handle();
}

function process_demo() {
  if (empty(get_config('DREVOPS_DEMO')) || !empty(get_config('DREVOPS_SKIP_DEMO'))) {
    return;
  }

  $url = getenv_or_default('CURL_DB_URL');
  if (empty($url)) {
    return;
  }

  $dir = get_dst_dir() . DIRECTORY_SEPARATOR . getenv_or_default('DB_DIR', './.data');
  $file = getenv_or_default('DB_FILE', 'db.sql');
  status(sprintf('No database dump file found in "%s" directory. Downloading DEMO database from %s.', $dir, $url), INSTALLER_STATUS_MESSAGE, FALSE);
  if (!file_exists($dir)) {
    mkdir($dir);
  }
  do_exec(sprintf('curl -s -L "%s" -o "%s/%s"', $url, $dir, $file), $output, $code);

  if ($code !== 0) {
    throw new \RuntimeException(sprintf('Unable to download demo database from "%s".', $url));
  }
  print ' ';
  status('Done', INSTALLER_STATUS_SUCCESS);
}

/**
 * Handling processing for Drupal 7 and Drupal 8.
 */
function process() {
  $dir = get_config('DREVOPS_TMP_DIR');

  status('Replacing tokens ', INSTALLER_STATUS_MESSAGE, FALSE);

  $processors = [
    'profile',
    'fresh_install',
    'database_download_source',
    'database_image',
    'deploy_type',
    'preserve_acquia',
    'preserve_lagoon',
    'preserve_ftp',
    'preserve_dependenciesio',
    'string_tokens',
    'preserve_doc_comments',
    'demo_mode',
    'preserve_drevops_info',
    'drevops_internal',
    'enable_commented_code',
  ];

  foreach ($processors as $name) {
    process_answer($name, $dir);
    print_tick($name);
  }

  print ' ';
  status('Done', INSTALLER_STATUS_SUCCESS);
}

function copy_files() {
  $src = get_config('DREVOPS_TMP_DIR');
  $dst = get_dst_dir();

  // Due to the way symlinks can be ordered, we cannot copy files one-by-one
  // into destination directory. Instead, we are removing all ignored files
  // and empty directories, making the src directory "clean", and then
  // recursively copying the whole directory.
  $all = scandir_recursive($src, ignore_paths(), TRUE);
  $files = scandir_recursive($src);
  $valid_files = scandir_recursive($src, ignore_paths());
  $dirs = array_diff($all, $valid_files);
  $ignored_files = array_diff($files, $valid_files);

  status('Copying files', INSTALLER_STATUS_DEBUG);

  foreach ($valid_files as $filename) {
    $relative_file = str_replace($src . DIRECTORY_SEPARATOR, '.' . DIRECTORY_SEPARATOR, $filename);

    if (is_internal_path($relative_file)) {
      status("Skipped file $relative_file as an internal DrevOps file.", INSTALLER_STATUS_DEBUG);
      unlink($filename);
      continue;
    }
  }

  // Remove skipped files.
  foreach ($ignored_files as $skipped_file) {
    if (is_readable($skipped_file)) {
      unlink($skipped_file);
    }
  }

  // Remove empty directories.
  foreach ($dirs as $dir) {
    rmdir_recursive_empty($dir);
  }

  // Src directory is now "clean" - copy it to dst directory.
  if (is_dir($src) && !dir_is_empty($src)) {
    copy_recursive($src, $dst);
  }

  // Special case for .env.local as it may exist.
  if (!file_exists($dst . '/.env.local')) {
    copy_recursive($dst . '/default.env.local', $dst . '/.env.local');
  }
}

function copy_recursive($source, $dest, $permissions = 0755, $copy_empty_dirs = FALSE) {
  $parent = dirname($dest);

  if (!is_dir($parent)) {
    mkdir($parent, $permissions, TRUE);
  }

  // Note that symlink target must exist.
  if (is_link($source)) {
    // Changing dir symlink will be relevant to the current destination's file
    // directory.
    $cur_dir = getcwd();
    chdir($parent);
    $ret = TRUE;
    if (!is_readable(basename($dest))) {
      $ret = symlink(readlink($source), basename($dest));
    }
    chdir($cur_dir);
    return $ret;
  }

  if (is_file($source)) {
    $ret = copy($source, $dest);
    if ($ret) {
      chmod($dest, fileperms($source));
    }
    return $ret;
  }

  if (!is_dir($dest) && $copy_empty_dirs) {
    mkdir($dest, $permissions, TRUE);
  }

  $dir = dir($source);
  while (FALSE !== $entry = $dir->read()) {
    if ($entry == '.' || $entry == '..') {
      continue;
    }
    copy_recursive("$source/$entry", "$dest/$entry", $permissions);
  }

  $dir->close();
  return TRUE;
}

function git_file_is_tracked($path, $dir) {
  if (is_dir($dir . DIRECTORY_SEPARATOR . '.git')) {
    $cwd = getcwd();
    chdir($dir);
    do_exec("git ls-files --error-unmatch \"{$path}\" 2>&1 >/dev/null", $output, $code);
    chdir($cwd);
    return $code === 0;
  }
  return FALSE;
}

function drupal_core_profiles() {
  return [
    'standard',
    'minimal',
    'testing',
    'demo_umami',
  ];
}

// ////////////////////////////////////////////////////////////////////////// //
//                              PROCESSORS                                    //
// ////////////////////////////////////////////////////////////////////////// //

/**
 * Process answers.
 */
function process_answer($name, $dir) {
  return execute_callback('process', $name, $dir);
}

function process__profile($dir) {
  // For core profiles - remove custom profile and direct links to it.
  if (in_array(get_answer('profile'), drupal_core_profiles())) {
    rmdir_recursive("{$dir}/docroot/profiles/your_site_profile");
    rmdir_recursive("{$dir}/docroot/profiles/custom/your_site_profile");
    dir_replace_content('docroot/profiles/your_site_profile,', '', $dir);
    dir_replace_content('docroot/profiles/custom/your_site_profile,', '', $dir);
  }
  dir_replace_content('your_site_profile', get_answer('profile'), $dir);
}

function process__fresh_install($dir) {
  if (get_answer('fresh_install') == ANSWER_YES) {
    remove_token_with_content('!FRESH_INSTALL', $dir);
  }
  else {
    remove_token_with_content('FRESH_INSTALL', $dir);
  }
}

function process__database_download_source($dir) {
  $type = get_answer('database_download_source');
  file_replace_content('/DATABASE_DOWNLOAD_SOURCE=.*/', "DATABASE_DOWNLOAD_SOURCE=$type", $dir . '/.env');

  if ($type == 'docker_registry') {
    remove_token_with_content('!DATABASE_DOWNLOAD_SOURCE_DOCKER_REGISTRY', $dir);
  }
  else {
    remove_token_with_content('DATABASE_DOWNLOAD_SOURCE_DOCKER_REGISTRY', $dir);
  }
}

function process__database_image($dir) {
  $image = get_answer('database_image');
  file_replace_content('/DATABASE_IMAGE=.*/', "DATABASE_IMAGE=$image", $dir . '/.env');

  if ($image) {
    remove_token_with_content('!DATABASE_IMAGE', $dir);
  }
  else {
    remove_token_with_content('DATABASE_IMAGE', $dir);
  }
}

function process__deploy_type($dir) {
  $type = get_answer('deploy_type');
  if ($type != 'none') {
    file_replace_content('/DEPLOY_TYPE=.*/', "DEPLOY_TYPE=$type", $dir . '/.env');
    remove_token_with_content('!DEPLOYMENT', $dir);
  }
  else {
    if (strpos($type, 'code') === FALSE) {
      @unlink("$dir/.gitignore.deployment");
    }
    @unlink("$dir/DEPLOYMENT.md");
    remove_token_with_content('DEPLOYMENT', $dir);
  }
}

function process__preserve_acquia($dir) {
  if (get_answer('preserve_acquia') == ANSWER_YES) {
    remove_token_with_content('!ACQUIA', $dir);
  }
  else {
    rmdir_recursive("$dir/hooks");
    remove_token_with_content('ACQUIA', $dir);
  }
}

function process__preserve_lagoon($dir) {
  if (get_answer('preserve_lagoon') == ANSWER_YES) {
    remove_token_with_content('!LAGOON', $dir);
  }
  else {
    @unlink("$dir/drush/aliases.drushrc.php");
    @unlink("$dir/.lagoon.yml");
    remove_token_with_content('LAGOON', $dir);
  }
}

function process__preserve_ftp($dir) {
  if (get_answer('preserve_ftp') == ANSWER_YES) {
    remove_token_with_content('!FTP', $dir);
  }
  else {
    remove_token_with_content('FTP', $dir);
  }
}

function process__preserve_dependenciesio($dir) {
  if (get_answer('preserve_dependenciesio') == ANSWER_YES) {
    remove_token_with_content('!DEPENDENCIESIO', $dir);
  }
  else {
    @unlink("$dir/dependencies.yml");
    remove_token_with_content('DEPENDENCIESIO', $dir);
  }
}

function process__string_tokens($dir) {
  $machine_name_hyphenated = str_replace('_', '-', get_answer('machine_name'));
  $machine_name_camel_cased = to_camel_case(get_answer('machine_name'), TRUE);
  $drevops_version_urlencoded = str_replace('-', '--', get_config('DREVOPS_VERSION'));

  // @formatter:off
  // phpcs:disable Generic.Functions.FunctionCallArgumentSpacing.TooMuchSpaceAfterComma
  // phpcs:disable Drupal.WhiteSpace.Comma.TooManySpaces
  dir_replace_content('your_site_theme',   get_answer('theme'),              $dir);
  dir_replace_content('your_org',          get_answer('org_machine_name'),   $dir);
  dir_replace_content('YOURORG',           get_answer('org'),                $dir);
  dir_replace_content('your-site-url',     get_answer('url'),                $dir);
  dir_replace_content('your_site',         get_answer('module_prefix'),      $dir . '/docroot/modules/custom');
  dir_replace_content('your_site',         get_answer('machine_name'),       $dir);
  dir_replace_content('your-site',         $machine_name_hyphenated,         $dir);
  dir_replace_content('YOURSITE',          get_answer('name'),               $dir);
  dir_replace_content('YourSite',          $machine_name_camel_cased,        $dir);
  replace_string_filename('YourSite',         $machine_name_camel_cased,        $dir);

  dir_replace_content('DREVOPS_VERSION_URLENCODED',  $drevops_version_urlencoded,    $dir);
  dir_replace_content('DREVOPS_VERSION',             get_config('DREVOPS_VERSION'),  $dir);

  replace_string_filename('your_site_theme',  get_answer('theme'),              $dir);
  replace_string_filename('your_site',        get_answer('module_prefix'),      $dir . '/docroot/modules/custom');
  replace_string_filename('your_org',         get_answer('org_machine_name'),   $dir);
  replace_string_filename('your_site',        get_answer('machine_name'),       $dir);
  // @formatter:on
  // phpcs:enable Generic.Functions.FunctionCallArgumentSpacing.TooMuchSpaceAfterComma
  // phpcs:enable Drupal.WhiteSpace.Comma.TooManySpaces
}

function process__preserve_doc_comments($dir) {
  if (get_answer('preserve_doc_comments') == ANSWER_YES) {
    // Replace special "#: " comments with normal "#" comments.
    dir_replace_content('#:', '#', $dir);
  }
  else {
    remove_token_line('#:', $dir);
  }
}

function process__demo_mode($dir) {
  // Only discover demo mode if not explicitly set.
  if (is_null(get_config('DREVOPS_DEMO'))) {
    if (get_answer('fresh_install') == ANSWER_NO) {
      $download_source = get_answer('database_download_source');
      $db_file = getenv_or_default('DB_DIR', './.data') . DIRECTORY_SEPARATOR . getenv_or_default('DB_FILE', 'db.sql');
      $has_comment = file_contains('to allow to demonstrate how DrevOps works without', get_dst_dir() . '/.env');

      // Enable DrevOps demo mode if download source is file AND
      // there is no downloaded file present OR if there is a demo comment in
      // destination .env file.
      if ($download_source != 'docker_registry') {
        if ($has_comment || !file_exists($db_file)) {
          set_config('DREVOPS_DEMO', TRUE);
        }
        else {
          set_config('DREVOPS_DEMO', FALSE);
        }
      }
      elseif ($has_comment || $download_source == 'docker_registry') {
        set_config('DREVOPS_DEMO', TRUE);
      }
      else {
        set_config('DREVOPS_DEMO', FALSE);
      }
    }
    else {
      set_config('DREVOPS_DEMO', FALSE);
    }
  }

  if (!get_config('DREVOPS_DEMO')) {
    remove_token_with_content('DEMO', $dir);
  }
}

function process__preserve_drevops_info($dir) {
  if (get_answer('preserve_drevops_info') == ANSWER_NO) {
    // Remove code required for DrevOps maintenance.
    remove_token_with_content('DREVOPS', $dir);

    // Remove all other comments.
    remove_token_line('#;', $dir);
  }
}

function process__drevops_internal($dir) {
  // Remove DrevOps internal files.
  rmdir_recursive("$dir/scripts/drevops/docs");
  rmdir_recursive("$dir/scripts/drevops/tests");
  rmdir_recursive("$dir/scripts/drevops/utils");

  // Remove other unhandled tokenized comments.
  remove_token_line('#;<', $dir);
  remove_token_line('#;>', $dir);
}

function process__enable_commented_code($dir) {
  // Enable_commented_code.
  dir_replace_content('##### ', '', $dir);
}

// ////////////////////////////////////////////////////////////////////////// //
//                              DOWNLOADS                                     //
// ////////////////////////////////////////////////////////////////////////// //

/**
 * Download DrevOps source files.
 */
function download() {
  if (get_config('DREVOPS_LOCAL_REPO')) {
    download_local();
  }
  else {
    download_remote();
  }
}

function download_local() {
  $dst = get_config('DREVOPS_TMP_DIR');
  $repo = get_config('DREVOPS_LOCAL_REPO');
  $ref = get_config('DREVOPS_COMMIT');

  status(sprintf('Downloading DrevOps from the local repository "%s" at ref "%s".', $repo, $ref), INSTALLER_STATUS_MESSAGE, FALSE);

  $command = "git --git-dir=\"{$repo}/.git\" --work-tree=\"{$repo}\" archive --format=tar \"{$ref}\" | tar xf - -C \"{$dst}\"";
  do_exec($command, $output, $code);

  status(implode(PHP_EOL, $output), INSTALLER_STATUS_DEBUG);

  if ($code != 0) {
    throw new \RuntimeException(implode(PHP_EOL, $output));
  }

  status(sprintf('Downloaded to "%s".', $dst), INSTALLER_STATUS_DEBUG);

  print ' ';
  status('Done', INSTALLER_STATUS_SUCCESS);
}

function download_remote() {
  $dst = get_config('DREVOPS_TMP_DIR');
  $org = 'richardgaunt';
  $project = 'drevops';
  $ref = get_config('DREVOPS_COMMIT');
  $release_prefix = get_config('DREVOPS_VERSION');

  if ($ref == 'HEAD') {
    $ref = find_latest_drevops_release($org, $project, $release_prefix);
  }

  $url = "https://github.com/{$org}/{$project}/archive/${ref}.tar.gz";
  status(sprintf('Downloading DrevOps from the remote repository "%s" at ref "%s".', $url, $ref));
  do_exec("curl -sS -L \"$url\" | tar xzf - -C \"${dst}\" --strip 1", $output, $code);

  if ($code != 0) {
    throw new \RuntimeException(implode(PHP_EOL, $output));
  }

  status(sprintf('Downloaded to "%s".', $dst), INSTALLER_STATUS_DEBUG);

  status('Done', INSTALLER_STATUS_SUCCESS);
}

function find_latest_drevops_release($org, $project, $release_prefix) {
  $release_url = "https://api.github.com/repos/{$org}/{$project}/releases";
  $release_contents = file_get_contents($release_url, FALSE, stream_context_create([
    'http' => ['method' => 'GET', 'header' => ['User-Agent: PHP']],
  ]));

  if (!$release_contents) {
    throw new \RuntimeException(sprintf('Unable to download release information from "%s".', $release_url));
  }

  $records = json_decode($release_contents, TRUE);
  foreach ($records as $record) {
    if (isset($record['tag_name']) && strpos($record['tag_name'], $release_prefix) === 0) {
      return $record['tag_name'];
    }
  }
}

// ////////////////////////////////////////////////////////////////////////// //
//                          QUESTIONS AND ANSWERS                             //
// ////////////////////////////////////////////////////////////////////////// //

/**
 * Gather answers.
 *
 * This is how the values pipeline works for a variable:
 * 1. Read from .env
 * 2. Read from environment
 * 3. Read from user: default->discovered->answer->normalisation->save answer
 * 4. Use answers for processing, including writing values into correct
 *    variables in .env.
 */
function gather_answers() {
  // @formatter:off
  // phpcs:disable Generic.Functions.FunctionCallArgumentSpacing.TooMuchSpaceAfterComma
  // phpcs:disable Drupal.WhiteSpace.Comma.TooManySpaces
  ask_for_answer('name',              'What is your site name?');
  ask_for_answer('machine_name',      'What is your site machine name?');
  ask_for_answer('org',               'What is your organization name');
  ask_for_answer('org_machine_name',  'What is your organization machine name?');
  ask_for_answer('module_prefix',     'What is your project-specific module prefix?');
  ask_for_answer('profile',           'What is your custom profile machine name (leave empty to not use profile)?');
  ask_for_answer('theme',             'What is your theme machine name?');
  ask_for_answer('url',               'What is your site public URL?');

  ask_for_answer('fresh_install',     'Do you want to use fresh Drupal installation for every build?');

  if (get_answer('fresh_install') == ANSWER_YES) {
    set_answer('database_download_source', 'none');
    set_answer('database_image', '');
  }
  else {
    ask_for_answer('database_download_source', "When developing locally, where do you download the database dump from:\n  - [u]rl\n  - [f]tp\n  - [a]cquia backup\n  - [d]ocker registry?");

    if (get_answer('database_download_source') != 'docker_registry') {
      // Note that "database_store_type" is a pseudo-answer - it is only used to
      // improve UX and is not exposed as a variable (although has default,
      // discovery and normalisation callbacks).
      ask_for_answer('database_store_type',    '  When developing locally, do you want to import the database dump from the [f]ile or store it imported in the [d]ocker image for faster builds?');
    }

    if (get_answer('database_store_type') == 'file') {
      set_answer('database_image', '');
    }
    else {
      ask_for_answer('database_image',         '  What is your database Docker image name and a tag (e.g. drevops/drevops-mariadb-drupal-data:latest)?');
    }
  }
  // @formatter:on
  // phpcs:enable Generic.Functions.FunctionCallArgumentSpacing.TooMuchSpaceAfterComma
  // phpcs:enable Drupal.WhiteSpace.Comma.TooManySpaces

  ask_for_answer('deploy_type', 'How do you deploy your code to the hosting ([w]ebhook notification, [c]ode artifact, [d]ocker image, [l]agoon, [n]one as a comma-separated list)?');

  if (get_answer('database_download_source') != 'ftp') {
    ask_for_answer('preserve_ftp', 'Do you want to keep FTP integration?');
  }
  else {
    set_answer('preserve_ftp', ANSWER_YES);
  }

  if (get_answer('database_download_source') != 'acquia') {
    ask_for_answer('preserve_acquia', 'Do you want to keep Acquia Cloud integration?');
  }
  else {
    set_answer('preserve_acquia', ANSWER_YES);
  }

  ask_for_answer('preserve_lagoon', 'Do you want to keep Amazee.io Lagoon integration?');
  ask_for_answer('preserve_dependenciesio', 'Do you want to keep dependencies.io integration?');

  ask_for_answer('preserve_doc_comments', 'Do you want to keep detailed documentation in comments?');
  ask_for_answer('preserve_drevops_info', 'Do you want to keep all DrevOps information?');

  print_summary();

  if (is_install_debug()) {
    print_box(format_values_list(get_answers(), '', 80 - 6), 'DEBUG RESOLVED ANSWERS');
  }
}

function ask_should_proceed() {
  $proceed = ANSWER_YES;

  if (!is_quiet()) {
    $proceed = ask(sprintf('Proceed with installing DrevOps into your project\'s directory "%s"? (Y,n)', get_dst_dir()), $proceed);
  }

  // Kill-switch to not proceed with install. If false, the install will not
  // proceed despite the answer received above.
  if (!get_config('DREVOPS_PROCEED')) {
    $proceed = ANSWER_NO;
  }

  return strtolower($proceed) == ANSWER_YES;
}

function ask_for_answer($name, $question) {
  $default = get_default_value($name);
  $discovered = discover_value($name);
  $suggested = !empty($discovered) ? $discovered : $default;
  $answer = ask($question, $suggested);
  $answer = normalise_answer($name, $answer);

  set_answer($name, $answer);
}

function ask($question, $default) {
  if (is_quiet()) {
    return $default;
  }

  $question = "> $question [$default] ";

  out($question, 'question', FALSE);
  $handle = get_stdin_handle();
  $answer = trim(fgets($handle));

  return !empty($answer) ? $answer : $default;
}

// ////////////////////////////////////////////////////////////////////////// //
//                              CONFIG                                        //
// ////////////////////////////////////////////////////////////////////////// //

/**
 * Get configuration.
 */
function get_config($name, $default = NULL) {
  global $_config;

  return isset($_config[$name]) ? $_config[$name] : $default;
}

function set_config($name, $value) {
  global $_config;

  if (!is_null($value)) {
    $_config[$name] = $value;
  }
}

function get_configs() {
  global $_config;
  return $_config;
}

function get_answer($name, $default = NULL) {
  global $_answers;
  return $_answers[$name] ?? $default;
}

function set_answer($name, $value) {
  global $_answers;
  $_answers[$name] = $value;
}

function get_answers() {
  global $_answers;
  return $_answers;
}

/**
 * Initialise CLI options.
 */
function init_cli_args_and_options($argv) {
  $opts = [
    'help' => 'h',
    'quiet' => 'q',
    'no-ansi' => 'n',
  ];

  $options = getopt(implode('', $opts), array_keys($opts), $optind);

  foreach ($opts as $longopt => $shortopt) {
    $options[$longopt] = isset($options[$shortopt]) || isset($options[$longopt]);
    unset($options[$shortopt]);
  }

  $pos_args = array_slice($argv, $optind);
  $pos_args = array_filter($pos_args);

  if (!empty($options['quiet'])) {
    set_config('quiet', TRUE);
  }

  if (!empty($options['no-ansi'])) {
    set_config('ANSI', FALSE);
  }
  else {
    // On Windows, default to no ANSI, except in ANSICON and ConEmu.
    // Everywhere else, default to ANSI if stdout is a terminal.
    $is_ansi = (DIRECTORY_SEPARATOR == '\\')
      ? (FALSE !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI'))
      : (function_exists('posix_isatty') && posix_isatty(1));
    set_config('ANSI', $is_ansi);
  }

  if (!empty($options['help']) || in_array('help', $pos_args)) {
    set_config('help', TRUE);
    return;
  }

  // Show help if more arguments provided than expected.
  if (count($pos_args) > 1) {
    set_config('help', TRUE);
    return;
  }

  if (!empty($pos_args)) {
    set_config('DST_DIR', reset($pos_args));
  }
  else {
    set_config('DST_DIR', getenv_or_default('DST_DIR', CUR_DIR));
  }
}

/**
 * Instantiate install configuration from environment variables.
 *
 * Install configuration is a set of internal install script variables, read
 * from the environment variables. These environment variables are not read
 * directly in any operations of this installation script. Instead, these
 * environment variables are accessible with get_config().
 *
 * For simplicity of naming, internal install config variables are matching
 * environment variables names.
 */
function init_config() {
  // Internal version of DrevOps.
  set_config('DREVOPS_VERSION', getenv_or_default('DREVOPS_VERSION', INSTALLER_DRUPAL_VERSION . '.x'));
  // Flag to display install debug information.
  set_config('DREVOPS_INSTALL_DEBUG', (bool) getenv_or_default('DREVOPS_INSTALL_DEBUG', FALSE));
  // Flag to proceed with installation. If FALSE - the installation will only
  // print resolved values and will not proceed.
  set_config('DREVOPS_PROCEED', (bool) getenv_or_default('DREVOPS_PROCEED', TRUE));
  // Temporary directory to download and expand files to.
  set_config('DREVOPS_TMP_DIR', getenv_or_default('DREVOPS_TMP_DIR', tempdir()));
  // Path to local DrevOps repository. If not provided - remote will be used.
  set_config('DREVOPS_LOCAL_REPO', getenv_or_default('DREVOPS_LOCAL_REPO'));
  // Optional commit to download. If not provided, latest release will be
  // downloaded.
  set_config('DREVOPS_COMMIT', getenv_or_default('DREVOPS_COMMIT', 'HEAD'));

  // Internal flag to enforce DEMO mode. If not set, the demo mode will be
  // discovered automatically.
  if (!is_null(getenv_or_default('DREVOPS_DEMO'))) {
    set_config('DREVOPS_DEMO', (bool) getenv_or_default('DREVOPS_DEMO'));
  }
  // Internal flag to skip processing of the demo mode.
  set_config('DREVOPS_SKIP_DEMO', (bool) getenv_or_default('DREVOPS_SKIP_DEMO', FALSE));
}

function get_dst_dir() {
  return get_config('DST_DIR');
}

/**
 * Shorthand to get the value of DREVOPS_IS_INTERACTIVE.
 */
function is_quiet() {
  return get_config('quiet');
}

/**
 * Shorthand to get the value of DREVOPS_IS_INTERACTIVE.
 */
function is_install_debug() {
  return get_config('DREVOPS_INSTALL_DEBUG');
}

// ////////////////////////////////////////////////////////////////////////// //
//                        DEFAULT VALUE CALLBACKS                             //
// ////////////////////////////////////////////////////////////////////////// //

/**
 * Get default value router.
 */
function get_default_value($name) {
  // Allow to override default values from config variables.
  $config_name = strtoupper($name);
  return get_config($config_name, execute_callback('get_default_value', $name));
}

function get_default_value__name() {
  return getenv_or_default('PROJECT', basename(get_dst_dir()));
}

function get_default_value__machine_name() {
  return to_machine_name(get_answer('name'));
}

function get_default_value__org() {
  return get_answer('name') . ' Org';
}

function get_default_value__org_machine_name() {
  return to_machine_name(get_answer('org'));
}

function get_default_value__module_prefix() {
  return get_answer('machine_name');
}

function get_default_value__profile() {
  return ANSWER_NO;
}

function get_default_value__theme() {
  return get_answer('machine_name');
}

function get_default_value__url() {
  $value = get_answer('machine_name');
  $value = str_replace('_', '-', $value);
  $value .= '.com';

  return $value;
}

function get_default_value__fresh_install() {
  return ANSWER_NO;
}

function get_default_value__database_download_source() {
  return 'curl';
}

function get_default_value__database_store_type() {
  return 'file';
}

function get_default_value__database_image() {
  return 'drevops/mariadb-drupal-data:latest';
}

function get_default_value__deploy_type() {
  return 'code';
}

function get_default_value__preserve_acquia() {
  return ANSWER_NO;
}

function get_default_value__preserve_lagoon() {
  return ANSWER_NO;
}

function get_default_value__preserve_ftp() {
  return ANSWER_NO;
}

function get_default_value__preserve_dependenciesio() {
  return ANSWER_YES;
}

function get_default_value__preserve_doc_comments() {
  return ANSWER_YES;
}

function get_default_value__preserve_drevops_info() {
  return ANSWER_NO;
}

// ////////////////////////////////////////////////////////////////////////// //
//                        DISCOVERY VALUE CALLBACKS                           //
// ////////////////////////////////////////////////////////////////////////// //

/**
 * Discover value router.
 *
 * Value discoveries must return NULL if they do not have the resources
 * available to discover a value (i.e., NULL if value is from the expected file
 * but the file is not available, rather then a FALSEy value.)
 */
function discover_value($name) {
  return execute_callback('discover_value', $name);
}

function discover_value__name() {
  $value = get_composer_json_value('description');
  if ($value && preg_match('/Drupal [78] implementation of ([^"]+) for ([^"]+)/', $value, $matches)) {
    if (!empty($matches[1])) {
      return $matches[1];
    }
  }
  return NULL;
}

function discover_value__machine_name() {
  $value = get_composer_json_value('name');
  if ($value && preg_match('/([^\/]+)\/(.+)/', $value, $matches)) {
    if (!empty($matches[2])) {
      return $matches[2];
    }
  }
  return NULL;
}

function discover_value__org() {
  $value = get_composer_json_value('description');
  if ($value && preg_match('/Drupal [78] implementation of ([^"]+) for ([^"]+)/', $value, $matches)) {
    if (!empty($matches[2])) {
      return $matches[2];
    }
  }
  return NULL;
}

function discover_value__org_machine_name() {
  $value = get_composer_json_value('name');
  if ($value && preg_match('/([^\/]+)\/(.+)/', $value, $matches)) {
    if (!empty($matches[1])) {
      return $matches[1];
    }
  }
  return NULL;
}

function discover_value__module_prefix() {
  $locations = [
    get_dst_dir() . '/docroot/modules/custom/*_core',
    get_dst_dir() . '/docroot/sites/all/modules/custom/*_core',
    get_dst_dir() . '/docroot/profiles/*/modules/*_core',
    get_dst_dir() . '/docroot/profiles/*/modules/custom/*_core',
    get_dst_dir() . '/docroot/profiles/custom/*/modules/*_core',
    get_dst_dir() . '/docroot/profiles/custom/*/modules/custom/*_core',
  ];

  $name = find_matching_path($locations);

  if (empty($name)) {
    return NULL;
  }

  if ($name) {
    $name = basename($name);
    $name = str_replace('_core', '', $name);
  }

  return $name;
}

function discover_value__profile() {
  $locations = [
    get_dst_dir() . '/docroot/profiles/*/*.info',
    get_dst_dir() . '/docroot/profiles/*/*.info.yml',
    get_dst_dir() . '/docroot/profiles/custom/*/*.info',
    get_dst_dir() . '/docroot/profiles/custom/*/*.info.yml',
  ];

  $name = find_matching_path($locations, 'Drupal 8 profile implementation of');

  if (empty($name)) {
    return NULL;
  }

  if ($name) {
    $name = basename($name);
    $name = str_replace(['.info.yml', '.info'], '', $name);
  }

  return $name;
}

function discover_value__theme() {
  $locations = [
    get_dst_dir() . '/docroot/themes/custom/*/*.info',
    get_dst_dir() . '/docroot/themes/custom/*/*.info.yml',
    get_dst_dir() . '/docroot/sites/all/themes/custom/*/*.info',
    get_dst_dir() . '/docroot/sites/all/themes/custom/*/*.info.yml',
    get_dst_dir() . '/docroot/profiles/*/themes/custom/*/*.info',
    get_dst_dir() . '/docroot/profiles/*/themes/custom/*/*.info.yml',
    get_dst_dir() . '/docroot/profiles/custom/*/themes/custom/*/*.info',
    get_dst_dir() . '/docroot/profiles/custom/*/themes/custom/*/*.info.yml',
  ];

  $name = find_matching_path($locations);

  if (empty($name)) {
    return NULL;
  }

  if ($name) {
    $name = basename($name);
    $name = str_replace(['.info.yml', '.info'], '', $name);
  }

  return $name;
}

function discover_value__url() {
  $origin = NULL;
  $path = get_dst_dir() . '/docroot/sites/default/settings.php';

  if (!is_readable($path)) {
    return NULL;
  }

  $contents = file_get_contents($path);

  // Drupal 8.
  if (preg_match('/\$config\s*\[\'stage_file_proxy.settings\'\]\s*\[\'origin\'\]\s*=\s*[\'"]([^\'"]+)[\'"];/', $contents, $matches)) {
    if (!empty($matches[1])) {
      $origin = $matches[1];
    }
  }
  // Drupal 7.
  elseif (preg_match('/\$conf\s*\[\'stage_file_proxy_origin\'\]\s*=\s*[\'"]([^\'"]+)[\'"];/', $contents, $matches)) {
    if (!empty($matches[1])) {
      $origin = $matches[1];
    }
  }
  if ($origin) {
    $origin = parse_url($origin, PHP_URL_HOST);
  }

  return !empty($origin) ? $origin : NULL;
}

function discover_value__fresh_install() {
  $file = get_dst_dir() . '/.ahoy.yml';
  if (!file_exists($file)) {
    return NULL;
  }
  $found = file_contains('download-db:', $file);
  return $found ? ANSWER_NO : ANSWER_YES;
}

function discover_value__database_download_source() {
  return get_value_from_dst_dotenv('DATABASE_DOWNLOAD_SOURCE');
}

function discover_value__database_store_type() {
  return discover_value__database_image() ? 'docker_image' : 'file';
}

function discover_value__database_image() {
  return get_value_from_dst_dotenv('DATABASE_IMAGE');
}

function discover_value__deploy_type() {
  return get_value_from_dst_dotenv('DEPLOY_TYPE');
}

function discover_value__preserve_acquia() {
  if (is_readable(get_dst_dir() . '/hooks')) {
    return ANSWER_YES;
  }
  $value = get_value_from_dst_dotenv('DATABASE_DOWNLOAD_SOURCE');

  if (is_null($value)) {
    return NULL;
  }

  return $value == 'acquia' ? ANSWER_YES : ANSWER_NO;
}

function discover_value__preserve_lagoon() {
  if (is_readable(get_dst_dir() . '/.lagoon.yml')) {
    return ANSWER_YES;
  }

  $value = get_value_from_dst_dotenv('LAGOON_PROJECT');

  // Special case - only work with non-empty value as 'LAGOON_PROJECT' may not
  // exist in installed site's .env file.
  if (empty($value)) {
    return NULL;
  }

  return ANSWER_YES;
}

function discover_value__preserve_ftp() {
  $value = get_value_from_dst_dotenv('DATABASE_DOWNLOAD_SOURCE');
  if (is_null($value)) {
    return NULL;
  }

  return $value == 'ftp' ? ANSWER_YES : ANSWER_NO;
}

function discover_value__preserve_dependenciesio() {
  if (!is_installed()) {
    return NULL;
  }
  return is_readable(get_dst_dir() . '/dependencies.yml') ? ANSWER_YES : ANSWER_NO;
}

function discover_value__preserve_doc_comments() {
  $file = get_dst_dir() . '/.ahoy.yml';
  if (!is_readable($file)) {
    return NULL;
  }
  return file_contains('Ahoy configuration file', $file) ? ANSWER_YES : ANSWER_NO;
}

function discover_value__preserve_drevops_info() {
  $file = get_dst_dir() . '/.ahoy.yml';
  if (!is_readable($file)) {
    return NULL;
  }
  return file_contains('Comments starting with', $file) ? ANSWER_YES : ANSWER_NO;
}

function get_value_from_dst_dotenv($name, $default = NULL) {
  if (!is_null(getenv($name))) {
    return getenv($name);
  }
  $file = get_dst_dir() . '/.env';
  if (!is_readable($file)) {
    return $default;
  }
  $parsed = parse_dotenv($file);
  return $parsed ? $parsed[$name] ?? $default : $default;
}

function find_matching_path($paths, $text = NULL) {
  $paths = is_array($paths) ? $paths : [$paths];

  foreach ($paths as $path) {
    $files = glob($path);
    if (empty($files)) {
      continue;
    }

    if (count($files)) {
      if (!empty($text)) {
        foreach ($files as $file) {
          if (file_contains($text, $file)) {
            return $file;
          }
        }
      }
      else {
        return reset($files);
      }
    }
  }
  return NULL;
}

/**
 * Check that DrevOps is installed for this project.
 */
function is_installed() {
  $path = get_dst_dir() . DIRECTORY_SEPARATOR . 'README.md';
  return file_exists($path) && preg_match('/badge\/DrevOps\-/', file_get_contents($path));
}

// ////////////////////////////////////////////////////////////////////////// //
//                       NORMALISER VALUE CALLBACKS                           //
// ////////////////////////////////////////////////////////////////////////// //

/**
 * Normalisation router.
 */
function normalise_answer($name, $value) {
  $normalised = execute_callback('normalise_answer', $name, $value);
  return $normalised ?? $value;
}

function normalise_answer__name($value) {
  return ucfirst(to_human_name($value));
}

function normalise_answer__machine_name($value) {
  return to_machine_name($value);
}

function normalise_answer__org_machine_name($value) {
  return to_machine_name($value);
}

function normalise_answer__module_prefix($value) {
  return to_machine_name($value);
}

function normalise_answer__profile($value) {
  $profile = to_machine_name($value);
  if (empty($profile) || strtolower($profile) == ANSWER_NO) {
    $profile = 'standard';
  }

  return $profile;
}

function normalise_answer__theme($value) {
  return to_machine_name($value);
}

function normalise_answer__url($url) {
  return str_replace([' ', '_'], '-', $url);
}

function normalise_answer__fresh_install($value) {
  return strtolower($value) != ANSWER_YES ? ANSWER_NO : ANSWER_YES;
}

function normalise_answer__database_download_source($value) {
  $value = strtolower($value);

  switch ($value) {
    case 'f':
    case 'ftp':
      return 'ftp';

    case 'a':
    case 'acquia':
      return 'acquia';

    case 'i':
    case 'd':
    case 'image':
    case 'docker':
    case 'docker_image':
    case 'docker_registry':
      return 'docker_registry';

    case 'c':
    case 'curl':
      return 'curl';

    default:
      return get_default_value__database_download_source();
  }
}

function normalise_answer__database_store_type($value) {
  $value = strtolower($value);

  switch ($value) {
    case 'i':
    case 'd':
    case 'image':
    case 'docker_image':
    case 'docker':
      return 'docker_image';

    case 'f':
    case 'file':
      return 'file';

    default:
      return get_default_value__database_store_type();
  }
}

function normalise_answer__database_image($value) {
  $value = to_machine_name($value, ['-', '/', ':', '.']);
  return strpos($value, ':') !== FALSE ? $value : $value . ':latest';
}

function normalise_answer__deploy_type($value) {
  $types = explode(',', $value);

  $normalised = [];
  foreach ($types as $type) {
    $type = trim($type);
    switch ($type) {
      case 'w':
      case 'webhook':
        $normalised[] = 'webhook';
        break;

      case 'c':
      case 'code':
        $normalised[] = 'code';
        break;

      case 'd':
      case 'docker':
        $normalised[] = 'docker';
        break;

      case 'l':
      case 'lagoon':
        $normalised[] = 'lagoon';
        break;

      case 'n':
      case 'none':
        $normalised[] = 'none';
        break;
    }
  }

  if (in_array('none', $normalised)) {
    return NULL;
  }

  $normalised = array_unique($normalised);

  return implode(',', $normalised);
}

function normalise_answer__preserve_acquia($value) {
  return strtolower($value) != ANSWER_YES ? ANSWER_NO : ANSWER_YES;
}

function normalise_answer__preserve_lagoon($value) {
  return strtolower($value) != ANSWER_YES ? ANSWER_NO : ANSWER_YES;
}

function normalise_answer__preserve_ftp($value) {
  return strtolower($value) != ANSWER_YES ? ANSWER_NO : ANSWER_YES;
}

function normalise_answer__preserve_dependenciesio($value) {
  return strtolower($value) != ANSWER_YES ? ANSWER_NO : ANSWER_YES;
}

function normalise_answer__preserve_doc_comments($value) {
  return strtolower($value) != ANSWER_YES ? ANSWER_NO : ANSWER_YES;
}

function normalise_answer__preserve_drevops_info($value) {
  return strtolower($value) != ANSWER_YES ? ANSWER_NO : ANSWER_YES;
}

// ////////////////////////////////////////////////////////////////////////// //
//                          INFORMATION SCREENS                               //
// ////////////////////////////////////////////////////////////////////////// //

/**
 * Print help.
 */
function print_help() {
  print <<<EOF
DrevOps Installer
------------------
Arguments
  destination          Destination directory. Optional. Defaults to the current
                       directory.

Options
  --help               This help.
  --quiet              Quiet installation.
EOF;
  print PHP_EOL;
}

function print_header() {
  if (is_quiet()) {
    print_header_quiet();
  }
  else {
    print_header_interactive();
  }
  print PHP_EOL;
}

function print_header_interactive() {
  $commit = get_config('DREVOPS_COMMIT');

  $content = '';
  if ($commit == 'HEAD') {
    $content .= 'This will install the latest version of DrevOps into your project.' . PHP_EOL;
  }
  else {
    $content .= "This will install DrevOps into your project at commit \"$commit\"." . PHP_EOL;
  }
  $content .= PHP_EOL;
  if (is_installed()) {
    $content .= 'It looks like DrevOps is already installed into this project.' . PHP_EOL;
    $content .= PHP_EOL;
  }
  $content .= 'Please answer the questions below to install configuration relevant to your site.' . PHP_EOL;
  $content .= 'No changes will be applied until the last confirmation step.' . PHP_EOL;
  $content .= PHP_EOL;
  $content .= 'Existing committed files will be modified. You will need to resolve changes manually.' . PHP_EOL;
  $content .= PHP_EOL;
  $content .= 'Press Ctrl+C at any time to exit this installer.' . PHP_EOL;

  print_box($content, 'WELCOME TO DREVOPS INTERACTIVE INSTALLER');
}

function print_header_quiet() {
  $commit = get_config('DREVOPS_COMMIT');

  $content = '';
  if ($commit == 'HEAD') {
    $content .= 'This will install the latest version of DrevOps into your project.' . PHP_EOL;
  }
  else {
    $content .= "This will install DrevOps into your project at commit \"$commit\"." . PHP_EOL;
  }
  $content .= PHP_EOL;
  if (is_installed()) {
    $content .= 'It looks like DrevOps is already installed into this project.' . PHP_EOL;
    $content .= PHP_EOL;
  }
  $content .= 'DrevOps installer will try to discover the settings from the environment and will install configuration relevant to your site.' . PHP_EOL;
  $content .= PHP_EOL;
  $content .= 'Existing committed files will be modified. You will need to resolve changes manually.' . PHP_EOL;

  print_box($content, 'WELCOME TO DREVOPS QUIET INSTALLER');
}

function print_summary() {
  $values['Current directory'] = CUR_DIR;
  $values['Destination directory'] = get_dst_dir();
  $values['Drupal version'] = getenv_or_default('DRUPAL_VERSION', INSTALLER_DRUPAL_VERSION);
  $values['DrevOps version'] = get_config('DREVOPS_VERSION');
  $values['DrevOps commit'] = format_not_empty(get_config('DREVOPS_COMMIT'), 'Latest');

  $values[] = '';
  $values[] = str_repeat('*', 80 - 2 - 2 * 2);
  $values[] = '';

  $values['Name'] = get_answer('name');
  $values['Machine name'] = get_answer('machine_name');
  $values['Organisation'] = get_answer('org');
  $values['Organisation machine name'] = get_answer('org_machine_name');
  $values['Module prefix'] = get_answer('module_prefix');
  $values['Profile'] = get_answer('profile');
  $values['Theme name'] = get_answer('theme');
  $values['URL'] = get_answer('url');

  if (get_answer('fresh_install') == ANSWER_YES) {
    $values['Fresh install for every build'] = format_yes_no(get_answer('fresh_install'));
  }
  else {
    $values['Database download source'] = get_answer('database_download_source');
    $image = get_answer('database_image');
    $values['Database store type'] = !empty($image) ? 'docker_image' : 'file';
    if ($image) {
      $values['Database image name'] = $image;
    }
  }

  $values['Deployment'] = format_not_empty(get_answer('deploy_type'), 'Disabled');
  $values['FTP integration'] = format_enabled(get_answer('preserve_ftp'));
  $values['Acquia integration'] = format_enabled(get_answer('preserve_acquia'));
  $values['Lagoon integration'] = format_enabled(get_answer('preserve_lagoon'));
  $values['dependencies.io integration'] = format_enabled(get_answer('preserve_dependenciesio'));
  $values['Preserve docs in comments'] = format_yes_no(get_answer('preserve_doc_comments'));
  $values['Preserve DrevOps comments'] = format_yes_no(get_answer('preserve_drevops_info'));

  $content = format_values_list($values, '', 80 - 2 - 2 * 2);

  print_box($content, 'INSTALLATION SUMMARY');
}

function print_abort() {
  print_box('Aborting project installation. No files were changed.');
}

function print_footer() {
  print PHP_EOL;
  status('Finished installing DrevOps. Review changes and commit required files.', INSTALLER_STATUS_SUCCESS);
}

function print_title($text, $fill = '*', $width = 80) {
  print_divider($fill, $width);
  $lines = explode(PHP_EOL, wordwrap($text, $width - 4, PHP_EOL));
  foreach ($lines as $line) {
    $line = ' ' . $line . ' ';
    print $fill . str_pad($line, $width - 2, ' ', STR_PAD_BOTH) . $fill . PHP_EOL;
  }
  print_divider($fill, $width);
}

function print_subtitle($text, $fill = '=', $width = 80) {
  $is_multiline = strlen($text) + 4 >= $width;
  if ($is_multiline) {
    print_title($text, $fill, $width);
  }
  else {
    $text = ' ' . $text . ' ';
    print str_pad($text, $width, $fill, STR_PAD_BOTH) . PHP_EOL;
  }
}

function print_divider($fill = '=', $width = 80) {
  print str_repeat($fill, $width) . PHP_EOL;
}

function print_box($content, $title = '', $fill = '*', $padding = 2, $width = 80) {
  $max_width = $width - 2 - $padding * 2;
  $lines = explode(PHP_EOL, wordwrap(rtrim($content, PHP_EOL), $max_width, PHP_EOL));
  $pad = str_pad(' ', $padding);
  $mask = "{$fill}{$pad}%-{$max_width}s{$pad}{$fill}" . PHP_EOL;

  print PHP_EOL;
  if (!empty($title)) {
    print_title($title, $fill, $width);
  }
  else {
    print_divider($fill, $width);
  }

  array_unshift($lines, '');
  $lines[] = '';
  foreach ($lines as $line) {
    printf($mask, $line);
  }

  print_divider($fill, $width);
  print PHP_EOL;
}

function print_tick($text = NULL) {
  if (!empty($text) && is_install_debug()) {
    print PHP_EOL;
    status($text, INSTALLER_STATUS_DEBUG, FALSE);
  }
  else {
    print status('.', INSTALLER_STATUS_MESSAGE, FALSE, FALSE);
  }
}

function format_values_list($values, $delim = '', $width = 80) {
  // Line width - length of delimiters * 2 - 2 spacers.
  $line_width = $width - strlen($delim) * 2 - 2;

  // Max name length + spaced on the sides + colon.
  $max_name_width = max(array_map('strlen', array_keys($values))) + 2 + 1;

  // Whole width - (name width + 2 delimiters on the sides + 1 delimiter in
  // the middle + 2 spaces on the sides  + 2 spaces for the center delimiter).
  $value_width = $width - ($max_name_width + strlen($delim) * 2 + strlen($delim) + 2 + 2);

  $mask1 = "{$delim} %{$max_name_width}s {$delim} %-{$value_width}.{$value_width}s {$delim}" . PHP_EOL;
  $mask2 = "{$delim}%2\${$line_width}s{$delim}" . PHP_EOL;

  $output = [];
  foreach ($values as $name => $value) {
    $is_multiline_value = strlen($value) > $value_width;

    if (is_numeric($name)) {
      $name = '';
      $mask = $mask2;
      $is_multiline_value = FALSE;
    }
    else {
      $name .= ':';
      $mask = $mask1;
    }

    if ($is_multiline_value) {
      $lines = array_filter(explode(PHP_EOL, chunk_split($value, $value_width, PHP_EOL)));
      $first_line = array_shift($lines);
      $output[] = sprintf($mask, $name, $first_line);
      foreach ($lines as $line) {
        $output[] = sprintf($mask, '', $line);
      }
    }
    else {
      $output[] = sprintf($mask, $name, $value);
    }
  }
  return implode('', $output);
}

function format_enabled($value) {
  return $value && strtolower($value) != 'n' ? 'Enabled' : 'Disabled';
}

function format_yes_no($value) {
  return $value == ANSWER_YES ? 'Yes' : 'No';
}

function format_not_empty($value, $default) {
  return !empty($value) ? $value : $default;
}

// ////////////////////////////////////////////////////////////////////////// //
//                        STRING MANIPULATORS                                 //
// ////////////////////////////////////////////////////////////////////////// //

/**
 * Check if file contains a string.
 */
function file_contains($needle, $file) {
  if (!is_readable($file)) {
    return FALSE;
  }

  $content = file_get_contents($file);

  if (is_regex($needle)) {
    return preg_match($needle, $content);
  }

  return strpos($content, $needle) !== FALSE;
}

function dir_contains($needle, $dir) {
  $files = scandir_recursive($dir, ignore_paths());
  foreach ($files as $filename) {
    if (file_contains($needle, $filename)) {
      return TRUE;
    }
  }
  return FALSE;
}

function is_regex($str) {
  if (preg_match('/^(.{3,}?)[imsxuADU]*$/', $str, $m)) {
    $start = substr($m[1], 0, 1);
    $end = substr($m[1], -1);

    if ($start === $end) {
      return !preg_match('/[*?[:alnum:] \\\\]/', $start);
    }

    foreach ([['{', '}'], ['(', ')'], ['[', ']'], ['<', '>']] as $delimiters) {
      if ($start === $delimiters[0] && $end === $delimiters[1]) {
        return TRUE;
      }
    }
  }

  return FALSE;
}

function file_replace_content($needle, $replacement, $filename) {
  if (!is_readable($filename) || file_is_excluded_from_processing($filename)) {
    return FALSE;
  }

  $content = file_get_contents($filename);

  if (is_regex($needle)) {
    $replaced = preg_replace($needle, $replacement, $content);
  }
  else {
    $replaced = str_replace($needle, $replacement, $content);
  }
  if ($replaced != $content) {
    file_put_contents($filename, $replaced);
  }
}

function dir_replace_content($needle, $replacement, $dir) {
  $files = scandir_recursive($dir, ignore_paths());
  foreach ($files as $filename) {
    file_replace_content($needle, $replacement, $filename);
  }
}

function remove_token_with_content($token, $dir) {
  $files = scandir_recursive($dir, ignore_paths());
  foreach ($files as $filename) {
    remove_token_from_file($filename, "#;< $token", "#;> $token", TRUE);
  }
}

function remove_token_line($token, $dir) {
  if (!empty($token)) {
    $files = scandir_recursive($dir, ignore_paths());
    foreach ($files as $filename) {
      remove_token_from_file($filename, $token);
    }
  }
}

function remove_token_from_file($filename, $token_begin, $token_end = NULL, $with_content = FALSE) {
  if (file_is_excluded_from_processing($filename)) {
    return;
  }

  $token_end = $token_end ?? $token_begin;

  $content = file_get_contents($filename);

  if ($token_begin != $token_end) {
    $token_begin_count = preg_match_all('/' . preg_quote($token_begin) . '/', $content);
    $token_end_count = preg_match_all('/' . preg_quote($token_end) . '/', $content);
    if ($token_begin_count != $token_end_count) {
      throw new \RuntimeException(sprintf('Invalid begin and end token count in file %s: begin is %s(%s), end is %s(%s).', $filename, $token_begin, $token_begin_count, $token_end, $token_end_count));
    }
  }

  $out = [];
  $within_token = FALSE;

  $lines = file($filename);
  foreach ($lines as $line) {
    if (strpos($line, $token_begin) !== FALSE) {
      if ($with_content) {
        $within_token = TRUE;
      }
      continue;
    }
    elseif (strpos($line, $token_end) !== FALSE) {
      if ($with_content) {
        $within_token = FALSE;
      }
      continue;
    }

    if ($with_content && $within_token) {
      // Skip content as contents of the token.
      continue;
    }

    $out[] = $line;
  }

  file_put_contents($filename, implode('', $out));
}

function replace_string_filename($search, $replace, $dir) {
  $files = scandir_recursive($dir, ignore_paths());
  foreach ($files as $filename) {
    $new_filename = str_replace($search, $replace, $filename);
    if ($filename != $new_filename) {
      $new_dir = dirname($new_filename);
      if (!is_dir($new_dir)) {
        mkdir($new_dir, 0777, TRUE);
      }
      rename($filename, $new_filename);
    }
  }
}

function scandir_recursive($dir, $ignore_paths = [], $include_dirs = FALSE) {
  $discovered = [];

  if (is_dir($dir)) {
    $paths = array_diff(scandir($dir), ['.', '..']);
    foreach ($paths as $k => $path) {
      $path = $dir . '/' . $path;
      foreach ($ignore_paths as $ignore_path) {
        // Exlude based on sub-path match.
        if (strpos($path, $ignore_path) !== FALSE) {
          continue(2);
        }
      }
      if (is_dir($path)) {
        if ($include_dirs) {
          $discovered[] = $path;
        }
        $discovered = array_merge($discovered, scandir_recursive($path, $ignore_paths, $include_dirs));
      }
      else {
        $discovered[] = $path;
      }
    }
  }

  return $discovered;
}

function glob_recursive($pattern, $flags = 0) {
  $files = glob($pattern, $flags | GLOB_BRACE);
  foreach (glob(dirname($pattern) . '/{,.}*[!.]', GLOB_BRACE | GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
    $files = array_merge($files, glob_recursive($dir . '/' . basename($pattern), $flags));
  }
  return $files;
}

function ignore_paths() {
  return array_merge([
    '/.git/',
    '/.idea/',
    '/vendor/',
    '/node_modules/',
    '/.data/',
  ], internal_paths());
}

function internal_paths() {
  return [
    '/install.sh',
    '/install.php',
    '/LICENSE',
    '/scripts/drevops/docs',
    '/scripts/drevops/tests',
    '/scripts/drevops/utils',
  ];
}

function is_internal_path($relative_path) {
  $relative_path = '/' . ltrim($relative_path, './');
  return in_array($relative_path, internal_paths());
}

function file_is_excluded_from_processing($filename) {
  $excluded_patterns = [
    '.+\.png',
    '.+\.jpg',
    '.+\.jpeg',
    '.+\.bpm',
    '.+\.tiff',
  ];

  return preg_match('/^(' . implode('|', $excluded_patterns) . ')$/', $filename);
}

// ////////////////////////////////////////////////////////////////////////// //
//                                HELPERS                                     //
// ////////////////////////////////////////////////////////////////////////// //

/**
 * Execute command wrapper.
 */
function do_exec($command, array &$output = NULL, &$return_var = NULL) {
  if (is_install_debug()) {
    status(sprintf('COMMAND: %s', $command), INSTALLER_STATUS_DEBUG);
  }
  $result = exec($command, $output, $return_var);
  if (is_install_debug()) {
    status(sprintf('  OUTPUT: %s', implode($output)), INSTALLER_STATUS_DEBUG);
    status(sprintf('  CODE  : %s', $return_var), INSTALLER_STATUS_DEBUG);
    status(sprintf('  RESULT: %s', $result), INSTALLER_STATUS_DEBUG);
  }
  return $result;
}

function rmdir_recursive($directory, $options = []) {
  if (!isset($options['traverseSymlinks'])) {
    $options['traverseSymlinks'] = FALSE;
  }
  $items = glob($directory . DIRECTORY_SEPARATOR . '{,.}*', GLOB_MARK | GLOB_BRACE);
  foreach ($items as $item) {
    if (basename($item) == '.' || basename($item) == '..') {
      continue;
    }
    if (substr($item, -1) == DIRECTORY_SEPARATOR) {
      if (!$options['traverseSymlinks'] && is_link(rtrim($item, DIRECTORY_SEPARATOR))) {
        unlink(rtrim($item, DIRECTORY_SEPARATOR));
      }
      else {
        rmdir_recursive($item, $options);
      }
    }
    else {
      unlink($item);
    }
  }
  if (is_dir($directory = rtrim($directory, '\\/'))) {
    if (is_link($directory)) {
      unlink($directory);
    }
    else {
      rmdir($directory);
    }
  }
}

function rmdir_recursive_empty($directory, $options = []) {
  if (dir_is_empty($directory)) {
    rmdir_recursive($directory, $options);
    rmdir_recursive_empty(dirname($directory), $options);
  }
}

function dir_is_empty($directory) {
  return is_dir($directory) && count(scandir($directory)) === 2;
}

function status($message, $level = INSTALLER_STATUS_MESSAGE, $eol = TRUE, $use_prefix = TRUE) {
  $prefix = '';
  $color = NULL;

  switch ($level) {
    case INSTALLER_STATUS_SUCCESS:
      $prefix = '✓️';
      $color = 'success';
      break;

    case INSTALLER_STATUS_ERROR:
      $prefix = '✗';
      $color = 'error';
      break;

    case INSTALLER_STATUS_MESSAGE:
      $prefix = 'i️';
      $color = 'info';
      break;

    case INSTALLER_STATUS_DEBUG:
      $prefix = '  [D]';
      break;
  }

  if ($level != INSTALLER_STATUS_DEBUG || is_install_debug()) {
    out(($use_prefix ? $prefix . ' ' : '') . $message, $color, $eol);
  }
}

function parse_dotenv($filename = '.env') {
  if (!is_readable($filename)) {
    return FALSE;
  }

  $contents = file_get_contents($filename);
  // Replace all # not inside quotes.
  $contents = preg_replace('/#(?=(?:(?:[^"]*"){2})*[^"]*$)/', ';', $contents);

  return parse_ini_string($contents);
}

function load_dotenv($filename = '.env', $override_existing = FALSE) {
  $parsed = parse_dotenv($filename);

  if ($parsed === FALSE) {
    return;
  }

  foreach ($parsed as $var => $value) {
    if (getenv($var) === FALSE || $override_existing) {
      putenv($var . '=' . $value);
    }
  }

  $GLOBALS['_ENV'] = $GLOBALS['_ENV'] ?? [];
  $GLOBALS['_SERVER'] = $GLOBALS['_SERVER'] ?? [];

  if ($override_existing) {
    $GLOBALS['_ENV'] = $parsed + $GLOBALS['_ENV'];
    $GLOBALS['_SERVER'] = $parsed + $GLOBALS['_SERVER'];
  }
  else {
    $GLOBALS['_ENV'] += $parsed;
    $GLOBALS['_SERVER'] += $parsed;
  }
}

function getenv_or_default($name, $default = NULL) {
  $vars = getenv();
  if (!isset($vars[$name]) || $vars[$name] == '') {
    return $default;
  }
  return $vars[$name];
}

/**
 * Creates a random unique temporary directory.
 */
function tempdir($dir = NULL, $prefix = 'tmp_', $mode = 0700, $max_attempts = 1000) {
  if (is_null($dir)) {
    $dir = sys_get_temp_dir();
  }

  $dir = rtrim($dir, DIRECTORY_SEPARATOR);

  if (!is_dir($dir) || !is_writable($dir)) {
    return FALSE;
  }

  if (strpbrk($prefix, '\\/:*?"<>|') !== FALSE) {
    return FALSE;
  }
  $attempts = 0;

  do {
    $path = sprintf('%s%s%s%s', $dir, DIRECTORY_SEPARATOR, $prefix, mt_rand(100000, mt_getrandmax()));
  } while (!mkdir($path, $mode) && $attempts++ < $max_attempts);

  if (!is_dir($path) || !is_writable($path)) {
    throw new \RuntimeException(sprintf('Unable to create temporary directory "%s".', $path));
  }

  return $path;
}

function command_exists($command) {
  do_exec("command -v $command", $lines, $ret);
  if ($ret === 1) {
    throw new \RuntimeException(sprintf('Command "%s" does not exist in the current environment.', $command));
  }
}

function to_human_name($value) {
  $value = preg_replace('/[^a-zA-Z0-9]/', ' ', $value);
  $value = trim($value);
  $value = preg_replace('/\s{2,}/', ' ', $value);
  return $value;
}

function to_machine_name($value, $preserve_chars = []) {
  $preserve = '';
  foreach ($preserve_chars as $char) {
    $preserve .= preg_quote($char, '/');
  }
  $pattern = '/[^a-zA-Z0-9' . $preserve . ']/';

  $value = preg_replace($pattern, '_', $value);
  $value = strtolower($value);
  return $value;
}

function to_camel_case($value, $capitalise_first = FALSE) {
  $value = str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z0-9]/', ' ', $value)));
  return $capitalise_first ? $value : lcfirst($value);
}

function execute_callback($prefix, $name) {
  $args = func_get_args();
  $args = array_slice($args, 2);

  $callback = $prefix . '__' . $name;
  if (function_exists($callback)) {
    return call_user_func_array($callback, $args);
  }

  return NULL;
}

function get_composer_json_value($name) {
  $composer_json = get_dst_dir() . DIRECTORY_SEPARATOR . 'composer.json';
  if (is_readable($composer_json)) {
    $json = json_decode(file_get_contents($composer_json), TRUE);
    if (isset($json[$name])) {
      return $json[$name];
    }
  }
  return NULL;
}

function get_stdin_handle() {
  global $_stdin_handle;
  if (!$_stdin_handle) {
    $_stdin_handle = fopen('php://stdin', 'r');
  }
  return $_stdin_handle;
}

function close_stdin_handle() {
  $_stdin_handle = get_stdin_handle();
  fclose($_stdin_handle);
}

function out($text, $color = NULL, $new_line = TRUE) {
  $styles = [
    'success' => "\033[0;32m%s\033[0m",
    'error' => "\033[31;31m%s\033[0m",
    'info' => "\033[33;33m%s\033[0m",
  ];

  $format = '%s';

  if (isset($styles[$color]) && get_config('ANSI')) {
    $format = $styles[$color];
  }

  if ($new_line) {
    $format .= PHP_EOL;
  }

  printf($format, $text);
}

function debug($value, $name = '') {
  print PHP_EOL;
  print trim($name . ' DEBUG START') . PHP_EOL;
  print print_r($value, TRUE) . PHP_EOL;
  print trim($name . ' DEBUG FINISH') . PHP_EOL;
  print PHP_EOL;
}

// ////////////////////////////////////////////////////////////////////////// //
//                                ENTRYPOINT                                  //
// ////////////////////////////////////////////////////////////////////////// //

ini_set('display_errors', 1);

if (PHP_SAPI != 'cli' || !empty($_SERVER['REMOTE_ADDR'])) {
  die('This script can be only ran from the command line.');
}

// Do not run this script if INSTALLER_SKIP_RUN is set. Useful when requiring
// this file from other scripts (e.g. for testing).
if (!getenv('INSTALLER_SKIP_RUN')) {
  try {
    $code = install($argv, $argc);
    if (is_null($code)) {
      throw new \Exception('Installer exited without providing an exit code.');
    }
    exit($code);
  }
  catch (\RuntimeException $exception) {
    status($exception->getMessage(), INSTALLER_STATUS_ERROR);
    exit($exception->getCode() == 0 ? INSTALLER_EXIT_ERROR : $exception->getCode());
  }
}
