<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Render;

use Drupal\Core\Render\RenderContext;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\ThemeManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

// cspell:ignore fooalert

/**
 * @coversDefaultClass \Drupal\Core\Render\Renderer
 * @group Render
 */
class RendererTest extends RendererTestBase {

  /**
   * The expected theme variables.
   *
   * @var array
   */
  protected $defaultThemeVars = [
    '#cache' => [
      'contexts' => [
        'languages:language_interface',
        'theme',
      ],
      'tags' => [],
      'max-age' => Cache::PERMANENT,
    ],
    '#attached' => [],
    '#children' => '',
  ];

  /**
   * @covers ::render
   * @covers ::doRender
   *
   * @dataProvider providerTestRenderBasic
   */
  public function testRenderBasic($build, $expected, ?callable $setup_code = NULL): void {
    if (isset($setup_code)) {
      $setup_code = $setup_code->bindTo($this);
      $setup_code($this->themeManager, $this);
    }

    if (isset($build['#markup'])) {
      $this->assertNotInstanceOf(MarkupInterface::class, $build['#markup']);
    }
    $render_output = $this->renderer->renderRoot($build);
    $this->assertSame($expected, (string) $render_output);
    if ($render_output !== '') {
      $this->assertInstanceOf(MarkupInterface::class, $render_output);
      $this->assertInstanceOf(MarkupInterface::class, $build['#markup']);
    }
  }

  /**
   * Provides a list of render arrays to test basic rendering.
   *
   * @return array
   *   An array of render arrays and their expected output.
   */
  public static function providerTestRenderBasic(): array {
    $data = [];

    // Part 1: the most simplistic render arrays possible, none using #theme.

    // Pass a NULL.
    $data[] = [NULL, ''];
    // Pass an empty string.
    $data[] = ['', ''];
    // Previously printed, see ::renderTwice for a more integration-like test.
    $data[] = [
      ['#markup' => 'foo', '#printed' => TRUE],
      '',
    ];
    // Printed in pre_render.
    $data[] = [
      [
        '#markup' => 'foo',
        '#pre_render' => [[new TestCallables(), 'preRenderPrinted']],
      ],
      '',
    ];
    // Basic #markup based renderable array.
    $data[] = [
      ['#markup' => 'foo'],
      'foo',
    ];
    // Basic #markup based renderable array with value '0'.
    $data[] = [
      ['#markup' => '0'],
      '0',
    ];
    // Basic #markup based renderable array with value 0.
    $data[] = [
      ['#markup' => 0],
      '0',
    ];
    // Basic #markup based renderable array with value ''.
    $data[] = [
      ['#markup' => ''],
      '',
    ];
    // Basic #markup based renderable array with value NULL.
    $data[] = [
      ['#markup' => NULL],
      '',
    ];
    // Basic #plain_text based renderable array.
    $data[] = [
      ['#plain_text' => 'foo'],
      'foo',
    ];
    // Mixing #plain_text and #markup based renderable array.
    $data[] = [
      ['#plain_text' => '<em>foo</em>', '#markup' => 'bar'],
      '&lt;em&gt;foo&lt;/em&gt;',
    ];
    // Safe strings in #plain_text are still escaped.
    $data[] = [
      ['#plain_text' => Markup::create('<em>foo</em>')],
      '&lt;em&gt;foo&lt;/em&gt;',
    ];
    // #plain_text based renderable array with value '0'.
    $data[] = [
      ['#plain_text' => '0'],
      '0',
    ];
    // #plain_text based renderable array with value 0.
    $data[] = [
      ['#plain_text' => 0],
      '0',
    ];
    // #plain_text based renderable array with value ''.
    $data[] = [
      ['#plain_text' => ''],
      '',
    ];
    // #plain_text based renderable array with value NULL.
    $data[] = [
      ['#plain_text' => NULL],
      '',
    ];
    // Renderable child element.
    $data[] = [
      ['child' => ['#markup' => 'bar']],
      'bar',
    ];
    // XSS filtering test.
    $data[] = [
      ['child' => ['#markup' => "This is <script>alert('XSS')</script> test"]],
      "This is alert('XSS') test",
    ];
    // XSS filtering test.
    $data[] = [
      [
        'child' => [
          '#markup' => "This is <script>alert('XSS')</script> test",
          '#allowed_tags' => ['script'],
        ],
      ],
      "This is <script>alert('XSS')</script> test",
    ];
    // XSS filtering test.
    $data[] = [
      [
        'child' => [
          '#markup' => "This is <script><em>alert('XSS')</em></script> <strong>test</strong>",
          '#allowed_tags' => ['em', 'strong'],
        ],
      ],
      "This is <em>alert('XSS')</em> <strong>test</strong>",
    ];
    // Html escaping test.
    $data[] = [
      [
        'child' => [
          '#plain_text' => "This is <script><em>alert('XSS')</em></script> <strong>test</strong>",
        ],
      ],
      "This is &lt;script&gt;&lt;em&gt;alert(&#039;XSS&#039;)&lt;/em&gt;&lt;/script&gt; &lt;strong&gt;test&lt;/strong&gt;",
    ];
    // XSS filtering by default test.
    $data[] = [
      [
        'child' => [
          '#markup' => "This is <script><em>alert('XSS')</em></script> <strong>test</strong>",
        ],
      ],
      "This is <em>alert('XSS')</em> <strong>test</strong>",
    ];
    // Ensure non-XSS tags are not filtered out.
    $data[] = [
      [
        'child' => [
          '#markup' => "This is <strong><script>alert('not a giraffe')</script></strong> test",
        ],
      ],
      "This is <strong>alert('not a giraffe')</strong> test",
    ];
    // #children set but empty, and renderable children.
    $data[] = [
      ['#children' => '', 'child' => ['#markup' => 'bar']],
      'bar',
    ];
    // #children set, not empty, and renderable children. #children will be
    // assumed oto be the rendered child elements, even though the #markup for
    // 'child' differs.
    $data[] = [
      ['#children' => 'foo', 'child' => ['#markup' => 'bar']],
      'foo',
    ];
    // Ensure that content added to #markup via a #pre_render callback is safe.
    $data[] = [
      [
        '#markup' => 'foo',
        '#pre_render' => [
          function ($elements) {
            $elements['#markup'] .= '<script>alert("bar");</script>';
            return $elements;
          },
        ],
      ],
      'fooalert("bar");',
    ];
    // Test #allowed_tags in combination with #markup and #pre_render.
    $data[] = [
      [
        '#markup' => 'foo',
        '#allowed_tags' => ['script'],
        '#pre_render' => [
          function ($elements) {
            $elements['#markup'] .= '<script>alert("bar");</script>';
            return $elements;
          },
        ],
      ],
      'foo<script>alert("bar");</script>',
    ];
    // Ensure output is escaped when adding content to #check_plain through
    // a #pre_render callback.
    $data[] = [
      [
        '#plain_text' => 'foo',
        '#pre_render' => [
          function ($elements) {
            $elements['#plain_text'] .= '<script>alert("bar");</script>';
            return $elements;
          },
        ],
      ],
      'foo&lt;script&gt;alert(&quot;bar&quot;);&lt;/script&gt;',
    ];

    // Part 2: render arrays using #theme and #theme_wrappers.

    // Tests that #theme and #theme_wrappers can co-exist on an element.
    $build = [
      '#theme' => 'common_test_foo',
      '#foo' => 'foo',
      '#bar' => 'bar',
      '#theme_wrappers' => ['container'],
      '#attributes' => ['class' => ['baz']],
    ];
    $setup_code_type_link = function (ThemeManagerInterface&MockObject $themeManager, RendererTestBase $testCase): void {
      $themeManager->expects($testCase->exactly(2))
        ->method('render')
        ->with(static::logicalOr('common_test_foo', 'container'))
        ->willReturnCallback(function ($theme, $vars) {
          if ($theme == 'container') {
            return '<div' . (string) (new Attribute($vars['#attributes'])) . '>' . $vars['#children'] . "</div>\n";
          }
          return $vars['#foo'] . $vars['#bar'];
        });
    };
    $data[] = [$build, '<div class="baz">foobar</div>' . "\n", $setup_code_type_link];

    // Tests that #theme_wrappers can disambiguate element attributes shared
    // with rendering methods that build #children by using the alternate
    // #theme_wrappers attribute override syntax.
    $build = [
      '#type' => 'link',
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => ['class' => ['baz']],
        ],
      ],
      '#attributes' => ['id' => 'foo'],
      '#url' => 'https://www.drupal.org',
      '#title' => 'bar',
    ];
    $setup_code_type_link = function (ThemeManagerInterface&MockObject $themeManager, RendererTestBase $testCase): void {
      $themeManager->expects($testCase->exactly(2))
        ->method('render')
        ->with(static::logicalOr('link', 'container'))
        ->willReturnCallback(function ($theme, $vars) {
          if ($theme == 'container') {
            return '<div' . (string) (new Attribute($vars['#attributes'])) . '>' . $vars['#children'] . "</div>\n";
          }
          $attributes = new Attribute(['href' => $vars['#url']] + ($vars['#attributes'] ?? []));
          return '<a' . (string) $attributes . '>' . $vars['#title'] . '</a>';
        });
    };
    $data[] = [$build, '<div class="baz"><a href="https://www.drupal.org" id="foo">bar</a></div>' . "\n", $setup_code_type_link];

    // Tests that #theme_wrappers can disambiguate element attributes when the
    // "base" attribute is not set for #theme.
    $build = [
      '#type' => 'link',
      '#url' => 'https://www.drupal.org',
      '#title' => 'foo',
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => ['class' => ['baz']],
        ],
      ],
    ];
    $data[] = [$build, '<div class="baz"><a href="https://www.drupal.org">foo</a></div>' . "\n", $setup_code_type_link];

    // Tests two 'container' #theme_wrappers, one using the "base" attributes
    // and one using an override.
    $build = [
      '#attributes' => ['class' => ['foo']],
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => ['class' => ['bar']],
        ],
        'container',
      ],
    ];
    $setup_code = function (ThemeManagerInterface&MockObject $themeManager, RendererTestBase $testCase): void {
      $themeManager->expects($testCase->exactly(2))
        ->method('render')
        ->with('container')
        ->willReturnCallback(function ($theme, $vars) {
          return '<div' . (string) (new Attribute($vars['#attributes'])) . '>' . $vars['#children'] . "</div>\n";
        });
    };
    $data[] = [$build, '<div class="foo"><div class="bar"></div>' . "\n" . '</div>' . "\n", $setup_code];

    // Tests array syntax theme hook suggestion in #theme_wrappers.
    $build = [
      '#theme_wrappers' => [['container']],
      '#attributes' => ['class' => ['foo']],
    ];
    $setup_code = function (ThemeManagerInterface&MockObject $themeManager, RendererTestBase $testCase): void {
      $themeManager->expects($testCase->once())
        ->method('render')
        ->with(['container'])
        ->willReturnCallback(function ($theme, $vars) {
          return '<div' . (string) (new Attribute($vars['#attributes'])) . '>' . $vars['#children'] . "</div>\n";
        });
    };
    $data[] = [$build, '<div class="foo"></div>' . "\n", $setup_code];

    // Part 3: render arrays using #markup as a fallback for #theme hooks.

    // Theme suggestion is not implemented, #markup should be rendered.
    $build = [
      '#theme' => ['suggestion_not_implemented'],
      '#markup' => 'foo',
    ];
    $setup_code = function (ThemeManagerInterface&MockObject $themeManager, RendererTestBase $testCase): void {
      $themeManager->expects($testCase->once())
        ->method('render')
        ->with(['suggestion_not_implemented'], $testCase->anything())
        ->willReturn(FALSE);
    };
    $data[] = [$build, 'foo', $setup_code];

    // Tests unimplemented theme suggestion, child #markup should be rendered.
    $build = [
      '#theme' => ['suggestion_not_implemented'],
      'child' => [
        '#markup' => 'foo',
      ],
    ];
    $setup_code = function (ThemeManagerInterface&MockObject $themeManager, RendererTestBase $testCase): void {
      $themeManager->expects($testCase->once())
        ->method('render')
        ->with(['suggestion_not_implemented'], $testCase->anything())
        ->willReturn(FALSE);
    };
    $data[] = [$build, 'foo', $setup_code];

    // Tests implemented theme suggestion: #markup should not be rendered.
    $build = [
      '#theme' => ['common_test_empty'],
      '#markup' => 'foo',
    ];
    $theme_function_output = static::randomContextValue();
    $setup_code = function (ThemeManagerInterface&MockObject $themeManager, RendererTestBase $testCase) use ($theme_function_output): void {
      $themeManager->expects($testCase->once())
        ->method('render')
        ->with(['common_test_empty'], $testCase->anything())
        ->willReturn($theme_function_output);
    };
    $data[] = [$build, $theme_function_output, $setup_code];

    // Tests implemented theme suggestion: children should not be rendered.
    $build = [
      '#theme' => ['common_test_empty'],
      'child' => [
        '#markup' => 'foo',
      ],
    ];
    $data[] = [$build, $theme_function_output, $setup_code];

    // Part 4: handling of #children and child renderable elements.

    // #theme is implemented so the values of both #children and 'child' will
    // be ignored - it is the responsibility of the theme hook to render these
    // if appropriate.
    $build = [
      '#theme' => 'common_test_foo',
      '#children' => 'baz',
      'child' => ['#markup' => 'boo'],
    ];
    $setup_code = function (ThemeManagerInterface&MockObject $themeManager, RendererTestBase $testCase): void {
      $themeManager->expects($testCase->once())
        ->method('render')
        ->with('common_test_foo', $testCase->anything())
        ->willReturn('foobar');
    };
    $data[] = [$build, 'foobar', $setup_code];

    // #theme is implemented but #render_children is TRUE. As in the case where
    // #theme is not set, empty #children means child elements are rendered
    // recursively.
    $build = [
      '#theme' => 'common_test_foo',
      '#children' => '',
      '#render_children' => TRUE,
      'child' => [
        '#markup' => 'boo',
      ],
    ];
    $setup_code = function (ThemeManagerInterface&MockObject $themeManager, RendererTestBase $testCase): void {
      $themeManager->expects($testCase->never())
        ->method('render');
    };
    $data[] = [$build, 'boo', $setup_code];

    // #theme is implemented but #render_children is TRUE. As in the case where
    // #theme is not set, #children will take precedence over 'child'.
    $build = [
      '#theme' => 'common_test_foo',
      '#children' => 'baz',
      '#render_children' => TRUE,
      'child' => [
        '#markup' => 'boo',
      ],
    ];
    $setup_code = function (ThemeManagerInterface&MockObject $themeManager, RendererTestBase $testCase): void {
      $themeManager->expects($testCase->never())
        ->method('render');
    };
    $data[] = [$build, 'baz', $setup_code];

    // #theme is implemented but #render_children is TRUE. In this case the
    // calling code is expecting only the children to be rendered. #prefix and
    // #suffix should not be inherited for the children.
    $build = [
      '#theme' => 'common_test_foo',
      '#children' => '',
      '#prefix' => 'kangaroo',
      '#suffix' => 'unicorn',
      '#render_children' => TRUE,
      'child' => [
        '#markup' => 'kitten',
      ],
    ];
    $setup_code = function (ThemeManagerInterface&MockObject $themeManager, RendererTestBase $testCase): void {
      $themeManager->expects($testCase->never())
        ->method('render');
    };
    $data[] = [$build, 'kitten', $setup_code];

    return $data;
  }

  /**
   * @covers ::render
   * @covers ::doRender
   */
  public function testRenderSorting(): void {
    $first = $this->randomMachineName();
    $second = $this->randomMachineName();
    // Build an array with '#weight' set for each element.
    $elements = [
      'second' => [
        '#weight' => 10,
        '#markup' => $second,
      ],
      'first' => [
        '#weight' => 0,
        '#markup' => $first,
      ],
    ];
    $output = (string) $this->renderer->renderRoot($elements);

    // The lowest weight element should appear last in $output.
    $this->assertGreaterThan(strpos($output, $first), strpos($output, $second));

    // Confirm that the $elements array has '#sorted' set to TRUE.
    $this->assertTrue($elements['#sorted'], "'#sorted' => TRUE was added to the array");

    // Pass $elements through \Drupal\Core\Render\Element::children() and
    // ensure it remains sorted in the correct order.
    // \Drupal::service('renderer')->render() will return an empty string if
    // used on the same array in the same request.
    $children = Element::children($elements);
    $this->assertSame('first', array_shift($children), 'Child found in the correct order.');
    $this->assertSame('second', array_shift($children), 'Child found in the correct order.');
  }

  /**
   * @covers ::render
   * @covers ::doRender
   */
  public function testRenderSortingWithSetHashSorted(): void {
    $first = $this->randomMachineName();
    $second = $this->randomMachineName();
    // The same array structure again, but with #sorted set to TRUE.
    $elements = [
      'second' => [
        '#weight' => 10,
        '#markup' => $second,
      ],
      'first' => [
        '#weight' => 0,
        '#markup' => $first,
      ],
      '#sorted' => TRUE,
    ];
    $output = (string) $this->renderer->renderRoot($elements);

    // The elements should appear in output in the same order as the array.
    $this->assertLessThan(strpos($output, $first), strpos($output, $second));
  }

  /**
   * Tests that element defaults are added.
   *
   * @covers ::render
   * @covers ::doRender
   */
  public function testElementDefaultsAdded(): void {
    $build = ['#type' => 'details'];
    $this->renderer->renderInIsolation($build);
    $this->assertTrue($build['#defaults_loaded'], "An element with a type had said type's defaults loaded.");

    $build = [
      '#lazy_builder' => [
        'Drupal\Tests\Core\Render\TestCallables::lazyBuilder',
        [FALSE],
      ],
      '#create_placeholder' => FALSE,
    ];

    $this->renderer->renderInIsolation($build);
    $this->assertArrayNotHasKey('#defaults_loaded', $build, "A lazy builder that did not set a type had no type defaults loaded.");

    $build = [
      '#lazy_builder' => [
        'Drupal\Tests\Core\Render\TestCallables::lazyBuilder',
        [TRUE],
      ],
      '#create_placeholder' => FALSE,
    ];

    $this->renderer->renderInIsolation($build);
    $this->assertTrue($build['#defaults_loaded'], "A lazy builder that set a type had said type's defaults loaded.");
  }

  /**
   * @covers ::render
   * @covers ::doRender
   *
   * @dataProvider providerAccessValues
   */
  public function testRenderWithPresetAccess($access): void {
    $build = [
      '#access' => $access,
    ];

    $this->assertAccess($build, $access);
  }

  /**
   * @covers ::render
   * @covers ::doRender
   *
   * @dataProvider providerAccessValues
   */
  public function testRenderWithAccessCallbackCallable($access): void {
    $build = [
      '#access_callback' => function () use ($access) {
        return $access;
      },
    ];

    $this->assertAccess($build, $access);
  }

  /**
   * Ensures that the #access property wins over the callable.
   *
   * @covers ::render
   * @covers ::doRender
   *
   * @dataProvider providerAccessValues
   */
  public function testRenderWithAccessPropertyAndCallback($access): void {
    $build = [
      '#access' => $access,
      '#access_callback' => function () {
        return TRUE;
      },
    ];

    $this->assertAccess($build, $access);
  }

  /**
   * @covers ::render
   * @covers ::doRender
   *
   * @dataProvider providerAccessValues
   */
  public function testRenderWithAccessControllerResolved($access): void {

    switch ($access) {
      case AccessResult::allowed():
        $method = 'accessResultAllowed';
        break;

      case AccessResult::forbidden():
        $method = 'accessResultForbidden';
        break;

      case FALSE:
        $method = 'accessFalse';
        break;

      case TRUE:
        $method = 'accessTrue';
        break;
    }

    $build = [
      '#access_callback' => 'Drupal\Tests\Core\Render\TestAccessClass::' . $method,
    ];

    $this->assertAccess($build, $access);
  }

  /**
   * @covers ::render
   * @covers ::doRender
   */
  public function testRenderAccessCacheabilityDependencyInheritance(): void {
    $build = [
      '#access' => AccessResult::allowed()->addCacheContexts(['user']),
    ];

    $this->renderer->renderInIsolation($build);

    $this->assertEqualsCanonicalizing(['languages:language_interface', 'theme', 'user'], $build['#cache']['contexts']);
  }

  /**
   * Tests rendering same render array twice.
   *
   * Tests that a first render returns the rendered output and a second doesn't
   * because of the #printed property. Also tests that correct metadata has been
   * set for re-rendering.
   *
   * @covers ::render
   * @covers ::doRender
   *
   * @dataProvider providerRenderTwice
   */
  public function testRenderTwice($build): void {
    $this->assertEquals('kittens', $this->renderer->renderRoot($build));
    $this->assertEquals('kittens', $build['#markup']);
    $this->assertEquals(['kittens-147'], $build['#cache']['tags']);
    $this->assertTrue($build['#printed']);

    // We don't want to reprint already printed render arrays.
    $this->assertEquals('', $this->renderer->renderRoot($build));
  }

  /**
   * Provides a list of render array iterations.
   *
   * @return array
   *   An array of render arrays.
   */
  public static function providerRenderTwice() {
    return [
      [
        [
          '#markup' => 'kittens',
          '#cache' => [
            'tags' => ['kittens-147'],
          ],
        ],
      ],
      [
        [
          'child' => [
            '#markup' => 'kittens',
            '#cache' => [
              'tags' => ['kittens-147'],
            ],
          ],
        ],
      ],
      [
        [
          '#render_children' => TRUE,
          'child' => [
            '#markup' => 'kittens',
            '#cache' => [
              'tags' => ['kittens-147'],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Ensures that #access is taken in account when rendering #render_children.
   */
  public function testRenderChildrenAccess(): void {
    $build = [
      '#access' => FALSE,
      '#render_children' => TRUE,
      'child' => [
        '#markup' => 'kittens',
      ],
    ];

    $this->assertEquals('', $this->renderer->renderRoot($build));
  }

  /**
   * Provides a list of both booleans.
   *
   * @return array
   *   A list of boolean values and AccessResult objects.
   */
  public static function providerAccessValues() {
    return [
      [FALSE],
      [TRUE],
      [AccessResult::forbidden()],
      [AccessResult::allowed()],
    ];
  }

  /**
   * Asserts that a render array with access checking renders correctly.
   *
   * @param array $build
   *   A render array with either #access or #access_callback.
   * @param \Drupal\Core\Access\AccessResultInterface|bool $access
   *   Whether the render array is accessible or not.
   *
   * @internal
   */
  protected function assertAccess(array $build, $access): void {
    $sensitive_content = $this->randomContextValue();
    $build['#markup'] = $sensitive_content;
    if (($access instanceof AccessResultInterface && $access->isAllowed()) || $access === TRUE) {
      $this->assertSame($sensitive_content, (string) $this->renderer->renderRoot($build));
    }
    else {
      $this->assertSame('', (string) $this->renderer->renderRoot($build));
    }
  }

  /**
   * @covers ::render
   * @covers ::doRender
   */
  public function testRenderWithoutThemeArguments(): void {
    $element = [
      '#theme' => 'common_test_foo',
    ];

    $this->themeManager->expects($this->once())
      ->method('render')
      ->with('common_test_foo', $this->defaultThemeVars + $element)
      ->willReturn('foobar');

    // Test that defaults work.
    $this->assertEquals('foobar', $this->renderer->renderRoot($element), 'Defaults work');
  }

  /**
   * @covers ::render
   * @covers ::doRender
   */
  public function testRenderWithThemeArguments(): void {
    $element = [
      '#theme' => 'common_test_foo',
      '#foo' => $this->randomMachineName(),
      '#bar' => $this->randomMachineName(),
    ];

    $this->themeManager->expects($this->once())
      ->method('render')
      ->with('common_test_foo', $this->defaultThemeVars + $element)
      ->willReturnCallback(function ($hook, $vars) {
        return $vars['#foo'] . $vars['#bar'];
      });

    // Tests that passing arguments to the theme function works.
    $this->assertEquals($this->renderer->renderRoot($element), $element['#foo'] . $element['#bar'], 'Passing arguments to theme functions works');
  }

  /**
   * Provides a list of access conditions and expected cache metadata.
   *
   * @return array
   *   An array of access conditions and expected cache metadata.
   */
  public static function providerRenderCache() {
    return [
      'full access' => [
        NULL,
        [
          'render_cache_tag',
          'render_cache_tag_child:1',
          'render_cache_tag_child:2',
        ],
      ],
      'no child access' => [
        AccessResult::forbidden()
          ->addCacheTags([
            'render_cache_tag_child_access:1',
            'render_cache_tag_child_access:2',
          ]),
        [
          'render_cache_tag',
          'render_cache_tag_child:1',
          'render_cache_tag_child:2',
          'render_cache_tag_child_access:1',
          'render_cache_tag_child_access:2',
        ],
      ],
    ];
  }

  /**
   * @covers ::render
   * @covers ::doRender
   * @covers \Drupal\Core\Render\RenderCache::get
   * @covers \Drupal\Core\Render\RenderCache::set
   *
   * @dataProvider providerRenderCache
   */
  public function testRenderCache($child_access, $expected_tags): void {
    $this->setUpRequest();
    $this->setUpMemoryCache();

    // Create an empty element.
    $test_element = [
      '#cache' => [
        'keys' => ['render_cache_test'],
        'tags' => ['render_cache_tag'],
      ],
      '#markup' => '',
      'child' => [
        '#access' => $child_access,
        '#cache' => [
          'keys' => ['render_cache_test_child'],
          'tags' => ['render_cache_tag_child:1', 'render_cache_tag_child:2'],
        ],
        '#markup' => '',
      ],
    ];

    // Render the element and confirm that it goes through the rendering
    // process (which will set $element['#printed']).
    $element = $test_element;
    $this->renderer->renderRoot($element);
    $this->assertTrue(isset($element['#printed']), 'No cache hit');

    // Render the element again and confirm that it is retrieved from the cache
    // instead (so $element['#printed'] will not be set).
    $element = $test_element;
    $this->renderer->renderRoot($element);
    $this->assertFalse(isset($element['#printed']), 'Cache hit');

    // Test that cache tags are correctly collected from the render element,
    // including the ones from its subchild.
    $this->assertEquals($expected_tags, $element['#cache']['tags'], 'Cache tags were collected from the element and its subchild.');

    // The cache item also has a 'rendered' cache tag.
    $cache_item = $this->cacheFactory->get('render')->get(['render_cache_test'], CacheableMetadata::createFromRenderArray($element));
    $this->assertSame(Cache::mergeTags($expected_tags, ['rendered']), $cache_item->tags);
  }

  /**
   * @covers ::render
   * @covers ::doRender
   * @covers \Drupal\Core\Render\RenderCache::get
   * @covers \Drupal\Core\Render\RenderCache::set
   *
   * @dataProvider providerTestRenderCacheMaxAge
   */
  public function testRenderCacheMaxAge($max_age, $is_render_cached, $render_cache_item_expire): void {
    $this->setUpRequest();
    $this->setUpMemoryCache();

    $element = [
      '#cache' => [
        'keys' => ['render_cache_test'],
        'max-age' => $max_age,
      ],
      '#markup' => '',
    ];
    $this->renderer->renderRoot($element);

    $cache_item = $this->cacheFactory->get('render')->get(['render_cache_test'], CacheableMetadata::createFromRenderArray($element));
    if (!$is_render_cached) {
      $this->assertFalse($cache_item);
    }
    else {
      $this->assertNotFalse($cache_item);
      $this->assertSame($render_cache_item_expire, $cache_item->expire);
    }
  }

  public static function providerTestRenderCacheMaxAge() {
    return [
      [0, FALSE, NULL],
      [60, TRUE, (int) $_SERVER['REQUEST_TIME'] + 60],
      [Cache::PERMANENT, TRUE, -1],
    ];
  }

  /**
   * Tests that #cache_properties are properly handled.
   *
   * @param array $expected_results
   *   An associative array of expected results keyed by property name.
   *
   * @covers ::render
   * @covers ::doRender
   * @covers \Drupal\Core\Render\RenderCache::get
   * @covers \Drupal\Core\Render\RenderCache::set
   * @covers \Drupal\Core\Render\RenderCache::getCacheableRenderArray
   *
   * @dataProvider providerTestRenderCacheProperties
   */
  public function testRenderCacheProperties(array $expected_results): void {
    $this->setUpRequest();
    $this->setUpMemoryCache();

    $element = $original = [
      '#cache' => [
        'keys' => ['render_cache_test'],
      ],
      // Collect expected property names.
      '#cache_properties' => array_keys(array_filter($expected_results)),
      'child1' => ['#markup' => Markup::create('1')],
      'child2' => ['#markup' => Markup::create('2')],
      // Mark the value as safe.
      '#custom_property' => Markup::create('custom_value'),
      '#custom_property_array' => ['custom value'],
    ];

    $this->renderer->renderRoot($element);

    $cache = $this->cacheFactory->get('render');
    $data = $cache->get(['render_cache_test'], CacheableMetadata::createFromRenderArray($element))->data;

    // Check that parent markup is ignored when caching children's markup.
    $this->assertEquals($data['#markup'] === '', (bool) Element::children($data));

    // Check that the element properties are cached as specified.
    foreach ($expected_results as $property => $expected) {
      $cached = !empty($data[$property]);
      $this->assertEquals($cached, (bool) $expected);
      // Check that only the #markup key is preserved for children.
      if ($cached) {
        $this->assertEquals($data[$property], $original[$property]);
      }
    }
    // #custom_property_array can not be a safe_cache_property.
    $safe_cache_properties = array_diff(Element::properties(array_filter($expected_results)), ['#custom_property_array']);
    foreach ($safe_cache_properties as $cache_property) {
      $this->assertInstanceOf(MarkupInterface::class, $data[$cache_property]);
    }
  }

  /**
   * Data provider for ::testRenderCacheProperties().
   *
   * @return array
   *   An array of associative arrays of expected results keyed by property
   *   name.
   */
  public static function providerTestRenderCacheProperties() {
    return [
      [[]],
      [['child1' => 0, 'child2' => 0, '#custom_property' => 0, '#custom_property_array' => 0]],
      [['child1' => 0, 'child2' => 0, '#custom_property' => 1, '#custom_property_array' => 0]],
      [['child1' => 0, 'child2' => 1, '#custom_property' => 0, '#custom_property_array' => 0]],
      [['child1' => 0, 'child2' => 1, '#custom_property' => 1, '#custom_property_array' => 0]],
      [['child1' => 1, 'child2' => 0, '#custom_property' => 0, '#custom_property_array' => 0]],
      [['child1' => 1, 'child2' => 0, '#custom_property' => 1, '#custom_property_array' => 0]],
      [['child1' => 1, 'child2' => 1, '#custom_property' => 0, '#custom_property_array' => 0]],
      [['child1' => 1, 'child2' => 1, '#custom_property' => 1, '#custom_property_array' => 0]],
      [['child1' => 1, 'child2' => 1, '#custom_property' => 1, '#custom_property_array' => 1]],
    ];
  }

  /**
   * @covers ::addCacheableDependency
   *
   * @dataProvider providerTestAddCacheableDependency
   */
  public function testAddCacheableDependency(array $build, $object, array $expected): void {
    $this->renderer->addCacheableDependency($build, $object);
    $this->assertEquals($build, $expected);
  }

  public static function providerTestAddCacheableDependency() {
    return [
      // Empty render array, typical default cacheability.
      [
        [],
        new TestCacheableDependency([], [], Cache::PERMANENT),
        [
          '#cache' => [
            'contexts' => [],
            'tags' => [],
            'max-age' => Cache::PERMANENT,
          ],
        ],
      ],
      // Empty render array, some cacheability.
      [
        [],
        new TestCacheableDependency(['user.roles'], ['foo'], Cache::PERMANENT),
        [
          '#cache' => [
            'contexts' => ['user.roles'],
            'tags' => ['foo'],
            'max-age' => Cache::PERMANENT,
          ],
        ],
      ],
      // Cacheable render array, some cacheability.
      [
        [
          '#cache' => [
            'contexts' => ['theme'],
            'tags' => ['bar'],
            'max-age' => 600,
          ],
        ],
        new TestCacheableDependency(['user.roles'], ['foo'], Cache::PERMANENT),
        [
          '#cache' => [
            'contexts' => ['theme', 'user.roles'],
            'tags' => ['bar', 'foo'],
            'max-age' => 600,
          ],
        ],
      ],
      // Cacheable render array, no cacheability.
      [
        [
          '#cache' => [
            'contexts' => ['theme'],
            'tags' => ['bar'],
            'max-age' => 600,
          ],
        ],
        (new CacheableMetadata())->setCacheMaxAge(0),
        [
          '#cache' => [
            'contexts' => ['theme'],
            'tags' => ['bar'],
            'max-age' => 0,
          ],
        ],
      ],
    ];
  }

  /**
   * @covers ::hasRenderContext
   */
  public function testHasRenderContext(): void {
    // Tests with no render context.
    $this->assertFalse($this->renderer->hasRenderContext());

    // Tests in a render context.
    $this->renderer->executeInRenderContext(new RenderContext(), function () {
      $this->assertTrue($this->renderer->hasRenderContext());
    });

    // Test that the method works with no current request.
    $this->requestStack->pop();
    $this->assertFalse($this->renderer->hasRenderContext());
  }

  /**
   * @covers ::executeInRenderContext
   */
  public function testExecuteInRenderContext(): void {
    $return = $this->renderer->executeInRenderContext(new RenderContext(), function () {
      $fiber_callback = function () {

        // Create a #pre_render callback that renders a render array in
        // isolation. This has its own #pre_render callback that calls
        // Fiber::suspend(). This ensures that suspending a Fiber within
        // multiple nested calls to ::executeInRenderContext() doesn't
        // allow render context to get out of sync. This simulates similar
        // conditions to BigPipe placeholder rendering.
        $fiber_suspend_pre_render = function ($elements) {
          $fiber_suspend = function ($elements) {
            \Fiber::suspend();
            return $elements;
          };
          $build = [
            'foo' => [
              '#markup' => 'foo',
              '#pre_render' => [$fiber_suspend],
            ],
          ];
          $markup = $this->renderer->renderInIsolation($build);
          $elements['#markup'] = $markup;
          return $elements;
        };
        $build = [
          'foo' => [
            '#pre_render' => [$fiber_suspend_pre_render],
          ],
        ];
        return $this->renderer->render($build);
      };

      // Build an array of two fibers that executes the code defined above. This
      // ensures that Fiber::suspend() is called from within two
      // ::renderInIsolation() calls without either having been completed.
      $fibers = [];
      foreach ([0, 1] as $key) {
        $fibers[] = new \Fiber(static fn () => $fiber_callback());
      }
      while ($fibers) {
        foreach ($fibers as $key => $fiber) {
          if ($fiber->isTerminated()) {
            unset($fibers[$key]);
            continue;
          }
          if ($fiber->isSuspended()) {
            $fiber->resume();
          }
          else {
            $fiber->start();
          }
        }
      }
      return $fiber->getReturn();
    });
    $this->assertEquals(Markup::create('foo'), $return);
  }

}

/**
 * Test class for mocking the access callback.
 */
class TestAccessClass implements TrustedCallbackInterface {

  public static function accessTrue() {
    return TRUE;
  }

  public static function accessFalse() {
    return FALSE;
  }

  public static function accessResultAllowed() {
    return AccessResult::allowed();
  }

  public static function accessResultForbidden() {
    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['accessTrue', 'accessFalse', 'accessResultAllowed', 'accessResultForbidden'];
  }

}

/**
 * Mock callable for testing the pre_render callback.
 */
class TestCallables implements TrustedCallbackInterface {

  public function preRenderPrinted($elements) {
    $elements['#printed'] = TRUE;
    return $elements;
  }

  public static function lazyBuilder(bool $set_type): array {
    $build['content'] = ['#markup' => 'Content'];
    if ($set_type) {
      $build['#type'] = 'details';
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderPrinted', 'lazyBuilder'];
  }

}
