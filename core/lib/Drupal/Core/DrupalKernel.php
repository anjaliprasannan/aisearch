<?php

namespace Drupal\Core;

use Composer\Autoload\ClassLoader;
use Drupal\Component\EventDispatcher\Event;
use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Component\Serialization\PhpSerialize;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\DatabaseBackend;
use Drupal\Core\ClassLoader\BackwardsCompatibilityClassLoader;
use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\Config\NullStorage;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Component\DependencyInjection\ReverseContainer;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\DependencyInjection\YamlFileLoader;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\Core\File\MimeType\MimeTypeGuesser;
use Drupal\Core\Http\TrustedHostsRequestFactory;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Installer\InstallerRedirectTrait;
use Drupal\Core\Language\Language;
use Drupal\Core\Security\RequestSanitizer;
use Drupal\Core\Site\Settings;
use Drupal\Core\Test\TestDatabase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * The DrupalKernel class is the core of Drupal itself.
 *
 * This class is responsible for building the Dependency Injection Container and
 * also deals with the registration of service providers. It allows registered
 * service providers to add their services to the container. Core provides the
 * CoreServiceProvider, which, in addition to registering any core services that
 * cannot be registered in the core.services.yaml file, adds any compiler passes
 * needed by core, e.g. for processing tagged services. Each module can add its
 * own service provider, i.e. a class implementing
 * Drupal\Core\DependencyInjection\ServiceProvider, to register services to the
 * container, or modify existing services.
 */
class DrupalKernel implements DrupalKernelInterface, TerminableInterface {
  use InstallerRedirectTrait;

  /**
   * Holds the class used for dumping the container to a PHP array.
   *
   * In combination with swapping the container class this is useful to e.g.
   * dump to the human-readable PHP array format to debug the container
   * definition in an easier way.
   *
   * @var string
   */
  protected $phpArrayDumperClass = '\Drupal\Component\DependencyInjection\Dumper\OptimizedPhpArrayDumper';

  /**
   * Holds the default bootstrap container definition.
   *
   * @var array
   */
  protected $defaultBootstrapContainerDefinition = [
    'parameters' => [],
    'services' => [
      'database' => [
        'class' => 'Drupal\Core\Database\Connection',
        'factory' => 'Drupal\Core\Database\Database::getConnection',
        'arguments' => ['default'],
      ],
      'request_stack' => [
        'class' => 'Symfony\Component\HttpFoundation\RequestStack',
      ],
      'datetime.time' => [
        'class' => 'Drupal\Component\Datetime\Time',
        'arguments' => ['@request_stack'],
      ],
      'cache.container' => [
        'class' => 'Drupal\Core\Cache\DatabaseBackend',
        'arguments' => [
          '@database',
          '@cache_tags_provider.container',
          'container',
          '@serialization.phpserialize',
          '@datetime.time',
          DatabaseBackend::MAXIMUM_NONE,
        ],
      ],
      'cache_tags_provider.container' => [
        'class' => 'Drupal\Core\Cache\DatabaseCacheTagsChecksum',
        'arguments' => ['@database'],
      ],
      'serialization.phpserialize' => [
        'class' => PhpSerialize::class,
      ],
    ],
  ];

  /**
   * Holds the class used for instantiating the bootstrap container.
   *
   * @var string
   */
  protected $bootstrapContainerClass = '\Drupal\Component\DependencyInjection\PhpArrayContainer';

  /**
   * Holds the bootstrap container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $bootstrapContainer;

  /**
   * Holds the container instance.
   *
   * @var \Drupal\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The environment, e.g. 'testing', 'install'.
   *
   * @var string
   */
  protected $environment;

  /**
   * Whether the kernel has been booted.
   *
   * @var bool
   */
  protected $booted = FALSE;

  /**
   * Whether essential services have been set up properly by preHandle().
   *
   * @var bool
   */
  protected $prepared = FALSE;

  /**
   * Holds the list of enabled modules.
   *
   * @var array
   *   An associative array whose keys are module names and whose values are
   *   ignored.
   */
  protected $moduleList;

  /**
   * List of available modules and installation profiles.
   *
   * @var \Drupal\Core\Extension\Extension[]
   */
  protected $moduleData = [];

  /**
   * The class loader object.
   *
   * @var \Composer\Autoload\ClassLoader
   */
  protected $classLoader;

  /**
   * Config storage object used for reading enabled modules configuration.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * Whether the container can be dumped.
   *
   * @var bool
   */
  protected $allowDumping;

  /**
   * Whether the container needs to be rebuilt the next time it is initialized.
   *
   * @var bool
   */
  protected $containerNeedsRebuild = FALSE;

  /**
   * Whether the container needs to be dumped once booting is complete.
   *
   * @var bool
   */
  protected $containerNeedsDumping;

  /**
   * List of discovered services.yml path names.
   *
   * This is a nested array whose top-level keys are 'app' and 'site', denoting
   * the origin of a service provider. Site-specific providers have to be
   * collected separately, because they need to be processed last, so as to be
   * able to override services from application service providers.
   *
   * @var array
   */
  protected $serviceYamls;

  /**
   * List of discovered service provider class names or objects.
   *
   * This is a nested array whose top-level keys are 'app' and 'site', denoting
   * the origin of a service provider. Site-specific providers have to be
   * collected separately, because they need to be processed last, so as to be
   * able to override services from application service providers.
   *
   * Allowing objects is for example used to allow
   * \Drupal\KernelTests\KernelTestBase to register itself as service provider.
   *
   * @var array
   */
  protected $serviceProviderClasses;

  /**
   * List of instantiated service provider classes.
   *
   * @var array
   *
   * @see \Drupal\Core\DrupalKernel::$serviceProviderClasses
   */
  protected $serviceProviders;

  /**
   * Whether the PHP environment has been initialized.
   *
   * This legacy phase can only be booted once because it sets session INI
   * settings. If a session has already been started, re-generating these
   * settings would break the session.
   *
   * @var bool
   */
  protected static $isEnvironmentInitialized = FALSE;

  /**
   * The site path directory.
   *
   * Site path is relative to the app root directory.
   * Usually defined as "sites/default".
   *
   * By default, Drupal uses sites/default.
   *
   * @var string
   */
  protected $sitePath;

  /**
   * The app root.
   *
   * @var string
   */
  protected $root;

  /**
   * Create a DrupalKernel object from a request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Composer\Autoload\ClassLoader $class_loader
   *   The class loader. Normally Composer's ClassLoader, as included by the
   *   front controller, but may also be decorated.
   * @param string $environment
   *   String indicating the environment, e.g. 'prod' or 'dev'.
   * @param bool $allow_dumping
   *   (optional) FALSE to stop the container from being written to or read
   *   from disk. Defaults to TRUE.
   * @param string $app_root
   *   (optional) The path to the application root as a string. If not supplied,
   *   the application root will be computed.
   *
   * @return static
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   In case the host name in the request is not trusted.
   */
  public static function createFromRequest(Request $request, $class_loader, $environment, $allow_dumping = TRUE, $app_root = NULL) {
    $kernel = new static($environment, $class_loader, $allow_dumping, $app_root);
    static::bootEnvironment($app_root);
    $kernel->initializeSettings($request);
    return $kernel;
  }

  /**
   * Constructs a DrupalKernel object.
   *
   * @param string $environment
   *   String indicating the environment, e.g. 'prod' or 'dev'.
   * @param \Composer\Autoload\ClassLoader $class_loader
   *   The class loader. Normally \Composer\Autoload\ClassLoader, as included by
   *   the front controller, but may also be decorated.
   * @param bool $allow_dumping
   *   (optional) FALSE to stop the container from being written to or read
   *   from disk. Defaults to TRUE.
   * @param string $app_root
   *   (optional) The path to the application root as a string. If not supplied,
   *   the application root will be computed.
   */
  public function __construct($environment, $class_loader, $allow_dumping = TRUE, $app_root = NULL) {
    $this->environment = $environment;
    $this->classLoader = $class_loader;
    $this->allowDumping = $allow_dumping;
    if ($app_root === NULL) {
      $app_root = static::guessApplicationRoot();
    }
    $this->root = $app_root;
  }

  /**
   * Determine the application root directory based on this file's location.
   *
   * @return string
   *   The application root.
   */
  protected static function guessApplicationRoot() {
    // Determine the application root by:
    // - Removing the namespace directories from the path.
    // - Getting the path to the directory two levels up from the path
    //   determined in the previous step.
    return dirname(substr(__DIR__, 0, -strlen(__NAMESPACE__)), 2);
  }

  /**
   * Returns the appropriate site directory for a request.
   *
   * Once the kernel has been created DrupalKernelInterface::getSitePath() is
   * preferred since it gets the statically cached result of this method.
   *
   * Site directories contain all site specific code. This includes settings.php
   * for bootstrap level configuration, file configuration stores, public file
   * storage and site specific modules and themes.
   *
   * A file named sites.php must be present in the sites directory for
   * multisite. If it doesn't exist, then 'sites/default' will be used.
   *
   * Finds a matching site directory file by stripping the website's hostname
   * from left to right and pathname from right to left. By default, the
   * directory must contain a 'settings.php' file for it to match. If the
   * parameter $require_settings is set to FALSE, then a directory without a
   * 'settings.php' file will match as well. The first configuration file found
   * will be used and the remaining ones will be ignored. If no configuration
   * file is found, returns a default value 'sites/default'. See
   * default.settings.php for examples on how the URL is converted to a
   * directory.
   *
   * The sites.php file in the sites directory can define aliases in an
   * associative array named $sites. The array is written in the format
   * '<port>.<domain>.<path>' => 'directory'. As an example, to create a
   * directory alias for https://www.drupal.org:8080/my-site/test whose
   * configuration file is in sites/example.com, the array should be defined as:
   * @code
   * $sites = [
   *   '8080.www.drupal.org.my-site.test' => 'example.com',
   * ];
   * @endcode
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param bool $require_settings
   *   Only directories with an existing settings.php file will be recognized.
   *   Defaults to TRUE. During initial installation, this is set to FALSE so
   *   that Drupal can detect a matching directory, then create a new
   *   settings.php file in it.
   * @param string $app_root
   *   (optional) The path to the application root as a string. If not supplied,
   *   the application root will be computed.
   *
   * @return string
   *   The path of the matching directory.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   In case the host name in the request is invalid.
   *
   * @see \Drupal\Core\DrupalKernelInterface::getSitePath()
   * @see \Drupal\Core\DrupalKernelInterface::setSitePath()
   * @see default.settings.php
   * @see example.sites.php
   */
  public static function findSitePath(Request $request, $require_settings = TRUE, $app_root = NULL) {
    if (static::validateHostname($request) === FALSE) {
      throw new BadRequestHttpException();
    }

    if ($app_root === NULL) {
      $app_root = static::guessApplicationRoot();
    }

    // Check for a test override.
    if ($test_prefix = drupal_valid_test_ua()) {
      $test_db = new TestDatabase($test_prefix);
      return $test_db->getTestSitePath();
    }

    // Determine whether multi-site functionality is enabled. If not, return
    // the default directory.
    if (!is_file($app_root . '/sites/sites.php')) {
      return 'sites/default';
    }

    // Pre-populate host and script variables, then include sites.php which may
    // populate $sites with a site-directory mapping.
    $script_name = $request->server->get('SCRIPT_NAME');
    if (!$script_name) {
      $script_name = $request->server->get('SCRIPT_FILENAME');
    }
    $http_host = $request->getHttpHost();

    $sites = [];
    include $app_root . '/sites/sites.php';

    // Construct an identifier from pieces of the (port plus) host plus script
    // path (excluding the filename). Loop over all possibilities starting from
    // most specific, then dropping pieces from the start of the port/hostname
    // while keeping the full path, then gradually dropping pieces from the end
    // of the path... until we find a directory corresponding to the identifier.
    $path_parts = explode('/', $script_name);
    $host_parts = explode('.', implode('.', array_reverse(explode(':', rtrim($http_host, '.')))));
    for ($i = count($path_parts) - 1; $i > 0; $i--) {
      for ($j = count($host_parts); $j > 0; $j--) {
        // Assume the path has a leading slash, so the imploded path parts are
        // either a path identifier with leading dot, or an empty string.
        $site_id = implode('.', array_slice($host_parts, -$j)) . implode('.', array_slice($path_parts, 0, $i));

        // If the identifier is a key in $sites, check for a directory matching
        // the corresponding value. Otherwise, check for a directory matching
        // the identifier.
        if (isset($sites[$site_id]) && is_dir($app_root . '/sites/' . $sites[$site_id])) {
          $site_id = $sites[$site_id];
        }
        if (is_file($app_root . '/sites/' . $site_id . '/settings.php') || (!$require_settings && is_file($app_root . '/sites/' . $site_id))) {
          return "sites/$site_id";
        }
      }
    }
    return 'sites/default';
  }

  /**
   * {@inheritdoc}
   */
  public function setSitePath($path) {
    if ($this->booted && $path !== $this->sitePath) {
      throw new \LogicException('Site path cannot be changed after calling boot()');
    }
    $this->sitePath = $path;
  }

  /**
   * {@inheritdoc}
   */
  public function getSitePath() {
    return $this->sitePath;
  }

  /**
   * {@inheritdoc}
   */
  public function getAppRoot() {
    return $this->root;
  }

  /**
   * {@inheritdoc}
   */
  public function boot() {
    if ($this->booted) {
      return $this;
    }

    // Ensure that findSitePath is set.
    if (!$this->sitePath) {
      throw new \Exception('Kernel does not have site path set before calling boot()');
    }

    // Initialize the FileCacheFactory component. We have to do it here instead
    // of in \Drupal\Component\FileCache\FileCacheFactory because we can not use
    // the Settings object in a component.
    $configuration = Settings::get('file_cache');

    // Provide a default configuration, if not set.
    if (!isset($configuration['default'])) {
      // @todo Use extension_loaded('apcu') for non-testbot
      //   https://www.drupal.org/node/2447753.
      if (function_exists('apcu_fetch')) {
        $configuration['default']['cache_backend_class'] = '\Drupal\Component\FileCache\ApcuFileCacheBackend';
      }
    }
    FileCacheFactory::setConfiguration($configuration);
    FileCacheFactory::setPrefix(Settings::getApcuPrefix('file_cache', $this->root));

    $this->bootstrapContainer = new $this->bootstrapContainerClass(Settings::get('bootstrap_container_definition', $this->defaultBootstrapContainerDefinition));

    // Initialize the container.
    $this->initializeContainer();

    // Add the APCu prefix to use to cache found/not-found classes.
    if (Settings::get('class_loader_auto_detect', TRUE) && method_exists($this->classLoader, 'setApcuPrefix')) {
      // Vary the APCu key by which modules are installed to allow
      // class_exists() checks to determine functionality.
      $id = 'class_loader:' . crc32(implode(':', array_keys($this->container->getParameter('container.modules'))));
      $prefix = Settings::getApcuPrefix($id, $this->root);
      $this->classLoader->setApcuPrefix($prefix);
    }

    if ($this->container->hasParameter('moved_classes')) {
      $bc_class_loader = new BackwardsCompatibilityClassLoader($this->container->getParameter('moved_classes'));
      spl_autoload_register([$bc_class_loader, 'loadClass']);
    }

    $this->booted = TRUE;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function shutdown() {
    if (FALSE === $this->booted) {
      return;
    }
    $this->container->get('stream_wrapper_manager')->unregister();
    $this->booted = FALSE;
    $this->configStorage = NULL;
    $this->container = NULL;
    $this->moduleList = NULL;
    $this->moduleData = [];
  }

  /**
   * {@inheritdoc}
   */
  public function getContainer() {
    return $this->container;
  }

  /**
   * {@inheritdoc}
   */
  public function getCachedContainerDefinition() {
    $cache = $this->bootstrapContainer->get('cache.container')->get($this->getContainerCacheKey());

    if ($cache) {
      return $cache->data;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadLegacyIncludes() {
    require_once $this->root . '/core/includes/common.inc';
    require_once $this->root . '/core/includes/module.inc';
    require_once $this->root . '/core/includes/theme.inc';
    require_once $this->root . '/core/includes/form.inc';
    require_once $this->root . '/core/includes/errors.inc';
  }

  /**
   * {@inheritdoc}
   */
  public function preHandle(Request $request) {
    // Sanitize the request.
    $request = RequestSanitizer::sanitize(
      $request,
      (array) Settings::get(RequestSanitizer::SANITIZE_INPUT_SAFE_KEYS, []),
      (bool) Settings::get(RequestSanitizer::SANITIZE_LOG, FALSE)
    );

    // Ensure that there is a session on every request.
    if (!$request->hasSession()) {
      $this->initializeEphemeralSession($request);
    }

    $this->loadLegacyIncludes();

    // Load all enabled modules.
    $this->container->get('module_handler')->loadAll();

    // Register stream wrappers.
    $this->container->get('stream_wrapper_manager')->register();

    // Initialize legacy request globals.
    $this->initializeRequestGlobals($request);

    // Put the request on the stack.
    $this->container->get('request_stack')->push($request);

    // Set the allowed protocols.
    UrlHelper::setAllowedProtocols($this->container->getParameter('filter_protocols'));

    // Override of Symfony's MIME type guesser singleton.
    MimeTypeGuesser::registerWithSymfonyGuesser($this->container);

    $this->prepared = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function discoverServiceProviders() {
    $this->serviceYamls = [
      'app' => [],
      'site' => [],
    ];
    $this->serviceProviderClasses = [
      'app' => [],
      'site' => [],
    ];
    $this->serviceYamls['app']['core'] = 'core/core.services.yml';
    $this->serviceProviderClasses['app']['core'] = 'Drupal\Core\CoreServiceProvider';

    // Retrieve enabled modules and register their namespaces.
    if (!isset($this->moduleList)) {
      $extensions = $this->getExtensions();
      // If core.extension configuration does not exist and we're not in the
      // installer itself, then we need to put the kernel into a pre-installer
      // mode. The container should not be dumped because Drupal is yet to be
      // installed. The installer service provider is registered to ensure that
      // cache and other automatically created tables are not created if
      // database settings are available. None of this is required when the
      // installer is running because the installer has its own kernel and
      // manages the addition of its own service providers.
      // @see install_begin_request()
      if ($extensions === FALSE && !InstallerKernel::installationAttempted()) {
        $this->allowDumping = FALSE;
        $this->containerNeedsDumping = FALSE;
        $GLOBALS['conf']['container_service_providers']['InstallerServiceProvider'] = 'Drupal\Core\Installer\InstallerServiceProvider';
      }
      $this->moduleList = $extensions['module'] ?? [];
    }
    $module_filenames = $this->getModuleFileNames();
    $this->classLoaderAddMultiplePsr4($this->getModuleNamespacesPsr4($module_filenames));

    // Load each module's serviceProvider class.
    foreach ($module_filenames as $module => $filename) {
      $camelized = ContainerBuilder::camelize($module);
      $name = "{$camelized}ServiceProvider";
      $class = "Drupal\\{$module}\\{$name}";
      if (class_exists($class)) {
        $this->serviceProviderClasses['app'][$module] = $class;
      }
      $filename = dirname($filename) . "/$module.services.yml";
      if (is_file($filename)) {
        $this->serviceYamls['app'][$module] = $filename;
      }
    }

    // Add site-specific service providers.
    if (!empty($GLOBALS['conf']['container_service_providers'])) {
      foreach ($GLOBALS['conf']['container_service_providers'] as $class) {
        if ((is_string($class) && class_exists($class)) || (is_object($class) && ($class instanceof ServiceProviderInterface || $class instanceof ServiceModifierInterface))) {
          $this->serviceProviderClasses['site'][] = $class;
        }
      }
    }
    $this->addServiceFiles(Settings::get('container_yamls', []));
  }

  /**
   * {@inheritdoc}
   */
  public function getServiceProviders($origin) {
    return $this->serviceProviders[$origin];
  }

  /**
   * {@inheritdoc}
   */
  public function terminate(Request $request, Response $response): void {
    if ($this->booted && $this->getHttpKernel() instanceof TerminableInterface) {
      // Only run terminate() when essential services have been set up properly
      // by preHandle() before.
      if ($this->prepared === TRUE) {
        $this->getHttpKernel()->terminate($request, $response);
      }
      // For destructable services, always call the destruct method if they were
      // initialized during the request. Destruction is not necessary if the
      // service was not used.
      foreach ($this->container->getParameter('kernel.destructable_services') as $id) {
        if ($this->container->initialized($id)) {
          $service = $this->container->get($id);
          $service->destruct();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    // Ensure sane PHP environment variables.
    static::bootEnvironment();

    try {
      if (!$this->booted) {
        $this->initializeSettings($request);
        $this->boot();
      }
      $response = $this->getHttpKernel()->handle($request, $type, $catch);
    }
    catch (\Exception $e) {
      if ($catch === FALSE) {
        throw $e;
      }

      $response = $this->handleException($e, $request, $type);
    }

    // Adapt response headers to the current request.
    $response->prepare($request);

    return $response;
  }

  /**
   * Converts an exception into a response.
   *
   * @param \Exception $e
   *   An exception.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A Request instance.
   * @param int $type
   *   The type of the request (one of HttpKernelInterface::MAIN_REQUEST or
   *   HttpKernelInterface::SUB_REQUEST)
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A Response instance
   *
   * @throws \Exception
   *   If the passed in exception cannot be turned into a response.
   */
  protected function handleException(\Exception $e, $request, $type) {
    if ($this->shouldRedirectToInstaller($e, $this->container ? $this->container->get('database') : NULL)) {
      return new RedirectResponse($request->getBasePath() . '/core/install.php', 302, ['Cache-Control' => 'no-cache']);
    }

    if ($e instanceof HttpExceptionInterface) {
      $response = new Response($e->getMessage(), $e->getStatusCode());
      $response->headers->add($e->getHeaders());
      $response->headers->set('Content-Type', 'text/plain');
      return $response;
    }

    throw $e;
  }

  /**
   * Returns module data on the filesystem.
   *
   * @param string $module
   *   The name of the module.
   *
   * @return \Drupal\Core\Extension\Extension|bool
   *   Returns an Extension object if the module is found, FALSE otherwise.
   */
  protected function moduleData($module) {
    if (!$this->moduleData) {
      // First, find profiles.
      $listing = new ExtensionDiscovery($this->root);
      $listing->setProfileDirectories([]);
      $all_profiles = $listing->scan('profile');
      $profiles = array_intersect_key($all_profiles, $this->moduleList);

      $profile_directories = array_map(function (Extension $profile) {
        return $profile->getPath();
      }, $profiles);
      $listing->setProfileDirectories($profile_directories);

      // Now find modules.
      $this->moduleData = $profiles + $listing->scan('module');
    }
    return $this->moduleData[$module] ?? FALSE;
  }

  /**
   * Implements Drupal\Core\DrupalKernelInterface::updateModules().
   *
   * @todo Remove obsolete $module_list parameter. Only $module_filenames is
   *   needed.
   */
  public function updateModules(array $module_list, array $module_filenames = []) {
    $pre_existing_module_namespaces = [];
    if ($this->booted && is_array($this->moduleList)) {
      $pre_existing_module_namespaces = $this->getModuleNamespacesPsr4($this->getModuleFileNames());
    }
    $this->moduleList = $module_list;
    foreach ($module_filenames as $name => $extension) {
      $this->moduleData[$name] = $extension;
    }

    // If we haven't yet booted, we don't need to do anything: the new module
    // list will take effect when boot() is called. However we set a
    // flag that the container needs a rebuild, so that a potentially cached
    // container is not used. If we have already booted, then rebuild the
    // container in order to refresh the serviceProvider list and container.
    $this->containerNeedsRebuild = TRUE;
    if ($this->booted) {
      // We need to register any new namespaces to a new class loader because
      // the current class loader might have stored a negative result for a
      // class that is now available.
      // @see \Composer\Autoload\ClassLoader::findFile()
      $new_namespaces = array_diff_key(
        $this->getModuleNamespacesPsr4($this->getModuleFileNames()),
        $pre_existing_module_namespaces
      );
      if (!empty($new_namespaces)) {
        $additional_class_loader = new ClassLoader();
        $this->classLoaderAddMultiplePsr4($new_namespaces, $additional_class_loader);
        $additional_class_loader->register();
      }

      $this->initializeContainer();
    }
  }

  /**
   * Returns the container cache key based on the environment.
   *
   * The 'environment' consists of:
   * - The kernel environment string.
   * - The Drupal version constant.
   * - The deployment identifier from settings.php. This allows custom
   *   deployments to force a container rebuild.
   * - The operating system running PHP. This allows compiler passes to optimize
   *   services for different operating systems.
   * - The paths to any additional container YAMLs from settings.php.
   *
   * @return string
   *   The cache key used for the service container.
   */
  protected function getContainerCacheKey() {
    $parts = [
      'service_container',
      $this->environment,
      \Drupal::VERSION,
      Settings::get('deployment_identifier'),
      PHP_OS,
      serialize(Settings::get('container_yamls')),
    ];
    return implode(':', $parts);
  }

  /**
   * Returns the kernel parameters.
   *
   * @return array
   *   An associative array of kernel parameters
   */
  protected function getKernelParameters() {
    return [
      'kernel.environment' => $this->environment,
    ];
  }

  /**
   * Initializes the service container.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   An initialized container object.
   */
  protected function initializeContainer() {
    $this->containerNeedsDumping = FALSE;
    $session_started = FALSE;
    $all_messages = [];
    if (isset($this->container)) {
      // Save the id of the currently logged in user.
      if ($this->container->initialized('current_user')) {
        $current_user_id = $this->container->get('current_user')->id();
      }
      // After rebuilding the container some objects will have stale services.
      // Record a map of objects to service IDs prior to rebuilding the
      // container in order to ensure
      // \Drupal\Core\DependencyInjection\DependencySerializationTrait works as
      // expected.
      $this->container->get(ReverseContainer::class)->recordContainer();

      // If there is a session, close and save it.
      if ($this->container->initialized('session')) {
        $session = $this->container->get('session');
        if ($session->isStarted()) {
          $session_started = TRUE;
          $session->save();
        }
        unset($session);
      }

      $all_messages = $this->container->get('messenger')->all();
    }

    // If the module list hasn't already been set in updateModules and we are
    // not forcing a rebuild, then try and load the container from the cache.
    if (empty($this->moduleList) && !$this->containerNeedsRebuild) {
      $container_definition = $this->getCachedContainerDefinition();
    }

    // If there is no cached container definition, build a new container from
    // scratch.
    if (!isset($container_definition)) {
      $container = $this->compileContainer();

      // Only dump the container if dumping is allowed. This is useful for
      // KernelTestBase, which never wants to use the real container, but always
      // the container builder.
      if ($this->allowDumping) {
        $dumper = new $this->phpArrayDumperClass($container);
        $container_definition = $dumper->getArray();
      }
    }

    // The container was rebuilt successfully.
    $this->containerNeedsRebuild = FALSE;

    // Only create a new class if we have a container definition.
    if (isset($container_definition)) {
      // Drupal provides two dynamic parameters to access specific paths that
      // are determined from the request.
      $container_definition['parameters']['app.root'] = $this->getAppRoot();
      $container_definition['parameters']['site.path'] = $this->getSitePath();
      $class = Settings::get('container_base_class', '\Drupal\Core\DependencyInjection\Container');
      $container = new $class($container_definition);
    }

    $this->attachSynthetic($container);

    $this->container = $container;
    if ($session_started) {
      $this->container->get('session')->start();
    }

    // The request stack is preserved across container rebuilds. Re-inject the
    // new session into the main request if one was present before.
    if (($request_stack = $this->container->get('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE))) {
      if ($request = $request_stack->getMainRequest()) {
        $subrequest = TRUE;
        $request->setSession($this->container->get('session'));
      }
    }

    if (!empty($current_user_id)) {
      $this->container->get('current_user')->setInitialAccountId($current_user_id);
    }

    // Re-add messages.
    foreach ($all_messages as $type => $messages) {
      foreach ($messages as $message) {
        $this->container->get('messenger')->addMessage($message, $type);
      }
    }

    \Drupal::setContainer($this->container);

    // Allow other parts of the codebase to react on container initialization in
    // subrequest.
    if (!empty($subrequest)) {
      $this->container->get('event_dispatcher')->dispatch(new Event(), self::CONTAINER_INITIALIZE_SUBREQUEST_FINISHED);
    }

    // If needs dumping flag was set, dump the container.
    if ($this->containerNeedsDumping && !$this->cacheDrupalContainer($container_definition)) {
      $this->container->get('logger.factory')->get('DrupalKernel')->error('Container cannot be saved to cache.');
    }

    return $this->container;
  }

  /**
   * Setup a consistent PHP environment.
   *
   * This method sets PHP environment options we want to be sure are set
   * correctly for security or just saneness.
   *
   * @param string $app_root
   *   (optional) The path to the application root as a string. If not supplied,
   *   the application root will be computed.
   */
  public static function bootEnvironment($app_root = NULL) {
    if (static::$isEnvironmentInitialized) {
      return;
    }

    // Determine the application root if it's not supplied.
    if ($app_root === NULL) {
      $app_root = static::guessApplicationRoot();
    }

    error_reporting(E_ALL);

    // Override PHP settings required for Drupal to work properly.
    // sites/default/default.settings.php contains more runtime settings.
    // The .htaccess file contains settings that cannot be changed at runtime.

    if (PHP_SAPI !== 'cli') {
      // Use session cookies, not transparent sessions that puts the session id
      // in the query string.
      ini_set('session.use_cookies', '1');
      ini_set('session.use_strict_mode', '1');
      if (\PHP_VERSION_ID < 80400) {
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
      }
      // Don't send HTTP headers using PHP's session handler.
      // Send an empty string to disable the cache limiter.
      ini_set('session.cache_limiter', '');
      // Use httponly session cookies.
      ini_set('session.cookie_httponly', '1');
    }

    // Set sane locale settings, to ensure consistent string, dates, times and
    // numbers handling.
    setlocale(LC_ALL, 'C.UTF-8', 'C');

    // Set appropriate configuration for multi-byte strings.
    mb_internal_encoding('utf-8');
    mb_language('uni');

    // Indicate that code is operating in a test child site.
    if (!defined('DRUPAL_TEST_IN_CHILD_SITE')) {
      if ($test_prefix = drupal_valid_test_ua()) {
        $test_db = new TestDatabase($test_prefix);
        // Only code that interfaces directly with tests should rely on this
        // constant; e.g., the error/exception handler conditionally adds
        // further error information into HTTP response headers that are
        // consumed by the internal browser.
        define('DRUPAL_TEST_IN_CHILD_SITE', TRUE);

        // Log fatal errors to the test site directory.
        ini_set('log_errors', 1);
        ini_set('error_log', $app_root . '/' . $test_db->getTestSitePath() . '/error.log');

        // Ensure that a rewritten settings.php is used if OPcache is on.
        ini_set('opcache.validate_timestamps', 'on');
        ini_set('opcache.revalidate_freq', 0);
      }
      else {
        // Ensure that no other code defines this.
        define('DRUPAL_TEST_IN_CHILD_SITE', FALSE);
      }
    }

    // Set the Drupal custom error handler.
    set_error_handler('_drupal_error_handler');
    set_exception_handler('_drupal_exception_handler');

    static::$isEnvironmentInitialized = TRUE;
  }

  /**
   * Locate site path and initialize settings singleton.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   In case the host name in the request is not trusted.
   */
  protected function initializeSettings(Request $request) {
    $site_path = static::findSitePath($request);
    $this->setSitePath($site_path);
    Settings::initialize($this->root, $site_path, $this->classLoader);

    // Initialize our list of trusted HTTP Host headers to protect against
    // header attacks.
    $host_patterns = Settings::get('trusted_host_patterns', []);
    if (PHP_SAPI !== 'cli' && !empty($host_patterns)) {
      if (static::setupTrustedHosts($request, $host_patterns) === FALSE) {
        throw new BadRequestHttpException('The provided host name is not valid for this server.');
      }
    }
  }

  /**
   * Bootstraps the legacy global request variables.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @todo D8: Eliminate this entirely in favor of Request object.
   */
  protected function initializeRequestGlobals(Request $request) {
    global $base_url;
    // Set and derived from $base_url by this function.
    global $base_path, $base_root;
    global $base_secure_url, $base_insecure_url;

    // Create base URL.
    $base_root = $request->getSchemeAndHttpHost();
    $base_url = $base_root;

    // For a request URI of '/index.php/foo', $_SERVER['SCRIPT_NAME'] is
    // '/index.php', whereas $_SERVER['PHP_SELF'] is '/index.php/foo'.
    if ($dir = rtrim(dirname($request->server->get('SCRIPT_NAME')), '\/')) {
      // Remove "core" directory if present, allowing install.php,
      // authorize.php, and others to auto-detect a base path.
      $core_position = strrpos($dir, '/core');
      if ($core_position !== FALSE && strlen($dir) - 5 == $core_position) {
        $base_path = substr($dir, 0, $core_position);
      }
      else {
        $base_path = $dir;
      }
      $base_url .= $base_path;
      $base_path .= '/';
    }
    else {
      $base_path = '/';
    }
    $base_secure_url = str_replace('http://', 'https://', $base_url);
    $base_insecure_url = str_replace('https://', 'http://', $base_url);
  }

  /**
   * Returns service instances to persist from an old container to a new one.
   */
  protected function getServicesToPersist(ContainerInterface $container) {
    $persist = [];
    foreach ($container->getParameter('persist_ids') as $id) {
      // It's pointless to persist services not yet initialized.
      if ($container->initialized($id)) {
        $persist[$id] = $container->get($id);
      }
    }
    return $persist;
  }

  /**
   * Moves persistent service instances into a new container.
   */
  protected function persistServices(ContainerInterface $container, array $persist) {
    foreach ($persist as $id => $object) {
      // Do not override services already set() on the new container, for
      // example 'service_container'.
      if (!$container->initialized($id)) {
        $container->set($id, $object);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function rebuildContainer() {
    // Empty module properties and for them to be reloaded from scratch.
    $this->moduleList = NULL;
    $this->moduleData = [];
    $this->containerNeedsRebuild = TRUE;
    $container = $this->initializeContainer();
    // ThemeManager::render() fails without this. Normally ::preHandle() has
    // a ->loadAll() call.
    $container->get('module_handler')->loadAll();
    return $container;
  }

  /**
   * {@inheritdoc}
   */
  public function resetContainer(): ContainerInterface {
    $session_started = FALSE;
    $subrequest = FALSE;
    $reload_module_handler = FALSE;

    // Save the id of the currently logged in user.
    if ($this->container->initialized('current_user')) {
      $current_user_id = $this->container->get('current_user')->id();
    }

    if ($this->container->initialized('module_handler') && $this->container->get('module_handler')->isLoaded()) {
      $reload_module_handler = TRUE;
    }

    // After rebuilding the container some objects will have stale services.
    // Record a map of objects to service IDs prior to rebuilding the
    // container in order to ensure
    // \Drupal\Core\DependencyInjection\DependencySerializationTrait works as
    // expected.
    $this->container->get(ReverseContainer::class)->recordContainer();

    // If there is a session, close and save it.
    if ($this->container->initialized('session')) {
      $session = $this->container->get('session');
      if ($session->isStarted()) {
        $session_started = TRUE;
        $session->save();
      }
      unset($session);
    }

    $all_messages = $this->container->get('messenger')->all();

    $persist = $this->getServicesToPersist($this->container);
    $this->container->reset();
    $this->persistServices($this->container, $persist);

    $this->container->set('kernel', $this);

    // Set the class loader which was registered as a synthetic service.
    $this->container->set('class_loader', $this->classLoader);

    if ($reload_module_handler) {
      $this->container->get('module_handler')->reload();
    }

    if ($session_started) {
      $this->container->get('session')->start();
    }

    // The request stack is preserved across container rebuilds. Re-inject the
    // new session into the main request if one was present before.
    if (($request_stack = $this->container->get('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE))) {
      if ($request = $request_stack->getMainRequest()) {
        $subrequest = TRUE;
        $request->setSession($this->container->get('session'));
      }
    }

    if (!empty($current_user_id)) {
      $this->container->get('current_user')->setInitialAccountId($current_user_id);
    }

    // Re-add messages.
    foreach ($all_messages as $type => $messages) {
      foreach ($messages as $message) {
        $this->container->get('messenger')->addMessage($message, $type);
      }
    }

    // Allow other parts of the codebase to react on container reset in
    // subrequest.
    if (!empty($subrequest)) {
      $this->container->get('event_dispatcher')->dispatch(new Event(), self::CONTAINER_INITIALIZE_SUBREQUEST_FINISHED);
    }

    return $this->container;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateContainer() {
    // An invalidated container needs a rebuild.
    $this->containerNeedsRebuild = TRUE;

    // If we have not yet booted, settings or bootstrap services might not yet
    // be available. In that case the container will not be loaded from cache
    // due to the above setting when the Kernel is booted.
    if (!$this->booted) {
      return;
    }

    // Also remove the container definition from the cache backend.
    $this->bootstrapContainer->get('cache.container')->deleteAll();
  }

  /**
   * Attach synthetic values on to kernel.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container object.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   The container object with the kernel and the class loader added.
   */
  protected function attachSynthetic(ContainerInterface $container) {
    $persist = [];
    if (isset($this->container)) {
      $persist = $this->getServicesToPersist($this->container);
    }
    $this->persistServices($container, $persist);

    // All namespaces must be registered before we attempt to use any service
    // from the container.
    $this->classLoaderAddMultiplePsr4($container->getParameter('container.namespaces'));

    $container->set('kernel', $this);

    // Set the class loader which was registered as a synthetic service.
    $container->set('class_loader', $this->classLoader);
    return $container;
  }

  /**
   * Compiles a new service container.
   *
   * @return \Drupal\Core\DependencyInjection\ContainerBuilder
   *   The compiled service container
   */
  protected function compileContainer() {
    // We are forcing a container build so it is reasonable to assume that the
    // calling method knows something about the system has changed requiring the
    // container to be dumped to the filesystem.
    if ($this->allowDumping) {
      $this->containerNeedsDumping = TRUE;
    }

    $this->initializeServiceProviders();
    $container = $this->getContainerBuilder();
    $container->set('kernel', $this);
    $container->setParameter('container.modules', $this->getModulesParameter());
    $container->setParameter('install_profile', $this->getInstallProfile());

    // Get a list of namespaces and put it onto the container.
    $namespaces = $this->getModuleNamespacesPsr4($this->getModuleFileNames());
    // Add all components in \Drupal\Core and \Drupal\Component that have one or
    // more of Element, Entity and Plugin directories.
    foreach (['Core', 'Component'] as $parent_directory) {
      $path = 'core/lib/Drupal/' . $parent_directory;
      $parent_namespace = 'Drupal\\' . $parent_directory;
      foreach (new \DirectoryIterator($this->root . '/' . $path) as $component) {
        /** @var \DirectoryIterator $component */
        $pathname = $component->getPathname();
        if (!$component->isDot() && $component->isDir() && (
          is_dir($pathname . '/Plugin') ||
          is_dir($pathname . '/Entity') ||
          is_dir($pathname . '/Element')
        )) {
          $namespaces[$parent_namespace . '\\' . $component->getFilename()] = $path . '/' . $component->getFilename();
        }
      }
    }
    $container->setParameter('container.namespaces', $namespaces);

    // Store the default language values on the container. This is so that the
    // default language can be configured using the configuration factory. This
    // avoids the circular dependencies that would created by
    // \Drupal\language\LanguageServiceProvider::alter() and allows the default
    // language to not be English in the installer.
    $default_language_values = Language::$defaultValues;
    if ($system = $this->getConfigStorage()->read('system.site')) {
      if ($default_language_values['id'] != $system['langcode']) {
        $default_language_values = ['id' => $system['langcode']];
      }
    }
    $container->setParameter('language.default_values', $default_language_values);

    // Register synthetic services.
    $container->register('class_loader')->setSynthetic(TRUE);
    $container->register('kernel', 'Symfony\Component\HttpKernel\KernelInterface')->setSynthetic(TRUE);
    $container->register('service_container', 'Symfony\Component\DependencyInjection\ContainerInterface')->setSynthetic(TRUE);

    // Register aliases of synthetic services for autowiring.
    $container->setAlias(DrupalKernelInterface::class, 'kernel');
    $container->setAlias(ContainerInterface::class, 'service_container');

    // Register application services.
    $yaml_loader = new YamlFileLoader($container);
    foreach ($this->serviceYamls['app'] as $filename) {
      $yaml_loader->load($filename);
    }
    foreach ($this->serviceProviders['app'] as $provider) {
      if ($provider instanceof ServiceProviderInterface) {
        $provider->register($container);
      }
    }
    // Register site-specific service overrides.
    foreach ($this->serviceYamls['site'] as $filename) {
      $yaml_loader->load($filename);
    }
    foreach ($this->serviceProviders['site'] as $provider) {
      if ($provider instanceof ServiceProviderInterface) {
        $provider->register($container);
      }
    }

    // Identify all services whose instances should be persisted when rebuilding
    // the container during the lifetime of the kernel (e.g., during a kernel
    // reboot). Include synthetic services, because by definition, they cannot
    // be automatically re-instantiated. Also include services tagged to
    // persist.
    $persist_ids = [];
    foreach ($container->getDefinitions() as $id => $definition) {
      // It does not make sense to persist the container itself, exclude it.
      if ($id !== 'service_container' && ($definition->isSynthetic() || $definition->getTag('persist'))) {
        $persist_ids[] = $id;
      }
    }
    $container->setParameter('persist_ids', $persist_ids);

    $container->setParameter('app.root', $this->getAppRoot());
    $container->setParameter('site.path', $this->getSitePath());

    $container->compile();
    return $container;
  }

  /**
   * Registers all service providers to the kernel.
   *
   * @throws \LogicException
   */
  protected function initializeServiceProviders() {
    $this->discoverServiceProviders();
    $this->serviceProviders = [
      'app' => [],
      'site' => [],
    ];
    foreach ($this->serviceProviderClasses as $origin => $classes) {
      foreach ($classes as $name => $class) {
        if (!is_object($class)) {
          $this->serviceProviders[$origin][$name] = new $class();
        }
        else {
          $this->serviceProviders[$origin][$name] = $class;
        }
      }
    }
  }

  /**
   * Gets a new ContainerBuilder instance used to build the service container.
   *
   * @return \Drupal\Core\DependencyInjection\ContainerBuilder
   *   The Drupal dependency injection container builder.
   */
  protected function getContainerBuilder() {
    return new ContainerBuilder(new ParameterBag($this->getKernelParameters()));
  }

  /**
   * Stores the container definition in a cache.
   *
   * @param array $container_definition
   *   The container definition to cache.
   *
   * @return bool
   *   TRUE if the container was successfully cached.
   */
  protected function cacheDrupalContainer(array $container_definition) {
    $saved = TRUE;
    try {
      $this->bootstrapContainer->get('cache.container')->set($this->getContainerCacheKey(), $container_definition);
    }
    catch (\Exception) {
      // There is no way to get from the Cache API if the cache set was
      // successful or not, hence an Exception is caught and the caller informed
      // about the error condition.
      $saved = FALSE;
    }

    return $saved;
  }

  /**
   * Gets a http kernel from the container.
   *
   * @return \Symfony\Component\HttpKernel\HttpKernelInterface
   *   The Symfony HTTP kernel service.
   */
  protected function getHttpKernel() {
    return $this->container->get('http_kernel');
  }

  /**
   * Gets the active configuration storage to use during building the container.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   The configuration storage.
   */
  protected function getConfigStorage() {
    if (!isset($this->configStorage)) {
      // The active configuration storage may not exist yet; e.g., in the early
      // installer so if an exception is thrown use a NullStorage.
      try {
        $this->configStorage = BootstrapConfigStorageFactory::get($this->classLoader);
      }
      catch (\Exception) {
        $this->configStorage = new NullStorage();
      }
    }
    return $this->configStorage;
  }

  /**
   * Returns an array of Extension class parameters for all enabled modules.
   *
   * @return array
   *   An associated array of module class parameters, keyed by module name, for
   *   all enabled modules.
   */
  protected function getModulesParameter() {
    $extensions = [];
    foreach ($this->moduleList as $name => $weight) {
      if ($data = $this->moduleData($name)) {
        $extensions[$name] = [
          'type' => $data->getType(),
          'pathname' => $data->getPathname(),
          'filename' => $data->getExtensionFilename(),
        ];
      }
    }
    return $extensions;
  }

  /**
   * Gets the file name for each enabled module.
   *
   * @return array
   *   Array where each key is a module name, and each value is a path to the
   *   respective *.info.yml file.
   */
  protected function getModuleFileNames() {
    $filenames = [];
    foreach ($this->moduleList as $module => $weight) {
      if ($data = $this->moduleData($module)) {
        $filenames[$module] = $data->getPathname();
      }
    }
    return $filenames;
  }

  /**
   * Gets the PSR-4 base directories for module namespaces.
   *
   * @param string[] $module_file_names
   *   Array where each key is a module name, and each value is a path to the
   *   respective *.info.yml file.
   *
   * @return string[]
   *   Array where each key is a module namespace like 'Drupal\system', and each
   *   value is the PSR-4 base directory associated with the module namespace.
   */
  protected function getModuleNamespacesPsr4($module_file_names) {
    $namespaces = [];
    foreach ($module_file_names as $module => $filename) {
      $namespaces["Drupal\\$module"] = dirname($filename) . '/src';
    }
    return $namespaces;
  }

  /**
   * Registers a list of namespaces with PSR-4 directories for class loading.
   *
   * @param array $namespaces
   *   Array where each key is a namespace like 'Drupal\system', and each value
   *   is either a PSR-4 base directory, or an array of PSR-4 base directories
   *   associated with this namespace.
   * @param object $class_loader
   *   The class loader. Normally \Composer\Autoload\ClassLoader, as included by
   *   the front controller, but may also be decorated.
   */
  protected function classLoaderAddMultiplePsr4(array $namespaces = [], $class_loader = NULL) {
    if ($class_loader === NULL) {
      $class_loader = $this->classLoader;
    }
    foreach ($namespaces as $prefix => $paths) {
      if (is_array($paths)) {
        foreach ($paths as $key => $value) {
          $paths[$key] = $this->root . '/' . $value;
        }
      }
      elseif (is_string($paths)) {
        $paths = $this->root . '/' . $paths;
      }
      $class_loader->addPsr4($prefix . '\\', $paths);
    }
  }

  /**
   * Validates a hostname length.
   *
   * @param string $host
   *   A hostname.
   *
   * @return bool
   *   TRUE if the length is appropriate, or FALSE otherwise.
   */
  protected static function validateHostnameLength($host) {
    // Limit the length of the host name to 1000 bytes to prevent DoS attacks
    // with long host names.
    return strlen($host) <= 1000
    // Limit the number of subdomains and port separators to prevent DoS attacks
    // in findSitePath().
    && substr_count($host, '.') <= 100
    && substr_count($host, ':') <= 100;
  }

  /**
   * Validates the hostname supplied from the HTTP request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return bool
   *   TRUE if the hostname is valid, or FALSE otherwise.
   */
  public static function validateHostname(Request $request) {
    // $request->getHost() can throw an UnexpectedValueException if it
    // detects a bad hostname, but it does not validate the length.
    try {
      $http_host = $request->getHost();
    }
    catch (\UnexpectedValueException) {
      return FALSE;
    }

    if (static::validateHostnameLength($http_host) === FALSE) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Sets up the lists of trusted HTTP Host headers.
   *
   * Since the HTTP Host header can be set by the user making the request, it
   * is possible to create an attack vectors against a site by overriding this.
   * Symfony provides a mechanism for creating a list of trusted Host values.
   *
   * Host patterns (as regular expressions) can be configured through
   * settings.php for multisite installations, sites using ServerAlias without
   * canonical redirection, or configurations where the site responds to default
   * requests. For example,
   *
   * @code
   * $settings['trusted_host_patterns'] = [
   *   '^example\.com$',
   *   '^*.example\.com$',
   * ];
   * @endcode
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param array $host_patterns
   *   The array of trusted host patterns.
   *
   * @return bool
   *   TRUE if the Host header is trusted, FALSE otherwise.
   *
   * @see https://www.drupal.org/docs/installing-drupal/trusted-host-settings
   * @see \Drupal\Core\Http\TrustedHostsRequestFactory
   */
  protected static function setupTrustedHosts(Request $request, $host_patterns) {
    Request::setTrustedHosts($host_patterns);

    // Get the host, which will validate the current request.
    try {
      $host = $request->getHost();

      // Fake requests created through Request::create() without passing in the
      // server variables from the main request have a default host of
      // 'localhost'. If 'localhost' does not match any of the trusted host
      // patterns these fake requests would fail the host verification. Instead,
      // TrustedHostsRequestFactory makes sure to pass in the server variables
      // from the main request.
      $request_factory = new TrustedHostsRequestFactory($host);
      Request::setFactory([$request_factory, 'createRequest'](...));

    }
    catch (\UnexpectedValueException) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Add service files.
   *
   * @param string[] $service_yamls
   *   A list of service files.
   */
  protected function addServiceFiles(array $service_yamls) {
    $this->serviceYamls['site'] = array_filter($service_yamls, 'is_file');
  }

  /**
   * Gets the active install profile.
   *
   * @return string|false|null
   *   The name of the active install profile or distribution, FALSE if there is
   *   no install profile or NULL if Drupal is being installed.
   */
  protected function getInstallProfile() {
    $config = $this->getExtensions();
    if (is_array($config) && !array_key_exists('profile', $config)) {
      return FALSE;
    }
    return $config['profile'] ?? NULL;
  }

  /**
   * Initializes a session backed by in-memory store and puts it on the request.
   *
   * A simple in-memory store is sufficient for command line tools and tests.
   * Web requests will be processed by the session middleware where the mock
   * session is replaced by a session object backed with persistent storage and
   * a real session handler.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @see \Drupal\Core\StackMiddleware\Session::handle()
   */
  protected function initializeEphemeralSession(Request $request): void {
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    $request->setSession($session);
  }

  /**
   * Get the core.extension config object.
   *
   * @return array|false
   *   The core.extension config object if it exists or FALSE.
   */
  protected function getExtensions(): array|false {
    return $this->getConfigStorage()->read('core.extension');
  }

}
