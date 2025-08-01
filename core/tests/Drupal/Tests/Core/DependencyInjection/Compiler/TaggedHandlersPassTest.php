<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\Compiler;

use Drupal\Core\DependencyInjection\Compiler\TaggedHandlersPass;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @coversDefaultClass \Drupal\Core\DependencyInjection\Compiler\TaggedHandlersPass
 * @group DependencyInjection
 */
class TaggedHandlersPassTest extends UnitTestCase {

  protected function buildContainer($environment = 'dev') {
    $container = new ContainerBuilder();
    $container->setParameter('kernel.environment', $environment);
    return $container;
  }

  /**
   * Tests without any consumers.
   *
   * @covers ::process
   */
  public function testProcessNoConsumers(): void {
    $container = $this->buildContainer();
    $container
      ->register('consumer_id', __NAMESPACE__ . '\ValidConsumer');

    $handler_pass = new TaggedHandlersPass();
    $handler_pass->process($container);

    $this->assertCount(2, $container->getDefinitions());
    $this->assertFalse($container->getDefinition('consumer_id')->hasMethodCall('addHandler'));
  }

  /**
   * Tests a required consumer with no handlers.
   *
   * @covers ::process
   */
  public function testProcessRequiredHandlers(): void {
    $container = $this->buildContainer();
    $container
      ->register('consumer_id', __NAMESPACE__ . '\ValidConsumer')
      ->addTag('service_collector', [
        'required' => TRUE,
      ]);

    $handler_pass = new TaggedHandlersPass();
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage("At least one service tagged with 'consumer_id' is required.");
    $handler_pass->process($container);
  }

  /**
   * Tests a required consumer with no handlers.
   *
   * @covers ::process
   * @covers ::processServiceIdCollectorPass
   */
  public function testIdCollectorProcessRequiredHandlers(): void {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage("At least one service tagged with 'consumer_id' is required.");
    $container = $this->buildContainer();
    $container
      ->register('consumer_id', __NAMESPACE__ . '\ValidConsumer')
      ->addTag('service_id_collector', [
        'required' => TRUE,
      ]);

    $handler_pass = new TaggedHandlersPass();
    $handler_pass->process($container);
  }

  /**
   * Tests consumer with missing interface in non-production environment.
   *
   * @covers ::process
   */
  public function testProcessMissingInterface(): void {
    $container = $this->buildContainer();
    $container
      ->register('consumer_id0', __NAMESPACE__ . '\ValidConsumer')
      ->addTag('service_collector');
    $container
      ->register('consumer_id1', __NAMESPACE__ . '\InvalidConsumer')
      ->addTag('service_collector');

    $handler_pass = new TaggedHandlersPass();
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage("Service consumer 'consumer_id1' class method Drupal\Tests\Core\DependencyInjection\Compiler\InvalidConsumer::addHandler() has to type-hint an interface.");
    $handler_pass->process($container);
  }

  /**
   * Tests one consumer and two handlers.
   *
   * @covers ::process
   */
  public function testProcess(): void {
    $container = $this->buildContainer();
    $container
      ->register('consumer_id', __NAMESPACE__ . '\ValidConsumer')
      ->addTag('service_collector');

    $container
      ->register('handler1', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id');
    $container
      ->register('handler2', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id');

    $handler_pass = new TaggedHandlersPass();
    $handler_pass->process($container);

    $method_calls = $container->getDefinition('consumer_id')->getMethodCalls();
    $this->assertCount(2, $method_calls);
  }

  /**
   * Tests one consumer and two handlers with service ID collection.
   *
   * @covers ::process
   */
  public function testServiceIdProcess(): void {
    $container = $this->buildContainer();
    $container
      ->register('consumer_id', __NAMESPACE__ . '\ValidConsumer')
      ->addTag('service_id_collector');

    $container
      ->register('handler1', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id');
    $container
      ->register('handler2', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id');

    $handler_pass = new TaggedHandlersPass();
    $handler_pass->process($container);

    $arguments = $container->getDefinition('consumer_id')->getArguments();
    $this->assertCount(1, $arguments);
    $this->assertCount(2, $arguments[0]);
  }

  /**
   * Tests handler priority sorting.
   *
   * @covers ::process
   */
  public function testProcessPriority(): void {
    $container = $this->buildContainer();
    $container
      ->register('consumer_id', __NAMESPACE__ . '\ValidConsumer')
      ->addTag('service_collector');

    $container
      ->register('handler1', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id');
    $container
      ->register('handler2', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id', [
        'priority' => 10,
      ]);

    $handler_pass = new TaggedHandlersPass();
    $handler_pass->process($container);

    $method_calls = $container->getDefinition('consumer_id')->getMethodCalls();
    $this->assertCount(2, $method_calls);
    $this->assertEquals(new Reference('handler2'), $method_calls[0][1][0]);
    $this->assertEquals(10, $method_calls[0][1][1]);
    $this->assertEquals(new Reference('handler1'), $method_calls[1][1][0]);
    $this->assertEquals(0, $method_calls[1][1][1]);
  }

  /**
   * Tests handler priority sorting for service ID collection.
   *
   * @covers ::process
   */
  public function testServiceIdProcessPriority(): void {
    $container = $this->buildContainer();
    $container
      ->register('consumer_id', __NAMESPACE__ . '\ValidConsumer')
      ->addTag('service_id_collector');

    $container
      ->register('handler1', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id');
    $container
      ->register('handler2', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id', [
        'priority' => 20,
      ]);
    $container
      ->register('handler3', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id', [
        'priority' => 10,
      ]);

    $handler_pass = new TaggedHandlersPass();
    $handler_pass->process($container);

    $arguments = $container->getDefinition('consumer_id')->getArguments();
    $this->assertCount(1, $arguments);
    $this->assertSame(['handler2', 'handler3', 'handler1'], $arguments[0]);
  }

  /**
   * Tests consumer method without priority parameter.
   *
   * @covers ::process
   */
  public function testProcessNoPriorityParam(): void {
    $container = $this->buildContainer();
    $container
      ->register('consumer_id', __NAMESPACE__ . '\ValidConsumer')
      ->addTag('service_collector', [
        'call' => 'addNoPriority',
      ]);

    $container
      ->register('handler1', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id');
    $container
      ->register('handler2', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id', [
        'priority' => 10,
      ]);

    $handler_pass = new TaggedHandlersPass();
    $handler_pass->process($container);

    $method_calls = $container->getDefinition('consumer_id')->getMethodCalls();
    $this->assertCount(2, $method_calls);
    $this->assertEquals(new Reference('handler2'), $method_calls[0][1][0]);
    $this->assertCount(1, $method_calls[0][1]);
    $this->assertEquals(new Reference('handler1'), $method_calls[1][1][0]);
    $this->assertCount(1, $method_calls[0][1]);
  }

  /**
   * Tests consumer method with an ID parameter.
   *
   * @covers ::process
   */
  public function testProcessWithIdParameter(): void {
    $container = $this->buildContainer();
    $container
      ->register('consumer_id', __NAMESPACE__ . '\ValidConsumer')
      ->addTag('service_collector', [
        'call' => 'addWithId',
      ]);

    $container
      ->register('handler1', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id');
    $container
      ->register('handler2', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id', [
        'priority' => 10,
      ]);

    $handler_pass = new TaggedHandlersPass();
    $handler_pass->process($container);

    $method_calls = $container->getDefinition('consumer_id')->getMethodCalls();
    $this->assertCount(2, $method_calls);
    $this->assertEquals(new Reference('handler2'), $method_calls[0][1][0]);
    $this->assertEquals('handler2', $method_calls[0][1][1]);
    $this->assertEquals(10, $method_calls[0][1][2]);
    $this->assertEquals(new Reference('handler1'), $method_calls[1][1][0]);
    $this->assertEquals('handler1', $method_calls[1][1][1]);
    $this->assertEquals(0, $method_calls[1][1][2]);
  }

  /**
   * Tests interface validation in non-production environment.
   *
   * @covers ::process
   */
  public function testProcessInterfaceMismatch(): void {
    $container = $this->buildContainer();

    $container
      ->register('consumer_id', __NAMESPACE__ . '\ValidConsumer')
      ->addTag('service_collector');
    $container
      ->register('handler1', __NAMESPACE__ . '\InvalidHandler')
      ->addTag('consumer_id');
    $container
      ->register('handler2', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id', [
        'priority' => 10,
      ]);

    $handler_pass = new TaggedHandlersPass();
    $this->expectException(LogicException::class);
    $handler_pass->process($container);
  }

  /**
   * Tests child handler with parent service.
   *
   * @covers ::process
   */
  public function testProcessChildDefinition(): void {
    $container = $this->buildContainer();

    $container
      ->register('consumer_id', __NAMESPACE__ . '\ValidConsumer')
      ->addTag('service_collector');
    $container
      ->register('root_handler', __NAMESPACE__ . '\ValidHandler');
    $container->addDefinitions([
      'parent_handler' => new ChildDefinition('root_handler'),
      'child_handler' => (new ChildDefinition('parent_handler'))->addTag('consumer_id'),
    ]);

    $handler_pass = new TaggedHandlersPass();
    $handler_pass->process($container);

    $method_calls = $container->getDefinition('consumer_id')->getMethodCalls();
    $this->assertCount(1, $method_calls);
  }

  /**
   * Tests consumer method with extra parameters.
   *
   * @covers ::process
   */
  public function testProcessWithExtraArguments(): void {
    $container = $this->buildContainer();

    $container
      ->register('consumer_id', __NAMESPACE__ . '\ValidConsumerWithExtraArguments')
      ->addTag('service_collector');

    $container
      ->register('handler1', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id', [
        'extra1' => 'extra1',
        'extra2' => 'extra2',
      ]);

    $handler_pass = new TaggedHandlersPass();
    $handler_pass->process($container);

    $method_calls = $container->getDefinition('consumer_id')->getMethodCalls();
    $this->assertCount(4, $method_calls[0][1]);
    $this->assertEquals(new Reference('handler1'), $method_calls[0][1][0]);
    $this->assertEquals(0, $method_calls[0][1][1]);
    $this->assertEquals('extra1', $method_calls[0][1][2]);
    $this->assertEquals('extra2', $method_calls[0][1][3]);
  }

  /**
   * Tests consumer method with extra parameters and no priority.
   *
   * @covers ::process
   */
  public function testProcessNoPriorityAndExtraArguments(): void {
    $container = $this->buildContainer();

    $container
      ->register('consumer_id', __NAMESPACE__ . '\ValidConsumerWithExtraArguments')
      ->addTag('service_collector', [
        'call' => 'addNoPriority',
      ]);

    $container
      ->register('handler1', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id', [
        'extra' => 'extra',
      ]);

    $handler_pass = new TaggedHandlersPass();
    $handler_pass->process($container);

    $method_calls = $container->getDefinition('consumer_id')->getMethodCalls();
    $this->assertCount(2, $method_calls[0][1]);
    $this->assertEquals(new Reference('handler1'), $method_calls[0][1][0]);
    $this->assertEquals('extra', $method_calls[0][1][1]);
  }

  /**
   * Tests consumer method with priority, id and extra parameters.
   *
   * @covers ::process
   */
  public function testProcessWithIdAndExtraArguments(): void {
    $container = $this->buildContainer();

    $container
      ->register('consumer_id', __NAMESPACE__ . '\ValidConsumerWithExtraArguments')
      ->addTag('service_collector', [
        'call' => 'addWithId',
      ]);

    $container
      ->register('handler1', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id', [
        'extra1' => 'extra1',
      ]);

    $handler_pass = new TaggedHandlersPass();
    $handler_pass->process($container);

    $method_calls = $container->getDefinition('consumer_id')->getMethodCalls();
    $this->assertCount(5, $method_calls[0][1]);
    $this->assertEquals(new Reference('handler1'), $method_calls[0][1][0]);
    $this->assertEquals('handler1', $method_calls[0][1][1]);
    $this->assertEquals(0, $method_calls[0][1][2]);
    $this->assertEquals('extra1', $method_calls[0][1][3]);
    $this->assertNull($method_calls[0][1][4]);
  }

  /**
   * Tests consumer method with varying order of priority and extra parameters.
   *
   * @covers ::process
   */
  public function testProcessWithDifferentArgumentsOrderAndDefaultValue(): void {
    $container = $this->buildContainer();

    $container
      ->register('consumer_id', __NAMESPACE__ . '\ValidConsumerWithExtraArguments')
      ->addTag('service_collector', [
        'call' => 'addWithDifferentOrder',
      ]);

    $container
      ->register('handler1', __NAMESPACE__ . '\ValidHandler')
      ->addTag('consumer_id', [
        'priority' => 0,
        'extra1' => 'extra1',
        'extra3' => 'extra3',
      ]);

    $handler_pass = new TaggedHandlersPass();
    $handler_pass->process($container);

    $method_calls = $container->getDefinition('consumer_id')->getMethodCalls();
    $this->assertCount(5, $method_calls[0][1]);
    $expected = [new Reference('handler1'), 'extra1', 0, 'default2', 'extra3'];
    $this->assertEquals($expected, array_values($method_calls[0][1]));
  }

}

/**
 * Interface for test handlers.
 */
interface HandlerInterface {
}

/**
 * Test class of a valid consumer.
 */
class ValidConsumer {

  public function addHandler(HandlerInterface $instance, $priority = 0) {
  }

  public function addNoPriority(HandlerInterface $instance) {
  }

  public function addWithId(HandlerInterface $instance, $id, $priority = 0) {
  }

}

/**
 * Test class of an invalid consumer.
 */
class InvalidConsumer {

  public function addHandler($instance, $priority = 0) {
  }

}

/**
 * Test class of a valid consumer with extra arguments.
 */
class ValidConsumerWithExtraArguments {

  public function addHandler(HandlerInterface $instance, $priority = 0, $extra1 = '', $extra2 = '') {
  }

  public function addNoPriority(HandlerInterface $instance, $extra) {
  }

  public function addWithId(HandlerInterface $instance, $id, $priority = 0, $extra1 = '', $extra2 = NULL) {
  }

  public function addWithDifferentOrder(HandlerInterface $instance, $extra1, $priority = 0, $extra2 = 'default2', $extra3 = 'default3') {
  }

}

/**
 * Test handler class with interface implemented.
 */
class ValidHandler implements HandlerInterface {
}

/**
 * Invalid test handler class.
 */
class InvalidHandler {
}
