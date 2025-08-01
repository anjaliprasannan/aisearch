<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\DependencyInjection;

use Drupal\Component\DependencyInjection\Container;
use Drupal\Component\Utility\Crypt;
use Drupal\TestTools\Extension\DeprecationBridge\ExpectDeprecationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Tests Drupal\Component\DependencyInjection\Container.
 */
#[CoversClass(Container::class)]
#[Group('DependencyInjection')]
class ContainerTest extends TestCase {
  use ExpectDeprecationTrait;
  use ProphecyTrait;

  /**
   * The tested container.
   *
   * @var \Drupal\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The container definition used for the test.
   *
   * @var array
   */
  protected $containerDefinition;

  /**
   * The container class to be tested.
   *
   * @var bool
   */
  protected $containerClass;

  /**
   * Whether the container uses the machine-optimized format or not.
   *
   * @var bool
   */
  protected $machineFormat;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->machineFormat = TRUE;
    $this->containerClass = '\Drupal\Component\DependencyInjection\Container';
    $this->containerDefinition = $this->getMockContainerDefinition();
    $this->container = new $this->containerClass($this->containerDefinition);
  }

  /**
   * Tests that passing a non-supported format throws an InvalidArgumentException.
   *
   * @legacy-covers ::__construct
   */
  public function testConstruct(): void {
    $container_definition = $this->getMockContainerDefinition();
    $container_definition['machine_format'] = !$this->machineFormat;
    $this->expectException(InvalidArgumentException::class);
    new $this->containerClass($container_definition);
  }

  /**
   * Tests that Container::getParameter() works properly.
   *
   * @legacy-covers ::getParameter
   */
  public function testGetParameter(): void {
    $this->assertEquals($this->containerDefinition['parameters']['some_config'], $this->container->getParameter('some_config'), 'Container parameter matches for %some_config%.');
    $this->assertEquals($this->containerDefinition['parameters']['some_other_config'], $this->container->getParameter('some_other_config'), 'Container parameter matches for %some_other_config%.');
  }

  /**
   * Tests that Container::getParameter() works for non-existing parameters.
   *
   * @legacy-covers ::getParameter
   * @legacy-covers ::getParameterAlternatives
   * @legacy-covers ::getAlternatives
   */
  public function testGetParameterIfNotFound(): void {
    $this->expectException(ParameterNotFoundException::class);
    $this->container->getParameter('parameter_that_does_not_exist');
  }

  /**
   * Tests that Container::getParameter() works properly for NULL parameters.
   *
   * @legacy-covers ::getParameter
   */
  public function testGetParameterIfNotFoundBecauseNull(): void {
    $this->expectException(ParameterNotFoundException::class);
    $this->container->getParameter(NULL);
  }

  /**
   * Tests that Container::hasParameter() works properly.
   *
   * @legacy-covers ::hasParameter
   */
  public function testHasParameter(): void {
    $this->assertTrue($this->container->hasParameter('some_config'), 'Container parameters include %some_config%.');
    $this->assertFalse($this->container->hasParameter('some_config_not_exists'), 'Container parameters do not include %some_config_not_exists%.');
  }

  /**
   * Tests that Container::setParameter() in an unfrozen case works properly.
   *
   * @legacy-covers ::setParameter
   */
  public function testSetParameterWithUnfrozenContainer(): void {
    $container_definition = $this->containerDefinition;
    $container_definition['frozen'] = FALSE;
    $this->container = new $this->containerClass($container_definition);
    $this->container->setParameter('some_config', 'new_value');
    $this->assertEquals('new_value', $this->container->getParameter('some_config'), 'Container parameters can be set.');
  }

  /**
   * Tests that Container::setParameter() in a frozen case works properly.
   *
   * @legacy-covers ::setParameter
   */
  public function testSetParameterWithFrozenContainer(): void {
    $this->container = new $this->containerClass($this->containerDefinition);
    $this->expectException(LogicException::class);
    $this->container->setParameter('some_config', 'new_value');
  }

  /**
   * Tests that Container::get() works properly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   */
  public function testGet(): void {
    $container = $this->container->get('service_container');
    $this->assertSame($this->container, $container, 'Container can be retrieved from itself.');

    // Retrieve services of the container.
    $other_service_class = $this->containerDefinition['services']['other.service']['class'];
    $other_service = $this->container->get('other.service');
    $this->assertInstanceOf($other_service_class, $other_service);

    $some_parameter = $this->containerDefinition['parameters']['some_config'];
    $some_other_parameter = $this->containerDefinition['parameters']['some_other_config'];

    $service = $this->container->get('service.provider');

    $this->assertEquals($other_service, $service->getSomeOtherService(), '@other.service was injected via constructor.');
    $this->assertEquals($some_parameter, $service->getSomeParameter(), '%some_config% was injected via constructor.');
    $this->assertEquals($this->container, $service->getContainer(), 'Container was injected via setter injection.');
    $this->assertEquals($some_other_parameter, $service->getSomeOtherParameter(), '%some_other_config% was injected via setter injection.');
    $this->assertEquals('foo', $service->someProperty, 'Service has added properties.');
  }

  /**
   * Tests that Container::get() for non-shared services works properly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   */
  public function testGetForNonSharedService(): void {
    $service = $this->container->get('non_shared_service');
    $service2 = $this->container->get('non_shared_service');

    $this->assertNotSame($service, $service2, 'Non shared services are always re-instantiated.');
  }

  /**
   * Tests that Container::get() works properly for class from parameters.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   */
  public function testGetForClassFromParameter(): void {
    $container_definition = $this->containerDefinition;
    $container_definition['frozen'] = FALSE;
    $container = new $this->containerClass($container_definition);

    $other_service_class = $this->containerDefinition['parameters']['some_parameter_class'];
    $other_service = $container->get('other.service_class_from_parameter');
    $this->assertInstanceOf($other_service_class, $other_service);
  }

  /**
   * Tests that Container::set() works properly.
   *
   * @legacy-covers ::set
   */
  public function testSet(): void {
    $this->assertNull($this->container->get('new_id', ContainerInterface::NULL_ON_INVALID_REFERENCE));
    $mock_service = new MockService();
    $this->container->set('new_id', $mock_service);

    $this->assertSame($mock_service, $this->container->get('new_id'), 'A manual set service works as expected.');
  }

  /**
   * Tests that Container::has() works properly.
   *
   * @legacy-covers ::has
   */
  public function testHas(): void {
    $this->assertTrue($this->container->has('other.service'));
    $this->assertFalse($this->container->has('another.service'));

    // Set the service manually, ensure that its also respected.
    $mock_service = new MockService();
    $this->container->set('another.service', $mock_service);
    $this->assertTrue($this->container->has('another.service'));
  }

  /**
   * Tests that Container::has() for aliased services works properly.
   *
   * @legacy-covers ::has
   */
  public function testHasForAliasedService(): void {
    $service = $this->container->has('service.provider');
    $aliased_service = $this->container->has('service.provider_alias');
    $this->assertSame($service, $aliased_service);
  }

  /**
   * Tests that Container::get() for circular dependencies works properly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   */
  public function testGetForCircularServices(): void {
    $this->expectException(ServiceCircularReferenceException::class);
    $this->container->get('circular_dependency');
  }

  /**
   * Tests that Container::get() for non-existent services works properly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   * @legacy-covers ::getAlternatives
   * @legacy-covers ::getServiceAlternatives
   */
  public function testGetForNonExistentService(): void {
    $this->expectException(ServiceNotFoundException::class);
    $this->container->get('service_not_exists');
  }

  /**
   * Tests that Container::get() for a serialized definition works properly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   */
  public function testGetForSerializedServiceDefinition(): void {
    $container_definition = $this->containerDefinition;
    $container_definition['services']['other.service'] = serialize($container_definition['services']['other.service']);
    $container = new $this->containerClass($container_definition);

    // Retrieve services of the container.
    $other_service_class = $this->containerDefinition['services']['other.service']['class'];
    $other_service = $container->get('other.service');
    $this->assertInstanceOf($other_service_class, $other_service);

    $service = $container->get('service.provider');
    $this->assertEquals($other_service, $service->getSomeOtherService(), '@other.service was injected via constructor.');
  }

  /**
   * Tests that Container::get() for non-existent parameters works properly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   * @legacy-covers ::resolveServicesAndParameters
   */
  public function testGetForNonExistentParameterDependency(): void {
    $service = $this->container->get('service_parameter_not_exists', ContainerInterface::NULL_ON_INVALID_REFERENCE);
    $this->assertNull($service, 'Service is NULL.');
  }

  /**
   * Tests Container::get() with an exception due to missing parameter on the second call.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   * @legacy-covers ::resolveServicesAndParameters
   */
  public function testGetForParameterDependencyWithExceptionOnSecondCall(): void {
    $service = $this->container->get('service_parameter_not_exists', ContainerInterface::NULL_ON_INVALID_REFERENCE);
    $this->assertNull($service, 'Service is NULL.');

    // Reset the service.
    $this->container->set('service_parameter_not_exists', NULL);
    $this->expectException(InvalidArgumentException::class);
    $this->container->get('service_parameter_not_exists');
  }

  /**
   * Tests that Container::get() for non-existent parameters works properly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   * @legacy-covers ::resolveServicesAndParameters
   */
  public function testGetForNonExistentParameterDependencyWithException(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->container->get('service_parameter_not_exists');
  }

  /**
   * Tests that Container::get() for non-existent dependencies works properly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   * @legacy-covers ::resolveServicesAndParameters
   */
  public function testGetForNonExistentServiceDependency(): void {
    $service = $this->container->get('service_dependency_not_exists', ContainerInterface::NULL_ON_INVALID_REFERENCE);
    $this->assertNull($service, 'Service is NULL.');
  }

  /**
   * Tests that Container::get() for non-existent dependencies works properly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   * @legacy-covers ::resolveServicesAndParameters
   * @legacy-covers ::getAlternatives
   */
  public function testGetForNonExistentServiceDependencyWithException(): void {
    $this->expectException(ServiceNotFoundException::class);
    $this->container->get('service_dependency_not_exists');
  }

  /**
   * Tests that Container::get() for non-existent services works properly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   */
  public function testGetForNonExistentServiceWhenUsingNull(): void {
    $this->assertNull($this->container->get('service_not_exists', ContainerInterface::NULL_ON_INVALID_REFERENCE), 'Not found service does not throw exception.');
  }

  /**
   * Tests that Container::get() for NULL service works properly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   */
  public function testGetForNonExistentNULLService(): void {
    $this->expectException(ServiceNotFoundException::class);
    $this->container->get(NULL);
  }

  /**
   * Tests multiple Container::get() calls for non-existing dependencies work.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   */
  public function testGetForNonExistentServiceMultipleTimes(): void {
    $container = new $this->containerClass();

    $this->assertNull($container->get('service_not_exists', ContainerInterface::NULL_ON_INVALID_REFERENCE), 'Not found service does not throw exception.');
    $this->assertNull($container->get('service_not_exists', ContainerInterface::NULL_ON_INVALID_REFERENCE), 'Not found service does not throw exception on second call.');
  }

  /**
   * Tests multiple Container::get() calls with exception on the second time.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   * @legacy-covers ::getAlternatives
   */
  public function testGetForNonExistentServiceWithExceptionOnSecondCall(): void {
    $this->assertNull($this->container->get('service_not_exists', ContainerInterface::NULL_ON_INVALID_REFERENCE), 'Not found service does nto throw exception.');
    $this->expectException(ServiceNotFoundException::class);
    $this->container->get('service_not_exists');
  }

  /**
   * Tests that Container::get() for aliased services works properly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   */
  public function testGetForAliasedService(): void {
    $service = $this->container->get('service.provider');
    $aliased_service = $this->container->get('service.provider_alias');
    $this->assertSame($service, $aliased_service);
  }

  /**
   * Tests that Container::get() for synthetic services works - if defined.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   */
  public function testGetForSyntheticService(): void {
    $synthetic_service = new \stdClass();
    $this->container->set('synthetic', $synthetic_service);
    $test_service = $this->container->get('synthetic');
    $this->assertSame($synthetic_service, $test_service);
  }

  /**
   * Tests that Container::get() for synthetic services throws an Exception if not defined.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   */
  public function testGetForSyntheticServiceWithException(): void {
    $this->expectException(RuntimeException::class);
    $this->container->get('synthetic');
  }

  /**
   * Tests that Container::get() for services with file includes works.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   */
  public function testGetWithFileInclude(): void {
    $this->container->get('container_test_file_service_test');
    $this->assertTrue(function_exists('container_test_file_service_test_service_function'));
    $this->assertEquals('Hello Container', container_test_file_service_test_service_function());
  }

  /**
   * Tests that Container::get() for various arguments lengths works.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   * @legacy-covers ::resolveServicesAndParameters
   */
  public function testGetForInstantiationWithVariousArgumentLengths(): void {
    $args = [];
    for ($i = 0; $i < 12; $i++) {
      $instantiation_service = $this->container->get('service_test_instantiation_' . $i);
      $this->assertEquals($args, $instantiation_service->getArguments());
      $args[] = 'arg_' . $i;
    }
  }

  /**
   * Tests that Container::get() for wrong factories works correctly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   */
  public function testGetForWrongFactory(): void {
    $this->expectException(RuntimeException::class);
    $this->container->get('wrong_factory');
  }

  /**
   * Tests Container::get() for factories via services (Symfony 2.7.0).
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   */
  public function testGetForFactoryService(): void {
    $factory_service = $this->container->get('factory_service');
    $factory_service_class = $this->container->getParameter('factory_service_class');
    $this->assertInstanceOf($factory_service_class, $factory_service);
  }

  /**
   * Tests that Container::get() for factories via class works (Symfony 2.7.0).
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   */
  public function testGetForFactoryClass(): void {
    $service = $this->container->get('service.provider');
    $factory_service = $this->container->get('factory_class');

    $this->assertInstanceOf(get_class($service), $factory_service);
    $this->assertEquals('bar', $factory_service->getSomeParameter(), 'Correct parameter was passed via the factory class instantiation.');
    $this->assertEquals($this->container, $factory_service->getContainer(), 'Container was injected via setter injection.');
  }

  /**
   * Tests that Container::get() for configurable services throws an Exception.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   */
  public function testGetForConfiguratorWithException(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->container->get('configurable_service_exception');
  }

  /**
   * Tests that Container::get() for configurable services works.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   */
  public function testGetForConfigurator(): void {
    $container = $this->container;

    // Setup a configurator.
    $configurator = $this->prophesize('\Drupal\Tests\Component\DependencyInjection\MockConfiguratorInterface');
    $configurator->configureService(Argument::type('object'))
      ->shouldBeCalled(1)
      ->will(function ($args) use ($container) {
        $args[0]->setContainer($container);
      });
    $container->set('configurator', $configurator->reveal());

    // Test that the configurator worked.
    $service = $container->get('configurable_service');
    $this->assertSame($container, $service->getContainer(), 'Container was injected via configurator.');
  }

  /**
   * Tests that private services work correctly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   * @legacy-covers ::resolveServicesAndParameters
   */
  public function testResolveServicesAndParametersForPrivateService(): void {
    $service = $this->container->get('service_using_private');
    $private_service = $service->getSomeOtherService();
    $this->assertEquals('really_private_lama', $private_service->getSomeParameter(), 'Private was found successfully.');

    // Test that sharing the same private services works.
    $service = $this->container->get('another_service_using_private');
    $another_private_service = $service->getSomeOtherService();
    $this->assertNotSame($private_service, $another_private_service, 'Private service is not shared.');
    $this->assertEquals('really_private_lama', $private_service->getSomeParameter(), 'Private was found successfully.');
  }

  /**
   * Tests that private service sharing works correctly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   * @legacy-covers ::resolveServicesAndParameters
   */
  public function testResolveServicesAndParametersForSharedPrivateService(): void {
    $service = $this->container->get('service_using_shared_private');
    $private_service = $service->getSomeOtherService();
    $this->assertEquals('really_private_lama', $private_service->getSomeParameter(), 'Private was found successfully.');

    // Test that sharing the same private services works.
    $service = $this->container->get('another_service_using_shared_private');
    $same_private_service = $service->getSomeOtherService();
    $this->assertSame($private_service, $same_private_service, 'Private service is shared.');
    $this->assertEquals('really_private_lama', $private_service->getSomeParameter(), 'Private was found successfully.');
  }

  /**
   * Tests that services with an array of arguments work correctly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   * @legacy-covers ::resolveServicesAndParameters
   */
  public function testResolveServicesAndParametersForArgumentsUsingDeepArray(): void {
    $service = $this->container->get('service_using_array');
    $other_service = $this->container->get('other.service');
    $this->assertEquals($other_service, $service->getSomeOtherService(), '@other.service was injected via constructor.');
  }

  /**
   * Tests that services that are optional work correctly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   * @legacy-covers ::resolveServicesAndParameters
   */
  public function testResolveServicesAndParametersForOptionalServiceDependencies(): void {
    $service = $this->container->get('service_with_optional_dependency');
    $this->assertNull($service->getSomeOtherService(), 'other service was NULL was expected.');
  }

  /**
   * Tests that services wrapped in a closure work correctly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   * @legacy-covers ::resolveServicesAndParameters
   */
  public function testResolveServicesAndParametersForServiceReferencedViaServiceClosure(): void {
    $service = $this->container->get('service_within_service_closure');
    $other_service = $this->container->get('other.service');
    $factory_function = $service->getSomeOtherService();
    $this->assertInstanceOf(\Closure::class, $factory_function);
    $this->assertEquals($other_service, call_user_func($factory_function));
  }

  /**
   * Tests that an invalid argument throw an Exception.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   * @legacy-covers ::resolveServicesAndParameters
   */
  public function testResolveServicesAndParametersForInvalidArgument(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->container->get('invalid_argument_service');
  }

  /**
   * Tests that invalid arguments throw an Exception.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   * @legacy-covers ::resolveServicesAndParameters
   */
  public function testResolveServicesAndParametersForInvalidArguments(): void {
    // In case the machine-optimized format is not used, we need to simulate the
    // test failure.
    $this->expectException(InvalidArgumentException::class);
    if (!$this->machineFormat) {
      throw new InvalidArgumentException('Simulating the test failure.');
    }
    $this->container->get('invalid_arguments_service');
  }

  /**
   * Tests that a parameter that points to a service works correctly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   * @legacy-covers ::resolveServicesAndParameters
   */
  public function testResolveServicesAndParametersForServiceInstantiatedFromParameter(): void {
    $service = $this->container->get('service.provider');
    $test_service = $this->container->get('service_with_parameter_service');
    $this->assertSame($service, $test_service->getSomeOtherService(), 'Service was passed via parameter.');
  }

  /**
   * Tests that Container::initialized works correctly.
   *
   * @legacy-covers ::initialized
   */
  public function testInitialized(): void {
    $this->assertFalse($this->container->initialized('late.service'), 'Late service is not initialized.');
    $this->container->get('late.service');
    $this->assertTrue($this->container->initialized('late.service'), 'Late service is initialized after it was retrieved once.');
  }

  /**
   * Tests that Container::initialized works correctly for aliases.
   *
   * @legacy-covers ::initialized
   */
  public function testInitializedForAliases(): void {
    $this->assertFalse($this->container->initialized('late.service_alias'), 'Late service is not initialized.');
    $this->container->get('late.service');
    $this->assertTrue($this->container->initialized('late.service_alias'), 'Late service is initialized after it was retrieved once.');
  }

  /**
   * Tests that Container::getServiceIds() works properly.
   *
   * @legacy-covers ::getServiceIds
   */
  public function testGetServiceIds(): void {
    $service_definition_keys = array_merge(['service_container'], array_keys($this->containerDefinition['services']));
    $this->assertEquals($service_definition_keys, $this->container->getServiceIds(), 'Retrieved service IDs match definition.');

    $mock_service = new MockService();
    $this->container->set('bar', $mock_service);
    $this->container->set('service.provider', $mock_service);
    $service_definition_keys[] = 'bar';

    $this->assertEquals($service_definition_keys, $this->container->getServiceIds(), 'Retrieved service IDs match definition after setting new services.');
  }

  /**
   * Tests that raw type services arguments are resolved correctly.
   *
   * @legacy-covers ::get
   * @legacy-covers ::createService
   * @legacy-covers ::resolveServicesAndParameters
   */
  public function testResolveServicesAndParametersForRawArgument(): void {
    $this->assertEquals(['ccc'], $this->container->get('service_with_raw_argument')->getArguments());
  }

  /**
   * Tests that service iterators are lazily instantiated.
   */
  public function testIterator(): void {
    $iterator = $this->container->get('service_iterator')->getArguments()[0];
    $this->assertIsIterable($iterator);
    $this->assertFalse($this->container->initialized('other.service'));
    foreach ($iterator as $service) {
      $this->assertIsObject($service);
    }
    $this->assertTrue($this->container->initialized('other.service'));
  }

  /**
   * Tests Container::reset().
   *
   * @legacy-covers ::reset
   */
  public function testReset(): void {
    $this->assertFalse($this->container->initialized('late.service'), 'Late service is not initialized.');
    $this->container->get('late.service');
    $this->assertTrue($this->container->initialized('late.service'), 'Late service is initialized after it was retrieved once.');

    // Reset the container. All initialized services will be reset.
    $this->container->reset();

    $this->assertFalse($this->container->initialized('late.service'), 'Late service is not initialized.');
    $this->container->get('late.service');
    $this->assertTrue($this->container->initialized('late.service'), 'Late service is initialized after it was retrieved once.');
    $this->assertSame($this->container, $this->container->get('service_container'));
  }

  /**
   * Gets a mock container definition.
   *
   * @return array
   *   Associated array with parameters and services.
   */
  protected function getMockContainerDefinition(): array {
    $fake_service = new \stdClass();
    $parameters = [];
    $parameters['some_parameter_class'] = get_class($fake_service);
    $parameters['some_private_config'] = 'really_private_lama';
    $parameters['some_config'] = 'foo';
    $parameters['some_other_config'] = 'lama';
    $parameters['factory_service_class'] = get_class($fake_service);
    // Also test alias resolving.
    $parameters['service_from_parameter'] = $this->getServiceCall('service.provider_alias');

    $services = [];
    $services['other.service'] = [
      'class' => get_class($fake_service),
    ];

    $services['non_shared_service'] = [
      'class' => get_class($fake_service),
      'shared' => FALSE,
    ];

    $services['other.service_class_from_parameter'] = [
      'class' => $this->getParameterCall('some_parameter_class'),
    ];
    $services['late.service'] = [
      'class' => get_class($fake_service),
    ];
    $services['service.provider'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockService',
      'arguments' => $this->getCollection([
        $this->getServiceCall('other.service'),
        $this->getParameterCall('some_config'),
      ]),
      'properties' => $this->getCollection(['someProperty' => 'foo']),
      'calls' => [
        [
          'setContainer',
          $this->getCollection([
            $this->getServiceCall('service_container'),
          ]),
        ],
        [
          'setOtherConfigParameter',
          $this->getCollection([
            $this->getParameterCall('some_other_config'),
          ]),
        ],
      ],
      'priority' => 0,
    ];

    // Test private services.
    $private_service = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockService',
      'arguments' => $this->getCollection([
        $this->getServiceCall('other.service'),
        $this->getParameterCall('some_private_config'),
      ]),
      'public' => FALSE,
    ];

    $services['service_using_private'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockService',
      'arguments' => $this->getCollection([
        $this->getPrivateServiceCall(NULL, $private_service),
        $this->getParameterCall('some_config'),
      ]),
    ];
    $services['another_service_using_private'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockService',
      'arguments' => $this->getCollection([
        $this->getPrivateServiceCall(NULL, $private_service),
        $this->getParameterCall('some_config'),
      ]),
    ];

    // Test shared private services.
    $id = 'private_service_shared_1';

    $services['service_using_shared_private'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockService',
      'arguments' => $this->getCollection([
        $this->getPrivateServiceCall($id, $private_service, TRUE),
        $this->getParameterCall('some_config'),
      ]),
    ];
    $services['another_service_using_shared_private'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockService',
      'arguments' => $this->getCollection([
        $this->getPrivateServiceCall($id, $private_service, TRUE),
        $this->getParameterCall('some_config'),
      ]),
    ];

    // Tests service with invalid argument.
    $services['invalid_argument_service'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockService',
      'arguments' => $this->getCollection([
        // Test passing non-strings, too.
        1,
        (object) [
          'type' => 'invalid',
        ],
      ]),
    ];

    $services['invalid_arguments_service'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockService',
      'arguments' => (object) [
        'type' => 'invalid',
      ],
    ];

    // Test service that needs deep-traversal.
    $services['service_using_array'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockService',
      'arguments' => $this->getCollection([
        $this->getCollection([
          $this->getServiceCall('other.service'),
        ]),
        $this->getParameterCall('some_private_config'),
      ]),
    ];

    $services['service_with_optional_dependency'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockService',
      'arguments' => $this->getCollection([
        $this->getServiceCall('service.does_not_exist', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        $this->getParameterCall('some_private_config'),
      ]),

    ];

    $services['service_within_service_closure'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockService',
      'arguments' => $this->getCollection([
        $this->getServiceClosureCall('other.service', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
        $this->getParameterCall('some_private_config'),
      ]),
    ];

    $services['factory_service'] = [
      'class' => '\Drupal\service_container\ServiceContainer\ControllerInterface',
      'factory' => [
        $this->getServiceCall('service.provider'),
        'getFactoryMethod',
      ],
      'arguments' => $this->getCollection([
        $this->getParameterCall('factory_service_class'),
      ]),
    ];
    $services['factory_class'] = [
      'class' => '\Drupal\service_container\ServiceContainer\ControllerInterface',
      'factory' => '\Drupal\Tests\Component\DependencyInjection\MockService::getFactoryMethod',
      'arguments' => [
        '\Drupal\Tests\Component\DependencyInjection\MockService',
        [NULL, 'bar'],
      ],
      'calls' => [
        [
          'setContainer',
          $this->getCollection([
            $this->getServiceCall('service_container'),
          ]),
        ],
      ],
    ];

    $services['wrong_factory'] = [
      'class' => '\Drupal\service_container\ServiceContainer\ControllerInterface',
      'factory' => (object) ['I am not a factory, but I pretend to be.'],
    ];

    $services['circular_dependency'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockService',
      'arguments' => $this->getCollection([
        $this->getServiceCall('circular_dependency'),
      ]),
    ];
    $services['synthetic'] = [
      'synthetic' => TRUE,
    ];
    $services['container_test_file_service_test'] = [
      'class' => '\stdClass',
      'file' => __DIR__ . '/Fixture/container_test_file_service_test_service_function.php',
    ];

    // Test multiple arguments.
    $args = [];
    for ($i = 0; $i < 12; $i++) {
      $services['service_test_instantiation_' . $i] = [
        'class' => '\Drupal\Tests\Component\DependencyInjection\MockInstantiationService',
        // Also test a collection that does not need resolving.
        'arguments' => $this->getCollection($args, FALSE),
      ];
      $args[] = 'arg_' . $i;
    }

    $services['service_parameter_not_exists'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockService',
      'arguments' => $this->getCollection([
        $this->getServiceCall('service.provider'),
        $this->getParameterCall('not_exists'),
      ]),
    ];
    $services['service_dependency_not_exists'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockService',
      'arguments' => $this->getCollection([
        $this->getServiceCall('service_not_exists'),
        $this->getParameterCall('some_config'),
      ]),
    ];

    $services['service_with_parameter_service'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockService',
      'arguments' => $this->getCollection([
        $this->getParameterCall('service_from_parameter'),
        // Also test deep collections that don't need resolving.
        $this->getCollection([
          1,
        ], FALSE),
      ]),
    ];

    // To ensure getAlternatives() finds something.
    $services['service_not_exists_similar'] = [
      'synthetic' => TRUE,
    ];

    // Test configurator.
    $services['configurator'] = [
      'synthetic' => TRUE,
    ];
    $services['configurable_service'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockService',
      'arguments' => [],
      'configurator' => [
        $this->getServiceCall('configurator'),
        'configureService',
      ],
    ];
    $services['configurable_service_exception'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockService',
      'arguments' => [],
      'configurator' => 'configurator_service_test_does_not_exist',
    ];

    // Raw argument.
    $services['service_with_raw_argument'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockInstantiationService',
      'arguments' => $this->getCollection([$this->getRaw('ccc')]),
    ];

    // Iterator argument.
    $services['service_iterator'] = [
      'class' => '\Drupal\Tests\Component\DependencyInjection\MockInstantiationService',
      'arguments' => $this->getCollection([
        $this->getIterator([
          $this->getServiceCall('other.service'),
        ]),
      ]),
    ];

    $aliases = [];
    $aliases['service.provider_alias'] = 'service.provider';
    $aliases['late.service_alias'] = 'late.service';

    return [
      'aliases' => $aliases,
      'parameters' => $parameters,
      'services' => $services,
      'frozen' => TRUE,
      'machine_format' => $this->machineFormat,
    ];
  }

  /**
   * Helper function to return a service definition.
   */
  protected function getServiceCall($id, $invalid_behavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE) {
    return (object) [
      'type' => 'service',
      'id' => $id,
      'invalidBehavior' => $invalid_behavior,
    ];
  }

  /**
   * Helper function to return a service closure definition.
   */
  protected function getServiceClosureCall($id, $invalid_behavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE) {
    return (object) [
      'type' => 'service_closure',
      'id' => $id,
      'invalidBehavior' => $invalid_behavior,
    ];
  }

  /**
   * Helper function to return a service iterator.
   */
  protected function getIterator($iterator) {
    return (object) [
      'type' => 'iterator',
      'value' => $iterator,
    ];
  }

  /**
   * Helper function to return a parameter definition.
   */
  protected function getParameterCall($name) {
    return (object) [
      'type' => 'parameter',
      'name' => $name,
    ];
  }

  /**
   * Helper function to return a private service definition.
   */
  protected function getPrivateServiceCall($id, $service_definition, $shared = FALSE) {
    if (!$id) {
      $hash = Crypt::hashBase64(serialize($service_definition));
      $id = 'private__' . $hash;
    }
    return (object) [
      'type' => 'private_service',
      'id' => $id,
      'value' => $service_definition,
      'shared' => $shared,
    ];
  }

  /**
   * Helper function to return a machine-optimized collection.
   */
  protected function getCollection($collection, $resolve = TRUE) {
    return (object) [
      'type' => 'collection',
      'value' => $collection,
      'resolve' => $resolve,
    ];
  }

  /**
   * Helper function to return a raw value definition.
   */
  protected function getRaw($value) {
    return (object) [
      'type' => 'raw',
      'value' => $value,
    ];
  }

}

/**
 * Helper interface to test Container::get() with configurator.
 *
 * @group DependencyInjection
 */
interface MockConfiguratorInterface {

  /**
   * Configures a service.
   *
   * @param object $service
   *   The service to configure.
   */
  public function configureService($service);

}


/**
 * Helper class to test Container::get() method for varying number of parameters.
 *
 * @group DependencyInjection
 */
class MockInstantiationService {

  /**
   * @var mixed[]
   */
  protected $arguments;

  /**
   * Construct a mock instantiation service.
   */
  public function __construct() {
    $this->arguments = func_get_args();
  }

  /**
   * Return arguments injected into the service.
   *
   * @return mixed[]
   *   Return the passed arguments.
   */
  public function getArguments() {
    return $this->arguments;
  }

}


/**
 * Helper class to test Container::get() method.
 *
 * @group DependencyInjection
 */
class MockService {

  /**
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * @var object
   */
  protected $someOtherService;

  /**
   * @var string
   */
  protected $someParameter;

  /**
   * @var string
   */
  protected $someOtherParameter;

  /**
   * @var string
   */
  public string $someProperty;

  /**
   * Constructs a MockService object.
   *
   * @param object $some_other_service
   *   (optional) Another injected service.
   * @param string $some_parameter
   *   (optional) An injected parameter.
   */
  public function __construct($some_other_service = NULL, $some_parameter = NULL) {
    if (is_array($some_other_service)) {
      $some_other_service = $some_other_service[0];
    }
    $this->someOtherService = $some_other_service;
    $this->someParameter = $some_parameter;
  }

  /**
   * Sets the container object.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to inject via setter injection.
   */
  public function setContainer(ContainerInterface $container): void {
    $this->container = $container;
  }

  /**
   * Gets the container object.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   The internally set container.
   */
  public function getContainer() {
    return $this->container;
  }

  /**
   * Gets the someOtherService object.
   *
   * @return object
   *   The injected service.
   */
  public function getSomeOtherService() {
    return $this->someOtherService;
  }

  /**
   * Gets the someParameter property.
   *
   * @return string
   *   The injected parameter.
   */
  public function getSomeParameter() {
    return $this->someParameter;
  }

  /**
   * Sets the someOtherParameter property.
   *
   * @param string $some_other_parameter
   *   The setter injected parameter.
   */
  public function setOtherConfigParameter($some_other_parameter): void {
    $this->someOtherParameter = $some_other_parameter;
  }

  /**
   * Gets the someOtherParameter property.
   *
   * @return string
   *   The injected parameter.
   */
  public function getSomeOtherParameter() {
    return $this->someOtherParameter;
  }

  /**
   * Provides a factory method to get a service.
   *
   * @param string $class
   *   The class name of the class to instantiate.
   * @param array $arguments
   *   (optional) Arguments to pass to the new class.
   *
   * @return object
   *   The instantiated service object.
   */
  public static function getFactoryMethod($class, $arguments = []) {
    $r = new \ReflectionClass($class);
    $service = ($r->getConstructor() === NULL) ? $r->newInstance() : $r->newInstanceArgs($arguments);

    return $service;
  }

}
