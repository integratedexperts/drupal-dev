<?php

/**
 * @file
 * Drupal settings.
 *
 * Create settings.local.php file to include local settings overrides.
 */

// Environment constants.
// Use these constants anywhere in code to alter behaviour for specific
// environment.
if (!defined('ENVIRONMENT_LOCAL')) {
  define('ENVIRONMENT_LOCAL', 'local');
}
if (!defined('ENVIRONMENT_CI')) {
  define('ENVIRONMENT_CI', 'ci');
}
if (!defined('ENVIRONMENT_PROD')) {
  define('ENVIRONMENT_PROD', 'prod');
}
if (!defined('ENVIRONMENT_TEST')) {
  define('ENVIRONMENT_TEST', 'test');
}
if (!defined('ENVIRONMENT_DEV')) {
  define('ENVIRONMENT_DEV', 'dev');
}
$settings['environment'] = ENVIRONMENT_LOCAL;

$contrib_path = $app_root . DIRECTORY_SEPARATOR . (is_dir('modules/contrib') ? 'modules/contrib' : 'modules');

////////////////////////////////////////////////////////////////////////////////
///                       SITE-SPECIFIC SETTINGS                             ///
////////////////////////////////////////////////////////////////////////////////

ini_set('date.timezone', 'Australia/Melbourne');
date_default_timezone_set('Australia/Melbourne');

$settings['entity_update_batch_size'] = 50;

// Location of the site configuration files.
$config_directories = [
  CONFIG_SYNC_DIRECTORY => '../config/default',
];

// Salt for one-time login links, cancel links, form tokens, etc.
$settings['hash_salt'] = hash('sha256', 'CHANGE_ME');

// Expiration of cached pages on Varnish to 15 min.
$config['system.performance']['cache']['page']['max_age'] = 900;

// Aggregate CSS and JS files.
$config['system.performance']['css']['preprocess'] = 1;
$config['system.performance']['js']['preprocess'] = 1;

// Fast404.
$settings['fast404_exts'] = '/^(?!robots).*\.(txt|png|gif|jpe?g|css|js|ico|swf|flv|cgi|bat|pl|dll|exe|asp)$/i';
$settings['fast404_allow_anon_imagecache'] = TRUE;
$settings['fast404_whitelist'] = [
  'index.php',
  'rss.xml',
  'install.php',
  'cron.php',
  'update.php',
  'xmlrpc.php',
];
$settings['fast404_string_whitelisting'] = ['/advagg_'];
$settings['fast404_html'] = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML+RDFa 1.0//EN" "http://www.w3.org/MarkUp/DTD/xhtml-rdfa-1.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL "@path" was not found on this server.</p></body></html>';
if (file_exists($contrib_path . '/fast404/fast404.inc')) {
  include_once $contrib_path . '/fast404/fast404.inc';
  fast404_preboot($settings);
}

// Temp directory.
if (getenv('TMP')) {
  $config['system.file']['path']['temporary'] = getenv('TMP');
}

// Load services definition file.
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';

// The default list of directories that will be ignored by Drupal's file API.
$settings['file_scan_ignore_directories'] = [
  'node_modules',
];

// The default number of entities to update in a batch process.
$settings['entity_update_batch_size'] = 50;

////////////////////////////////////////////////////////////////////////////////
///                   END OF SITE-SPECIFIC SETTINGS                          ///
////////////////////////////////////////////////////////////////////////////////

// Include Acquia settings.
// @see https://docs.acquia.com/acquia-cloud/develop/env-variable
if (file_exists('/var/www/site-php')) {
  // Delay the initial database connection.
  $config['acquia_hosting_settings_autoconnect'] = FALSE;
  require '/var/www/site-php/mysite/mysite-settings.inc';
  // Do not put any Acquia-specific settings in this code block. It is used
  // for explicit mapping of Acquia environments to $conf['environment']
  // variable only. Instead, use 'PER-ENVIRONMENT SETTINGS' section below.
  switch ($_ENV['AH_SITE_ENVIRONMENT']) {
    case 'dev':
      $settings['environment'] = ENVIRONMENT_DEV;
      break;

    case 'test':
      $settings['environment'] = ENVIRONMENT_TEST;
      break;

    case 'prod':
      $settings['environment'] = ENVIRONMENT_PROD;
      break;
  }
}

////////////////////////////////////////////////////////////////////////////////
///                       PER-ENVIRONMENT SETTINGS                           ///
////////////////////////////////////////////////////////////////////////////////

$config['environment_indicator.indicator']['bg_color'] = $settings['environment'] == ENVIRONMENT_PROD ? '#ff0000' : '#006600';
$config['environment_indicator.indicator']['name'] = $settings['environment'];

if ($settings['environment'] !== ENVIRONMENT_PROD) {
  $config['stage_file_proxy.settings']['origin'] = 'http://mysiteurl/';
  $config['stage_file_proxy.settings']['hotlink'] = FALSE;
}

if ($settings['environment'] == ENVIRONMENT_LOCAL || $settings['environment'] == ENVIRONMENT_CI) {
  // Show all error messages on the site.
  $config['system.logging']['error_level'] = 'all';

  // Enable local split.
  $config['config_split.config_split.local']['status'] = TRUE;

  // Skip permissions hardening.
  $settings['skip_permissions_hardening'] = TRUE;

  // Allow to bypass Shield.
  $config['shield.settings']['credentials']['shield']['user'] = '';
  $config['shield.settings']['credentials']['shield']['pass'] = '';
}

////////////////////////////////////////////////////////////////////////////////
///                    END OF PER-ENVIRONMENT SETTINGS                       ///
////////////////////////////////////////////////////////////////////////////////

// Include generated settings file, if available.
if (file_exists($app_root . '/' . $site_path . '/settings.generated.php')) {
  include $app_root . '/' . $site_path . '/settings.generated.php';
}

// Load local development override configuration, if available.
//
// Use settings.local.php to override variables on secondary (staging,
// development, etc) installations of this site. Typically used to disable
// caching, JavaScript/CSS compression, re-routing of outgoing emails, and
// other things that should not happen on development and testing sites.
//
// Keep this code block at the end of this file to take full effect.
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
if (file_exists($app_root . '/' . $site_path . '/services.local.yml')) {
  $settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.local.yml';
}
