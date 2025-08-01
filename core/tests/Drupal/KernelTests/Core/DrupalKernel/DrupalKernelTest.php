<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\DrupalKernel;

use Composer\Autoload\ClassLoader;
use Drupal\Core\DrupalKernel;
use Drupal\Core\DrupalKernelInterface;
use Drupal\KernelTests\KernelTestBase;
use org\bovigo\vfs\vfsStream;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;

// cspell:ignore äöüßαβγδεζηθικλμνξοσὠ

/**
 * Tests DIC compilation to disk.
 *
 * @group DrupalKernel
 * @coversDefaultClass \Drupal\Core\DrupalKernel
 */
class DrupalKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (get_error_handler() === '_drupal_error_handler') {
      restore_error_handler();
    }
    parent::tearDown();
  }

  /**
   * {@inheritdoc}
   */
  protected function bootKernel(): void {
    // Do not boot the kernel, because we are testing aspects of this process.
  }

  /**
   * Build a kernel for testings.
   *
   * Because the bootstrap is in DrupalKernel::boot and that involved loading
   * settings from the filesystem we need to go to extra lengths to build a
   * kernel for testing.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object to use in booting the kernel.
   * @param array $modules_enabled
   *   A list of modules to install on the kernel.
   *
   * @return \Drupal\Core\DrupalKernel
   *   New kernel for testing.
   */
  protected function getTestKernel(Request $request, ?array $modules_enabled = NULL) {
    // Manually create kernel to avoid replacing settings.
    $class_loader = require $this->root . '/autoload.php';
    $kernel = DrupalKernel::createFromRequest($request, $class_loader, 'testing');
    $this->setSetting('container_yamls', []);
    $this->setSetting('hash_salt', $this->databasePrefix);
    if (isset($modules_enabled)) {
      $kernel->updateModules($modules_enabled);
    }
    $kernel->boot();

    return $kernel;
  }

  /**
   * Tests DIC compilation.
   */
  public function testCompileDIC(): void {
    // @todo Write a memory based storage backend for testing.
    $modules_enabled = [
      'system' => 'system',
      'user' => 'user',
    ];

    $request = Request::createFromGlobals();
    $this->getTestKernel($request, $modules_enabled);

    // Instantiate it a second time and we should get the compiled Container
    // class.
    $kernel = $this->getTestKernel($request);
    $container = $kernel->getContainer();
    $refClass = new \ReflectionClass($container);
    $is_compiled_container = !$refClass->isSubclassOf('Symfony\Component\DependencyInjection\ContainerBuilder');
    $this->assertTrue($is_compiled_container);
    // Verify that the list of modules is the same for the initial and the
    // compiled container.
    $module_list = array_keys($container->get('module_handler')->getModuleList());
    $this->assertEquals(array_values($modules_enabled), $module_list);

    // Get the container another time, simulating a "production" environment.
    $container = $this->getTestKernel($request, NULL)
      ->getContainer();

    $refClass = new \ReflectionClass($container);
    $is_compiled_container = !$refClass->isSubclassOf('Symfony\Component\DependencyInjection\ContainerBuilder');
    $this->assertTrue($is_compiled_container);

    // Verify that the list of modules is the same for the initial and the
    // compiled container.
    $module_list = array_keys($container->get('module_handler')->getModuleList());
    $this->assertEquals(array_values($modules_enabled), $module_list);

    // Test that our synthetic services are there.
    $class_loader = $container->get('class_loader');
    $refClass = new \ReflectionClass($class_loader);
    $this->assertTrue($refClass->hasMethod('loadClass'), 'Container has a class loader');

    // We make this assertion here purely to show that the new container below
    // is functioning correctly, i.e. we get a brand new ContainerBuilder
    // which has the required new services, after changing the list of enabled
    // modules.
    $this->assertFalse($container->has('service_provider_test_class'));

    // Add another module so that we can test that the new module's bundle is
    // registered to the new container.
    $modules_enabled['service_provider_test'] = 'service_provider_test';
    $this->getTestKernel($request, $modules_enabled);

    // Instantiate it a second time and we should not get a ContainerBuilder
    // class because we are loading the container definition from cache.
    $kernel = $this->getTestKernel($request, $modules_enabled);
    $container = $kernel->getContainer();

    $refClass = new \ReflectionClass($container);
    $is_container_builder = $refClass->isSubclassOf('Symfony\Component\DependencyInjection\ContainerBuilder');
    $this->assertFalse($is_container_builder, 'Container is not a builder');

    // Assert that the new module's bundle was registered to the new container.
    $this->assertTrue($container->has('service_provider_test_class'), 'Container has test service');

    // Test that our synthetic services are there.
    $class_loader = $container->get('class_loader');
    $refClass = new \ReflectionClass($class_loader);
    $this->assertTrue($refClass->hasMethod('loadClass'), 'Container has a class loader');

    // Check that the location of the new module is registered.
    $modules = $container->getParameter('container.modules');
    $module_extension_list = $container->get('extension.list.module');
    $this->assertEquals(['type' => 'module', 'pathname' => $module_extension_list->getPathname('service_provider_test'), 'filename' => NULL], $modules['service_provider_test']);

    // Check that the container itself is not among the persist IDs because it
    // does not make sense to persist the container itself.
    $persist_ids = $container->getParameter('persist_ids');
    $this->assertNotContains('service_container', $persist_ids);
  }

  /**
   * Tests repeated loading of compiled DIC with different environment.
   */
  public function testRepeatedBootWithDifferentEnvironment(): void {
    $request = Request::createFromGlobals();
    $class_loader = require $this->root . '/autoload.php';

    $environments = [
      'testing1',
      'testing1',
      'testing2',
      'testing2',
    ];

    foreach ($environments as $environment) {
      $kernel = DrupalKernel::createFromRequest($request, $class_loader, $environment);
      $this->setSetting('container_yamls', []);
      $this->setSetting('hash_salt', $this->databasePrefix);
      $this->assertInstanceOf(DrupalKernelInterface::class, $kernel->boot(), "Environment $environment should boot.");
    }
  }

  /**
   * Tests setting of site path after kernel boot.
   */
  public function testPreventChangeOfSitePath(): void {
    // @todo Write a memory based storage backend for testing.
    $modules_enabled = [
      'system' => 'system',
      'user' => 'user',
    ];

    $request = Request::createFromGlobals();
    $kernel = $this->getTestKernel($request, $modules_enabled);
    $pass = FALSE;
    try {
      $kernel->setSitePath('/dev/null');
    }
    catch (\LogicException) {
      $pass = TRUE;
    }
    $this->assertTrue($pass, 'Throws LogicException if DrupalKernel::setSitePath() is called after boot');

    // Ensure no LogicException if DrupalKernel::setSitePath() is called with
    // identical path after boot.
    $path = $kernel->getSitePath();
    $kernel->setSitePath($path);
  }

  /**
   * Data provider for self::testClassLoaderAutoDetect.
   *
   * @return array
   *   An array of test cases. Each test case is an array containing a single boolean value
   *   that represents the class_loader_auto_detect setting to be tested.
   */
  public static function providerClassLoaderAutoDetect() {
    return [
      'TRUE' => [TRUE],
      'FALSE' => [FALSE],
    ];
  }

  /**
   * Tests class_loader_auto_detect setting.
   *
   * This test runs in a separate process since it registers class loaders and
   * results in statics being set.
   *
   * @param bool $value
   *   The value to set class_loader_auto_detect to.
   *
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @covers ::boot
   * @dataProvider providerClassLoaderAutoDetect
   */
  public function testClassLoaderAutoDetect($value): void {
    // Create a virtual file system containing items that should be
    // excluded. Exception being modules directory.
    vfsStream::setup('root', NULL, [
      'sites' => [
        'default' => [],
      ],
      'core' => [
        'lib' => [
          'Drupal' => [
            'Core' => [],
            'Component' => [],
          ],
        ],
      ],
    ]);

    $this->setSetting('class_loader_auto_detect', $value);
    $classloader = $this->prophesize(ClassLoader::class);

    // Assert that we call the setApcuPrefix on the classloader if
    // class_loader_auto_detect is set to TRUE.
    if ($value) {
      $classloader->setApcuPrefix(Argument::type('string'))->shouldBeCalled();
    }
    else {
      $classloader->setApcuPrefix(Argument::type('string'))->shouldNotBeCalled();
    }

    // Create a kernel suitable for testing.
    $kernel = new DrupalKernel('test', $classloader->reveal(), FALSE, vfsStream::url('root'));
    $kernel->setSitePath(vfsStream::url('root/sites/default'));
    $kernel->boot();
  }

  /**
   * @covers ::resetContainer
   */
  public function testResetContainer(): void {
    $modules_enabled = [
      'system' => 'system',
      'user' => 'user',
    ];

    $request = Request::createFromGlobals();
    $kernel = $this->getTestKernel($request, $modules_enabled);
    $container = $kernel->getContainer();

    // Ensure services are reset when ::resetContainer is called.
    $this->assertFalse($container->initialized('renderer'));
    $renderer = $container->get('renderer');
    $this->assertTrue($container->initialized('renderer'));

    // Ensure the current user is maintained through a container reset.
    $this->assertSame(0, $container->get('current_user')->id());
    $container->get('current_user')->setInitialAccountId(2);

    // Ensure messages are maintained through a container reset.
    $this->assertEmpty($container->get('messenger')->messagesByType('Container reset'));
    $container->get('messenger')->addMessage('Test reset', 'Container reset');
    $this->assertSame(['Test reset'], $container->get('messenger')->messagesByType('Container reset'));

    // Ensure persisted services are persisted.
    $request_stack = $container->get('request_stack');

    $kernel->resetContainer();

    // Ensure services are reset when ::resetContainer is called.
    $this->assertFalse($container->initialized('renderer'));
    $this->assertNotSame($renderer, $container->get('renderer'));
    $this->assertTrue($container->initialized('renderer'));
    $this->assertSame($kernel, $container->get('kernel'));

    // Ensure the current user is maintained through a container reset.
    $this->assertSame(2, $container->get('current_user')->id());

    // Ensure messages are maintained through a container reset.
    $this->assertSame(['Test reset'], $container->get('messenger')->messagesByType('Container reset'));

    // Ensure persisted services are persisted.
    $this->assertSame($request_stack, $container->get('request_stack'));
  }

  /**
   * Tests system locale.
   */
  public function testLocale(): void {
    $utf8_string = 'äöüßαβγδεζηθικλμνξοσὠ';
    // Test environment locale should be UTF-8.
    $this->assertSame($utf8_string, escapeshellcmd($utf8_string));
    $request = Request::createFromGlobals();
    $this->getTestKernel($request);
    // Kernel environment locale should be UTF-8.
    $this->assertSame($utf8_string, escapeshellcmd($utf8_string));
  }

}
