<?php

declare(strict_types=1);

namespace Drupal\system\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Component\FileSystem\FileSystem as FileSystemComponent;
use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Environment;
use Drupal\Component\Utility\OpCodeCache;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Database\Database;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Extension\ExtensionLifecycle;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\Core\Utility\PhpRequirements;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Install time requirements for the system module.
 */
class SystemRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRequirements(): array {
    return self::checkRequirements('install');
  }

  /**
   * Check requirements for a given phase.
   *
   * @param string $phase
   *   The phase in which requirements are checked, as documented in
   *   hook_runtime_requirements() and hook_update_requirements().
   *
   * @return array
   *   An associative array of requirements, as documented in
   *   hook_runtime_requirements() and hook_update_requirements().
   */
  public static function checkRequirements(string $phase): array {
    global $install_state;

    // Get the current default PHP requirements for this version of Drupal.
    $minimum_supported_php = PhpRequirements::getMinimumSupportedPhp();

    // Reset the extension lists.
    /** @var \Drupal\Core\Extension\ModuleExtensionList $module_extension_list */
    $module_extension_list = \Drupal::service('extension.list.module');
    $module_extension_list->reset();
    /** @var \Drupal\Core\Extension\ThemeExtensionList $theme_extension_list */
    $theme_extension_list = \Drupal::service('extension.list.theme');
    $theme_extension_list->reset();
    $requirements = [];

    // Report Drupal version
    if ($phase == 'runtime') {
      $requirements['drupal'] = [
        'title' => t('Drupal'),
        'value' => \Drupal::VERSION,
        'severity' => RequirementSeverity::Info,
        'weight' => -10,
      ];

      // Display the currently active installation profile, if the site
      // is not running the default installation profile.
      $profile = \Drupal::installProfile();
      if ($profile != 'standard' && !empty($profile)) {
        $info = $module_extension_list->getExtensionInfo($profile);
        $requirements['install_profile'] = [
          'title' => t('Installation profile'),
          'value' => t('%profile_name (%profile%version)', [
            '%profile_name' => $info['name'],
            '%profile' => $profile,
            '%version' => !empty($info['version']) ? '-' . $info['version'] : '',
          ]),
          'severity' => RequirementSeverity::Info,
          'weight' => -9,
        ];
      }

      // Gather all obsolete and experimental modules being enabled.
      $obsolete_extensions = [];
      $deprecated_modules = [];
      $experimental_modules = [];
      $enabled_modules = \Drupal::moduleHandler()->getModuleList();
      foreach ($enabled_modules as $module => $data) {
        $info = $module_extension_list->getExtensionInfo($module);
        if (isset($info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER])) {
          if ($info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER] === ExtensionLifecycle::EXPERIMENTAL) {
            $experimental_modules[$module] = $info['name'];
          }
          elseif ($info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER] === ExtensionLifecycle::DEPRECATED) {
            $deprecated_modules[] = ['name' => $info['name'], 'lifecycle_link' => $info['lifecycle_link']];
          }
          elseif ($info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER] === ExtensionLifecycle::OBSOLETE) {
            $obsolete_extensions[$module] = ['name' => $info['name'], 'lifecycle_link' => $info['lifecycle_link']];
          }
        }
      }

      // Warn if any experimental modules are installed.
      if (!empty($experimental_modules)) {
        $requirements['experimental_modules'] = [
          'title' => t('Experimental modules installed'),
          'value' => t('Experimental modules found: %module_list. <a href=":url">Experimental modules</a> are provided for testing purposes only. Use at your own risk.', ['%module_list' => implode(', ', $experimental_modules), ':url' => 'https://www.drupal.org/core/experimental']),
          'severity' => RequirementSeverity::Warning,
        ];
      }
      // Warn if any deprecated modules are installed.
      if (!empty($deprecated_modules)) {
        foreach ($deprecated_modules as $deprecated_module) {
          $deprecated_modules_link_list[] = (string) Link::fromTextAndUrl($deprecated_module['name'], Url::fromUri($deprecated_module['lifecycle_link']))->toString();
        }
        $requirements['deprecated_modules'] = [
          'title' => t('Deprecated modules installed'),
          'value' => t('Deprecated modules found: %module_list.', [
            '%module_list' => Markup::create(implode(', ', $deprecated_modules_link_list)),
          ]),
          'severity' => RequirementSeverity::Warning,
        ];
      }

      // Gather all obsolete and experimental themes being installed.
      $experimental_themes = [];
      $deprecated_themes = [];
      $installed_themes = \Drupal::service('theme_handler')->listInfo();
      foreach ($installed_themes as $theme => $data) {
        $info = $theme_extension_list->getExtensionInfo($theme);
        if (isset($info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER])) {
          if ($info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER] === ExtensionLifecycle::EXPERIMENTAL) {
            $experimental_themes[$theme] = $info['name'];
          }
          elseif ($info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER] === ExtensionLifecycle::DEPRECATED) {
            $deprecated_themes[] = ['name' => $info['name'], 'lifecycle_link' => $info['lifecycle_link']];
          }
          elseif ($info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER] === ExtensionLifecycle::OBSOLETE) {
            $obsolete_extensions[$theme] = ['name' => $info['name'], 'lifecycle_link' => $info['lifecycle_link']];
          }
        }
      }

      // Warn if any experimental themes are installed.
      if (!empty($experimental_themes)) {
        $requirements['experimental_themes'] = [
          'title' => t('Experimental themes installed'),
          'value' => t('Experimental themes found: %theme_list. Experimental themes are provided for testing purposes only. Use at your own risk.', ['%theme_list' => implode(', ', $experimental_themes)]),
          'severity' => RequirementSeverity::Warning,
        ];
      }

      // Warn if any deprecated themes are installed.
      if (!empty($deprecated_themes)) {
        foreach ($deprecated_themes as $deprecated_theme) {
          $deprecated_themes_link_list[] = (string) Link::fromTextAndUrl($deprecated_theme['name'], Url::fromUri($deprecated_theme['lifecycle_link']))->toString();

        }
        $requirements['deprecated_themes'] = [
          'title' => t('Deprecated themes installed'),
          'value' => t('Deprecated themes found: %theme_list.', [
            '%theme_list' => Markup::create(implode(', ', $deprecated_themes_link_list)),
          ]),
          'severity' => RequirementSeverity::Warning,
        ];
      }

      // Warn if any obsolete extensions (themes or modules) are installed.
      if (!empty($obsolete_extensions)) {
        foreach ($obsolete_extensions as $obsolete_extension) {
          $obsolete_extensions_link_list[] = (string) Link::fromTextAndUrl($obsolete_extension['name'], Url::fromUri($obsolete_extension['lifecycle_link']))->toString();
        }
        $requirements['obsolete_extensions'] = [
          'title' => t('Obsolete extensions installed'),
          'value' => t('Obsolete extensions found: %extensions. Obsolete extensions are provided only so that they can be uninstalled cleanly. You should immediately <a href=":uninstall_url">uninstall these extensions</a> since they may be removed in a future release.', [
            '%extensions' => Markup::create(implode(', ', $obsolete_extensions_link_list)),
            ':uninstall_url' => Url::fromRoute('system.modules_uninstall')->toString(),
          ]),
          'severity' => RequirementSeverity::Warning,
        ];
      }
      self::systemAdvisoriesRequirements($requirements);
    }

    // Web server information.
    $request_object = \Drupal::request();
    $software = $request_object->server->get('SERVER_SOFTWARE');
    $requirements['webserver'] = [
      'title' => t('Web server'),
      'value' => $software,
    ];

    // Tests clean URL support.
    if ($phase == 'install' && $install_state['interactive'] && !$request_object->query->has('rewrite') && str_contains($software, 'Apache')) {
      // If the Apache rewrite module is not enabled, Apache version must be >=
      // 2.2.16 because of the FallbackResource directive in the root .htaccess
      // file. Since the Apache version reported by the server is dependent on
      // the ServerTokens setting in httpd.conf, we may not be able to
      // determine if a given config is valid. Thus we are unable to use
      // version_compare() as we need have three possible outcomes: the version
      // of Apache is greater than 2.2.16, is less than 2.2.16, or cannot be
      // determined accurately. In the first case, we encourage the use of
      // mod_rewrite; in the second case, we raise an error regarding the
      // minimum Apache version; in the third case, we raise a warning that the
      // current version of Apache may not be supported.
      $rewrite_warning = FALSE;
      $rewrite_error = FALSE;
      $apache_version_string = 'Apache';

      // Determine the Apache version number: major, minor and revision.
      if (preg_match('/Apache\/(\d+)\.?(\d+)?\.?(\d+)?/', $software, $matches)) {
        $apache_version_string = $matches[0];

        // Major version number
        if ($matches[1] < 2) {
          $rewrite_error = TRUE;
        }
        elseif ($matches[1] == 2) {
          if (!isset($matches[2])) {
            $rewrite_warning = TRUE;
          }
          elseif ($matches[2] < 2) {
            $rewrite_error = TRUE;
          }
          elseif ($matches[2] == 2) {
            if (!isset($matches[3])) {
              $rewrite_warning = TRUE;
            }
            elseif ($matches[3] < 16) {
              $rewrite_error = TRUE;
            }
          }
        }
      }
      else {
        $rewrite_warning = TRUE;
      }

      if ($rewrite_warning) {
        $requirements['apache_version'] = [
          'title' => t('Apache version'),
          'value' => $apache_version_string,
          'severity' => RequirementSeverity::Warning,
          'description' => t('Due to the settings for ServerTokens in httpd.conf, it is impossible to accurately determine the version of Apache running on this server. The reported value is @reported, to run Drupal without mod_rewrite, a minimum version of 2.2.16 is needed.', ['@reported' => $apache_version_string]),
        ];
      }

      if ($rewrite_error) {
        $requirements['Apache version'] = [
          'title' => t('Apache version'),
          'value' => $apache_version_string,
          'severity' => RequirementSeverity::Error,
          'description' => t('The minimum version of Apache needed to run Drupal without mod_rewrite enabled is 2.2.16. See the <a href=":link">enabling clean URLs</a> page for more information on mod_rewrite.', [':link' => 'https://www.drupal.org/docs/8/clean-urls-in-drupal-8']),
        ];
      }

      if (!$rewrite_error && !$rewrite_warning) {
        $requirements['rewrite_module'] = [
          'title' => t('Clean URLs'),
          'value' => t('Disabled'),
          'severity' => RequirementSeverity::Warning,
          'description' => t('Your server is capable of using clean URLs, but it is not enabled. Using clean URLs gives an improved user experience and is recommended. <a href=":link">Enable clean URLs</a>', [':link' => 'https://www.drupal.org/docs/8/clean-urls-in-drupal-8']),
        ];
      }
    }

    // Verify the user is running a supported PHP version.
    // If the site is running a recommended version of PHP, just display it
    // as an informational message on the status report. This will be overridden
    // with an error or warning if the site is running older PHP versions for
    // which Drupal has already or will soon drop support.
    $phpversion = $phpversion_label = phpversion();
    if ($phase === 'runtime') {
      $phpversion_label = t('@phpversion (<a href=":url">more information</a>)', [
        '@phpversion' => $phpversion,
        ':url' => (new Url('system.php'))->toString(),
      ]);
    }
    $requirements['php'] = [
      'title' => t('PHP'),
      'value' => $phpversion_label,
    ];

    // Check if the PHP version is below what Drupal supports.
    if (version_compare($phpversion, $minimum_supported_php) < 0) {
      $requirements['php']['description'] = t('Your PHP installation is too old. Drupal requires at least PHP %version. It is recommended to upgrade to PHP version %recommended or higher for the best ongoing support. See <a href="http://php.net/supported-versions.php">PHP\'s version support documentation</a> and the <a href=":php_requirements">Drupal PHP requirements</a> page for more information.',
        [
          '%version' => $minimum_supported_php,
          '%recommended' => \Drupal::RECOMMENDED_PHP,
          ':php_requirements' => 'https://www.drupal.org/docs/system-requirements/php-requirements',
        ]
      );

      // If the PHP version is also below the absolute minimum allowed, it's not
      // safe to continue with the requirements check, and should always be an
      // error.
      if (version_compare($phpversion, \Drupal::MINIMUM_PHP) < 0) {
        $requirements['php']['severity'] = RequirementSeverity::Error;
        return $requirements;
      }
      // Otherwise, the message should be an error at runtime, and a warning
      // during installation or update.
      $requirements['php']['severity'] = ($phase === 'runtime') ? RequirementSeverity::Error : RequirementSeverity::Warning;
    }
    // For PHP versions that are still supported but no longer recommended,
    // inform users of what's recommended, allowing them to take action before
    // it becomes urgent.
    elseif ($phase === 'runtime' && version_compare($phpversion, \Drupal::RECOMMENDED_PHP) < 0) {
      $requirements['php']['description'] = t('It is recommended to upgrade to PHP version %recommended or higher for the best ongoing support.  See <a href="http://php.net/supported-versions.php">PHP\'s version support documentation</a> and the <a href=":php_requirements">Drupal PHP requirements</a> page for more information.', ['%recommended' => \Drupal::RECOMMENDED_PHP, ':php_requirements' => 'https://www.drupal.org/docs/system-requirements/php-requirements']);
      $requirements['php']['severity'] = RequirementSeverity::Info;
    }

    // Test for PHP extensions.
    $requirements['php_extensions'] = [
      'title' => t('PHP extensions'),
    ];

    $missing_extensions = [];
    $required_extensions = [
      'date',
      'dom',
      'filter',
      'gd',
      'hash',
      'json',
      'pcre',
      'pdo',
      'session',
      'SimpleXML',
      'SPL',
      'tokenizer',
      'xml',
      'zlib',
    ];
    foreach ($required_extensions as $extension) {
      if (!extension_loaded($extension)) {
        $missing_extensions[] = $extension;
      }
    }

    if (!empty($missing_extensions)) {
      $description = t('Drupal requires you to enable the PHP extensions in the following list (see the <a href=":system_requirements">system requirements page</a> for more information):', [
        ':system_requirements' => 'https://www.drupal.org/docs/system-requirements',
      ]);

      // We use twig inline_template to avoid twig's autoescape.
      $description = [
        '#type' => 'inline_template',
        '#template' => '{{ description }}{{ missing_extensions }}',
        '#context' => [
          'description' => $description,
          'missing_extensions' => [
            '#theme' => 'item_list',
            '#items' => $missing_extensions,
          ],
        ],
      ];

      $requirements['php_extensions']['value'] = t('Disabled');
      $requirements['php_extensions']['severity'] = RequirementSeverity::Error;
      $requirements['php_extensions']['description'] = $description;
    }
    else {
      $requirements['php_extensions']['value'] = t('Enabled');
    }

    if ($phase == 'install' || $phase == 'runtime') {
      // Check to see if OPcache is installed.
      if (!OpCodeCache::isEnabled()) {
        $requirements['php_opcache'] = [
          'value' => t('Not enabled'),
          'severity' => RequirementSeverity::Warning,
          'description' => t('PHP OPcode caching can improve your site\'s performance considerably. It is <strong>highly recommended</strong> to have <a href="http://php.net/manual/opcache.installation.php" target="_blank">OPcache</a> installed on your server.'),
        ];
      }
      else {
        $requirements['php_opcache']['value'] = t('Enabled');
      }
      $requirements['php_opcache']['title'] = t('PHP OPcode caching');
    }

    // Check to see if APCu is installed and configured correctly.
    if ($phase == 'runtime' && PHP_SAPI != 'cli') {
      $requirements['php_apcu_enabled']['title'] = t('PHP APCu caching');
      $requirements['php_apcu_available']['title'] = t('PHP APCu available caching');
      if (extension_loaded('apcu') && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN)) {
        $memory_info = apcu_sma_info(TRUE);
        $apcu_actual_size = ByteSizeMarkup::create($memory_info['seg_size'] * $memory_info['num_seg']);
        $apcu_recommended_size = '32 MB';
        $requirements['php_apcu_enabled']['value'] = t('Enabled (@size)', ['@size' => $apcu_actual_size]);
        if (Bytes::toNumber(ini_get('apc.shm_size')) * ini_get('apc.shm_segments') < Bytes::toNumber($apcu_recommended_size)) {
          $requirements['php_apcu_enabled']['severity'] = RequirementSeverity::Warning;
          $requirements['php_apcu_enabled']['description'] = t('Depending on your configuration, Drupal can run with a @apcu_size APCu limit. However, a @apcu_default_size APCu limit (the default) or above is recommended, especially if your site uses additional custom or contributed modules.', [
            '@apcu_size' => $apcu_actual_size,
            '@apcu_default_size' => $apcu_recommended_size,
          ]);
        }
        else {
          $memory_available = $memory_info['avail_mem'] / ($memory_info['seg_size'] * $memory_info['num_seg']);
          if ($memory_available < 0.1) {
            $requirements['php_apcu_available']['severity'] = RequirementSeverity::Error;
            $requirements['php_apcu_available']['description'] = t('APCu is using over 90% of its allotted memory (@apcu_actual_size). To improve APCu performance, consider increasing this limit.', [
              '@apcu_actual_size' => $apcu_actual_size,
            ]);
          }
          elseif ($memory_available < 0.25) {
            $requirements['php_apcu_available']['severity'] = RequirementSeverity::Warning;
            $requirements['php_apcu_available']['description'] = t('APCu is using over 75% of its allotted memory (@apcu_actual_size). To improve APCu performance, consider increasing this limit.', [
              '@apcu_actual_size' => $apcu_actual_size,
            ]);
          }
          else {
            $requirements['php_apcu_available']['severity'] = RequirementSeverity::OK;
          }
          $requirements['php_apcu_available']['value'] = t('Memory available: @available.', [
            '@available' => ByteSizeMarkup::create($memory_info['avail_mem']),
          ]);
        }
      }
      else {
        $requirements['php_apcu_enabled'] += [
          'value' => t('Not enabled'),
          'severity' => RequirementSeverity::Info,
          'description' => t('PHP APCu caching can improve your site\'s performance considerably. It is <strong>highly recommended</strong> to have <a href="https://www.php.net/manual/apcu.installation.php" target="_blank">APCu</a> installed on your server.'),
        ];
      }
    }

    if ($phase != 'update') {
      // Test whether we have a good source of random bytes.
      $requirements['php_random_bytes'] = [
        'title' => t('Random number generation'),
      ];
      try {
        $bytes = random_bytes(10);
        if (strlen($bytes) != 10) {
          throw new \Exception("Tried to generate 10 random bytes, generated '" . strlen($bytes) . "'");
        }
        $requirements['php_random_bytes']['value'] = t('Successful');
      }
      catch (\Exception $e) {
        // If /dev/urandom is not available on a UNIX-like system, check whether
        // open_basedir restrictions are the cause.
        $open_basedir_blocks_urandom = FALSE;
        if (DIRECTORY_SEPARATOR === '/' && !@is_readable('/dev/urandom')) {
          $open_basedir = ini_get('open_basedir');
          if ($open_basedir) {
            $open_basedir_paths = explode(PATH_SEPARATOR, $open_basedir);
            $open_basedir_blocks_urandom = !array_intersect(['/dev', '/dev/', '/dev/urandom'], $open_basedir_paths);
          }
        }
        $args = [
          ':drupal-php' => 'https://www.drupal.org/docs/system-requirements/php-requirements',
          '%exception_message' => $e->getMessage(),
        ];
        if ($open_basedir_blocks_urandom) {
          $requirements['php_random_bytes']['description'] = t('Drupal is unable to generate highly randomized numbers, which means certain security features like password reset URLs are not as secure as they should be. Instead, only a slow, less-secure fallback generator is available. The most likely cause is that open_basedir restrictions are in effect and /dev/urandom is not on the allowed list. See the <a href=":drupal-php">system requirements</a> page for more information. %exception_message', $args);
        }
        else {
          $requirements['php_random_bytes']['description'] = t('Drupal is unable to generate highly randomized numbers, which means certain security features like password reset URLs are not as secure as they should be. Instead, only a slow, less-secure fallback generator is available. See the <a href=":drupal-php">system requirements</a> page for more information. %exception_message', $args);
        }
        $requirements['php_random_bytes']['value'] = t('Less secure');
        $requirements['php_random_bytes']['severity'] = RequirementSeverity::Error;
      }
    }

    if ($phase === 'runtime' && PHP_SAPI !== 'cli') {
      if (!function_exists('fastcgi_finish_request') && !function_exists('litespeed_finish_request') && !ob_get_status()) {
        $requirements['output_buffering'] = [
          'title' => t('Output Buffering'),
          'error_value' => t('Not enabled'),
          'severity' => RequirementSeverity::Warning,
          'description' => t('<a href="https://www.php.net/manual/en/function.ob-start.php">Output buffering</a> is not enabled. This may degrade Drupal\'s performance. You can enable output buffering by default <a href="https://www.php.net/manual/en/outcontrol.configuration.php#ini.output-buffering">in your PHP settings</a>.'),
        ];
      }
    }

    if ($phase == 'install' || $phase == 'update') {
      // Test for PDO (database).
      $requirements['database_extensions'] = [
        'title' => t('Database support'),
      ];

      // Make sure PDO is available.
      $database_ok = extension_loaded('pdo');
      if (!$database_ok) {
        $pdo_message = t('Your web server does not appear to support PDO (PHP Data Objects). Ask your hosting provider if they support the native PDO extension. See the <a href=":link">system requirements</a> page for more information.', [
          ':link' => 'https://www.drupal.org/docs/system-requirements/php-requirements#database',
        ]);
      }
      else {
        // Make sure at least one supported database driver exists.
        if (empty(Database::getDriverList()->getInstallableList())) {
          $database_ok = FALSE;
          $pdo_message = t('Your web server does not appear to support any common PDO database extensions. Check with your hosting provider to see if they support PDO (PHP Data Objects) and offer any databases that <a href=":drupal-databases">Drupal supports</a>.', [
            ':drupal-databases' => 'https://www.drupal.org/docs/system-requirements/database-server-requirements',
          ]);
        }
        // Make sure the native PDO extension is available, not the older PEAR
        // version. (See install_verify_pdo() for details.)
        if (!defined('PDO::ATTR_DEFAULT_FETCH_MODE')) {
          $database_ok = FALSE;
          $pdo_message = t('Your web server seems to have the wrong version of PDO installed. Drupal requires the PDO extension from PHP core. This system has the older PECL version. See the <a href=":link">system requirements</a> page for more information.', [
            ':link' => 'https://www.drupal.org/docs/system-requirements/php-requirements#database',
          ]);
        }
      }

      if (!$database_ok) {
        $requirements['database_extensions']['value'] = t('Disabled');
        $requirements['database_extensions']['severity'] = RequirementSeverity::Error;
        $requirements['database_extensions']['description'] = $pdo_message;
      }
      else {
        $requirements['database_extensions']['value'] = t('Enabled');
      }
    }

    if ($phase === 'runtime' || $phase === 'update') {
      // Database information.
      $class = Database::getConnection()->getConnectionOptions()['namespace'] . '\\Install\\Tasks';
      /** @var \Drupal\Core\Database\Install\Tasks $tasks */
      $tasks = new $class();
      $requirements['database_system'] = [
        'title' => t('Database system'),
        'value' => $tasks->name(),
      ];
      $requirements['database_system_version'] = [
        'title' => t('Database system version'),
        'value' => Database::getConnection()->version(),
      ];

      $errors = $tasks->engineVersionRequirementsCheck();
      $error_count = count($errors);
      if ($error_count > 0) {
        $error_message = [
          '#theme' => 'item_list',
          '#items' => $errors,
          // Use the comma-list style to display a single error without bullets.
          '#context' => ['list_style' => $error_count === 1 ? 'comma-list' : ''],
        ];
        $requirements['database_system_version']['severity'] = RequirementSeverity::Error;
        $requirements['database_system_version']['description'] = $error_message;
      }
    }

    if ($phase === 'runtime' || $phase === 'update') {
      // Test database JSON support.
      $requirements['database_support_json'] = [
        'title' => t('Database support for JSON'),
        'severity' => RequirementSeverity::OK,
        'value' => t('Available'),
        'description' => t('Drupal requires databases that support JSON storage.'),
      ];

      if (!Database::getConnection()->hasJson()) {
        $requirements['database_support_json']['value'] = t('Not available');
        $requirements['database_support_json']['severity'] = RequirementSeverity::Error;
      }
    }

    // Test PHP memory_limit
    $memory_limit = ini_get('memory_limit');
    $requirements['php_memory_limit'] = [
      'title' => t('PHP memory limit'),
      'value' => $memory_limit == -1 ? t('-1 (Unlimited)') : $memory_limit,
    ];

    if (!Environment::checkMemoryLimit(\Drupal::MINIMUM_PHP_MEMORY_LIMIT, $memory_limit)) {
      $description = [];
      if ($phase == 'install') {
        $description['phase'] = t('Consider increasing your PHP memory limit to %memory_minimum_limit to help prevent errors in the installation process.', ['%memory_minimum_limit' => \Drupal::MINIMUM_PHP_MEMORY_LIMIT]);
      }
      elseif ($phase == 'update') {
        $description['phase'] = t('Consider increasing your PHP memory limit to %memory_minimum_limit to help prevent errors in the update process.', ['%memory_minimum_limit' => \Drupal::MINIMUM_PHP_MEMORY_LIMIT]);
      }
      elseif ($phase == 'runtime') {
        $description['phase'] = t('Depending on your configuration, Drupal can run with a %memory_limit PHP memory limit. However, a %memory_minimum_limit PHP memory limit or above is recommended, especially if your site uses additional custom or contributed modules.', ['%memory_limit' => $memory_limit, '%memory_minimum_limit' => \Drupal::MINIMUM_PHP_MEMORY_LIMIT]);
      }

      if (!empty($description['phase'])) {
        if ($php_ini_path = get_cfg_var('cfg_file_path')) {
          $description['memory'] = t('Increase the memory limit by editing the memory_limit parameter in the file %configuration-file and then restart your web server (or contact your system administrator or hosting provider for assistance).', ['%configuration-file' => $php_ini_path]);
        }
        else {
          $description['memory'] = t('Contact your system administrator or hosting provider for assistance with increasing your PHP memory limit.');
        }

        $handbook_link = t('For more information, see the online handbook entry for <a href=":memory-limit">increasing the PHP memory limit</a>.', [':memory-limit' => 'https://www.drupal.org/node/207036']);

        $description = [
          '#type' => 'inline_template',
          '#template' => '{{ description_phase }} {{ description_memory }} {{ handbook }}',
          '#context' => [
            'description_phase' => $description['phase'],
            'description_memory' => $description['memory'],
            'handbook' => $handbook_link,
          ],
        ];

        $requirements['php_memory_limit']['description'] = $description;
        $requirements['php_memory_limit']['severity'] = RequirementSeverity::Warning;
      }
    }

    // Test if configuration files and directory are writable.
    if ($phase == 'runtime') {
      $conf_errors = [];
      // Find the site path. Kernel service is not always available at this
      // point, but is preferred, when available.
      if (\Drupal::hasService('kernel')) {
        $site_path = \Drupal::getContainer()->getParameter('site.path');
      }
      else {
        $site_path = DrupalKernel::findSitePath(Request::createFromGlobals());
      }
      // Allow system administrators to disable permissions hardening for the
      // site directory. This allows additional files in the site directory to
      // be updated when they are managed in a version control system.
      if (Settings::get('skip_permissions_hardening')) {
        $error_value = t('Protection disabled');
        // If permissions hardening is disabled, then only show a warning for a
        // writable file, as a reminder, rather than an error.
        $file_protection_severity = RequirementSeverity::Warning;
      }
      else {
        $error_value = t('Not protected');
        // In normal operation, writable files or directories are an error.
        $file_protection_severity = RequirementSeverity::Error;
        if (!drupal_verify_install_file($site_path, FILE_NOT_WRITABLE, 'dir')) {
          $conf_errors[] = t("The directory %file is not protected from modifications and poses a security risk. You must change the directory's permissions to be non-writable.", ['%file' => $site_path]);
        }
      }
      foreach (['settings.php', 'settings.local.php', 'services.yml'] as $conf_file) {
        $full_path = $site_path . '/' . $conf_file;
        if (file_exists($full_path) && !drupal_verify_install_file($full_path, FILE_EXIST | FILE_READABLE | FILE_NOT_WRITABLE, 'file', !Settings::get('skip_permissions_hardening'))) {
          $conf_errors[] = t("The file %file is not protected from modifications and poses a security risk. You must change the file's permissions to be non-writable.", ['%file' => $full_path]);
        }
      }
      if (!empty($conf_errors)) {
        if (count($conf_errors) == 1) {
          $description = $conf_errors[0];
        }
        else {
          // We use twig inline_template to avoid double escaping.
          $description = [
            '#type' => 'inline_template',
            '#template' => '{{ configuration_error_list }}',
            '#context' => [
              'configuration_error_list' => [
                '#theme' => 'item_list',
                '#items' => $conf_errors,
              ],
            ],
          ];
        }
        $requirements['configuration_files'] = [
          'value' => $error_value,
          'severity' => $file_protection_severity,
          'description' => $description,
        ];
      }
      else {
        $requirements['configuration_files'] = [
          'value' => t('Protected'),
        ];
      }
      $requirements['configuration_files']['title'] = t('Configuration files');
    }

    // Test the contents of the .htaccess files.
    if ($phase == 'runtime' && Settings::get('auto_create_htaccess', TRUE)) {
      // Try to write the .htaccess files first, to prevent false alarms in
      // case (for example) the /tmp directory was wiped.
      /** @var \Drupal\Core\File\HtaccessWriterInterface $htaccessWriter */
      $htaccessWriter = \Drupal::service("file.htaccess_writer");
      $htaccessWriter->ensure();
      foreach ($htaccessWriter->defaultProtectedDirs() as $protected_dir) {
        $htaccess_file = $protected_dir->getPath() . '/.htaccess';
        // Check for the string which was added to the recommended .htaccess
        // file in the latest security update.
        if (!file_exists($htaccess_file) || !($contents = @file_get_contents($htaccess_file)) || !str_contains($contents, 'Drupal_Security_Do_Not_Remove_See_SA_2013_003')) {
          $url = 'https://www.drupal.org/SA-CORE-2013-003';
          $requirements[$htaccess_file] = [
            // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
            'title' => new TranslatableMarkup($protected_dir->getTitle()),
            'value' => t('Not fully protected'),
            'severity' => RequirementSeverity::Error,
            'description' => t('See <a href=":url">@url</a> for information about the recommended .htaccess file which should be added to the %directory directory to help protect against arbitrary code execution.', [':url' => $url, '@url' => $url, '%directory' => $protected_dir->getPath()]),
          ];
        }
      }
    }

    // Report cron status.
    if ($phase == 'runtime') {
      $cron_config = \Drupal::config('system.cron');
      // Cron warning threshold defaults to two days.
      $threshold_warning = $cron_config->get('threshold.requirements_warning');
      // Cron error threshold defaults to two weeks.
      $threshold_error = $cron_config->get('threshold.requirements_error');

      // Determine when cron last ran.
      $cron_last = \Drupal::state()->get('system.cron_last');
      if (!is_numeric($cron_last)) {
        $cron_last = \Drupal::state()->get('install_time', 0);
      }

      // Determine severity based on time since cron last ran.
      $severity = RequirementSeverity::Info;
      $request_time = \Drupal::time()->getRequestTime();
      if ($request_time - $cron_last > $threshold_error) {
        $severity = RequirementSeverity::Error;
      }
      elseif ($request_time - $cron_last > $threshold_warning) {
        $severity = RequirementSeverity::Warning;
      }

      // Set summary and description based on values determined above.
      $summary = t('Last run @time ago', ['@time' => \Drupal::service('date.formatter')->formatTimeDiffSince($cron_last)]);

      $requirements['cron'] = [
        'title' => t('Cron maintenance tasks'),
        'severity' => $severity,
        'value' => $summary,
      ];
      if ($severity != RequirementSeverity::Info) {
        $requirements['cron']['description'][] = [
          [
            '#markup' => t('Cron has not run recently.'),
            '#suffix' => ' ',
          ],
          [
            '#markup' => t('For more information, see the online handbook entry for <a href=":cron-handbook">configuring cron jobs</a>.', [':cron-handbook' => 'https://www.drupal.org/docs/administering-a-drupal-site/cron-automated-tasks/cron-automated-tasks-overview']),
            '#suffix' => ' ',
          ],
        ];
      }
      $requirements['cron']['description'][] = [
        [
          '#type' => 'link',
          '#prefix' => '(',
          '#title' => t('more information'),
          '#suffix' => ')',
          '#url' => Url::fromRoute('system.cron_settings'),
        ],
        [
          '#prefix' => '<span class="cron-description__run-cron">',
          '#suffix' => '</span>',
          '#type' => 'link',
          '#title' => t('Run cron'),
          '#url' => Url::fromRoute('system.run_cron'),
        ],
      ];
    }
    if ($phase != 'install') {
      $directories = [
        PublicStream::basePath(),
        // By default no private files directory is configured. For private
        // files to be secure the admin needs to provide a path outside the
        // webroot.
        PrivateStream::basePath(),
        \Drupal::service('file_system')->getTempDirectory(),
      ];
    }

    // During an install we need to make assumptions about the file system
    // unless overrides are provided in settings.php.
    if ($phase == 'install') {
      $directories = [];
      if ($file_public_path = Settings::get('file_public_path')) {
        $directories[] = $file_public_path;
      }
      else {
        // If we are installing Drupal, the settings.php file might not exist
        // yet in the intended site directory, so don't require it.
        $request = Request::createFromGlobals();
        $site_path = DrupalKernel::findSitePath($request);
        $directories[] = $site_path . '/files';
      }
      if ($file_private_path = Settings::get('file_private_path')) {
        $directories[] = $file_private_path;
      }
      if (Settings::get('file_temp_path')) {
        $directories[] = Settings::get('file_temp_path');
      }
      else {
        // If the temporary directory is not overridden use an appropriate
        // temporary path for the system.
        $directories[] = FileSystemComponent::getOsTemporaryDirectory();
      }
    }

    // Check the config directory if it is defined in settings.php. If it isn't
    // defined, the installer will create a valid config directory later, but
    // during runtime we must always display an error.
    $config_sync_directory = Settings::get('config_sync_directory');
    if (!empty($config_sync_directory)) {
      // If we're installing Drupal try and create the config sync directory.
      if (!is_dir($config_sync_directory) && $phase == 'install') {
        \Drupal::service('file_system')->prepareDirectory($config_sync_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      }
      if (!is_dir($config_sync_directory)) {
        if ($phase == 'install') {
          $description = t('An automated attempt to create the directory %directory failed, possibly due to a permissions problem. To proceed with the installation, either create the directory and modify its permissions manually or ensure that the installer has the permissions to create it automatically. For more information, see INSTALL.txt or the <a href=":handbook_url">online handbook</a>.', ['%directory' => $config_sync_directory, ':handbook_url' => 'https://www.drupal.org/server-permissions']);
        }
        else {
          $description = t('The directory %directory does not exist.', ['%directory' => $config_sync_directory]);
        }
        $requirements['config sync directory'] = [
          'title' => t('Configuration sync directory'),
          'description' => $description,
          'severity' => RequirementSeverity::Error,
        ];
      }
    }
    if ($phase != 'install' && empty($config_sync_directory)) {
      $requirements['config sync directory'] = [
        'title' => t('Configuration sync directory'),
        'value' => t('Not present'),
        'description' => t("Your %file file must define the %setting setting as a string containing the directory in which configuration files can be found.", ['%file' => $site_path . '/settings.php', '%setting' => "\$settings['config_sync_directory']"]),
        'severity' => RequirementSeverity::Error,
      ];
    }

    $requirements['file system'] = [
      'title' => t('File system'),
    ];

    $error = '';
    // For installer, create the directories if possible.
    foreach ($directories as $directory) {
      if (!$directory) {
        continue;
      }
      if ($phase == 'install') {
        \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      }
      $is_writable = is_writable($directory);
      $is_directory = is_dir($directory);
      if (!$is_writable || !$is_directory) {
        $description = '';
        $requirements['file system']['value'] = t('Not writable');
        if (!$is_directory) {
          $error = t('The directory %directory does not exist.', ['%directory' => $directory]);
        }
        else {
          $error = t('The directory %directory is not writable.', ['%directory' => $directory]);
        }
        // The files directory requirement check is done only during install and
        // runtime.
        if ($phase == 'runtime') {
          $description = t('You may need to set the correct directory at the <a href=":admin-file-system">file system settings page</a> or change the current directory\'s permissions so that it is writable.', [':admin-file-system' => Url::fromRoute('system.file_system_settings')->toString()]);
        }
        elseif ($phase == 'install') {
          // For the installer UI, we need different wording. 'value' will
          // be treated as version, so provide none there.
          $description = t('An automated attempt to create this directory failed, possibly due to a permissions problem. To proceed with the installation, either create the directory and modify its permissions manually or ensure that the installer has the permissions to create it automatically. For more information, see INSTALL.txt or the <a href=":handbook_url">online handbook</a>.', [':handbook_url' => 'https://www.drupal.org/server-permissions']);
          $requirements['file system']['value'] = '';
        }
        if (!empty($description)) {
          $description = [
            '#type' => 'inline_template',
            '#template' => '{{ error }} {{ description }}',
            '#context' => [
              'error' => $error,
              'description' => $description,
            ],
          ];
          $requirements['file system']['description'] = $description;
          $requirements['file system']['severity'] = RequirementSeverity::Error;
        }
      }
      else {
        // This function can be called before the config_cache table has been
        // created.
        if ($phase == 'install' || \Drupal::config('system.file')->get('default_scheme') == 'public') {
          $requirements['file system']['value'] = t('Writable (<em>public</em> download method)');
        }
        else {
          $requirements['file system']['value'] = t('Writable (<em>private</em> download method)');
        }
      }
    }

    // See if updates are available in update.php.
    if ($phase == 'runtime') {
      $requirements['update'] = [
        'title' => t('Database updates'),
        'value' => t('Up to date'),
      ];

      // Check installed modules.
      $has_pending_updates = FALSE;
      /** @var \Drupal\Core\Update\UpdateHookRegistry $update_registry */
      $update_registry = \Drupal::service('update.update_hook_registry');
      foreach (\Drupal::moduleHandler()->getModuleList() as $module => $filename) {
        $updates = $update_registry->getAvailableUpdates($module);
        if ($updates) {
          $default = $update_registry->getInstalledVersion($module);
          if (max($updates) > $default) {
            $has_pending_updates = TRUE;
            break;
          }
        }
      }
      if (!$has_pending_updates) {
        /** @var \Drupal\Core\Update\UpdateRegistry $post_update_registry */
        $post_update_registry = \Drupal::service('update.post_update_registry');
        $missing_post_update_functions = $post_update_registry->getPendingUpdateFunctions();
        if (!empty($missing_post_update_functions)) {
          $has_pending_updates = TRUE;
        }
      }

      if ($has_pending_updates) {
        $requirements['update']['severity'] = RequirementSeverity::Error;
        $requirements['update']['value'] = t('Out of date');
        $requirements['update']['description'] = t('Some modules have database schema updates to install. You should run the <a href=":update">database update script</a> immediately.', [':update' => Url::fromRoute('system.db_update')->toString()]);
      }

      $requirements['entity_update'] = [
        'title' => t('Entity/field definitions'),
        'value' => t('Up to date'),
      ];
      // Verify that no entity updates are pending.
      if ($change_list = \Drupal::entityDefinitionUpdateManager()->getChangeSummary()) {
        $build = [];
        foreach ($change_list as $entity_type_id => $changes) {
          $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
          $build[] = [
            '#theme' => 'item_list',
            '#title' => $entity_type->getLabel(),
            '#items' => $changes,
          ];
        }

        $entity_update_issues = \Drupal::service('renderer')->renderInIsolation($build);
        $requirements['entity_update']['severity'] = RequirementSeverity::Error;
        $requirements['entity_update']['value'] = t('Mismatched entity and/or field definitions');
        $requirements['entity_update']['description'] = t('The following changes were detected in the entity type and field definitions. @updates', ['@updates' => $entity_update_issues]);
      }
    }

    // Display the deployment identifier if set.
    if ($phase == 'runtime') {
      if ($deployment_identifier = Settings::get('deployment_identifier')) {
        $requirements['deployment identifier'] = [
          'title' => t('Deployment identifier'),
          'value' => $deployment_identifier,
          'severity' => RequirementSeverity::Info,
        ];
      }
    }

    // Verify the update.php access setting
    if ($phase == 'runtime') {
      if (Settings::get('update_free_access')) {
        $requirements['update access'] = [
          'value' => t('Not protected'),
          'severity' => RequirementSeverity::Error,
          'description' => t('The update.php script is accessible to everyone without authentication check, which is a security risk. You must change the @settings_name value in your settings.php back to FALSE.', ['@settings_name' => '$settings[\'update_free_access\']']),
        ];
      }
      else {
        $requirements['update access'] = [
          'value' => t('Protected'),
        ];
      }
      $requirements['update access']['title'] = t('Access to update.php');
    }

    // Display an error if a newly introduced dependency in a module is not
    // resolved.
    if ($phase === 'update' || $phase === 'runtime') {
      $create_extension_incompatibility_list = function (array $extension_names, PluralTranslatableMarkup $description, PluralTranslatableMarkup $title, TranslatableMarkup|string $message = '', TranslatableMarkup|string $additional_description = '') {
        if ($message === '') {
          $message = new TranslatableMarkup('Review the <a href=":url"> suggestions for resolving this incompatibility</a> to repair your installation, and then re-run update.php.', [':url' => 'https://www.drupal.org/docs/updating-drupal/troubleshooting-database-updates']);
        }
        // Use an inline twig template to:
        // - Concatenate MarkupInterface objects and preserve safeness.
        // - Use the item_list theme for the extension list.
        $template = [
          '#type' => 'inline_template',
          '#template' => '{{ description }}{{ extensions }}{{ additional_description }}<br>',
          '#context' => [
            'extensions' => [
              '#theme' => 'item_list',
            ],
          ],
        ];
        $template['#context']['extensions']['#items'] = $extension_names;
        $template['#context']['description'] = $description;
        $template['#context']['additional_description'] = $additional_description;
        return [
          'title' => $title,
          'value' => [
            'list' => $template,
            'handbook_link' => [
              '#markup' => $message,
            ],
          ],
          'severity' => RequirementSeverity::Error,
        ];
      };
      $profile = \Drupal::installProfile();
      $files = $module_extension_list->getList();
      $files += $theme_extension_list->getList();
      $core_incompatible_extensions = [];
      $php_incompatible_extensions = [];
      foreach ($files as $extension_name => $file) {
        // Ignore uninstalled extensions and installation profiles.
        if (!$file->status || $extension_name == $profile) {
          continue;
        }

        $name = $file->info['name'];
        if (!empty($file->info['core_incompatible'])) {
          $core_incompatible_extensions[$file->info['type']][] = $name;
        }

        // Check the extension's PHP version.
        $php = (string) $file->info['php'];
        if (version_compare($php, PHP_VERSION, '>')) {
          $php_incompatible_extensions[$file->info['type']][] = $name;
        }

        // Check the module's required modules.
        /** @var \Drupal\Core\Extension\Dependency $requirement */
        foreach ($file->requires as $requirement) {
          $required_module = $requirement->getName();
          // Check if the module exists.
          if (!isset($files[$required_module])) {
            $requirements["$extension_name-$required_module"] = [
              'title' => t('Unresolved dependency'),
              'description' => t('@name requires this module.', ['@name' => $name]),
              'value' => t('@required_name (Missing)', ['@required_name' => $required_module]),
              'severity' => RequirementSeverity::Error,
            ];
            continue;
          }
          // Check for an incompatible version.
          $required_file = $files[$required_module];
          $required_name = $required_file->info['name'];
          // Remove CORE_COMPATIBILITY- only from the start of the string.
          $version = preg_replace('/^(' . \Drupal::CORE_COMPATIBILITY . '\-)/', '', $required_file->info['version'] ?? '');
          if (!$requirement->isCompatible($version)) {
            $requirements["$extension_name-$required_module"] = [
              'title' => t('Unresolved dependency'),
              'description' => t('@name requires this module and version. Currently using @required_name version @version', ['@name' => $name, '@required_name' => $required_name, '@version' => $version]),
              'value' => t('@required_name (Version @compatibility required)', ['@required_name' => $required_name, '@compatibility' => $requirement->getConstraintString()]),
              'severity' => RequirementSeverity::Error,
            ];
            continue;
          }
        }
      }
      if (!empty($core_incompatible_extensions['module'])) {
        $requirements['module_core_incompatible'] = $create_extension_incompatibility_list(
          $core_incompatible_extensions['module'],
          new PluralTranslatableMarkup(
            count($core_incompatible_extensions['module']),
          'The following module is installed, but it is incompatible with Drupal @version:',
          'The following modules are installed, but they are incompatible with Drupal @version:',
          ['@version' => \Drupal::VERSION]
          ),
          new PluralTranslatableMarkup(
            count($core_incompatible_extensions['module']),
            'Incompatible module',
            'Incompatible modules'
          )
        );
      }
      if (!empty($core_incompatible_extensions['theme'])) {
        $requirements['theme_core_incompatible'] = $create_extension_incompatibility_list(
          $core_incompatible_extensions['theme'],
          new PluralTranslatableMarkup(
            count($core_incompatible_extensions['theme']),
            'The following theme is installed, but it is incompatible with Drupal @version:',
            'The following themes are installed, but they are incompatible with Drupal @version:',
            ['@version' => \Drupal::VERSION]
          ),
          new PluralTranslatableMarkup(
            count($core_incompatible_extensions['theme']),
            'Incompatible theme',
            'Incompatible themes'
          )
        );
      }
      if (!empty($php_incompatible_extensions['module'])) {
        $requirements['module_php_incompatible'] = $create_extension_incompatibility_list(
          $php_incompatible_extensions['module'],
          new PluralTranslatableMarkup(
            count($php_incompatible_extensions['module']),
            'The following module is installed, but it is incompatible with PHP @version:',
            'The following modules are installed, but they are incompatible with PHP @version:',
            ['@version' => phpversion()]
          ),
          new PluralTranslatableMarkup(
            count($php_incompatible_extensions['module']),
            'Incompatible module',
            'Incompatible modules'
          )
        );
      }
      if (!empty($php_incompatible_extensions['theme'])) {
        $requirements['theme_php_incompatible'] = $create_extension_incompatibility_list(
          $php_incompatible_extensions['theme'],
          new PluralTranslatableMarkup(
            count($php_incompatible_extensions['theme']),
            'The following theme is installed, but it is incompatible with PHP @version:',
            'The following themes are installed, but they are incompatible with PHP @version:',
            ['@version' => phpversion()]
          ),
          new PluralTranslatableMarkup(
            count($php_incompatible_extensions['theme']),
            'Incompatible theme',
            'Incompatible themes'
          )
        );
      }

      $extension_config = \Drupal::configFactory()->get('core.extension');

      // Look for removed core modules.
      $is_removed_module = function ($extension_name) use ($module_extension_list) {
        return !$module_extension_list->exists($extension_name)
            && array_key_exists($extension_name, DRUPAL_CORE_REMOVED_MODULE_LIST);
      };
      $removed_modules = array_filter(array_keys($extension_config->get('module')), $is_removed_module);
      if (!empty($removed_modules)) {
        $list = [];
        foreach ($removed_modules as $removed_module) {
          $list[] = t('<a href=":url">@module</a>', [
            ':url' => "https://www.drupal.org/project/$removed_module",
            '@module' => DRUPAL_CORE_REMOVED_MODULE_LIST[$removed_module],
          ]);
        }
        $requirements['removed_module'] = $create_extension_incompatibility_list(
          $list,
          new PluralTranslatableMarkup(
            count($removed_modules),
            'You must add the following contributed module and reload this page.',
            'You must add the following contributed modules and reload this page.'
          ),
          new PluralTranslatableMarkup(
            count($removed_modules),
            'Removed core module',
            'Removed core modules'
          ),
          new TranslatableMarkup(
            'For more information read the <a href=":url">documentation on deprecated modules.</a>',
            [':url' => 'https://www.drupal.org/node/3223395#s-recommendations-for-deprecated-modules']
          ),
          new PluralTranslatableMarkup(
            count($removed_modules),
            'This module is installed on your site but is no longer provided by Core.',
            'These modules are installed on your site but are no longer provided by Core.'
          ),
        );
      }

      // Look for removed core themes.
      $is_removed_theme = function ($extension_name) use ($theme_extension_list) {
        return !$theme_extension_list->exists($extension_name)
            && array_key_exists($extension_name, DRUPAL_CORE_REMOVED_THEME_LIST);
      };
      $removed_themes = array_filter(array_keys($extension_config->get('theme')), $is_removed_theme);
      if (!empty($removed_themes)) {
        $list = [];
        foreach ($removed_themes as $removed_theme) {
          $list[] = t('<a href=":url">@theme</a>', [
            ':url' => "https://www.drupal.org/project/$removed_theme",
            '@theme' => DRUPAL_CORE_REMOVED_THEME_LIST[$removed_theme],
          ]);
        }
        $requirements['removed_theme'] = $create_extension_incompatibility_list(
          $list,
          new PluralTranslatableMarkup(
            count($removed_themes),
            'You must add the following contributed theme and reload this page.',
            'You must add the following contributed themes and reload this page.'
          ),
          new PluralTranslatableMarkup(
            count($removed_themes),
            'Removed core theme',
            'Removed core themes'
          ),
          new TranslatableMarkup(
            'For more information read the <a href=":url">documentation on deprecated themes.</a>',
            [':url' => 'https://www.drupal.org/node/3223395#s-recommendations-for-deprecated-themes']
          ),
          new PluralTranslatableMarkup(
            count($removed_themes),
            'This theme is installed on your site but is no longer provided by Core.',
            'These themes are installed on your site but are no longer provided by Core.'
          ),
        );
      }

      // Look for missing modules.
      $is_missing_module = function ($extension_name) use ($module_extension_list) {
        return !$module_extension_list->exists($extension_name) && !in_array($extension_name, array_keys(DRUPAL_CORE_REMOVED_MODULE_LIST), TRUE);
      };
      $invalid_modules = array_filter(array_keys($extension_config->get('module')), $is_missing_module);

      if (!empty($invalid_modules)) {
        $requirements['invalid_module'] = $create_extension_incompatibility_list(
          $invalid_modules,
          new PluralTranslatableMarkup(
            count($invalid_modules),
            'The following module is marked as installed in the core.extension configuration, but it is missing:',
            'The following modules are marked as installed in the core.extension configuration, but they are missing:'
          ),
          new PluralTranslatableMarkup(
            count($invalid_modules),
            'Missing or invalid module',
            'Missing or invalid modules'
          )
        );
      }

      // Look for invalid themes.
      $is_missing_theme = function ($extension_name) use (&$theme_extension_list) {
        return !$theme_extension_list->exists($extension_name) && !in_array($extension_name, array_keys(DRUPAL_CORE_REMOVED_THEME_LIST), TRUE);
      };
      $invalid_themes = array_filter(array_keys($extension_config->get('theme')), $is_missing_theme);
      if (!empty($invalid_themes)) {
        $requirements['invalid_theme'] = $create_extension_incompatibility_list(
          $invalid_themes,
          new PluralTranslatableMarkup(
            count($invalid_themes),
            'The following theme is marked as installed in the core.extension configuration, but it is missing:',
            'The following themes are marked as installed in the core.extension configuration, but they are missing:'
          ),
          new PluralTranslatableMarkup(
            count($invalid_themes),
            'Missing or invalid theme',
            'Missing or invalid themes'
          )
        );
      }
    }

    // Returns Unicode library status and errors.
    $libraries = [
      Unicode::STATUS_SINGLEBYTE => t('Standard PHP'),
      Unicode::STATUS_MULTIBYTE => t('PHP Mbstring Extension'),
      Unicode::STATUS_ERROR => t('Error'),
    ];
    $severities = [
      Unicode::STATUS_SINGLEBYTE => RequirementSeverity::Warning,
      Unicode::STATUS_MULTIBYTE => NULL,
      Unicode::STATUS_ERROR => RequirementSeverity::Error,
    ];
    $failed_check = Unicode::check();
    $library = Unicode::getStatus();

    $requirements['unicode'] = [
      'title' => t('Unicode library'),
      'value' => $libraries[$library],
      'severity' => $severities[$library],
    ];
    switch ($failed_check) {
      case 'mb_strlen':
        $requirements['unicode']['description'] = t('Operations on Unicode strings are emulated on a best-effort basis. Install the <a href="http://php.net/mbstring">PHP mbstring extension</a> for improved Unicode support.');
        break;

      case 'mbstring.encoding_translation':
        $requirements['unicode']['description'] = t('Multibyte string input conversion in PHP is active and must be disabled. Check the php.ini <em>mbstring.encoding_translation</em> setting. Refer to the <a href="http://php.net/mbstring">PHP mbstring documentation</a> for more information.');
        break;
    }

    if ($phase == 'runtime') {
      // Check for update status module.
      if (!\Drupal::moduleHandler()->moduleExists('update')) {
        $requirements['update status'] = [
          'value' => t('Not enabled'),
          'severity' => RequirementSeverity::Warning,
          'description' => t('Update notifications are not enabled. It is <strong>highly recommended</strong> that you install the Update Status module from the <a href=":module">module administration page</a> in order to stay up-to-date on new releases. For more information, <a href=":update">Update status handbook page</a>.', [
            ':update' => 'https://www.drupal.org/documentation/modules/update',
            ':module' => Url::fromRoute('system.modules_list')->toString(),
          ]),
        ];
      }
      else {
        $requirements['update status'] = [
          'value' => t('Enabled'),
        ];
      }
      $requirements['update status']['title'] = t('Update notifications');

      if (Settings::get('rebuild_access')) {
        $requirements['rebuild access'] = [
          'title' => t('Rebuild access'),
          'value' => t('Enabled'),
          'severity' => RequirementSeverity::Error,
          'description' => t('The rebuild_access setting is enabled in settings.php. It is recommended to have this setting disabled unless you are performing a rebuild.'),
        ];
      }
    }

    // Check if the SameSite cookie attribute is set to a valid value. Since
    // this involves checking whether we are using a secure connection this
    // only makes sense inside an HTTP request, not on the command line.
    if ($phase === 'runtime' && PHP_SAPI !== 'cli') {
      $samesite = ini_get('session.cookie_samesite') ?: t('Not set');
      // Check if the SameSite attribute is set to a valid value. If it is set
      // to 'None' the request needs to be done over HTTPS.
      $valid = match ($samesite) {
        'Lax', 'Strict' => TRUE,
          'None' => $request_object->isSecure(),
          default => FALSE,
      };
      $requirements['php_session_samesite'] = [
        'title' => t('SameSite cookie attribute'),
        'value' => $samesite,
        'severity' => $valid ? RequirementSeverity::OK : RequirementSeverity::Warning,
        'description' => t('This attribute should be explicitly set to Lax, Strict or None. If set to None then the request must be made via HTTPS. See <a href=":url" target="_blank">PHP documentation</a>', [
          ':url' => 'https://www.php.net/manual/en/session.configuration.php#ini.session.cookie-samesite',
        ]),
      ];
    }

    // See if trusted host names have been configured, and warn the user if they
    // are not set.
    if ($phase == 'runtime') {
      $trusted_host_patterns = Settings::get('trusted_host_patterns');
      if (empty($trusted_host_patterns)) {
        $requirements['trusted_host_patterns'] = [
          'title' => t('Trusted Host Settings'),
          'value' => t('Not enabled'),
          'description' => t('The trusted_host_patterns setting is not configured in settings.php. This can lead to security vulnerabilities. It is <strong>highly recommended</strong> that you configure this. See <a href=":url">Protecting against HTTP HOST Header attacks</a> for more information.', [':url' => 'https://www.drupal.org/docs/installing-drupal/trusted-host-settings']),
          'severity' => RequirementSeverity::Error,
        ];
      }
      else {
        $requirements['trusted_host_patterns'] = [
          'title' => t('Trusted Host Settings'),
          'value' => t('Enabled'),
          'description' => t('The trusted_host_patterns setting is set to allow %trusted_host_patterns', ['%trusted_host_patterns' => implode(', ', $trusted_host_patterns)]),
        ];
      }
    }

    // When the database driver is provided by a module, then check that the
    // providing module is installed.
    if ($phase === 'runtime' || $phase === 'update') {
      $connection = Database::getConnection();
      $provider = $connection->getProvider();
      if ($provider !== 'core' && !\Drupal::moduleHandler()->moduleExists($provider)) {
        $autoload = $connection->getConnectionOptions()['autoload'] ?? '';
        if (str_contains($autoload, 'src/Driver/Database/')) {
          $post_update_registry = \Drupal::service('update.post_update_registry');
          $pending_updates = $post_update_registry->getPendingUpdateInformation();
          if (!in_array('enable_provider_database_driver', array_keys($pending_updates['system']['pending'] ?? []), TRUE)) {
            // Only show the warning when the post update function has run and
            // the module that is providing the database driver is not
            // installed.
            $requirements['database_driver_provided_by_module'] = [
              'title' => t('Database driver provided by module'),
              'value' => t('Not installed'),
              'description' => t('The current database driver is provided by the module: %module. The module is currently not installed. You should immediately <a href=":install">install</a> the module.', ['%module' => $provider, ':install' => Url::fromRoute('system.modules_list')->toString()]),
              'severity' => RequirementSeverity::Error,
            ];
          }
        }
      }
    }

    // Check xdebug.max_nesting_level, as some pages will not work if it is too
    // low.
    if (extension_loaded('xdebug')) {
      // Setting this value to 256 was considered adequate on Xdebug 2.3
      // (see http://bugs.xdebug.org/bug_view_page.php?bug_id=00001100)
      $minimum_nesting_level = 256;
      $current_nesting_level = ini_get('xdebug.max_nesting_level');

      if ($current_nesting_level < $minimum_nesting_level) {
        $requirements['xdebug_max_nesting_level'] = [
          'title' => t('Xdebug settings'),
          'value' => t('xdebug.max_nesting_level is set to %value.', ['%value' => $current_nesting_level]),
          'description' => t('Set <code>xdebug.max_nesting_level=@level</code> in your PHP configuration as some pages in your Drupal site will not work when this setting is too low.', ['@level' => $minimum_nesting_level]),
          'severity' => RequirementSeverity::Error,
        ];
      }
    }

    // Installations on Windows can run into limitations with MAX_PATH if the
    // Drupal root directory is too deep in the filesystem. Generally this
    // shows up in cached Twig templates and other public files with long
    // directory or file names. There is no definite root directory depth below
    // which Drupal is guaranteed to function correctly on Windows. Since
    // problems are likely with more than 100 characters in the Drupal root
    // path, show an error.
    if (str_starts_with(PHP_OS, 'WIN')) {
      $depth = strlen(realpath(DRUPAL_ROOT . '/' . PublicStream::basePath()));
      if ($depth > 120) {
        $requirements['max_path_on_windows'] = [
          'title' => t('Windows installation depth'),
          'description' => t('The public files directory path is %depth characters. Paths longer than 120 characters will cause problems on Windows.', ['%depth' => $depth]),
          'severity' => RequirementSeverity::Error,
        ];
      }
    }
    // Check to see if dates will be limited to 1901-2038.
    if (PHP_INT_SIZE <= 4) {
      $requirements['limited_date_range'] = [
        'title' => t('Limited date range'),
        'value' => t('Your PHP installation has a limited date range.'),
        'description' => t('You are running on a system where PHP is compiled or limited to using 32-bit integers. This will limit the range of dates and timestamps to the years 1901-2038. Read about the <a href=":url">limitations of 32-bit PHP</a>.', [':url' => 'https://www.drupal.org/docs/system-requirements/limitations-of-32-bit-php']),
        'severity' => RequirementSeverity::Warning,
      ];
    }

    // During installs from configuration don't support install profiles that
    // implement hook_install.
    if ($phase == 'install' && !empty($install_state['config_install_path'])) {
      $install_hook = $install_state['parameters']['profile'] . '_install';
      if (function_exists($install_hook)) {
        $requirements['config_install'] = [
          'title' => t('Configuration install'),
          'value' => $install_state['parameters']['profile'],
          'description' => t('The selected profile has a hook_install() implementation and therefore can not be installed from configuration.'),
          'severity' => RequirementSeverity::Error,
        ];
      }
    }

    if ($phase === 'runtime') {
      $settings = Settings::getAll();
      if (array_key_exists('install_profile', $settings)) {
        // The following message is only informational because not all site
        // owners have access to edit their settings.php as it may be
        // controlled by their hosting provider.
        $requirements['install_profile_in_settings'] = [
          'title' => t('Install profile in settings'),
          'value' => t("Drupal 9 no longer uses the \$settings['install_profile'] value in settings.php and it should be removed."),
          'severity' => RequirementSeverity::Warning,
        ];
      }
    }

    // Ensure that no module has a current schema version that is lower than the
    // one that was last removed.
    if ($phase == 'update') {
      $module_handler = \Drupal::moduleHandler();
      /** @var \Drupal\Core\Update\UpdateHookRegistry $update_registry */
      $update_registry = \Drupal::service('update.update_hook_registry');
      $module_list = [];
      // hook_update_last_removed() is a procedural hook hook because we
      // do not have classes loaded that would be needed.
      // Simply inlining the old hook mechanism is better than making
      // ModuleInstaller::invoke() public.
      foreach ($module_handler->getModuleList() as $module => $extension) {
        $function = $module . '_update_last_removed';
        if (function_exists($function)) {
          $last_removed = $function();
          if ($last_removed && $last_removed > $update_registry->getInstalledVersion($module)) {

            /** @var \Drupal\Core\Extension\Extension $module_info */
            $module_info = $module_extension_list->get($module);
            $module_list[$module] = [
              'name' => $module_info->info['name'],
              'last_removed' => $last_removed,
              'installed_version' => $update_registry->getInstalledVersion($module),
            ];
          }
        }
      }

      // If user module is in the list then only show a specific message for
      // Drupal core.
      if (isset($module_list['user'])) {
        $requirements['user_update_last_removed'] = [
          'title' => t('The version of Drupal you are trying to update from is too old'),
          'description' => t('Updating to Drupal @current_major is only supported from Drupal version @required_min_version or higher. If you are trying to update from an older version, first update to the latest version of Drupal @previous_major. (<a href=":url">Drupal upgrade guide</a>)', [
            '@current_major' => 10,
            '@required_min_version' => '9.4.0',
            '@previous_major' => 9,
            ':url' => 'https://www.drupal.org/docs/upgrading-drupal/drupal-8-and-higher',
          ]),
          'severity' => RequirementSeverity::Error,
        ];
      }
      else {
        foreach ($module_list as $module => $data) {
          $requirements[$module . '_update_last_removed'] = [
            'title' => t('Unsupported schema version: @module', ['@module' => $data['name']]),
            'description' => t('The installed version of the %module module is too old to update. Update to an intermediate version first (last removed version: @last_removed_version, installed version: @installed_version).', [
              '%module' => $data['name'],
              '@last_removed_version' => $data['last_removed'],
              '@installed_version' => $data['installed_version'],
            ]),
            'severity' => RequirementSeverity::Error,
          ];
        }
      }
      // Also check post-updates. Only do this if we're not already showing an
      // error for hook_update_N().
      $missing_updates = [];
      if (empty($module_list)) {
        $existing_updates = \Drupal::service('keyvalue')->get('post_update')->get('existing_updates', []);
        $post_update_registry = \Drupal::service('update.post_update_registry');
        $modules = \Drupal::moduleHandler()->getModuleList();
        foreach ($modules as $module => $extension) {
          $module_info = $module_extension_list->get($module);
          $removed_post_updates = $post_update_registry->getRemovedPostUpdates($module);
          if ($missing_updates = array_diff(array_keys($removed_post_updates), $existing_updates)) {
            $versions = array_unique(array_intersect_key($removed_post_updates, array_flip($missing_updates)));
            $description = new PluralTranslatableMarkup(count($versions),
              'The installed version of the %module module is too old to update. Update to a version prior to @versions first (missing updates: @missing_updates).',
              'The installed version of the %module module is too old to update. Update first to a version prior to all of the following: @versions (missing updates: @missing_updates).',
              [
                '%module' => $module_info->info['name'],
                '@missing_updates' => implode(', ', $missing_updates),
                '@versions' => implode(', ', $versions),
              ]
            );
            $requirements[$module . '_post_update_removed'] = [
              'title' => t('Missing updates for: @module', ['@module' => $module_info->info['name']]),
              'description' => $description,
              'severity' => RequirementSeverity::Error,
            ];
          }
        }
      }

      if (empty($missing_updates)) {
        foreach ($update_registry->getAllEquivalentUpdates() as $module => $equivalent_updates) {
          $module_info = $module_extension_list->get($module);
          foreach ($equivalent_updates as $future_update => $data) {
            $future_update_function_name = $module . '_update_' . $future_update;
            $ran_update_function_name = $module . '_update_' . $data['ran_update'];
            // If an update was marked as an equivalent by a previous update,
            // and both the previous update and the equivalent update are not
            // found in the current code base, prevent updating. This indicates
            // a site attempting to go 'backwards' in terms of database schema.
            // @see \Drupal\Core\Update\UpdateHookRegistry::markFutureUpdateEquivalent()
            if (!function_exists($ran_update_function_name) && !function_exists($future_update_function_name)) {
              // If the module is provided by core prepend helpful text as the
              // module does not exist in composer or Drupal.org.
              if (str_starts_with($module_info->getPathname(), 'core/')) {
                $future_version_string = 'Drupal Core ' . $data['future_version_string'];
              }
              else {
                $future_version_string = $data['future_version_string'];
              }
              $requirements[$module . '_equivalent_update_missing'] = [
                'title' => t('Missing updates for: @module', ['@module' => $module_info->info['name']]),
                'description' => t('The version of the %module module that you are attempting to update to is missing update @future_update (which was marked as an equivalent by @ran_update). Update to at least @future_version_string.', [
                  '%module' => $module_info->info['name'],
                  '@ran_update' => $data['ran_update'],
                  '@future_update' => $future_update,
                  '@future_version_string' => $future_version_string,
                ]),
                'severity' => RequirementSeverity::Error,
              ];
              break;
            }
          }
        }
      }
    }

    // Add warning when twig debug option is enabled.
    if ($phase === 'runtime') {
      $development_settings = \Drupal::keyValue('development_settings');
      $twig_debug = $development_settings->get('twig_debug', FALSE);
      $twig_cache_disable = $development_settings->get('twig_cache_disable', FALSE);
      if ($twig_debug || $twig_cache_disable) {
        $requirements['twig_debug_enabled'] = [
          'title' => t('Twig development mode'),
          'value' => t('Twig development mode settings are turned on. Go to @link to disable them.', [
            '@link' => Link::createFromRoute(
              'development settings page',
              'system.development_settings',
            )->toString(),
          ]),
          'severity' => RequirementSeverity::Warning,
        ];
      }
      $render_cache_disabled = $development_settings->get('disable_rendered_output_cache_bins', FALSE);
      if ($render_cache_disabled) {
        $requirements['render_cache_disabled'] = [
          'title' => t('Markup caching disabled'),
          'value' => t('Render cache, dynamic page cache, and page cache are bypassed. Go to @link to enable them.', [
            '@link' => Link::createFromRoute(
              'development settings page',
              'system.development_settings',
            )->toString(),
          ]),
          'severity' => RequirementSeverity::Warning,
        ];
      }
    }

    return $requirements;
  }

  /**
   * Display requirements from security advisories.
   *
   * @param array[] $requirements
   *   The requirements array as specified in hook_requirements().
   */
  public static function systemAdvisoriesRequirements(array &$requirements): void {
    if (!\Drupal::config('system.advisories')->get('enabled')) {
      return;
    }

    /** @var \Drupal\system\SecurityAdvisories\SecurityAdvisoriesFetcher $fetcher */
    $fetcher = \Drupal::service('system.sa_fetcher');
    try {
      $advisories = $fetcher->getSecurityAdvisories(TRUE, 5);
    }
    catch (ClientExceptionInterface $exception) {
      $requirements['system_advisories']['title'] = t('Critical security announcements');
      $requirements['system_advisories']['severity'] = RequirementSeverity::Warning;
      $requirements['system_advisories']['description'] = ['#theme' => 'system_security_advisories_fetch_error_message'];
      Error::logException(\Drupal::logger('system'), $exception, 'Failed to retrieve security advisory data.');
      return;
    }

    if (!empty($advisories)) {
      $advisory_links = [];
      $severity = RequirementSeverity::Warning;
      foreach ($advisories as $advisory) {
        if (!$advisory->isPsa()) {
          $severity = RequirementSeverity::Error;
        }
        $advisory_links[] = new Link($advisory->getTitle(), Url::fromUri($advisory->getUrl()));
      }
      $requirements['system_advisories']['title'] = t('Critical security announcements');
      $requirements['system_advisories']['severity'] = $severity;
      $requirements['system_advisories']['description'] = [
        'list' => [
          '#theme' => 'item_list',
          '#items' => $advisory_links,
        ],
      ];
      if (\Drupal::moduleHandler()->moduleExists('help')) {
        $requirements['system_advisories']['description']['help_link'] = Link::createFromRoute(
          'What are critical security announcements?',
          'help.page', ['name' => 'system'],
          ['fragment' => 'security-advisories']
        )->toRenderable();
      }
    }
  }

}
