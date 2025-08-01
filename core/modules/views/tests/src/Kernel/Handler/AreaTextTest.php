<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Handler;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;

/**
 * Tests the core views_handler_area_text handler.
 *
 * @group views
 * @see \Drupal\views\Plugin\views\area\Text
 */
class AreaTextTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'filter'];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp();

    $this->installConfig(['system', 'filter']);
    $this->installEntitySchema('user');
  }

  /**
   * Tests the rendering of a text area.
   */
  public function testAreaText(): void {
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');
    $view = Views::getView('test_view');
    $view->setDisplay();

    // Add a text header.
    $string = $this->randomMachineName();
    $view->displayHandlers->get('default')->overrideOption('header', [
      'area' => [
        'id' => 'area',
        'table' => 'views',
        'field' => 'area',
        'content' => [
          'value' => $string,
        ],
      ],
    ]);

    // Execute the view.
    $this->executeView($view);

    $view->display_handler->handlers['header']['area']->options['content']['format'] = $this->randomString();
    $build = $view->display_handler->handlers['header']['area']->render();
    $this->assertEquals('', $renderer->renderRoot($build), 'Nonexistent format should return empty markup.');

    $view->display_handler->handlers['header']['area']->options['content']['format'] = filter_default_format();
    $build = $view->display_handler->handlers['header']['area']->render();
    $this->assertEquals(check_markup($string), $renderer->renderRoot($build), 'Existent format should return something');

    // Empty results, and it shouldn't be displayed .
    $this->assertEquals([], $view->display_handler->handlers['header']['area']->render(TRUE), 'No result should lead to no header');
    // Empty results, and it should be displayed.
    $view->display_handler->handlers['header']['area']->options['empty'] = TRUE;
    $build = $view->display_handler->handlers['header']['area']->render(TRUE);
    $this->assertEquals(check_markup($string), $renderer->renderRoot($build), 'No result, but empty enabled lead to a full header');
  }

}
