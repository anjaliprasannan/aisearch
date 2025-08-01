<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\ProxyBuilder;

use Drupal\Component\ProxyBuilder\ProxyBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests Drupal\Component\ProxyBuilder\ProxyBuilder.
 */
#[CoversClass(ProxyBuilder::class)]
#[Group('proxy_builder')]
class ProxyBuilderTest extends TestCase {

  /**
   * The tested proxy builder.
   *
   * @var \Drupal\Component\ProxyBuilder\ProxyBuilder
   */
  protected $proxyBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->proxyBuilder = new ProxyBuilder();
  }

  /**
   * @legacy-covers ::buildProxyClassName
   */
  public function testBuildProxyClassName(): void {
    $class_name = $this->proxyBuilder->buildProxyClassName('Drupal\Tests\Component\ProxyBuilder\TestServiceNoMethod');
    $this->assertEquals('Drupal\Tests\ProxyClass\Component\ProxyBuilder\TestServiceNoMethod', $class_name);
  }

  /**
   * @legacy-covers ::buildProxyClassName
   */
  public function testBuildProxyClassNameForModule(): void {
    $class_name = $this->proxyBuilder->buildProxyClassName('Drupal\views_ui\ParamConverter\ViewUIConverter');
    $this->assertEquals('Drupal\views_ui\ProxyClass\ParamConverter\ViewUIConverter', $class_name);
  }

  /**
   * @legacy-covers ::buildProxyNamespace
   */
  public function testBuildProxyNamespace(): void {
    $class_name = $this->proxyBuilder->buildProxyNamespace('Drupal\Tests\Component\ProxyBuilder\TestServiceNoMethod');
    $this->assertEquals('Drupal\Tests\ProxyClass\Component\ProxyBuilder', $class_name);
  }

  /**
   * Tests the basic methods like the constructor and the lazyLoadItself method.
   *
   * @legacy-covers ::build
   * @legacy-covers ::buildConstructorMethod
   * @legacy-covers ::buildLazyLoadItselfMethod
   */
  public function testBuildNoMethod(): void {
    $class = 'Drupal\Tests\Component\ProxyBuilder\TestServiceNoMethod';

    $result = $this->proxyBuilder->build($class);
    $this->assertEquals($this->buildExpectedClass($class, ''), $result);
  }

  /**
   * @legacy-covers ::buildMethod
   * @legacy-covers ::buildMethodBody
   */
  public function testBuildSimpleMethod(): void {
    $class = 'Drupal\Tests\Component\ProxyBuilder\TestServiceSimpleMethod';

    $result = $this->proxyBuilder->build($class);

    $method_body = <<<'EOS'

/**
 * {@inheritdoc}
 */
public function method()
{
    return $this->lazyLoadItself()->method();
}

EOS;
    $this->assertEquals($this->buildExpectedClass($class, $method_body), $result);
  }

  /**
   * @legacy-covers ::buildMethod
   * @legacy-covers ::buildParameter
   * @legacy-covers ::buildMethodBody
   */
  public function testBuildMethodWithParameter(): void {
    $class = 'Drupal\Tests\Component\ProxyBuilder\TestServiceMethodWithParameter';

    $result = $this->proxyBuilder->build($class);

    $method_body = <<<'EOS'

/**
 * {@inheritdoc}
 */
public function methodWithParameter($parameter)
{
    return $this->lazyLoadItself()->methodWithParameter($parameter);
}

EOS;
    $this->assertEquals($this->buildExpectedClass($class, $method_body), $result);
  }

  /**
   * @legacy-covers ::buildMethod
   * @legacy-covers ::buildParameter
   * @legacy-covers ::buildMethodBody
   */
  public function testBuildComplexMethod(): void {
    $class = 'Drupal\Tests\Component\ProxyBuilder\TestServiceComplexMethod';

    $result = $this->proxyBuilder->build($class);

    // @todo Solve the silly linebreak for an empty array.
    $method_body = <<<'EOS'

/**
 * {@inheritdoc}
 */
public function complexMethod(string $parameter, callable $function, ?\Drupal\Tests\Component\ProxyBuilder\TestServiceNoMethod $test_service = NULL, array &$elements = array (
)): array
{
    return $this->lazyLoadItself()->complexMethod($parameter, $function, $test_service, $elements);
}

EOS;

    $this->assertEquals($this->buildExpectedClass($class, $method_body), $result);
  }

  /**
   * @legacy-covers ::buildMethodBody
   */
  public function testBuildServiceMethodReturnsVoid(): void {
    $class = TestServiceMethodReturnsVoid::class;

    $result = $this->proxyBuilder->build($class);

    // @todo Solve the silly linebreak for an empty array.
    $method_body = <<<'EOS'

/**
 * {@inheritdoc}
 */
public function methodReturnsVoid(string $parameter): void
{
    $this->lazyLoadItself()->methodReturnsVoid($parameter);
}

EOS;

    $this->assertEquals($this->buildExpectedClass($class, $method_body), $result);
  }

  /**
   * @legacy-covers ::buildMethod
   * @legacy-covers ::buildMethodBody
   */
  public function testBuildReturnReference(): void {
    $class = 'Drupal\Tests\Component\ProxyBuilder\TestServiceReturnReference';

    $result = $this->proxyBuilder->build($class);

    // @todo Solve the silly linebreak for an empty array.
    $method_body = <<<'EOS'

/**
 * {@inheritdoc}
 */
public function &returnReference()
{
    return $this->lazyLoadItself()->returnReference();
}

EOS;

    $this->assertEquals($this->buildExpectedClass($class, $method_body), $result);
  }

  /**
   * @legacy-covers ::buildMethod
   * @legacy-covers ::buildParameter
   * @legacy-covers ::buildMethodBody
   */
  public function testBuildWithInterface(): void {
    $class = 'Drupal\Tests\Component\ProxyBuilder\TestServiceWithInterface';

    $result = $this->proxyBuilder->build($class);

    $method_body = <<<'EOS'

/**
 * {@inheritdoc}
 */
public function testMethod($parameter)
{
    return $this->lazyLoadItself()->testMethod($parameter);
}

EOS;

    $interface_string = ' implements \Drupal\Tests\Component\ProxyBuilder\TestInterface';
    $this->assertEquals($this->buildExpectedClass($class, $method_body, $interface_string), $result);
  }

  /**
   * @legacy-covers ::build
   */
  public function testBuildWithNestedInterface(): void {
    $class = 'Drupal\Tests\Component\ProxyBuilder\TestServiceWithChildInterfaces';

    $result = $this->proxyBuilder->build($class);
    $method_body = '';

    $interface_string = ' implements \Drupal\Tests\Component\ProxyBuilder\TestChildInterface';
    $this->assertEquals($this->buildExpectedClass($class, $method_body, $interface_string), $result);
  }

  /**
   * @legacy-covers ::buildMethod
   * @legacy-covers ::buildParameter
   * @legacy-covers ::buildMethodBody
   */
  public function testBuildWithProtectedAndPrivateMethod(): void {
    $class = 'Drupal\Tests\Component\ProxyBuilder\TestServiceWithProtectedMethods';

    $result = $this->proxyBuilder->build($class);

    $method_body = <<<'EOS'

/**
 * {@inheritdoc}
 */
public function testMethod($parameter)
{
    return $this->lazyLoadItself()->testMethod($parameter);
}

EOS;

    $this->assertEquals($this->buildExpectedClass($class, $method_body), $result);
  }

  /**
   * @legacy-covers ::buildMethod
   * @legacy-covers ::buildParameter
   * @legacy-covers ::buildMethodBody
   */
  public function testBuildWithPublicStaticMethod(): void {
    $class = 'Drupal\Tests\Component\ProxyBuilder\TestServiceWithPublicStaticMethod';

    $result = $this->proxyBuilder->build($class);

    // Ensure that the static method is not wrapped.
    $method_body = <<<'EOS'

/**
 * {@inheritdoc}
 */
public static function testMethod($parameter)
{
    \Drupal\Tests\Component\ProxyBuilder\TestServiceWithPublicStaticMethod::testMethod($parameter);
}

EOS;

    $this->assertEquals($this->buildExpectedClass($class, $method_body), $result);
  }

  /**
   * @legacy-covers ::buildMethod
   * @legacy-covers ::buildParameter
   * @legacy-covers ::buildMethodBody
   */
  public function testBuildWithNullableSelfTypeHint(): void {
    $class = 'Drupal\Tests\Component\ProxyBuilder\TestServiceNullableTypeHintSelf';

    $result = $this->proxyBuilder->build($class);

    // Ensure that the static method is not wrapped.
    $method_body = <<<'EOS'

/**
 * {@inheritdoc}
 */
public function typeHintSelf(?\Drupal\Tests\Component\ProxyBuilder\TestServiceNullableTypeHintSelf $parameter): ?\Drupal\Tests\Component\ProxyBuilder\TestServiceNullableTypeHintSelf
{
    return $this->lazyLoadItself()->typeHintSelf($parameter);
}

EOS;

    $this->assertEquals($this->buildExpectedClass($class, $method_body), $result);
  }

  /**
   * Constructs the expected class output.
   *
   * @param string $class
   *   The class name that is being built.
   * @param string $expected_methods_body
   *   The expected body of decorated methods.
   * @param string $interface_string
   *   (optional) The expected "implements" clause of the class definition.
   *
   * @return string
   *   The code of the entire proxy.
   */
  protected function buildExpectedClass($class, $expected_methods_body, $interface_string = '') {
    $namespace = ProxyBuilder::buildProxyNamespace($class);
    $reflection = new \ReflectionClass($class);
    $proxy_class = $reflection->getShortName();

    $expected_string = <<<'EOS'

namespace {{ namespace }} {

    /**
     * Provides a proxy class for \{{ class }}.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class {{ proxy_class }}{{ interface_string }}
    {

        /**
         * The id of the original proxied service.
         *
         * @var string
         */
        protected $drupalProxyOriginalServiceId;

        /**
         * The real proxied service, after it was lazy loaded.
         *
         * @var \{{ class }}
         */
        protected $service;

        /**
         * The service container.
         *
         * @var \Symfony\Component\DependencyInjection\ContainerInterface
         */
        protected $container;

        /**
         * Constructs a ProxyClass Drupal proxy object.
         *
         * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
         *   The container.
         * @param string $drupal_proxy_original_service_id
         *   The service ID of the original service.
         */
        public function __construct(\Symfony\Component\DependencyInjection\ContainerInterface $container, $drupal_proxy_original_service_id)
        {
            $this->container = $container;
            $this->drupalProxyOriginalServiceId = $drupal_proxy_original_service_id;
        }

        /**
         * Lazy loads the real service from the container.
         *
         * @return object
         *   Returns the constructed real service.
         */
        protected function lazyLoadItself()
        {
            if (!isset($this->service)) {
                $this->service = $this->container->get($this->drupalProxyOriginalServiceId);
            }

            return $this->service;
        }
{{ expected_methods_body }}
    }

}

EOS;

    $expected_methods_body = implode("\n", array_map(function ($value) {
      if ($value === '') {
        return $value;
      }
      return "        $value";
    }, explode("\n", $expected_methods_body)));

    $expected_string = str_replace('{{ proxy_class }}', $proxy_class, $expected_string);
    $expected_string = str_replace('{{ namespace }}', $namespace, $expected_string);
    $expected_string = str_replace('{{ class }}', $class, $expected_string);
    $expected_string = str_replace('{{ expected_methods_body }}', $expected_methods_body, $expected_string);
    $expected_string = str_replace('{{ interface_string }}', $interface_string, $expected_string);

    return $expected_string;
  }

}

/**
 * Test service without methods.
 */
class TestServiceNoMethod {

}

/**
 * Test service with simple method.
 */
class TestServiceSimpleMethod {

  public function method() {

  }

}

/**
 * Test service with method without parameter.
 */
class TestServiceMethodWithParameter {

  public function methodWithParameter($parameter) {

  }

}

/**
 * Test service with complex method.
 */
class TestServiceComplexMethod {

  public function complexMethod(string $parameter, callable $function, ?TestServiceNoMethod $test_service = NULL, array &$elements = []): array {
    return [];
  }

}

/**
 * Test service with a nullable self parameter.
 */
class TestServiceNullableTypeHintSelf {

  public function typeHintSelf(?self $parameter): ?self {
    return NULL;
  }

}

/**
 * Test service with void returning method.
 */
class TestServiceMethodReturnsVoid {

  public function methodReturnsVoid(string $parameter): void {

  }

}

/**
 * Test service with method that returns reference.
 */
class TestServiceReturnReference {

  public function &returnReference() {

  }

}

/**
 * Test interface.
 */
interface TestInterface {

  public function testMethod($parameter);

}

/**
 * Test service that implements test interface.
 */
class TestServiceWithInterface implements TestInterface {

  public function testMethod($parameter) {

  }

}

/**
 * Test service with protected methods.
 */
class TestServiceWithProtectedMethods {

  public function testMethod($parameter) {

  }

  protected function protectedMethod($parameter) {

  }

  protected function privateMethod($parameter) {

  }

}

/**
 * Test service with public static method.
 */
class TestServiceWithPublicStaticMethod {

  public static function testMethod($parameter) {
  }

}

/**
 * Test base interface.
 */
interface TestBaseInterface {

}

/**
 * Test child interface.
 */
interface TestChildInterface extends TestBaseInterface {

}

/**
 * Test service that implements test child interface.
 */
class TestServiceWithChildInterfaces implements TestChildInterface {

}
